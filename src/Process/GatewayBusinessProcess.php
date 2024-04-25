<?php

/**
 * Created by PhpStorm.
 * User: anonymous
 * Created time 2024/4/24 23:08
 * Email: anonymous@qq.com
 */
declare (strict_types=1);

namespace LynnFly\GatewayWorker\Process;

use GatewayWorker\Lib\Context;
use GatewayWorker\Lib\Gateway;
use GatewayWorker\Protocols\GatewayProtocol;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\ProcessManager;
use LynnFly\GatewayWorker\EventHandler;
use LynnFly\GatewayWorker\Lib\GatewaySession;
use LynnFly\GatewayWorker\Lib\HookGateway;
use LynnFly\GatewayWorker\Proxy\GatewaySessionProxy;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine\Channel;
use Throwable;
use Workerman\Gateway\Config\GatewayWorkerConfig;
use Workerman\Gateway\Gateway\Contract\IGatewayClient;
use Workerman\Gateway\Gateway\GatewayWorkerClient;
use function Hyperf\Config\config;
use function Hyperf\Support\value;
use function Swoole\Coroutine\parallel;

class GatewayBusinessProcess extends AbstractProcess
{
    public function __construct(
        ContainerInterface              $container,
        protected StdoutLoggerInterface $logger,
        protected HookGateway           $hook,
        protected GatewaySessionProxy   $session,
    )
    {
        parent::__construct($container);

        $this->initProcessParams();
    }

    public function initProcessParams()
    {
        $config = $this->getConfig();
        $this->name = $config['name'];
        $this->nums = $config['count'];
    }

    public function isEnable($server): bool
    {
        return boolval($this->getConfig()['enable']);
    }

    public function handle(): void
    {
        // Hook GatewayWorker
        $this->hook->hookContext();
        $this->hook->hookGateway();

        Coroutine::create(function () {
            $this->create();
        });

        while (ProcessManager::isRunning()) {
            Coroutine::sleep(1);
        }
    }

    /**
     * 创建业务进程
     * @return void
     */
    public function create(): void
    {
        $config = $this->getConfig();
        $channel = new Channel($config['channel_count']);

        $handler = $config['event_handler'];

        $_SESSION = $this->session;

        Coroutine::create(function () use ($config, $channel, $handler) {
            // 通过 Channel 实现单进程多协程任务处理
            parallel($config['parallel_count'], function () use ($channel, $handler) {
                while (true) {
                    $result = $channel->pop();
                    if (false === $result) {
                        break;
                    }

                    switch ($result['type']) {
                        // 异常处理
                        case 'onException':
                            /** @var Throwable $th */
                            ['th' => $th] = $result['data'];
                            if (method_exists($handler, 'onException')) {
                                $handler::onException($th);
                            }
                            break;

                        // 网关消息处理
                        case 'onGatewayMessage':
                            /** @var IGatewayClient $client */
                            ['message' => $message] = $result['data'];

                            $clientId = Context::addressToClientId($message['local_ip'], $message['local_port'], $message['connection_id']);

                            $this->initContext($message, $clientId);

                            switch ($message['cmd']) {
                                case GatewayProtocol::CMD_ON_WEBSOCKET_CONNECT:
                                    if (method_exists($handler, 'onWebSocketConnect')) {
                                        $handler::onWebSocketConnect($clientId, $message['body']);
                                    }
                                    break;

                                case GatewayProtocol::CMD_ON_CONNECT:
                                    if (method_exists($handler, 'onConnect')) {
                                        $handler::onConnect($clientId);
                                    }
                                    break;
                                case GatewayProtocol::CMD_ON_MESSAGE:
                                    if (method_exists($handler, 'onMessage')) {
                                        $handler::onMessage($clientId, $message['body']);
                                    }
                                    break;
                                case GatewayProtocol::CMD_ON_CLOSE:
                                    if (method_exists($handler, 'onClose')) {
                                        $handler::onClose($clientId);
                                    }
                                    break;
                            }

                            $this->clearContext($message);
                            break;
                    }
                }
            });
        });

        // 连接 Gateway Worker
        $this->createGatewayWorkerClient($channel);
    }

    /**
     * 初始化上下文
     * @param array $message
     * @param string $clientId
     * @return void
     */
    public function initContext(array $message, string $clientId)
    {
        /** @var GatewaySessionProxy $_SESSION */
        if (!$_SESSION instanceof GatewaySessionProxy) {
            $this->logger->error('Please do not cover the $_SESSION variable, otherwise it will cause data confusion!');
            $_SESSION = $this->session;
        }

        Context::set('client_ip', $message['client_ip']);
        Context::set('client_port', $message['client_port']);
        Context::set('local_ip', $message['local_ip']);
        Context::set('local_port', $message['local_port']);
        Context::set('connection_id', $message['connection_id']);
        Context::set('client_id', $clientId);

        if ($message['cmd'] !== GatewayProtocol::CMD_ON_CLOSE) {
            GatewaySession::init();
        }
    }

    /**
     * 清理上下文
     * @param array $message
     * @return void
     */
    public function clearContext(array $message): void
    {
        if ($message['cmd'] !== GatewayProtocol::CMD_ON_CLOSE) {
            GatewaySession::save();
        }
    }

    /**
     * 创建与 Gateway Worker 的连接
     * @param Channel $channel
     * @return void
     */
    public function createGatewayWorkerClient(Channel $channel): void
    {
        $config = $this->getConfig();

        $gatewayConfig = new GatewayWorkerConfig();
        $gatewayConfig->setRegisterAddress($config['register_address']);

        // Gateway Client 配置
        Gateway::$registerAddress = $gatewayConfig->getRegisterAddress();

        $config['worker_key'] ??= getmypid() . '-' . Coroutine::id();
        $workerKey = value($config['worker_key']);

        // Gateway Worker
        $client = new GatewayWorkerClient($workerKey, $gatewayConfig);
        // 异常处理
        $client->onException = function (Throwable $th) use ($channel) {
            $channel->push([
                'type' => 'onException',
                'data' => [
                    'th' => $th,
                ],
            ]);
        };
        // 网关消息
        $client->onGatewayMessage = function (IGatewayClient $client, array $message) use ($channel) {
            $channel->push([
                'type' => 'onGatewayMessage',
                'data' => [
                    'client' => $client,
                    'message' => $message,
                ],
            ]);
        };
        $client->run();
    }

    public function getConfig(): array
    {
        return config('gateway_worker.business', [
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
        ]);
    }
}
