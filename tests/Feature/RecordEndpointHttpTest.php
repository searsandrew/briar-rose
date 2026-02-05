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