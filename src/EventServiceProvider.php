<?php

namespace Skylee\MonitorClient;

class EventServiceProvider extends \App\Providers\EventServiceProvider
{
    protected $listen
        = [
            \Illuminate\Database\Events\QueryExecuted::class => [
                QueryListener::class,
            ],
        ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

}