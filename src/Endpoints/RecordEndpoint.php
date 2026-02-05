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
     * ->where('isinactive IS false', ['limit' => 1000])
     *
     * NetSuite uses the "q" query parameter for record collection filtering.
     */
    public function where(string $q, array $query = []): Response
    {
        $query['q'] = $q;

        return $this->list($query);
    }

    /**
     * Generator that yields page Responses, following links.next if present.
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
     * Great for streaming through a large collection.
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
     * Generator of IDs across all pages.
     *
     * NetSuite record collections generally return items like:
     *   ['id' => 123, 'links' => [...]]
     *
     * If your record type uses a different key, pass an extractor:
     *   ->listItemIdsAll([], fn($item) => $item['internalId'] ?? null)
     */
    public function listItemIdsAll(array $query = [], ?callable $idExtractor = null): \Generator
    {
        $idExtractor ??= fn ($item) => $item['id'] ?? null;

        foreach ($this->listItemsAll($query) as $item) {
            $id = $idExtractor($item);

            if ($id === null || $id === '') {
                continue;
            }

            yield $id;
        }
    }

    /**
     * Collect all items into an array.
     * Use this only when you're sure the result set is manageable.
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

    /**
     * Hydrate many records by:
     *  1) listing IDs (paged)
     *  2) calling getFields(id, fields) for each
     *
     * Usage:
     *  foreach ($endpoint->hydrateAll(['limit'=>1000], ['id','name']) as $row) { ... }
     *
     * Or provide a callback (no generator):
     *  $endpoint->hydrateAll(['limit'=>1000], ['id','name'], fn($row, $id) => ...);
     *
     * Options:
     * - $idExtractor: if collection items don't use "id"
     * - $onRow: callback to process each hydrated row
     * - $onError: callback for failed hydrate responses (default: skip)
     * - $sleepMsBetween: optional throttle between instance calls
     */
    public function hydrateAll(
        array $listQuery,
        array $fields,
        ?callable $onRow = null,
        ?callable $idExtractor = null,
        ?callable $onError = null,
        int $sleepMsBetween = 0
    ): \Generator|null {
        $onError ??= function (Response $response, $id): void {
            // default behavior: skip failures
        };

        $generator = (function () use ($listQuery, $fields, $onRow, $idExtractor, $onError, $sleepMsBetween) {
            foreach ($this->listItemIdsAll($listQuery, $idExtractor) as $id) {
                $resp = $this->getFields($id, $fields);

                if (! $resp->successful()) {
                    $onError($resp, $id);
                    continue;
                }

                $row = $resp->json();

                if (is_callable($onRow)) {
                    $onRow($row, $id);
                } else {
                    yield $row;
                }

                if ($sleepMsBetween > 0) {
                    usleep($sleepMsBetween * 1000);
                }
            }
        })();

        // If they provided a callback, just exhaust the generator and return null.
        if (is_callable($onRow)) {
            foreach ($generator as $_) {
                // no-op
            }
            return null;
        }

        return $generator;
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