<?php

namespace WonderGame\EsUtility\Queue;

use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Runtime;
use Swoole\Timer;

/**
 * 异步任务的消费进程，配合 AsyncQueue 使用
 * 宿主类须已 use \WonderGame\EsUtility\Consumer\BaseTrait（如各模块的 Consumer\Base）：出队沿用 BaseTrait::run()，
 * 每 tick 毫秒从各分片最多取 limit 条交给 consume()
 *
 * 子类实现：
 *  - queueClass()：对应的 Queue 子类
 *  - handle(array $job): bool：处理一条，true 完成（含业务上的“不通过”），false 失败需要重试
 *  - 可选 init()（进程启动时初始化，如取 model）、giveUp()（重试用尽后的处理）
 *
 * 处理流程：
 *  - consume() 不等结果，每条放进独立协程处理：BaseTrait 是逐条串行调用，同步处理的话每条都要等外部服务返回
 *    配置里有 concurrency 时，本进程同时在途最多这么多条；名额满时 consume() 阻塞，BaseTrait 不叠加轮次，等于背压
 *  - 排队超过 Queue::ttl() 秒的丢弃
 *  - handle() 返回 false：放回队尾（按分片规则），下次取出（至少隔 1 秒）再处理，最多 Queue::maxRounds() 轮
 *  - 每秒续心跳、写统计；全集群每 5 秒巡检一次积压、每分钟汇总一次；启动时检查一次配置
 *  - 外部服务的 SDK 多走 PHP stream / curl，而项目没有开协程 Hook，所以本进程单独开启 TCP、SSL、curl 的 Hook，否则每条都要阻塞整个进程
 *  - 进程退出时不再取新任务，在途的最多等 2 秒；放回失败、到时还没处理完的记进 lost 并告警
 */
trait ConsumerTrait
{
    /** @var bool 进程正在退出 */
    protected $stopping = false;

    /** @var int 在途数 */
    protected $sending = 0;

    /** @var Channel|null 并发名额，配置了 concurrency 才有 */
    protected $slots;

    /** @var string 本进程在心跳里的标识 */
    protected $member = '';

    /** @var bool 启动后是否已检查过配置 */
    protected $checked = false;

    /** @var int 上次积压巡检的 5 秒时段 */
    protected $monitorSlot = 0;

    /** @var string 上次汇总的分钟 YmdHi */
    protected $reported = '';

    /**
     * 对应的队列类
     * @return string|AsyncQueue
     */
    abstract protected function queueClass(): string;

    /**
     * 处理一条任务
     * @param array $job
     * @return bool true 完成，false 失败需要重试
     */
    abstract protected function handle(array $job): bool;

    /**
     * 进程启动时初始化，在开始出队之前调用
     */
    protected function init()
    {
    }

    /**
     * 重试用尽后的处理，默认计 fail 并记日志
     * @param array $job
     */
    protected function giveUp(array $job)
    {
        $Queue = $this->queueClass();
        $Queue::incr('fail');
        trace($Queue::TITLE . '重试 ' . ($job['round'] ?? 1) . ' 轮后仍失败，放弃：' . $Queue::label($job), 'error');
    }

    /**
     * 在 BaseTrait::run() 前后各补一步，出队逻辑不变：
     *  - 之前：开启协程 Hook。必须在用到 curl 之前开启，Hook 前创建的 curl 句柄在 Hook 后再用会直接 Fatal（阿里云 SDK 会在进程内复用句柄）
     *  - 之后：每秒心跳。consume() 只在有任务时才调用，调用方要靠心跳判断消费进程是否存活
     */
    public function run($arg)
    {
        $flags = SWOOLE_HOOK_TCP | SWOOLE_HOOK_SSL | SWOOLE_HOOK_TLS;
        // 优先用 curl-native（编译时 --enable-swoole-curl，支持 curl_multi）
        // 没编译 curl-native 时 NATIVE_CURL 常量也可能有、但不起作用，要按类判断；PHP 8 引用未定义常量会直接报错，常量也要先判断
        // 退回 SWOOLE_HOOK_CURL：由 Swoole 协程客户端模拟 curl_exec，腾讯云、阿里云 SDK 的 Guzzle 同步请求够用；
        // 不支持 curl_multi，消费进程里不要用 Guzzle 的异步请求（sendAsync、Pool 等）
        if (defined('SWOOLE_HOOK_NATIVE_CURL') && class_exists(\Swoole\Coroutine\Curl\Exception::class)) {
            $flags |= SWOOLE_HOOK_NATIVE_CURL;
        } elseif (defined('SWOOLE_HOOK_CURL')) {
            $flags |= SWOOLE_HOOK_CURL;
        } else {
            trace('Swoole 未编译 curl-native，也没有 SWOOLE_HOOK_CURL（php --ri swoole 看 curl-native），走 curl 的 SDK 会阻塞消费进程', 'error');
        }
        Runtime::enableCoroutine($flags);

        /* @var AbstractProcess $this */
        $concurrency = intval($this->getArg()['concurrency'] ?? 0);
        $concurrency > 0 && $this->slots = new Channel($concurrency);
        $this->member = (config('SERVNAME') ?: gethostname()) . '#' . getmypid();
        // 统计在 Redis 里、全集群共用，启动前一分钟也补汇总一次（整体重启时那一分钟没人汇总），重复的由锁去掉
        $this->reported = date('YmdHi', time() - 120);
        $this->init();

        parent::run($arg);

        $this->addTick(1000, function () {
            $this->heartbeat();
        });
    }

