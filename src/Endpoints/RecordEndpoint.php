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
        $query = $this->applyDefaultLimit($query);

        return $this->client->get($this->basePath(), $query);
    }

    /**
     * Convenience for NetSuite collection filtering:
     * ->where('isInactive IS false', ['limit' => 1000])
     *
     * NetSuite uses the "q" query parameter for record collection filtering.
     */
    public function where(string $q, array $query = []): Response
    {
        $query['q'] = $q;

        return $this->list($query);
    }

    /**
     * Generator that yields page Responses, following links.
     */
    public function listAll(array $query = []): \Generator
    {
        $query = $this->applyDefaultLimit($query);

        $path = $this->basePath();
        $nextUrl = null;

        do {
            $response = $nextUrl
                ? $this->client->request('GET', $this->relativeFromAbsolute($nextUrl))
                : $this->client->get($path, $query);

            $json = $response->json();

            yield $response;

            $nextUrl = $this->extractNextHref($json);
        } while ($nextUrl);
    }

    /**
     * Generator that yields *items* across all pages (not responses).
     * Intended for streaming through a large collection.
     */
    public function listItemsAll(array $query = []): \Generator
    {
        foreach ($this->listAll($query) as $page) {
            $json = $page->json();
            $items = is_array($json) ? ($json['items'] ?? []) : [];

            foreach ($items as $item) {
                yield $item;
            }
        }
    }

    /**
     * Collect all items into an array.
     * Important: Use this only when you're sure the result set is manageable.
     */
    public function collectAllItems(array $query = []): array
    {
        $all = [];
        foreach ($this->listItemsAll($query) as $item) {
            $all[] = $item;
        }
        return $all;
    }

    public function get(int|string $id, array $query = []): Response
    {
        return $this->client->get($this->basePath() . '/' . rawurlencode((string) $id), $query);
    }

    /**
     * Request only certain fields for a record instance GET.
     * NetSuite supports: ?fields=field1,field2,...
     */
    public function getFields(int|string $id, array $fields): Response
    {
        return $this->get($id, [
            'fields' => implode(',', $fields),
        ]);
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

    protected function applyDefaultLimit(array $query): array
    {
        // If user didn't specify paging, apply default_limit from config.
        if (!array_key_exists('limit', $query)) {
            $defaultLimit = $this->client->restOptions()['default_limit'] ?? null;
            if ($defaultLimit !== null) {
                $query['limit'] = (int) $defaultLimit;
            }
        }

        return $query;
    }

    protected function extractNextHref($json): ?string
    {
        if (!is_array($json)) {
            return null;
        }

        $links = $json['links'] ?? null;
        if (!is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (($link['rel'] ?? null) === 'next' && !empty($link['href'])) {
                return $link['href'];
            }
        }

        return null;
    }

    protected function relativeFromAbsolute(string $absoluteUrl): string
    {
        $parsed = parse_url($absoluteUrl);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';

        return $query ? ($path . '?' . $query) : $path;
    }
}