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
    ) {
        $this->guardConfigured();
    }

    /**
     * Generic REST request using relative paths (recommended).
     *
     * Example:
     *   ->request('GET', '/services/rest/record/v1/inventoryItem/123')
     */
    public function request(string $method, string $path, array $options = []): Response
    {
        $method = strtoupper($method);

        $url = $this->buildUrl($path);

        $query = $options['query'] ?? [];
        $json  = $options['json'] ?? null;

        $authHeader = $this->getAuthHeader(
            method: $method,
            url: $url,
            queryParams: $query
        );

        $request = $this->http()->withHeaders([
            'Authorization' => $authHeader,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        if (!empty($options['headers']) && is_array($options['headers'])) {
            $request = $request->withHeaders($options['headers']);
        }

        if ($this->logRequests) {
            $this->maybeLog($method, $url, $query, is_array($json) ? array_keys($json) : null);
        }

        // Laravel Http supports ->send(method, url, options)
        $sendOptions = [];
        if (!empty($query)) {
            $sendOptions['query'] = $query;
        }
        if ($json !== null) {
            $sendOptions['json'] = $json;
        }

        return $request->send($method, $url, $sendOptions);
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

    /**
     * REST Record endpoint builder.
     */
    public function record(string $recordType): RecordEndpoint
    {
        return new RecordEndpoint($this, $recordType);
    }

    /**
     * SuiteQL helper.
     */
    public function suiteql(): SuiteQLEndpoint
    {
        return new SuiteQLEndpoint($this);
    }

    public function baseUrl(): string
    {
        return rtrim($this->restBaseUrl ?: $this->defaultBaseEndpoint(), '/');
    }

    protected function buildUrl(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        return $this->baseUrl() . $path;
    }

    protected function defaultBaseEndpoint(): string
    {
        // Production and sandbox both use this pattern; sandbox account may look like 5802217_SB1.
        return 'https://' . $this->account . '.suitetalk.api.netsuite.com';
    }

    /**
     * OAuth 1.0a header builder.
     * Includes query params in signature base string.
     */
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

        // Merge query in URL itself (if any) + explicit queryParams
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