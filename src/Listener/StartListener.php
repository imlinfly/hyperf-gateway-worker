<?php

/**
 * Created by PhpStorm.
 * User: anonymous
 * Created time 2025/3/18 15:26
 * Email: anonymous@qq.com
 */
declare (strict_types=1);

namespace LynnFly\GatewayWorker\Listener;

use Hyperf\Contract\ContainerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Hyperf\Process\Event\BeforeProcessHandle;
use LynnFly\GatewayWorker\Lib\GatewayConfig;
use LynnFly\GatewayWorker\Process\GatewayBusinessProcess;

class StartListener implements ListenerInterface
{
    public function __construct(
        protected ContainerInterface $container,
    )
    {
    }

    public function listen(): array
    {
        return [
            BeforeWorkerStart::class,
            BeforeProcessHandle::class,
        ];
    }

    public function process(object $event): void
    {
        if ($event instanceof BeforeProcessHandle) {
            // 排除 GatewayBusinessProcess 进程
            if (!$event->process instanceof GatewayBusinessProcess) {
                // dump($event->process->name . ':StartListener');
                GatewayConfig::initGatewayClient();
            }

        } else if ($event instanceof BeforeWorkerStart) {
            // dump('ServerWorker#' . $event->workerId . ':StartListener');
            GatewayConfig::initGatewayClient();
        } else {
            // dump('null:StartListener');
            GatewayConfig::initGatewayClient();
        }
    }
}
