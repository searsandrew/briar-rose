<?php

namespace Searsandrew\BriarRose\Facades;

use Illuminate\Support\Facades\Facade;
use Searsandrew\BriarRose\BriarRoseManager;

class BriarRose extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BriarRoseManager::class;
    }
}