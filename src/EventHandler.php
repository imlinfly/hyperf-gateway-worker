<?php

/**
 * Created by PhpStorm.
 * User: anonymous
 * Created time 2024/4/25 21:56
 * Email: anonymous@qq.com
 */
declare (strict_types=1);

namespace LynnFly\GatewayWorker;

use GatewayWorker\Lib\Gateway;
use LynnFly\GatewayWorker\Lib\GatewaySession;
use Throwable;

class EventHandler
{
    public static function onWebSocketConnect(string $clientId, array $body): void
    {
        var_dump(__METHOD__);
        echo "onWebSocketConnect clientId: $clientId\n";
    }

    public static function onConnect(string $clientId): void
    {
        var_dump(__METHOD__);
        echo "onConnect clientId: $clientId\n";

        $uid = mt_rand(1111, 9999);
        GatewaySession::set('uid', $uid); // or $_SESSION['uid'] = $uid;

        Gateway::sendToCurrentClient("Hello $uid, clientId: $clientId\n");
    }

    public static function onMessage(string $clientId, string $body): void
    {
        var_dump(__METHOD__);
        echo "onMessage clientId: $clientId, body: $body\n";

        $uid1 = GatewaySession::get('uid');
        $uid2 = $_SESSION['uid'] ?? null;

        Gateway::sendToCurrentClient("Hi，$uid1:$uid2\nReceived: $body\n");
    }

    public static function onClose(string $clientId): void
    {
        var_dump(__METHOD__);
        echo "onClose clientId: $clientId\n";
    }

    public static function onException(Throwable $throwable): void
    {
        var_dump(__METHOD__, $throwable->getMessage());
    }
}
