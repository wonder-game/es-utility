<?php

namespace WonderGame\EsUtility\Consumer;

use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

trait BaseTrait
{
    /**
     * 传递的参数
     * @var array{
            [
                'name' => 'login',                             // 进程名
                'class' => \App\Consumer\Login::class,         // 运行类
                'psnum' => 1,                                  // 进程数, 默认1个
                'queue' => 'queue_login',                  // 监听的redis队列名
                'tick' => 1000,                                // 多久运行一次，单位毫秒, 默认1000毫秒
                'limit' => 200,                                // 单次出队列的阈值, 默认200
                'coroutine' => false                            // 是否为每条数据开启协程
            ],
     * }
     */
    protected $args = [];

    /**
     * redis配置
     * @var array
     */
    protected $rcfg = [];

    protected function onException(\Throwable $throwable,...$args)
    {
        // 消费的consume是运行在回调内的，在consume发生的异常基本走不到这里
        \EasySwoole\EasySwoole\Trigger::getInstance()->throwable($throwable);
    }

    /**
     * 消费单条数据，由子类继承实现
     * @param string $data 每一条队列数据
     * @return mixed
     */
    abstract protected function consume($data = '');

    /**
     * EasySwoole自定义进程入口
     * @param $arg
     */
    public function run($arg)
    {
        $this->args = $this->getArg();

        $this->rcfg = config('REDIS.default');

        $this->addTick($this->args['tick'] ?? 1000, function () {

            RedisPool::invoke(function (Redis $Redis) {

                $Redis->select($this->rcfg['db']);

                for ($i = 0; $i < $this->args['limit'] ?? 200; ++$i)
                {
                    $data = $Redis->lPop($this->args['queue']);
                    if (!$data)
                    {
                        break;
                    }
                    try {
                        $openCoroutine = $this->args['coroutine'] ?? false;
                        if ($openCoroutine) {
                            go (function () use ($data) { $this->consume($data); });
                        } else {
                            $this->consume($data);
                        }
                    } catch (\Throwable $throwable) {
                        \EasySwoole\EasySwoole\Trigger::getInstance()->throwable($throwable);
                    }
                }
            });
        });
    }
}
