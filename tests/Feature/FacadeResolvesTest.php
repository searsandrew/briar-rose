<?php

use Searsandrew\BriarRose\Facades\BriarRose;
use Searsandrew\BriarRose\BriarRoseManager;

it('resolves the manager via the facade', function () {
    expect(app()->bound(BriarRoseManager::class))->toBeTrue();
    expect(BriarRose::rest())->not->toBeNull();
});