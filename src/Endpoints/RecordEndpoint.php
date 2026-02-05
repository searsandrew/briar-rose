<?php

namespace Searsandrew\BriarRose\Endpoints;

use Illuminate\Http\Client\Response;
use Searsandrew\BriarRose\Clients\RestClient;

class RecordEndpoint
{
    public function __construct(
        protected RestClient $client,
        protected string $recordType,
    ) {}

    public function list(array $query = []): Response
    {
        return $this->client->get($this->basePath(), $query);
    }

    /**
     * Generator that follows "links.next" if present.
     */
    public function listAll(array $query = []): \Generator
    {
        $path = $this->basePath();
        $nextUrl = null;

        do {
            $response = $nextUrl
                ? $this->client->request('GET', $this->relativeFromAbsolute($nextUrl))
                : $this->client->get($path, $query);

            $json = $response->json();

            yield $response;

            $nextUrl = null;
            if (is_array($json) && isset($json['links']) && is_array($json['links'])) {
                foreach ($json['links'] as $link) {
                    if (($link['rel'] ?? null) === 'next' && !empty($link['href'])) {
                        $nextUrl = $link['href'];
                        break;
                    }
                }
            }
        } while ($nextUrl);
    }

    public function get(int|string $id, array $query = []): Response
    {
        return $this->client->get($this->basePath() . '/' . rawurlencode((string) $id), $query);
    }

    public function create(array $payload): Response
    {
        return $this->client->post($this->basePath(), $payload);
    }

    public function update(int|string $id, array $payload): Response
    {
        return $this->client->patch($this->basePath() . '/' . rawurlencode((string) $id), $payload);
    }

    public function delete(int|string $id): Response
    {
        return $this->client->delete($this->basePath() . '/' . rawurlencode((string) $id));
    }

    /**
     * Upsert by external id.
     * NetSuite convention: eid:{externalId}
     */
    public function upsert(string $externalId, array $payload): Response
    {
        $eid = 'eid:' . $externalId;

        return $this->client->patch($this->basePath() . '/' . rawurlencode($eid), $payload);
    }

    protected function basePath(): string
    {
        return '/services/rest/record/v1/' . $this->normalizeRecordType($this->recordType);
    }

    protected function normalizeRecordType(string $type): string
    {
        $type = trim($type);

        // If snake_case or kebab-case, convert to camelCase.
        if (str_contains($type, '_') || str_contains($type, '-')) {
            $type = str_replace('-', '_', $type);
            $parts = array_filter(explode('_', $type), fn ($p) => $p !== '');
            $first = array_shift($parts) ?? '';
            $camel = $first;
            foreach ($parts as $p) {
                $camel .= ucfirst($p);
            }
            return $camel;
        }

        return $type;
    }

    protected function relativeFromAbsolute(string $absoluteUrl): string
    {
        $parsed = parse_url($absoluteUrl);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';

        return $query ? ($path . '?' . $query) : $path;
    }
}