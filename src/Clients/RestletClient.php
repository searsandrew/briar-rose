<?php

namespace Searsandrew\BriarRose\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SearsAndrew\BriarRose\Exceptions\BriarRoseConfigurationException;

class RestletClient
{
    public function __construct(
        protected string $account,
        protected string $consumerKey,
        protected string $consumerSecret,
        protected string $tokenId,
        protected string $tokenSecret,
        protected string $restletBaseUrl,
        protected int $timeout = 30,
        protected int $connectTimeout = 10,
        protected bool $logRequests = false,
    ) {
        $this->guardConfigured();
    }

    /**
     * Perform a request to the configured NetSuite RESTlet URL.
     */
    public function request(string $method, array $data = []): Response
    {
        return $this->send(strtoupper($method), $this->restletBaseUrl, $data);
    }

    /**
     * Internal method to handle requests with OAuth 1.0 authentication.
     */
    protected function send(string $method, string $url, array $data = []): Response
    {
        $method = strtoupper($method);
        $authHeader = $this->getAuthHeader($method, $url, $method === 'GET' ? $data : []);

        $request = $this->http()->withHeaders([
            'Authorization' => $authHeader,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        if ($method === 'GET') {
            if (! empty($data)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data, '', '&', PHP_QUERY_RFC3986);
            }

            $this->maybeLog($method, $url, $data);

            return $request->get($url);
        }

        $this->maybeLog($method, $url, $data);

        return $request->send($method, $url, [
            'json' => $data,
        ]);
    }

    /**
     * Generate the OAuth 1.0 Authorization header.
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
                     'restletBaseUrl' => $this->restletBaseUrl,
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