<?php

/**
 * Created by PhpStorm.
 * User: LynnFly
 * Created time 2024/4/25 18:55
 * Email: fly@eyabc.cn
 */
declare (strict_types=1);

return [
    // 业务进程
    'business' => [
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
        'event_handler' => LynnFly\GatewayWorker\EventHandler::class,
        // 注册中心地址
        'register_address' => ['127.0.0.1:1236'],
        // 注册中心密钥
        'register_secret_key' => '',
        // 业务进程Key
        // 'worker_key' => null,
    ],

    // 网关进程
    'gateway' => [
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
    ],

    // 注册中心进程
    'register' => [
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
    ],

    'options' => [
        'pidFile' => BASE_PATH . '/runtime/gateway-worker.pid',
        'logFile' => BASE_PATH . '/runtime/gateway-worker.log',
    ],
];
