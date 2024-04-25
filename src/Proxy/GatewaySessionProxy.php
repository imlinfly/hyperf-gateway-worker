<?php

/**
 * Created by PhpStorm.
 * User: anonymous
 * Created time 2024/4/25 17:44
 * Email: anonymous@qq.com
 */
declare (strict_types=1);

namespace LynnFly\GatewayWorker\Proxy;

use ArrayAccess;
use Hyperf\Contract\Arrayable;
use JsonSerializable;
use LynnFly\GatewayWorker\Lib\GatewaySession;

class GatewaySessionProxy implements ArrayAccess, JsonSerializable, Arrayable
{
    public function offsetExists($offset): bool
    {
        return GatewaySession::has($offset);
    }

    public function offsetGet($offset): mixed
    {
        return GatewaySession::get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        GatewaySession::set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        GatewaySession::delete($offset);
    }

    public function __debugInfo(): array
    {
        return GatewaySession::get();
    }

    public function jsonSerialize(): array
    {
        return GatewaySession::get();
    }

    public function toArray(): array
    {
        return GatewaySession::get();
    }
}
