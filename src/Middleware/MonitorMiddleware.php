<?php

namespace Skylee\MonitorClient\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Skylee\MonitorClient\MonitorPredis;

class MonitorMiddleware
{
    private $key = "monitor_task";

    /**
     * 处理传入的请求。
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    /**
     * 在响应发送到浏览器后处理任务。
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     *
     * @return void
     */
    public function terminate($request, $response): void
    {
        if (env("app_debug") == true) {
            return;
        }

        $res             = [];
        $res["router"]   = $this->getRouter($request);
        $res["time"]     = $this->getCountRequestTimes();
        $res["sql"]      = $this->sql($request);
        $res["memory"]   = round(memory_get_usage() / 1024 / 1024, 2);
        $res["name"]     = env("APP_NAME");
        $res["datetime"] = now()->toDateTimeString();
        $this->publish($res);
    }

    /**
     * 发布到redis中进行消费
     *
     * @param  array  $publishData
     *
     * @return void
     */
    private function publish(array $publishData): void
    {
        $db = config("app.monitor_redis_db", 2);
        //predis通过predis重新链接,不使用laravel的连接,但是会使用laravel的配置文件
        $redis = new MonitorPredis();
        $redis = $redis->conn();
        $redis->select($db);
        $redis->publish($this->key, json_encode($publishData));
    }

    /**
     * 获取当前请求路由
     *
     * @param  Request  $request
     *
     * @return string
     */
    private function getRouter(Request $request): string
    {
        try {
            $route = $request->route()->uri();
        } catch (\Exception  $exception) {
            return "#";
        }

        return "/".$route;
    }

    /**
     * 得到计算请求时间
     *
     * @return float
     */
    private function getCountRequestTimes(): float
    {
        $overTime = microtime(true);
        $time     = bcsub($overTime, LARAVEL_START, 2);

        return $time;
    }


    /**
     * 获取sql
     *
     * @param  Request  $request
     *
     * @return array
     */
    private function sql(Request $request): array
    {
        $sql = $request->attributes->get("sql");
        if (empty($sql)) {
            return [];
        }
        foreach ($sql as &$item) {
            $item['time'] = round($item['time'] / 1000, 2);
        }

        return $sql;
    }

}