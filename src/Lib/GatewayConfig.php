<?php

/**
 * Created by PhpStorm.
 * User: anonymous
 * Created time 2025/3/19 17:18
 * Email: anonymous@qq.com
 */
declare (strict_types=1);

namespace LynnFly\GatewayWorker\Lib;

use GatewayWorker\Lib\Gateway;
use Hyperf\Collection\Arr;
use LynnFly\GatewayWorker\EventHandler;
use function Hyperf\Config\config;

class GatewayConfig
{
    protected static array $config;

    public static function getBusiness(string $key = '', mixed $default = null): mixed
    {
        $defaultConfig = [
            // 是否启用
            'enable' => true,
            // 业务进程名称
            'name' => 'BusinessWorker',
            // 业务进程数量
            'count' => 1,
            // 单进程并发处理数
            'parallel_count' => 20,
            // 单进程channel数量
            'channel_count' => 1024,
            // 事件处理类
            'event_handler' => EventHandler::class,
            // 注册中心地址
            'register_address' => ['127.0.0.1:1236'],
            // 注册中心密钥
            'register_secret_key' => '',
        ];

        return static::get($key, $default, 'business', $defaultConfig);
    }

    public static function getGateway(string $key = '', mixed $default = null): mixed
    {
        $defaultConfig = [
            // 网关进程监听地址
            'listen' => 'websocket://0.0.0.0:7272',
            // 网关进程名称
            'name' => 'GatewayService',
            // 网关进程数量
            'count' => swoole_cpu_num() * 2,
            // 网关进程选项
            'options' => [
                'lanIp' => '127.0.0.1',
                'startPort' => 2300,
                'pingInterval' => 25,
                'pingData' => '{"type":"ping"}',
                'registerAddress' => '127.0.0.1:1236',
                'onConnect' => function () {
                },
            ],
        ];

        return static::get($key, $default, 'gateway', $defaultConfig);
    }

    public static function getRegister(string $key = '', mixed $default = null): mixed
    {
        $defaultConfig = [
            // 注册中心监听地址
            'listen' => 'text://127.0.0.1:1236',
            // 注册中心进程名称
            'name' => 'RegisterService',
            // 注册中心进程数量 必须是1个进程
            'count' => 1,
            // 注册中心选项
            'options' => [
                'secretKey' => '',
            ],
        ];

        return static::get($key, $default, 'register', $defaultConfig);
    }

    public static function getOptions(): mixed
    {
        return static::get('options', [], merge: [
            'pidFile' => BASE_PATH . '/runtime/gateway-worker.pid',
            'logFile' => BASE_PATH . '/runtime/gateway-worker.log',
        ]);
    }

    protected static function get(string $key = '', mixed $default = null, string $field = '', array $merge = []): mixed
    {
        $config = static::$config ??= config('gateway_worker', []);

        if ($field !== '') {
            $config = Arr::get($config, $field, []);
        }

        if ($merge) {
            $config = array_merge($merge, $config);
        }

        if ($key === '') {
            return $config;
        }

        return Arr::get($config, $key, $default);
    }

    public static function initGatewayClient(): void
    {
        $config = static::getBusiness();

        Gateway::$registerAddress = $config['register_address'];
        Gateway::$secretKey = $config['register_secret_key'];
    }
}