    /**
     * 取出一条任务：交给新协程处理，不等结果
     * @param array|string|null $data 队列任务（配置了 json 时已解码）
     * @param Redis|null $redis BaseTrait 出队用的连接
     */
    protected function consume($data = [], Redis $redis = null)
    {
        $Queue = $this->queueClass();
        is_string($data) && $data = json_decode($data, true);
        if ( ! is_array($data)) {
            trace($Queue::TITLE . '任务格式错误，已丢弃', 'error');
            return;
        }
        // 退出中：放回队首（按分片规则随机落到一个分片），不在退出等待里赶工
        // 落回当前正在取的分片时，本轮出队剩下的次数会反复取到这一条再放回，最多 limit 次，只在退出时发生
        if ($this->stopping) {
            $Queue::requeue($redis, $data, true);
            return;
        }
        // 名额满时在这里等，BaseTrait 本轮出队也随之暂停
        $this->slots && $this->slots->push(1);
        ++$this->sending;
        go(function () use ($data) {
            // go() 里未捕获的异常会让整个进程退出
            try {
                $this->process($data);
            } catch (\Throwable $e) {
                trace_limit('async_process_' . md5($e->getFile() . $e->getLine()), "异步任务处理异常：{$e->getMessage()} at {$e->getFile()}:{$e->getLine()}", 'error');
            } finally {
                --$this->sending;
                $this->slots && $this->slots->pop();
            }
        });
    }

    /**
     * 处理一条任务，失败的放回队尾重试
     * @param array $job
     */
    protected function process(array $job)
    {
        $Queue = $this->queueClass();
        $ttl = $Queue::ttl();
        if ($ttl > 0 && time() - intval($job['at'] ?? time()) > $ttl) {
            $Queue::incr('expired');
            trace_limit('async_expired_' . $Queue::NAME, $Queue::TITLE . "排队超过 {$ttl} 秒，不再处理：" . $Queue::label($job) . '，请检查消费进程数、云商是否变慢', 'error');
            return;
        }
        // 失败放回队尾的任务，队列不积压时马上又会被取到：离上次失败不到 1 秒的先等一下再处理
        // Swoole 定时器最小 1 毫秒，不到 1 毫秒时 sleep 会报 warning；最多睡 1 秒，系统时钟回拨时不会跟着停很久
        if (($wait = ($job['retry_at'] ?? 0) - microtime(true)) > 0) {
            Coroutine::sleep(min(max($wait, 0.001), 1));
        }
        if ($this->handle($job)) {
            return;
        }
        $round = $job['round'] ?? 1;
        if ($round >= $Queue::maxRounds()) {
            $this->giveUp($job);
            return;
        }
        // 放回队尾，下次取出再处理，可能由其他进程、其他机器接着做
        $job['round'] = $round + 1;
        $job['retry_at'] = microtime(true) + 1;
        $Queue::incr('retry');
        // Redis 出错放不回去时这条就丢了：记进 lost（会告警），不往外抛
        try {
            RedisPool::invoke(function (Redis $Redis) use ($Queue, $job) {
                $Queue::requeue($Redis, $job);
            }, $Queue::POOL);
        } catch (\Throwable $e) {
            $Queue::incr('lost');
            trace_limit('async_lost_' . $Queue::NAME, $Queue::TITLE . '放回队列失败，这条没有处理：' . $Queue::label($job) . " {$e->getMessage()}", 'error');
        }
    }

    /**
     * 每秒：续心跳、写统计；每 5 秒抢一次积压巡检；每分钟抢一次上一分钟的汇总；启动时检查一次配置
     */
    protected function heartbeat()
    {
        $Queue = $this->queueClass();
        try {
            RedisPool::invoke(function (Redis $Redis) use ($Queue) {
                $Queue::heartbeat($Redis, $this->member);
                $Queue::flush($Redis);
                if ( ! $this->checked) {
                    $this->checked = true;
                    $Queue::checkConfig($Redis);
                }
                if ($this->monitorSlot !== intdiv(time(), 5)) {
                    $this->monitorSlot = intdiv(time(), 5);
                    $Queue::monitor($Redis);
                }
                // 下一分钟第 5 秒后再汇总上一分钟，等各进程把计数写完
                $prev = date('YmdHi', time() - 60);
                if ($prev > $this->reported && intval(date('s')) >= 5) {
                    $this->reported = $prev;
                    $Queue::report($Redis, $prev);
                }
            }, $Queue::POOL);
        } catch (\Throwable $e) {
            trace_limit('async_heartbeat_' . $Queue::NAME, $Queue::TITLE . "消费进程心跳/统计异常：{$e->getMessage()}", 'error');
        }
    }

    protected function onSigTerm()
    {
        $Queue = $this->queueClass();
        // 信号回调在协程里执行（容器实测），可以等。框架在 onSigTerm 之后才清定时器，这里先清掉，等待期间不再出队
        // 正在跑的那一轮出队取到的，consume() 看到 stopping 会放回队首
        $this->stopping = true;
        Timer::clearAll();
        // 在途的最多等 2 秒（Swoole 的 max_wait_time 为 3 秒）
        for ($i = 0; $i < 20 && $this->sending > 0; ++$i) {
            Coroutine::sleep(0.1);
        }
        // 还没处理完的（多半卡在调外部服务）会随进程退出中断
        if ($this->sending > 0) {
            $Queue::incr('lost', $this->sending);
            trace($Queue::TITLE . "消费进程退出时还有 {$this->sending} 条在途，可能没处理完", 'error');
        }
        try {
            RedisPool::invoke(function (Redis $Redis) use ($Queue) {
                $Queue::leave($Redis, $this->member);
                $Queue::flush($Redis, true);
            }, $Queue::POOL);
        } catch (\Throwable $e) {
            trace($Queue::TITLE . "消费进程退出时摘心跳失败：{$e->getMessage()}", 'error');
        }
    }
}
