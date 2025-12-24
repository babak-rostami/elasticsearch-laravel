<?php

namespace Babak\Elasticsearch\Facades;

use Illuminate\Support\Facades\Facade;

class Elasticsearch extends Facade
{
    /**
     * Create a new class instance.
     */
    public static function getFacadeAccessor()
    {
        return 'elasticsearch_service';
    }
}
