<?php

namespace Latrell\Lock\Facades;

use Illuminate\Support\Facades\Facade;

class Lock extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'lock';
    }
}
