<?php

namespace Skylee\MonitorClient;

use Predis\Client;

class MonitorPredis
{

    public function conn()
    {
        $parameters = [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', '6379'),
        ];

        if ( ! empty(env('REDIS_PASSWORD'))) {
            $parameters['password'] = env('REDIS_PASSWORD');
        }

        return new Client($parameters);
    }


}