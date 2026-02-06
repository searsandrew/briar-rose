<?php

namespace Searsandrew\BriarRose\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Searsandrew\BriarRose\Endpoints\RecordEndpoint;
use Searsandrew\BriarRose\Endpoints\SuiteQLEndpoint;
use Searsandrew\BriarRose\Exceptions\BriarRoseConfigurationException;

class RestClient
{
    public function __construct(
        protected string $account,
        protected string $consumerKey,
        protected string $consumerSecret,
        protected string $tokenId,
        protected string $tokenSecret,
        protected ?string $restBaseUrl = null,
        protected int $timeout = 30,
        protected int $connectTimeout = 10,
        protected bool $logRequests = false,
        protected array $restOptions = [],
    ) {
        $this->guardConfigured();
    }

    public function request(string $method, string $path, array $options = []): Response
    {
        $method = strtoupper($method);

        $url = $this->buildUrl($path);

        $query = $options['query'] ?? [];
        $json  = $options['json'] ?? null;

        $authHeader = $this->getAuthHeader($method, $url, $query);

        $request = $this->http()->withHeaders([
            'Authorization' => $authHeader,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->connectTimeout((int) env('BRIAR_ROSE_CONNECT_TIMEOUT', 10))
        ->timeout((int) env('BRIAR_ROSE_TIMEOUT', 60))
        ->retry(
            (int) env('BRIAR_ROSE_RETRY', 3),
            (int) env('BRIAR_ROSE_RETRY_SLEEP_MS', 250),
            function ($exception, $request) use ($method) {
                // Retry only safe/idempotent methods by default
                return in_array($method, ['GET', 'HEAD'], true);
            },
            throw: false
        );

        if (!empty($options['headers']) && is_array($options['headers'])) {
            $request = $request->withHeaders($options['headers']);
        }

        if ($this->logRequests) {
            $this->maybeLog($method, $url, $query, is_array($json) ? array_keys($json) : null);
        }

        $sendOptions = [];
        if (!empty($query)) {
            $sendOptions['query'] = $query;
        }
        if ($json !== null) {
            $sendOptions['json'] = $json;
        }

        return $this->sendWithRetries(fn () => $request->send($method, $url, $sendOptions));
    }

    public function get(string $path, array $query = [], array $headers = []): Response
    {
        return $this->request('GET', $path, ['query' => $query, 'headers' => $headers]);
    }

    public function post(string $path, array $json = [], array $query = [], array $headers = []): Response
    {
        return $this->request('POST', $path, ['json' => $json, 'query' => $query, 'headers' => $headers]);
    }

    public function patch(string $path, array $json = [], array $query = [], array $headers = []): Response
    {
        return $this->request('PATCH', $path, ['json' => $json, 'query' => $query, 'headers' => $headers]);
    }

    public function put(string $path, array $json = [], array $query = [], array $headers = []): Response
    {
        return $this->request('PUT', $path, ['json' => $json, 'query' => $query, 'headers' => $headers]);
    }

    public function delete(string $path, array $query = [], array $headers = []): Response
    {
        return $this->request('DELETE', $path, ['query' => $query, 'headers' => $headers]);
    }

    public function record(string $recordType): RecordEndpoint
    {
        return new RecordEndpoint($this, $recordType);
    }

    public function suiteql(): SuiteQLEndpoint
    {
        return new SuiteQLEndpoint($this);
    }

    public function baseUrl(): string
    {
        return rtrim($this->restBaseUrl ?: $this->defaultBaseEndpoint(), '/');
    }

    public function restOptions(): array
    {
        return $this->restOptions;
    }

    protected function buildUrl(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        return $this->baseUrl() . $path;
    }

    protected function defaultBaseEndpoint(): string
    {
        return 'https://' . $this->account . '.suitetalk.api.netsuite.com';
    }

    protected function sendWithRetries(callable $send): Response
    {
        $cfg = $this->restOptions['retries'] ?? [
            'enabled' => true,
            'max_attempts' => 5,
            'base_delay_ms' => 250,
            'max_delay_ms' => 5000,
            'statuses' => [429, 500, 502, 503, 504],
        ];

        if (empty($cfg['enabled'])) {
            return $send();
        }

        $maxAttempts = max(1, (int) ($cfg['max_attempts'] ?? 5));
        $baseDelayMs = max(0, (int) ($cfg['base_delay_ms'] ?? 250));
        $maxDelayMs  = max($baseDelayMs, (int) ($cfg['max_delay_ms'] ?? 5000));
        $statuses    = (array) ($cfg['statuses'] ?? [429, 500, 502, 503, 504]);

        $attempt = 1;

        while (true) {
            /** @var Response $response */
            $response = $send();

            if (!in_array($response->status(), $statuses, true) || $attempt >= $maxAttempts) {
                return $response;
            }

            // Retry-After (seconds) if present, otherwise exponential backoff
            $retryAfter = $response->header('Retry-After');
            if (is_string($retryAfter) && ctype_digit($retryAfter)) {
                $sleepMs = ((int) $retryAfter) * 1000;
            } else {
                // exponential backoff + small jitter
                $sleepMs = min($maxDelayMs, (int) ($baseDelayMs * (2 ** ($attempt - 1))));
                $sleepMs += random_int(0, 125);
            }

            if ($this->logRequests) {
                Log::debug('Briar Rose REST retry', [
                    'attempt' => $attempt,
                    'status' => $response->status(),
                    'sleep_ms' => $sleepMs,
                ]);
            }

            usleep($sleepMs * 1000);
            $attempt++;
        }
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

    protected function maybeLog(string $method, string $url, array $query, ?array $jsonKeys): void
    {
        Log::debug('Briar Rose REST request', [
            'method' => $method,
            'url' => $url,
            'query_keys' => array_keys($query),
            'json_keys' => $jsonKeys,
        ]);
    }
}