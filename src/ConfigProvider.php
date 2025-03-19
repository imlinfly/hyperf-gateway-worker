<?php

/**
 * Created by PhpStorm.
 * User: LinFei
 * Created time 2023/12/14 14:11:28
 * E-mail: fly@eyabc.cn
 */
declare(strict_types=1);

namespace LynnFly\GatewayWorker;

class ConfigProvider
{
    public function __invoke(): array
    {
        defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__, 2));

        return [
            'processes' => [
                Process\GatewayBusinessProcess::class,
            ],
            'commands' => [
                Command\GatewayWorkerCommand::class,
            ],
            'listeners' => [
                Listener\StartListener::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config of ',
                    'source' => __DIR__ . '/../publish/gateway_worker.php',
                    'destination' => BASE_PATH . '/config/autoload/gateway_worker.php',
                ],
            ],
        ];
    }
}
