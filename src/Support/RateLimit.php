<?php

namespace Searsandrew\BriarRose\Support;

use Illuminate\Http\Client\Response;

class RateLimit
{
    /**
     * Best-effort extraction of common rate-limit headers.
     * Header names vary by gateway/NetSuite environment, so we check a few.
     */
    public static function fromResponse(Response $response): array
    {
        $h = fn(string $key) => $response->header($key);

        return [
            'limit' => $h('X-RateLimit-Limit') ?? $h('X-Rate-Limit-Limit') ?? null,
            'remaining' => $h('X-RateLimit-Remaining') ?? $h('X-Rate-Limit-Remaining') ?? null,
            'reset' => $h('X-RateLimit-Reset') ?? $h('X-Rate-Limit-Reset') ?? null,

            // Standard retry hint
            'retry_after' => $h('Retry-After') ?? null,
        ];
    }
}