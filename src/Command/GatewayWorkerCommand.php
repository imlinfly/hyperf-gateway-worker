<?php

declare(strict_types=1);

namespace LynnFly\GatewayWorker\Command;

use GatewayWorker\Gateway;
use GatewayWorker\Register;
use Hyperf\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Workerman\Worker;
use function Hyperf\Config\config as config;


class GatewayWorkerCommand extends Command
{
    protected ?string $name = 'gateway:worker';

    protected string $description = 'Gateway Worker Service.';

    protected bool $coroutine = false;

    protected function configure()
    {
        parent::configure();

        $this->addArgument('action', InputOption::VALUE_REQUIRED, 'start|stop|restart|reload|status|connections', 'start');
        $this->addOption('register', 'r', InputOption::VALUE_NONE, 'Enable register service');
        $this->addOption('gateway', 'g', InputOption::VALUE_NONE, 'Enable gateway service');
        $this->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run the worker in daemon mode.');
    }


    public function handle(): void
    {
        if ($this->input->getOption('register')) {
            $this->register();
        }

        if ($this->input->getOption('gateway')) {
            $this->gateway();
        }

        $this->runGatewayWorker();
    }

    /**
     * 运行 GatewayWorker
     * @return void
     */
    protected function runGatewayWorker(): void
    {
        global $argv;

        $argv[0] = 'gateway-worker';
        $argv[1] = $this->input->getArgument('action');

        Worker::$daemonize = (bool)$this->input->getOption('daemon');

        $config = config('gateway_worker.options', [
            'pidFile' => BASE_PATH . '/runtime/gateway-worker.pid',
            'logFile' => BASE_PATH . '/runtime/gateway-worker.log',
        ]);

        foreach ($config as $name => $value) {
            if (property_exists(Worker::class, $name)) {
                Worker::${$name} = $value;
            }
        }

        Worker::runAll();
    }

    /**
     * 创建注册中心服务
     * @return Register
     */
    protected function register(): Register
    {
        $config = config('gateway_worker.register', [
            'listen' => 'text://127.0.0.1:1238',
            'name' => 'RegisterService',
        ]);

        return $this->createProcess(Register::class, $config);
    }

    /**
     * 创建网关服务
     * @return Gateway
     */
    protected function gateway(): Gateway
    {
        $config = config('gateway_worker.gateway', [
            'listen' => 'websocket://0.0.0.0:7272',
            'name' => 'GatewayService',
        ]);

        return $this->createProcess(Gateway::class, $config);
    }

    /**
     * 创建进程
     * @param string $class
     * @param array $config
     * @return mixed
     */
    protected function createProcess(string $class, array $config): object
    {
        $instance = new $class($config['listen']);
        $instance->name = $config['name'];
        $instance->count = $config['count'] ?? 1;

        $options = $config['options'] ?? [];
        foreach ($options as $name => $value) {
            if (property_exists($instance, $name)) {
                $instance->{$name} = $value;
            }
        }

        return $instance;
    }
}
