<?php namespace QueryCommon;

use Illuminate\Support\Facades\Facade;

class Query extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'query';
    }
}