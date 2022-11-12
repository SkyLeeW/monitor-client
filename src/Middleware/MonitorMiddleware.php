<?php

namespace Skylee\MonitorClient\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Skylee\MonitorClient\MonitorPredis;
use Skylee\MonitorClient\Xhprof\XhprofProcesser;

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
//        xhprof_enable(XHPROF_FLAGS_MEMORY + XHPROF_FLAGS_NO_BUILTINS);

        $request->attributes->set("init_time", microtime(true));

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
        if (config("app.debug") == true) {
            return;
        }
//todo xhprof 定量分析模块
//        $xhprof_data = xhprof_disable();
//
//        $xhprof = new XhprofProcesser();
//        $xhprof->start($xhprof_data);

        $res             = [];
        $res["router"]   = $this->getRouter($request);
        $res["time"]     = $this->getCountRequestTimes();
        $res["sql"]      = $this->sql($request);
        $res["memory"]   = round(memory_get_usage() / 1024 / 1024, 2);
        $res["name"]     = config("app.name");
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
        } catch (\Throwable  $exception) {
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
        //octane cli模式下,常量是不存在的
        if (defined("LARAVEL_START")) {
            $startTime = LARAVEL_START;
        } else {
            $startTime = request()->attributes->get('init_time');
        }
        $overTime = microtime(true);
        $time     = bcsub($overTime, $startTime, 4);

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
            $item['time'] = round($item['time'] / 1000, 6);
        }


        return $sql;
    }

}