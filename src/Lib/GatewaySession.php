<?php

/**
 * Created by PhpStorm.
 * User: anonymous
 * Created time 2024/4/25 22:17
 * Email: anonymous@qq.com
 */
declare (strict_types=1);

namespace LynnFly\GatewayWorker\Lib;

use GatewayWorker\Lib\Context;
use GatewayWorker\Lib\Gateway;
use GatewayWorker\Protocols\GatewayProtocol;

class GatewaySession
{
    protected static string $key = 'session';

    protected static array $sessionVersion = [];

    public static function get(string $key = null, mixed $default = null): mixed
    {
        $data = Context::get(static::$key) ?? [];

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $session = Context::get(static::$key);
        $session[$key] = $value;
        Context::set(static::$key, $session);
    }

    public static function setData(array $data): void
    {
        Context::set(static::$key, $data);
    }

    public static function has(string $key): bool
    {
        return isset(Context::get(static::$key)[$key]);
    }

    public static function delete(string $key): void
    {
        $session = Context::get(static::$key);
        unset($session[$key]);
        Context::set(static::$key, $session);
    }

    public static function destroy(): void
    {
        Context::set(static::$key, []);
    }

    public static function init(int $cmd, array $data): void
    {
        $clientId = Context::get('client_id');
        $extData = $data['ext_data'] ?? '';
        $version = &static::$sessionVersion[$clientId];

        if ($cmd !== GatewayProtocol::CMD_ON_CLOSE && isset($version) && $version !== crc32($extData)) {
            $session = Gateway::getSession($clientId);
            $version = crc32($extData);
        } else {
            if (!isset(static::$sessionVersion[$clientId])) {
                $version = crc32($extData);
            }
            // 尝试解析 session
            if ($extData != '') {
                $session = Context::sessionDecode($extData);

            } else {
                $session = null;
            }
        }

        Context::set('old_session', $session);
        Context::set(static::$key, $session);
    }

    public static function save(): void
    {
        $clientId = Context::get('client_id');
        $oldSession = Context::get('old_session');
        $session = Context::get(static::$key);

        // 判断 session 是否被更改
        if ($session !== $oldSession) {
            $sessionString = $session !== null ? Context::sessionEncode($session) : '';
            Gateway::setSocketSession($clientId, $sessionString);
            static::$sessionVersion[$clientId] = crc32($sessionString);
        }
    }

    public static function deleteSessionVersion(string $clientId): void
    {
        unset(static::$sessionVersion[$clientId]);
    }
}
