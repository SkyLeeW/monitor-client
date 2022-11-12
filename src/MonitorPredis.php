<?php

namespace Skylee\MonitorClient;

use Predis\Client;

class MonitorPredis
{

    public function conn()
    {
        $parameters = [
            'host' => config("database.redis.default.host"),
            'port' => config("database.redis.default.port"),
        ];

        if ( ! empty(config("database.redis.default.password"))) {
            $parameters['password'] = config("database.redis.default.password");
        }

        return new Client($parameters);
    }


}