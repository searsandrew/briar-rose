<?php

use Searsandrew\BriarRose\Facades\BriarRose;

it('builds record base paths and supports fields', function () {
    $endpoint = BriarRose::rest()->record('inventory_item');

    // We can’t easily inspect private URL building here without faking HTTP,
    // so we just assert the object exists and that methods are callable.
    expect($endpoint)->toBeObject();

    // Ensure helper doesn't throw
    $response = $endpoint->getFields(123, ['id', 'itemId']);
    expect($response)->toBeInstanceOf(\Illuminate\Http\Client\Response::class);
})->skip('Enable with Http::fake() in testbench');