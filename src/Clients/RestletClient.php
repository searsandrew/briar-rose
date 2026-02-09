<?php

namespace Searsandrew\BriarRose\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Searsandrew\BriarRose\Exceptions\BriarRoseConfigurationException;

class RestletClient
{
    protected ?string $scriptId = null;
    protected ?string $deployId = null;

    public function __construct(
        protected string $account,
        protected string $consumerKey,
        protected string $consumerSecret,
        protected string $tokenId,
        protected string $tokenSecret,

        /**
         * Optional override.
         * https://{account}.restlets.api.netsuite.com/app/site/hosting/restlet.nl
         */
        protected ?string $restletBaseUrl = null,

        protected ?string $defaultScriptId = null,
        protected ?string $defaultDeployId = '1',
        protected string $environment = 'production',
        protected int $timeout = 30,
        protected int $connectTimeout = 10,
        protected bool $logRequests = false,
    ) {
        $this->guardConfigured();
    }

    /**
     * Set which RESTlet script/deploy to call.
     */
    public function script(int|string $scriptId, int|string $deployId = 1): self
    {
        $clone = clone $this;
        $clone->scriptId = (string) $scriptId;
        $clone->deployId = (string) $deployId;

        return $clone;
    }

    public function get(array $query = []): Response
    {
        return $this->send('GET', $this->buildUrl(), $query);
    }

    public function post(array $payload = []): Response
    {
        return $this->send('POST', $this->buildUrl(), $payload);
    }

    public function put(array $payload = []): Response
    {
        return $this->send('PUT', $this->buildUrl(), $payload);
    }

    public function patch(array $payload = []): Response
    {
        return $this->send('PATCH', $this->buildUrl(), $payload);
    }

    public function delete(array $payload = []): Response
    {
        // Some RESTlets accept DELETE with a body; some don't.
        return $this->send('DELETE', $this->buildUrl(), $payload);
    }

    /**
     * Back-compat: do a raw request against either:
     * - the built URL (script/deploy/default) OR
     * - a provided URL.
     */
    public function request(string $method, array $data = [], ?string $url = null): Response
    {
        return $this->send(strtoupper($method), $url ?? $this->buildUrl(), $data);
    }

    protected function buildUrl(): string
    {
        // Prefer per-call script/deploy, else default from config
        $script = $this->scriptId ?? $this->defaultScriptId;
        $deploy = $this->deployId ?? $this->defaultDeployId;

        // Determine base endpoint
        $base = $this->restletBaseUrl ?: $this->defaultBaseEndpoint();

        // If the base already has query params, we merge/override script/deploy
        if ($script !== null) {
            $base = $this->mergeQueryParams($base, [
                'script' => (string) $script,
                'deploy' => (string) ($deploy ?? '1'),
            ]);
        }

        return $base;
    }

    protected function defaultBaseEndpoint(): string
    {
        // For RESTlets, this hostname format is:
        $host = $this->account . '.restlets.api.netsuite.com';

        return 'https://' . $host . '/app/site/hosting/restlet.nl';
    }

    protected function send(string $method, string $url, array $data = []): Response
    {
        $method = strtoupper($method);

        // For OAuth signature: include query params for GET, not body payload.
        $authHeader = $this->getAuthHeader($method, $url, $method === 'GET' ? $data : []);

        $request = $this->http()->withHeaders([
            'Authorization' => $authHeader,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->connectTimeout((int) config('briar-rose.connect_timeout'))
        ->timeout((int) config('briar-rose.timeout', 60))
        ->retry(
            (int) config('briar-rose.retry'),
            (int) config('briar-rose.retry_sleep_ms'),
            function ($exception, $request) use ($method) {
                // Retry only safe/idempotent methods by default
                return in_array($method, ['GET', 'HEAD'], true);
            },
            throw: false
        );

        if ($method === 'GET') {
            if (! empty($data)) {
                $url = $this->mergeQueryParams($url, $data);
            }

            $this->maybeLog($method, $url, $data);

            return $request->get($url);
        }

        $this->maybeLog($method, $url, $data);

        return $request->send($method, $url, [
            'json' => $data,
        ]);
    }

    protected function getAuthHeader(string $method, string $url, array $queryParams = []): string
    {
        $oauthParams = [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_nonce' => Str::random(32),
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $this->tokenId,
            'oauth_version' => '1.0',
        ];

        $parsedUrl = parse_url($url);
        $baseUrl = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? '') . ($parsedUrl['path'] ?? '');

        $allParams = $oauthParams;

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $urlQueryParams);
            $allParams = array_merge($allParams, $urlQueryParams);
        }

        $allParams = array_merge($allParams, $queryParams);

        ksort($allParams);

        $paramString = http_build_query($allParams, '', '&', PHP_QUERY_RFC3986);

        $baseString = implode('&', [
            strtoupper($method),
            rawurlencode($baseUrl),
            rawurlencode($paramString),
        ]);

        $signingKey = implode('&', [
            rawurlencode($this->consumerSecret),
            rawurlencode($this->tokenSecret),
        ]);

        $signature = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));
        $oauthParams['oauth_signature'] = $signature;

        $headerParts = [];
        $realm = str_replace('-', '_', strtoupper($this->account));
        $headerParts[] = 'realm="' . $realm . '"';

        foreach ($oauthParams as $key => $value) {
            $headerParts[] = rawurlencode($key) . '="' . rawurlencode((string) $value) . '"';
        }

        return 'OAuth ' . implode(', ', $headerParts);
    }

    protected function http(): PendingRequest
    {
        return Http::timeout($this->timeout)->connectTimeout($this->connectTimeout);
    }

    protected function mergeQueryParams(string $url, array $params): string
    {
        $parsed = parse_url($url);

        $existing = [];
        if (! empty($parsed['query'])) {
            parse_str($parsed['query'], $existing);
        }

        $merged = array_merge($existing, $params);
        $query = http_build_query($merged, '', '&', PHP_QUERY_RFC3986);

        $base =
            ($parsed['scheme'] ?? 'https') . '://' .
            ($parsed['host'] ?? '') .
            ($parsed['path'] ?? '');

        return $query ? $base . '?' . $query : $base;
    }

    protected function guardConfigured(): void
    {
        $missing = [];
        foreach ([
                     'account' => $this->account,
                     'consumerKey' => $this->consumerKey,
                     'consumerSecret' => $this->consumerSecret,
                     'tokenId' => $this->tokenId,
                     'tokenSecret' => $this->tokenSecret,
                 ] as $name => $value) {
            if (blank($value)) {
                $missing[] = $name;
            }
        }

        if ($missing) {
            throw new BriarRoseConfigurationException('Missing Briar Rose config: ' . implode(', ', $missing));
        }
    }

    protected function maybeLog(string $method, string $url, array $data): void
    {
        if (! $this->logRequests) {
            return;
        }

        Log::debug('Briar Rose RESTlet request', [
            'method' => $method,
            'url' => $url,
            'payload_keys' => array_keys($data),
        ]);
    }
}