<?php

use Illuminate\Support\Facades\Http;
use Searsandrew\BriarRose\Facades\BriarRose;

it('sends fields query on record getFields', function () {
    Http::fake(fn ($request) => Http::response(['ok' => true], 200));

    $response = BriarRose::rest()
        ->record('inventoryItem')
        ->getFields(123, ['id', 'itemId']);

    expect($response->status())->toBe(200);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/services/rest/record/v1/inventoryItem/123')
            && str_contains($request->url(), 'fields=id%2CitemId');
    });
});

it('hydrates records with fields', function () {
    Http::fake(function ($request) {
        $url = $request->url(); // includes query string

        // 1) INSTANCE first (more specific)
        if (str_contains($url, '/services/rest/record/v1/classification/123')) {
            return Http::response(['id' => 123, 'name' => 'HVAC'], 200);
        }

        // 2) LIST second (less specific)
        if (str_contains($url, '/services/rest/record/v1/classification')) {
            return Http::response([
                'items' => [
                    ['id' => 123, 'links' => []],
                ],
                'links' => [],
            ], 200);
        }

        return Http::response(['error' => 'unexpected: ' . $url], 500);
    });

    $rows = [];
    foreach (BriarRose::rest()->record('classification')->hydrateAll(['limit' => 1000], ['id', 'name']) as $row) {
        $rows[] = $row;
    }

    expect($rows)->toHaveCount(1);
    expect($rows[0]['name'])->toBe('HVAC');
});