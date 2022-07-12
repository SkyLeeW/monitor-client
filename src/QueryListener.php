<?php

namespace Skylee\MonitorClient;

use Illuminate\Database\Events\QueryExecuted;

use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Contracts\Queue\ShouldQueue;


class QueryListener
{
    /**
     * Handle the event.
     *
     * @param  QueryExecuted  $event
     *
     * @return void
     */

    public function handle(QueryExecuted $event)
    {
        $data = [
            "sql"     => $event->sql,
            'time'    => $event->time,
            'binding' => $event->bindings,
        ];

        $sql = request()->attributes->get('sql');

        $sql[] = $data;

        request()->attributes->set('sql', $sql);
    }

}