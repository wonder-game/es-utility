<?php

namespace WonderGame\EsUtility\Consumer;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use WonderGame\EsUtility\EventMainServerCreate;

/**
 * @extends AbstractProcess;
 */
trait BaseTrait
{
    /**
     * 传递的参数
     * @var array[
     * 'name' => 'login',                             // 进程名
     * 'class' => \App\Consumer\Login::class,         // 运行类
     * 'psnum' => 1,                                  // 进程数, 默认1个
     * 'queue' => 'queue_login',                  // 监听的redis队列名
     * 'tick' => 1000,                                // 多久运行一次，单位毫秒, 默认1000毫秒
     * 'limit' => 200,                                // 单次出队列的阈值, 默认200
     * 'pool' => 'default'                            // redis连接池名称
     * 'json' => false                                // 是否需要json_decode
     * ],
     *
     */
    protected $args = [];

    protected function onException(\Throwable $throwable, ...$args)
    {
        // 消费的consume是运行在回调内的，在consume发生的异常基本走不到这里
        \EasySwoole\EasySwoole\Trigger::getInstance()->throwable($throwable);
    }

    /**
     * 消费单条数据，由子类继承实现
     * @param string|array $data 每一条队列数据
     * @param Redis|null $redis redis连接
     * @return mixed
     */
    abstract protected function consume($data = [], Redis $redis = null);

    /**
     * EasySwoole自定义进程入口
     */
    public function run()
    {
        /* @var AbstractProcess $this */
        $this->args = $this->getArg();

        if (config('PROCESS_INFO.isopen')) {
            EventMainServerCreate::listenProcessInfo();
        }

        $this->addTick($this->args['tick'] ?? 1000, function () {

            RedisPool::invoke(function (Redis $Redis) {

                for ($i = 0; $i < $this->args['limit'] ?? 200; ++$i) {
                    $data = $Redis->lPop($this->args['queue']);
                    if ( ! $data) {
                        break;
                    }
                    try {
                        if ( ! empty($this->args['json'])) {
                            $data = json_decode($data, true);
                        }
                        $this->consume($data, $Redis);
                    } catch (\Throwable $throwable) {
                        \EasySwoole\EasySwoole\Trigger::getInstance()->throwable($throwable);
                    }
                }
            }, $this->args['pool'] ?? 'default');
        });
    }
}
