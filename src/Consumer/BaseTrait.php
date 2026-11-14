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

        // 分片处理
        $queues = $this->getListenQueues();

        // 必须有这对括号：原来的 `$i < $this->args['limit'] ?? 200` 里，?? 的优先级比 < 低，
        // 实际比较的是 ($i < null)，没配 limit 的进程一条都取不出来。先取出来再比较
        $limit = intval($this->args['limit'] ?? 200);
        $json = ! empty($this->args['json']);
        // 本进程上一轮是否还在跑
        $running = false;

        $this->addTick($this->args['tick'] ?? 1000, function () use ($queues, $limit, $json, &$running) {

            // 上一轮还没跑完（consume 里同步调外部服务、积压大）就跳过这一轮：
            // Swoole 定时器到点就再起一轮，不判断的话每轮各占一条 Redis 连接，并发会越叠越高，
            // 进程数也就起不到「并发上限」的作用
            if ($running) {
                return;
            }
            $running = true;

            try {
                RedisPool::invoke(function (Redis $Redis) use ($queues, $limit, $json) {
                    foreach ($queues as $queue) {
                        for ($i = 0; $i < $limit; ++$i) {
                            $data = $Redis->lPop($queue);
                            if ( ! $data) {
                                break;
                            }
                            try {
                                if ($json) {
                                    $data = json_decode($data, true);
                                }
                                $this->consume($data, $Redis);
                            } catch (\Throwable $throwable) {
                                Trigger::getInstance()->throwable($throwable);
                            }
                        }
                    }
                }, $this->args['pool'] ?? 'default');
            } catch (\Throwable $throwable) {
                // 取连接失败、Redis 超时等：交给 Trigger 记日志后结束本轮。
                // 定时器回调里抛出的异常会让整个进程直接退出（不是只退出这一轮），原来只兜住了 consume 里的异常
                Trigger::getInstance()->throwable($throwable);
            } finally {
                $running = false;
            }
        });
    }
}
