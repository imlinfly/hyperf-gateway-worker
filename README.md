[//]: # (用到的组件)

### 让GatewayWorker支持在Hyperf Swoole环境下运行

## 用到的组件

1. [yurunsoft/workerman-gateway-sdk](https://github.com/Yurunsoft/workerman-gateway-sdk)
2. [workerman/gateway-worker](https://github.com/walkor/GatewayWorker)

### 安装

```bash
composer require lynnfly/hyperf-gateway-worker
```

### 发布配置

```bash
php bin/hyperf.php vendor:publish lynnfly/hyperf-gateway-worker
```

### 查看命令帮助

```bash
php bin/hyperf.php gateway:worker --help

Description:
  Gateway Worker Service.

Usage:
  gateway:worker [options] [--] [<action>]

Arguments:
  action                          start|stop|restart|reload|status|connections [default: "start"]

Options:
  -r, --register                  Enable register service
  -g, --gateway                   Enable gateway service
  -d, --daemon                    Run the worker in daemon mode.
```

### 启动服务

<div style="color:#ff4545">
该命令只用于开发环境使用，生产环境建议网关、注册中心进程与业务分开部署
</div>
<div style="color:#ff4545">
具体请参考GatewayWorker官方文档。
</div>

```bash
php bin/hyperf.php gateway:worker -r -g start
```

#### 业务代码示例

1. 修改gateway_worker.php配置文件中的event_handler配置项为你的业务类

```php
return [
    // 业务进程
    'business' => [
        // 是否启用
        'enable' => true,
        // ...
        // 事件处理类
        'event_handler' => LynnFly\GatewayWorker\EventHandler::class,
        // ...
    ],
    // ...
];
```

2. 业务事件处理类示例

- onWebSocketConnect: WebSocket连接事件
- onConnect: 客户端连接事件
- onMessage: 客户端消息事件
- onClose: 客户端关闭事件
- onException: 异常事件

<div style="color:#ff4545">
GatewayWorker的事件处理类必须是静态方法
</div>
<div style="color:#ff4545">
请勿覆盖$_SESSION变量，否则可能会导致数据不一致，建议使用GatewaySession类
</div>

```php
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
```
