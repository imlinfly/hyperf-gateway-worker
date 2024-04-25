<?php

/**
 * Created by PhpStorm.
 * User: anonymous
 * Created time 2024/4/25 12:37
 * Email: anonymous@qq.com
 */
declare (strict_types=1);

namespace LynnFly\GatewayWorker\Proxy;

use BadMethodCallException;
use Hyperf\Context\Context;

class GatewayContextProxy
{

    public static function get(string $name): mixed
    {
        $key = 'gateway.' . $name;
        return Context::get($key);
    }

    public static function set(string $name, mixed $value): void
    {
        $key = 'gateway.' . $name;
        Context::set($key, $value);
    }

    public static function clear(): void
    {
        Context::destroy('gateway');
    }

    public static function call(string $name, array $args): mixed
    {
        if (!method_exists(static::class, $name)) {
            throw new BadMethodCallException ("Call to undefined method " . static::class . "::" . $name . "()");
        }

        return static::$name(...$args);
    }
}
