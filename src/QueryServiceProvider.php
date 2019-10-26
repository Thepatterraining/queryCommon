<?php

namespace QueryCommon;

use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{

    protected $defer = true;

    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/QueryServiceProvider' => base_path('app/Providers'),
        ]);
    }

    public function provides() {
        return ['query'];
    }
}
