<?php

/**
 * Created by PhpStorm.
 * User: anonymous
 * Created time 2024/4/25 20:48
 * Email: anonymous@qq.com
 */
declare (strict_types=1);

namespace LynnFly\GatewayWorker\Lib;

use GatewayWorker\Lib\Gateway;
use Hyperf\Support\Composer;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;
use Throwable;

class HookGateway
{
    public string $gatewayVersion;

    public function __construct()
    {
        $this->init();
    }

    protected function init(): void
    {
        // 获取 workerman/gateway-worker 版本
        $versions = Composer::getVersions();
        $this->gatewayVersion = $versions['workerman/gateway-worker'] ?? null;

        if (empty($this->gatewayVersion)) {
            throw new RuntimeException('must install workerman/gateway-worker package');
        }

        $proxy = $this->getProxyPath();
        if (!is_dir($proxy)) {
            mkdir($proxy, 0755, true);
        }
    }

    /**
     * hook上下文管理类
     * @return void
     */
    public function hookContext(): void
    {
        $this->checkClassExist('GatewayWorker\Lib\Context');

        $path = $this->getName('Lib_Context');
        if (file_exists($path)) {
            require_once $path;
            return;
        }

        // 获取composer workerman/gateway-worker所在目录
        $file = Composer::getLoader()->findFile('GatewayWorker\Lib\Context');
        if (!file_exists($file)) {
            throw new RuntimeException('Load GatewayWorker\Lib\Context failed');
        }

        // // 获取文件内容
        $content = file_get_contents($file);

        // 创建解析器
        $parserFactory = new ParserFactory();
        $parser = $parserFactory->create(ParserFactory::ONLY_PHP7);

        try {
            // 解析代码为AST
            $ast = $parser->parse($content);

            // 创建AST遍历器
            $traverser = new NodeTraverser();

            // 创建静态成员删除器
            $staticMemberRemover = new class extends NodeVisitorAbstract {
                public function leaveNode(Node $node)
                {
                    // 如果是静态成员变量声明节点，将其从父节点中移除
                    if ($node instanceof Property && $node->isStatic()) {
                        return NodeTraverser::REMOVE_NODE;
                    }

                    return null;
                }
            };

            $staticMethodRemover = new class extends NodeVisitorAbstract {
                public function leaveNode(Node $node)
                {
                    // 如果是静态成员变量声明节点，将其从父节点中移除
                    if ($node instanceof ClassMethod && $node->name->name == 'clear') {
                        return NodeTraverser::REMOVE_NODE;
                    }

                    return null;
                }
            };

            $traverser->addVisitor($staticMemberRemover);
            $traverser->addVisitor($staticMethodRemover);

            // 使用遍历器处理AST
            $ast = $traverser->traverse($ast);

            // 使用AST Pretty Printer将AST转换为PHP代码
            $printer = new Standard();
            $newCode = $printer->prettyPrintFile($ast);

            // 在类的末尾加上__callStatic方法定义
            $callStaticMethodCode = "\n    public static function __callStatic(\$name, \$arguments) {\n" .
                "        return \LynnFly\GatewayWorker\Proxy\GatewayContextProxy::call(\$name, \$arguments);\n" .
                "    }";
            $newCode = preg_replace('/(}\s*)$/', "\n\n$callStaticMethodCode\n\n$1", $newCode);

            // 保存新代码
            if (!file_put_contents($path, $newCode)) {
                throw new RuntimeException('hook workerman/gateway-worker failed, save file failed');
            }

            require_once $path;

        } catch (Throwable $e) {
            throw new RuntimeException('hook workerman/gateway-worker failed, ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Hook Gateway类
     * @return void
     */
    public function hookGateway(): void
    {
        $this->checkClassExist(Gateway::class);
        $path = $this->getName('Lib_Gateway');
        if (file_exists($path)) {
            require_once $path;
            return;
        }

        // 获取composer workerman/gateway-worker所在目录
        $file = Composer::getLoader()->findFile(Gateway::class);
        if (!file_exists($file)) {
            throw new RuntimeException('Load GatewayWorker\Lib\Gateway failed');
        }

        // 获取文件内容
        $content = file_get_contents($file);

        // 创建解析器
        $parserFactory = new ParserFactory();
        $parser = $parserFactory->create(ParserFactory::ONLY_PHP7);

        // 解析代码为AST
        $ast = $parser->parse($content);

        $replaceContextStaticPropertyFetch = new class extends NodeVisitorAbstract {
            public function enterNode(Node $node)
            {
                $isStaticPropertyFetch = fn($node) => $node instanceof StaticPropertyFetch &&
                    $node->class instanceof Name &&
                    $node->class->toString() === 'Context';

                // 查找子级赋值节点
                foreach ($node->getSubNodeNames() as $name) {
                    $subNode = $node->$name;

                    if ($subNode instanceof Assign && $isStaticPropertyFetch($subNode->var)) {
                        // 替换赋值节点 Context::$var = $value => Context::set('var', $value)
                        $node->$name = new StaticCall(
                            new Name('Context'),
                            new Name('set'),
                            [
                                new Arg(new String_((string)$subNode->var->name)),
                                new Arg($subNode->expr)
                            ]
                        );
                    }

                    if (!$subNode instanceof Assign && $isStaticPropertyFetch($subNode)) {
                        // 替换属性访问节点 Context::$var => Context::get('var')
                        $node->$name = new StaticCall(
                            new Name('Context'),
                            new Name('get'),
                            [
                                new Arg(new String_((string)$subNode->name)),
                            ]
                        );
                    }

                    // $_SESSION = xxx => GatewaySession::setData($xxx)
                    if (
                        $subNode instanceof Assign &&
                        $subNode->var instanceof Node\Expr\Variable &&
                        $subNode->var->name == '_SESSION'
                    ) {
                        $node->$name = new StaticCall(
                            new Name('\LynnFly\GatewayWorker\Lib\GatewaySession'),
                            new Name('setData'),
                            [
                                new Arg($subNode->expr)
                            ]
                        );
                    }
                }

                return $node;
            }
        };

        // 创建AST遍历器
        $traverser = new NodeTraverser();

        $traverser->addVisitor($replaceContextStaticPropertyFetch);

        // 使用遍历器处理AST
        $ast = $traverser->traverse($ast);

        // 使用AST Pretty Printer将AST转换为PHP代码
        $printer = new Standard();
        $newCode = $printer->prettyPrintFile($ast);

        // 保存新代码
        if (!file_put_contents($path, $newCode)) {
            throw new RuntimeException('hook workerman/gateway-worker failed, save file failed');
        }

        require_once $path;
    }

    /**
     * 检查类是否存在
     * @param string $className
     * @return void
     */
    public function checkClassExist(string $className): void
    {
        if (class_exists($className, false)) {
            // 类 xx 不能在GatewayBusinessProcess进程启动前引入
            throw new RuntimeException('Class ' . $className . ' can not be loaded before GatewayBusinessProcess process start');
        }
    }

    /**
     * 获取代理路径
     * @param string $path
     * @return string
     */
    protected function getProxyPath(string $path = ''): string
    {
        return BASE_PATH . '/runtime/container/proxy/' . $path;
    }

    /**
     * 获取代理文件名
     * @param string $filename
     * @return string
     */
    protected function getName(string $filename): string
    {
        return $this->getProxyPath('GatewayWorker_' . $this->gatewayVersion . '_' . $filename . '.php');
    }
}
