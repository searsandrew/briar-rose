<?php

namespace Searsandrew\BriarRose\Facades;

use Illuminate\Support\Facades\Facade;

class BriarRose extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'briar-rose';
    }
}