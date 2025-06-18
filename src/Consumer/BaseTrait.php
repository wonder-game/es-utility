<?php

namespace WonderGame\EsUtility\Consumer;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\Trigger;
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
        Trigger::getInstance()->throwable($throwable);
    }

    /**
     * 消费单条数据，由子类继承实现
     * @param string|array $data 每一条队列数据
     * @param Redis|null $redis redis连接
     * @return mixed
     */
    abstract protected function consume($data = [], Redis $redis = null);

    public function getListenQueues()
    {
        // 在集群模式中，将队列数据均匀分布在不同分片的槽位中
        // 读一定要比写大！！！不然redis会爆！！！
        $clusterNumber = config('QUEUE.clusterNumber');
        $clusterNumberWrite = config('QUEUE.clusterNumberWrite');
        $queue = $this->args['queue'];
        // 读一定要比写大！！！不然redis会爆！！！
        $cn = max($clusterNumber ?: 0, $clusterNumberWrite ?: 0);
        $list[] = $queue;
        if ($cn > 0) {
            for ($i = 0; $i <= $cn; ++$i) {
                $list[] = "$queue.$i";
            }
        }

        return $list;
    }

    /**
     * EasySwoole自定义进程入口
     * @return void|bool
     */
    public function run($arg)
    {
        /* @var AbstractProcess $this */
        $this->args = $this->getArg();

        if (config('PROCESS_INFO.isopen')) {
            EventMainServerCreate::listenProcessInfo();
        }

        // TODO 待优化为移入addTick并从sysinfo取?
        // 分片处理
        $queues = $this->getListenQueues();

        $this->addTick($this->args['tick'] ?? 1000, function () use ($queues) {

            RedisPool::invoke(function (Redis $Redis) use ($queues) {
                foreach ($queues as $queue) {
                    for ($i = 0; $i < $this->args['limit'] ?? 200; ++$i) {
                        $data = $Redis->lPop($queue);
                        if ( ! $data) {
                            break;
                        }
                        try {
                            if ( ! empty($this->args['json'])) {
                                $data = json_decode($data, true);
                            }
                            $this->consume($data, $Redis);
                        } catch (\Exception|\Throwable $throwable) {
                            Trigger::getInstance()->throwable($throwable);
                        }
                    }
                }
            }, $this->args['pool'] ?? 'default');
        });
    }
}
