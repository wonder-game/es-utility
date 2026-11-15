<?php

namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * 有界异步上报队列（进程级，只在 HTTP worker 启用）
 *
 * 请求协程只把数据写进进程内 Channel，由每个连接池固定数量的常驻协程取出后 RPUSH：
 *  - 依赖变慢时不会无限新建协程，每个连接池最多占 CONSUMERS 条连接
 *  - 消费协程捕获所有异常；go() 内未捕获的异常会让整个 worker 退出（容器实测），连同在途请求一起丢失
 *  - 队列满（说明 Redis 已严重变慢）或推送失败时，数据写进 report_spill 日志（logs/…/{日}-report_spill.log），事后可按日志补推
 *  - 未启用（非 sdk HTTP worker、CLI）时退回逐条 go() 直推，并捕获异常
 *  - worker 平滑退出时（onWorkerExit）消费协程把队列清空后再退出，不丢数据
 */
class ReportQueue
{
    /** 每个连接池的队列长度 */
    const CAPACITY = 20000;

    /** 每个连接池的消费协程数 */
    const CONSUMERS = 4;

    /** 消费协程单次最多连续推送的条数（期间占用同一条连接） */
    const BATCH = 200;

    /** spill 缓冲的最大条数，达到后立即落盘 */
    const SPILL_BATCH = 500;

    /** spill 缓冲最长滞留秒数，避免低频时一直不落盘 */
    const SPILL_FLUSH_SECONDS = 5;

    protected static $enabled = false;

    protected static $stopping = false;

    /** @var Channel[] */
    protected static $channels = [];

    /** @var array 待写盘的 spill 数据，按 pool 分组批量写，避免 Redis 故障时每条数据抢一次文件锁 */
    protected static $spill = [];

    /** @var int 上次写盘时间 */
    protected static $spillAt = 0;

    public static function enable()
    {
        self::$enabled = true;
    }

    public static function stop()
    {
        self::$stopping = true;
        // worker 平滑退出时把还没写盘的 spill 数据落盘，不留下半截缓冲
        self::flushSpill();
    }

    /**
     * 等价于 go(fn => redis_list_push(defer_redis($pool), $key, $data, $left))
     * @param string $pool Redis 连接池名
     * @param string $key 队列 key
     * @param mixed $data
     * @param bool $left 是否 lpush
     */
    public static function push(string $pool, string $key, $data, bool $left = false)
    {
        $item = [$key, $data, $left];

        if (self::$enabled && Coroutine::getCid() > 0) {
            $chan = self::channel($pool);
            // 本进程内没有抢占，isFull 与 push 之间不会被其它协程插入，push 不会阻塞
            if ( ! $chan->isFull()) {
                $chan->push($item);
                return;
            }
            // 队列满说明 Redis 已严重变慢：不再新建协程去抢连接（只会更快把连接池打满），直接写进日志，事后可按日志补推
            trace_limit("report_queue_full_$pool", "上报队列已满，数据写入 report_spill 日志：$pool", 'error');
            self::spill($pool, [$item]);
            return;
        }

        // 未启用（非 sdk HTTP worker）时保持原来的写法，但兜住异常：go() 里未捕获的异常会让整个进程退出
        go(function () use ($pool, $item) {
            self::send($pool, [$item]);
        });
    }

    protected static function channel(string $pool): Channel
    {
        if ( ! isset(self::$channels[$pool])) {
            $chan = self::$channels[$pool] = new Channel(self::CAPACITY);
            for ($i = 0; $i < self::CONSUMERS; ++$i) {
                go(function () use ($chan, $pool) {
                    self::consume($chan, $pool);
                });
            }
        }
        return self::$channels[$pool];
    }

    protected static function consume(Channel $chan, string $pool)
    {
        while (true) {
            $item = $chan->pop(1.0);
            if ($item === false) {
                // 超时：worker 正在退出且队列已空才结束，否则继续等
                if (self::$stopping && $chan->isEmpty()) {
                    return;
                }
                continue;
            }
            $batch = [$item];
            // 不能先 isEmpty() 再 pop()：两个消费者之间会有竞态，无超时的 pop() 会永久阻塞，
            // 手上的 $batch 迟迟不发；worker 退出时这个协程也回不到外层循环，队列里剩下的数据会丢
            while (count($batch) < self::BATCH && ($next = $chan->pop(0.01)) !== false) {
                $batch[] = $next;
            }
            self::send($pool, $batch);
        }
    }

    protected static function send(string $pool, array $batch)
    {
        try {
            if ( ! RedisPool::getInstance()->getPool($pool)) {
                throw new \Exception("连接池 $pool 未注册");
            }
            RedisPool::invoke(function (Redis $redis) use ($batch) {
                foreach ($batch as list($key, $data, $left)) {
                    redis_list_push($redis, $key, $data, $left);
                }
            }, $pool);
        } catch (\Throwable $e) {
            // 失败的数据写进 report_spill 日志（与队列满时同一个文件、同一种格式），必要时可按日志补推
            trace_limit("report_send_fail_$pool", "上报失败，数据写入 report_spill 日志：$pool {$e->getMessage()}", 'error');
            self::spill($pool, $batch);
        }
    }

    /**
     * 先进缓冲、再批量写盘。Redis 故障时 spill 会以请求速率涌入，逐条 trace 会让每个请求都抢一次文件锁，
     * 变成比 Redis 更严重的全局串行点
     * @param string $pool
     * @param array $batch
     */
    protected static function spill(string $pool, array $batch)
    {
        self::$spill[$pool] = array_merge(self::$spill[$pool] ?? [], $batch);
        $count = 0;
        foreach (self::$spill as $rows) {
            $count += count($rows);
        }
        if ($count >= self::SPILL_BATCH || time() - self::$spillAt >= self::SPILL_FLUSH_SECONDS) {
            self::flushSpill();
        }
    }

    /**
     * 按 pool 分组成一行写盘，格式与原来一致：{"pool":…,"batch":[[key, data, left], …]}，可按 pool 和 key 重新 RPUSH
     * @return void
     */
    protected static function flushSpill()
    {
        if (empty(self::$spill)) {
            return;
        }
        $spill = self::$spill;
        self::$spill = [];
        self::$spillAt = time();
        foreach ($spill as $pool => $batch) {
            if (empty($batch)) {
                continue;
            }
            trace(['pool' => $pool, 'batch' => $batch], 'info', 'report_spill');
        }
    }
}
