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

class GatewaySession
{
    protected static string $key = 'session';

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

    public static function init(): void
    {
        $session = Gateway::getSession(Context::get('client_id'));
        Context::set('old_session', $session);
        Context::set(static::$key, $session);
    }

    public static function save(): void
    {
        $clientId = Context::get('client_id');
        $oldSession = Context::get('old_session');

        $session = Context::get(static::$key);

        // 如果session有变化则保存
        if ($oldSession !== $session && is_array($session)) {
            Gateway::updateSession($clientId, $session);
        }
    }
}
