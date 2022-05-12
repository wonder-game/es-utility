<?php
namespace WonderGame\EsUtility;

use EasySwoole\Command\Color;
use EasySwoole\EasySwoole\Command\Utility;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\Socket\AbstractInterface\ParserInterface;
use EasySwoole\Spl\SplBean;
use WonderGame\EsNotify\EsNotify;
use WonderGame\EsNotify\Interfaces\ConfigInterface;

class EventMainServerCreate extends SplBean
{
    /**
     * 必传，MainServerCreate EventRegister对象
     * @var null | EventRegister
     */
    protected $EventRegister = null;

    /**
     * WebSocket事件， [EventRegister::onOpen => [Events::class, 'onOpen']]
     * @var null
     */
    protected $webSocketEvents = null;

    /**
     * WebSocket解释器
     * @var null
     */
    protected $WebSocketParser = null;

    /**
     *
     * @var null
     */
    protected $crontabClass = null;
    protected $crontabRunEnv = null;


    protected $hotReloadWatchDirs = null;

    /**
     * @var null ['key' => new EsNotify/Config([])]
     */
    protected $notifyConfig = null;

    protected $consumerJobs = null;

    protected function initialize(): void
    {
        if (is_null($this->WebSocketParser)) {
            $this->WebSocketParser = \WonderGame\EsUtility\WebSocket\Parser::class;
        }
        if (is_null($this->crontabClass)) {
            $this->crontabClass = \WonderGame\EsUtility\Crontab\Crontab::class;
        }
        if (is_null($this->crontabRunEnv)) {
            $this->crontabRunEnv = ['dev', 'produce'];
        }
        if (is_null($this->hotReloadWatchDirs)) {
            $this->hotReloadWatchDirs = [EASYSWOOLE_ROOT . '/App', EASYSWOOLE_ROOT . '/vendor/wonder-game'];
        }
        if (is_null($this->notifyConfig)) {
            $this->notifyConfig = config('ES_NOTIFY');
        }
    }

    public function run()
    {
        $this->registerWebSocketServer();
        $this->registerCrontab();
        $this->watchHotReload();
        $this->registerNotify();
        $this->registerConsumer();
    }

    protected function registerWebSocketServer()
    {
        $register = $this->EventRegister;
        if ( ! $register instanceof EventRegister) {
            throw new \Exception('EventRegister Error');
        }

        $config = new \EasySwoole\Socket\Config();
        $config->setType(\EasySwoole\Socket\Config::WEB_SOCKET);
        if ($this->WebSocketParser) {
            $parserClassName = $this->WebSocketParser;
            $ParserClass = new $parserClassName();
            if ($ParserClass instanceof ParserInterface) {
                $config->setParser($ParserClass);
            }
        }

        $dispatch = new \EasySwoole\Socket\Dispatcher($config);
        $register->set(
            $register::onMessage,
            function (\Swoole\Websocket\Server $server, \Swoole\WebSocket\Frame $frame) use ($dispatch) {
                $dispatch->dispatch($server, $frame->data, $frame);
            }
        );
        $events = $this->webSocketEvents;
        if (is_array($events)) {
            foreach ($events as $event => $item) {
                $register->add($event, $item);
            }
        }
    }

    /**
     * 注册Crontab
     * @return void
     */
    protected function registerCrontab()
    {
        $envMode = \EasySwoole\EasySwoole\Core::getInstance()->runMode();
        if (
            // 运行环境
            is_array($this->crontabRunEnv) && in_array($envMode, $this->crontabRunEnv)
            // 运行类
            && $this->crontabClass && class_exists($this->crontabClass)
        ) {
            $Crontab = \EasySwoole\EasySwoole\Crontab\Crontab::getInstance();
            $Crontab->addTask($this->crontabClass);
        }
    }

    /**
     * 注册自定义进程
     * @return void
     */
    protected function registerConsumer()
    {
        $jobs = $this->consumerJobs;
        if (is_array($jobs)) {
            $group = config('SERVER_NAME') . '.my';
            foreach ($jobs as $value) {

                $proName = $group . '.' . $value['name'];

                $class = $value['class'];
                if (empty($class) || ! class_exists($class)) {
                    continue;
                }
                $psnum = intval($value['psnum'] ?? 1);

                for ($i = 0; $i < $psnum; ++$i) {
                    $cfg = array_merge([
                        'processName' => $proName . '.' . $i,
                        'processGroup' => $group,
                        'arg' => $value,
                        'enableCoroutine' => true,
                    ]);
                    $processConfig = new \EasySwoole\Component\Process\Config($cfg);
                    \EasySwoole\Component\Process\Manager::getInstance()->addProcess(new $class($processConfig));
                }
            }
        }
    }

    protected function watchHotReload()
    {
        $watchConfig = $this->hotReloadWatchDirs;
        // 只允许在开发环境运行
        if (is_env('dev') && is_array($watchConfig) && ! empty($watchConfig)) {
            $watcher = new \EasySwoole\FileWatcher\FileWatcher();
            // 设置监控规则和监控目录
            foreach ($watchConfig as $dir) {
                if (is_dir($dir)) {
                    $watcher->addRule(new \EasySwoole\FileWatcher\WatchRule($dir));
                }
            }

            $watcher->setOnChange(function (array $list) {
                echo PHP_EOL . PHP_EOL . Color::warning(' Worker进程重启，检测到以下文件变更: ') . PHP_EOL;

                foreach ($list as $item) {
                    $scanType = is_file($item) ? 'file' : (is_dir($item) ? 'dir' : '未知');
                    echo Utility::displayItem("[$scanType]", $item) . PHP_EOL;
                }
                $Server = ServerManager::getInstance()->getSwooleServer();

                // worker进程reload不会触发客户端的断线重连，但是原来的fd已经不可用了
                foreach ($Server->connections as $fd) {
                    // 不要在 close 之后写清理逻辑。应当放置到 onClose 回调中处理
                    $Server->close($fd);
                }

                $Server->reload();

                echo Color::success('Worker进程启动成功 ') . PHP_EOL;
                echo Color::red('请自行区分 Master 和 Worker 程序 !!!!!!!!!!') . PHP_EOL . PHP_EOL;
            });

            $watcher->setOnException(function (\Throwable $throwable) {

                echo PHP_EOL . Color::danger('Worker进程重启失败: ') . PHP_EOL;
                echo Utility::displayItem("[message]", $throwable->getMessage()) . PHP_EOL;
                echo Utility::displayItem("[file]", $throwable->getFile() . ', 第 ' . $throwable->getLine() . ' 行') . PHP_EOL;

                echo Color::warning('trace:') . PHP_EOL;
                if ($trace = $throwable->getTrace()) {
                    // 简单打印就行
                    var_dump($trace);
//                    foreach ($trace as $key => $item)
//                    {
//                        echo Utility::displayItem("$key-----------------------", $item) . PHP_EOL;
//                        foreach ($item as $ik => $iv)
//                        {
//                            echo Utility::displayItem("[$ik]", $iv) . PHP_EOL;
//                        }
//                        echo Utility::displayItem("$key-----------------------", $item) . PHP_EOL;
//                    }
                }
            });
            $watcher->attachServer(ServerManager::getInstance()->getSwooleServer());
        }
    }

    protected function registerNotify()
    {
        $config = $this->notifyConfig;
        if (is_array($config)) {
            foreach ($config as $name => $cfg) {
                if ($cfg instanceof ConfigInterface) {
                    EsNotify::getInstance()->register($cfg, $name);
                } else {
                    trace("EsNotify 注册失败: $name");
                }
            }
        }
    }
}
