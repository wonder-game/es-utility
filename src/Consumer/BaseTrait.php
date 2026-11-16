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

    /**
     * 当前 Redis 是否支持 LPOP key count（6.2+），null 为尚未探测
     * 不支持时本进程内永久退回单条出队，避免每条都先抛一次异常
     * @var bool|null
     */
    protected $batchPop = null;

    protected function onException(\Throwable $throwable, ...$args)
    {
        // 消费的consume是运行在回调内的，在consume发生的异常基本走不到这里
        Trigger::getInstance()->throwable($throwable);
    }

    /**
     * 从队列取一批数据，最多 $limit 条
     * Redis 6.2+ 用 LPOP key count 一次往返取走，原来是一条一次往返：
     * 52 个分片 × 200 条 = 10400 次串行往返，积压时单轮可能耗几十秒，出队能力只有一百多条/秒
     * @param Redis $Redis
     * @param string $key
     * @param int $limit
     * @return array
     * @throws \Throwable Redis 本身的异常（超时等）原样抛出，由调用方记录
     */
    protected function popQueue(Redis $Redis, string $key, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        if ($this->batchPop !== false) {
            try {
                // key 不存在或为空时返回 null，按没有数据处理
                $batch = $Redis->rawCommand(['LPOP', $key, $limit]);
                $this->batchPop = true;
                return is_array($batch) ? $batch : [];
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                // 只有「参数个数不对」这类才说明服务端不支持，其余（超时、连接失败）不能降级，要照常抛
                if (stripos($msg, 'wrong number of arguments') === false && stripos($msg, 'arity') === false) {
                    throw $e;
                }
                $this->batchPop = false;
                trace("队列 {$this->args['queue']} 批量出队不可用（Redis 低于 6.2？），已退回单条：{$msg}", 'error');
            }
        }

        // 降级：一条一次往返，保持与原来一致
        $list = [];
        for ($i = 0; $i < $limit; ++$i) {
            $data = $Redis->lPop($key);
            if ($data === false || $data === null) {
                break;
            }
            $list[] = $data;
        }
        return $list;
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
        // 在集群模式中，将队列数据均匀分布在不同分片的槽位中，分片规则见 redis_list_keys
        return redis_list_keys($this->args['queue']);
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
                        // 一次往返取走一批（Redis 6.2+ 的 LPOP key count），不再一条一次往返
                        foreach ($this->popQueue($Redis, $queue, $limit) as $data) {
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
