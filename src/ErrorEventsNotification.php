<?php

namespace Skylee\MonitorClient;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ErrorEventsNotification
{
    /**
     * Handle the event.
     *
     * @param  QueryExecuted  $event
     *
     * @return void
     */

    public function handle(MessageLogged $logged)
    {
        try {
            $this->processErrorException($logged->message, $logged->context);
        } catch (\Throwable $exception) {
        }
    }

    private function processErrorException(
        string $message,
        array $content
    ): void {
        if (env('APP_ENV') != 'production') {
            return;
        }

        if (true === self::filter($message)) {
            return;
        };
        if (empty($content["exception"])) {
            return;
        };

        $throws = $content["exception"];

        $line = $throws->getFile().' 第'.$throws->getLine().'行';
        $line = str_replace('\\', '/', $line);

        $data['message'] = $message;
        $data['line']    = $line;
        $data['time']    = now()->toDateTimeString();
        $data['router']  = request()->fullUrl();
        $data['params']  = json_encode(request()->all());

        $ip = request()->header('x-real-ip');
        if (empty($ip)) {
            $ip = request()->getClientIp();
        }
        $data["ip"] = $ip;

        //设置阻断,避免一直发送
        if (self::IsItBlock($message)) {
            dispatch(function () use ($data) {
                $this->notifyMessageToWechatBot($data);
            });
        };
    }

    /**
     * 过滤不需要的错误
     */
    static function filter($message)
    {
        if (Str::contains($message, "Invalid Host")) {
            return true;
        }

        return false;
    }

    /**
     * 是否阻挡上报异常,避免重复发送
     */
    static function IsItBlock($errorMessage)
    {
        $key     = "exceptions:key:".$errorMessage;
        $counter = Cache::get($key);

        if (empty($counter)) {
            $counter = 1;
            //如果第一次报错,则请求一次
            $exp = now()->addMinutes(1);
            Cache::put($key, ['counter' => $counter, 'exp' => $exp], $exp);

            return true;
        } else {
            $count = $counter['counter']++;
            //如果连续触发超过N次,则截断发送则触发时间会在加5分钟,最多加到直到15次大概一个小时在不触发为止,
            if ($count > 4 && $count < 15) {
                $exp = now()->addMinutes(5);
                Cache::put($key, ['counter' => $count, 'exp' => $exp], $exp);
            } else {
                $count++;
                //正常不调整时间
                Cache::put(
                    $key,
                    ['counter' => $count, 'exp' => $counter['exp']],
                    $counter['exp']
                );
            }

            return false;
        }
    }

    /**
     * 发送通知请求
     *
     * @param $data
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function notifyMessageToWechatBot($data): void
    {
        $randomStr = Str::random(6);
        $time      = time();
        $appId     = env('APP_NOTIFY_KEY');
        $sign      = $this->sign($randomStr, $time, $appId);
        if (empty($appId)) {
            return;
        }
        $botUrl = env("botUrl");
        if (empty($botUrl)) {
            return;
        }

        $client = new \GuzzleHttp\Client();
        $client->request("POST", $botUrl, [
            "form_params" => [
                "time"      => $time,
                'randomStr' => $randomStr,
                'ak'        => $appId,
                'sign'      => $sign,
                'msg'       => $data,
            ],
        ]);
    }


    /**
     * 签名
     *
     * @param $str
     * @param $time
     * @param $appId
     *
     * @return string
     */
    public function sign($str, $time, $appId)
    {
        $waitSign = "%s&%s&%s";

        return md5(sprintf($waitSign, $time, $str, $appId));
    }


}