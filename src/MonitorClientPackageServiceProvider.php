<?php

namespace Skylee\MonitorClient;

use App\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Skylee\MonitorClient\Middleware\MonitorMiddleware;

class MonitorClientPackageServiceProvider extends ServiceProvider
{


    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $kernel = $this->app[\Illuminate\Contracts\Http\Kernel::class];
        $kernel->prependMiddleware(MonitorMiddleware::class);
    }


}
