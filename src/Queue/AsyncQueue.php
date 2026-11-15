<?php

namespace WonderGame\EsUtility\Queue;

use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use Swoole\Coroutine;
use Swoole\Timer;

/**
 * 异步任务队列：入队、消费进程心跳、排不下的判断、统计、巡检与告警
 * 适用于「请求里入队 → 消费进程取出调外部服务（如多家云商）→ 失败重试」这类功能，如短信验证码、实名校验
 *
 * 用法：子类声明 NAME（队列名，与消费进程配置的 queue 一致）、TITLE、CFG，按需重写 ttl()、maxRounds() 等钩子；
 * 消费进程 use ConsumerTrait 并实现 handle()
 *
 * 队列按分片规则读写：写走 redis_list_push() 随机落到 NAME 或 NAME.N，读遍历 redis_list_keys()
 * 其余 key 都是 async:{NAME}:xxx（见 key()）
 *
 * 监控指标：
 *  - 实时（snapshot()）：积压条数 len、最早一条已等秒数 wait、存活的消费进程数 senders
 *  - 每分钟（stat:YmdHi 哈希，保留 1 天）：
 *      queued 入队、direct 当场处理（消费进程失联等）、rejected 排不下被拒
 *      sent 成功、sent_{云商}、fail 重试用尽后放弃、fail_{云商} 调这家云商失败、timeout_{云商} 调云商超时
 *      expired 排队过久丢弃、retry 失败放回队列重试、lost 放回队列失败、消费进程退出时没处理完
 *      len_max、wait_max 本分钟采样（每 5 秒）到的最大积压条数、最长等待秒数
 *    每分钟另写一行日志（分类见 logCategory()），看历史趋势
 *  - 告警（同类 ALARM_INTERVAL 秒内全集群只发一条）：
 *      积压等待超过 CFG.alarm_wait 秒（默认 10，0 关闭）、有过期丢弃、有请求被拒、失败率过高、有任务可能丢失、
 *      消费进程全部失联、配置有问题（消费进程启动时检查）
 */
abstract class AsyncQueue
{
    /** 队列名，与消费进程配置里的 queue 一致 */
    const NAME = '';

    /** 功能名，用于日志和告警，如“验证码”“实名” */
    const TITLE = '';

    /** 阈值配置所在的配置键，如 VERIFY_CODE，可配 queue_wait、queue_max、alarm_wait */
    const CFG = '';

    /** Redis 连接池 */
    const POOL = 'default';

    /** 心跳多久没续算失联（秒） */
    const ALIVE_SECONDS = 5;

    /** 同类告警的最小间隔（秒），全集群 */
    const ALARM_INTERVAL = 300;

    /**
     * 进程内状态，按子类分组（静态属性是父子类共用的，不分组的话多个队列会串数据）
     * 类名 => ['pending' => 未写进 Redis 的计数：分钟 => [字段 => 数量], 'flushAt', 'expireMinute', 'alive', 'aliveCheckAt', 'busy', 'busyCheckAt', 'flushTimer', 'sender']
     * @var array
     */
    protected static $state = [];

    /*************** 子类可重写的钩子 ***************/

    /**
     * 排队超过多少秒不再处理，0 为不过期
     * @return int
     */
    public static function ttl(): int
    {
        return 0;
    }

    /**
     * 失败后最多处理几轮（含第一次）
     * 不宜多：号码、证件错误这类失败重试也没用，列表写法 ['Tencent', 'Tencent', …] 每轮会失败两次，
     * 三轮就够 Breaker::FAILS 把正常的云商熔断
     * @return int
     */
    public static function maxRounds(): int
    {
        return 2;
    }

    /**
     * 业务配置检查（如云商配置块是否齐全），返回问题列表；消费进程启动时调用一次
     * @return array
     */
    protected static function configProblems(): array
    {
        return [];
    }

    /**
     * 日志里标识一条任务的文字，不要带证件号等敏感信息
     * @param array $job
     * @return string
     */
    public static function label(array $job): string
    {
        return '';
    }

    /**
     * 统计日志的分类
     * @return string
     */
    public static function logCategory(): string
    {
        return 'async_' . strtolower(static::NAME);
    }

    /**
     * 发告警，已在全集群去重之后调用
     * @param string $type
     * @param string $content
     */
    protected static function notify(string $type, string $content)
    {
        go_safe(function () use ($content) {
            notice($content);
        });
    }

    /**
     * 本进程记了计数后、平时没人写的时候，是否要安排 1 秒后补写一次（如 HTTP worker 量小时会错过每分钟汇总）
     * 消费进程每秒自己写，不用安排
     * @return bool
     */
    protected static function autoFlush(): bool
    {
        return ! static::st()['sender'] && Coroutine::getCid() > 0;
    }

    /*************** 入队 ***************/

    /**
     * 新任务入队，自动带上入队时间 at
     * @param Redis $Redis
     * @param array $job
     */
    public static function push(Redis $Redis, array $job)
    {
        static::requeue($Redis, $job + ['at' => time()]);
        static::incr('queued');
    }

    /**
     * 写入队列，不计入队数：消费进程放回队列也用这个
     * @param Redis $Redis
     * @param array $job
     * @param bool $head true 放队首，false 放队尾
     */
    public static function requeue(Redis $Redis, array $job, bool $head = false)
    {
        redis_list_push($Redis, static::NAME, $job, $head);
    }

    /**
     * 是否有消费进程在跑，每个进程每秒只查一次 Redis
     * 没有时调用方应改为当场处理，这里同时告警
     * @param Redis $Redis
     * @return bool
     */
    public static function senderAlive(Redis $Redis): bool
    {
        $st = &static::st();
        $now = time();
        if ($now !== $st['aliveCheckAt']) {
            $st['aliveCheckAt'] = $now;
            $st['alive'] = intval($Redis->zCount(static::key('alive'), $now - static::ALIVE_SECONDS, '+inf')) > 0;
            $st['alive'] or static::alarm($Redis, 'dead', static::TITLE . '消费进程全部失联，已改为请求内当场处理（失败不会重试）。'
                . '请检查 ' . static::NAME . ' 进程；新增的自定义进程 server reload 不会拉起，需要 restart（stop + start）');
        }
        return $st['alive'];
    }

    /**
     * 队列是否已经排不下（调用方据此拒绝新请求），判断见 monitor()
     * 每个进程每秒只查一次标记：队列分了几十个分片，每个请求都逐个分片查积压的话 Redis 压力太大；
     * 两个上限本来就是软的，晚几秒发现排不下，只是多排进去几秒的量
     * @param Redis $Redis
     * @return bool
     */
    public static function busy(Redis $Redis): bool
    {
        $st = &static::st();
        $now = time();
        if ($now !== $st['busyCheckAt']) {
            $st['busyCheckAt'] = $now;
            $st['busy'] = intval($Redis->exists(static::key('busy'))) > 0;
        }
        return $st['busy'];
    }

    /*************** 统计 ***************/

    /**
     * 统计计数：先记在进程内，flush() 时按分钟写入 Redis（逐条 HINCRBY 的话，高峰时每条任务都多一次 Redis 往返）
     * @param string $field
     * @param int $n
     */
    public static function incr(string $field, int $n = 1)
    {
        $st = &static::st();
        $minute = date('YmdHi');
        $st['pending'][$minute][$field] = ($st['pending'][$minute][$field] ?? 0) + $n;
        if ( ! $st['flushTimer'] && static::autoFlush()) {
            $st['flushTimer'] = true;
            Timer::after(1000, function () {
                go_safe(function () {
                    $st = &static::st();
                    $st['flushTimer'] = false;
                    static::flush(null, true);
                });
            });
        }
    }

    /**
     * 按云商统计的回调，给 CloudRouter::call() 的 observe 用：成功计 sent 和 sent_{云商}，失败、超时计 fail_{云商}、timeout_{云商}
     * @return callable
     */
    public static function observer(): callable
    {
        return function (string $cloud, string $status) {
            if ($status === 'ok') {
                static::incr('sent');
                static::incr("sent_$cloud");
            } else {
                static::incr("{$status}_$cloud");
            }
        };
    }

    /**
     * 把进程内的计数写进 Redis，同一秒内只写一次（$force 除外）。统计失败只记日志，不影响业务
     * @param Redis|null $Redis 不传时自己取连接
     * @param bool $force
     */
    public static function flush(Redis $Redis = null, bool $force = false)
    {
        $st = &static::st();
        if ( ! $st['pending'] || ( ! $force && time() === $st['flushAt'])) {
            return;
        }
        try {
            if ( ! $Redis) {
                RedisPool::invoke(function (Redis $Redis) use ($force) {
                    static::flush($Redis, $force);
                }, static::POOL);
                return;
            }
            $st['flushAt'] = time();
            $pending = $st['pending'];
            $st['pending'] = [];
            foreach ($pending as $minute => $fields) {
                $key = static::key("stat:$minute");
                foreach ($fields as $field => $n) {
                    $Redis->hIncrBy($key, $field, $n);
                }
                if ($st['expireMinute'] !== (string)$minute) {
                    $st['expireMinute'] = (string)$minute;
                    $Redis->expire($key, 86400);
                }
            }
        } catch (\Throwable $e) {
            trace_limit('async_stat_fail_' . static::NAME, static::TITLE . "队列统计写入失败：{$e->getMessage()}", 'error');
        }
    }

    /**
     * 某分钟的统计
     * @param Redis $Redis
     * @param string $minute YmdHi
     * @return array
     */
    public static function stat(Redis $Redis, string $minute): array
    {
        $stat = array_map('intval', $Redis->hGetAll(static::key("stat:$minute")) ?: []);
        ksort($stat);
        return $stat;
    }

    /**
     * 进程内还没写进 Redis 的计数
     * @return array 分钟 => [字段 => 数量]
     */
    public static function pending(): array
    {
        return static::st()['pending'];
    }

    /*************** 消费进程心跳 ***************/

    /**
     * 消费进程心跳，每秒一次
     * @param Redis $Redis
     * @param string $member 进程标识
     */
    public static function heartbeat(Redis $Redis, string $member)
    {
        $st = &static::st();
        $st['sender'] = true;
        $Redis->zAdd(static::key('alive'), time(), $member);
        $Redis->expire(static::key('alive'), 60);
    }

    /**
     * 消费进程退出时摘掉心跳：都退出时调用方马上改为当场处理，不用等心跳过期
     * @param Redis $Redis
     * @param string $member
     */
    public static function leave(Redis $Redis, string $member)
    {
        $Redis->zRem(static::key('alive'), $member);
    }

    /*************** 巡检、汇总、告警 ***************/

    /**
     * 实时指标，遍历全部分片：只在巡检、汇总、告警时调用（全集群每 5 秒一次），不在请求里调用
     * @param Redis $Redis
     * @return array ['len' => 积压条数, 'wait' => 最早一条已等秒数, 'senders' => 存活的消费进程数]
     */
    public static function snapshot(Redis $Redis): array
    {
        $len = 0;
        $at = null;
        foreach (redis_list_keys(static::NAME) as $key) {
            if (($n = intval($Redis->lLen($key))) <= 0) {
                continue;
            }
            $len += $n;
            // 各分片都是先进先出，队首就是这个分片里最早的一条
            $head = json_decode((string)$Redis->lIndex($key, 0), true);
            $headAt = intval($head['at'] ?? time());
            $at = $at === null ? $headAt : min($at, $headAt);
        }
        return [
            'len' => $len,
            'wait' => $at === null ? 0 : max(0, time() - $at),
            'senders' => intval($Redis->zCount(static::key('alive'), time() - static::ALIVE_SECONDS, '+inf')),
        ];
    }

    /**
     * 积压巡检：每 5 秒全集群只有一个消费进程执行（抢锁）
     * 记下本分钟最大积压、最长等待，等待超过 CFG.alarm_wait 秒告警，清理失联进程的心跳
     * 并判断是否排不下，写入 busy 标记供调用方拒绝新请求：
     *  - 最早的任务已经等了 CFG.queue_wait 秒（默认 30）：处理跟不上，再入队多半也会排到过期
     *  - 排队数达到 CFG.queue_max（默认 20 万，0 为不限）：防持续刷量把 Redis 撑爆
     * @param Redis $Redis
     */
    public static function monitor(Redis $Redis)
    {
        $slot = intdiv(time(), 5);
        if ( ! $Redis->set(static::key("lock:monitor-$slot"), 1, ['NX', 'EX' => 10])) {
            return;
        }
        $cfg = static::cfg();
        $snap = static::snapshot($Redis);

        $maxWait = intval($cfg['queue_wait'] ?? 30);
        $maxLen = intval($cfg['queue_max'] ?? 200000);
        if (($maxWait > 0 && $snap['wait'] > $maxWait) || ($maxLen > 0 && $snap['len'] >= $maxLen)) {
            // 10 秒过期：消费进程都挂了、没人再巡检时，标记自己消失，不会一直拒绝
            $Redis->set(static::key('busy'), 1, ['EX' => 10]);
        } else {
            $Redis->del(static::key('busy'));
        }

        $minute = date('YmdHi');
        $statKey = static::key("stat:$minute");
        // 每 5 秒只有一个写入方，先读后写不会互相覆盖
        foreach (['len', 'wait'] as $field) {
            if ($snap[$field] > intval($Redis->hGet($statKey, "{$field}_max"))) {
                $Redis->hSet($statKey, "{$field}_max", $snap[$field]);
            }
        }
        $Redis->expire($statKey, 86400);
        $Redis->zRemRangeByScore(static::key('alive'), '-inf', time() - 60);

        $alarmWait = intval($cfg['alarm_wait'] ?? 10);
        if ($alarmWait > 0 && $snap['wait'] >= $alarmWait) {
            $ttl = static::ttl();
            static::alarm($Redis, 'backlog', static::TITLE . "排队积压，最早一条已等 {$snap['wait']} 秒"
                . "（超过 {$maxWait} 秒新请求会被拒" . ($ttl > 0 ? "，超过 {$ttl} 秒的不再处理" : '') . '）。'
                . '请检查云商是否变慢、消费进程数（psnum）是否够用', $snap, static::stat($Redis, $minute));
        }
    }

    /**
     * 每分钟汇总：全集群只有一个消费进程执行（抢锁），写一行日志，按阈值告警
     * 调用方在下一分钟的第 5 秒后汇总上一分钟，等各进程把计数写完
     * @param Redis $Redis
     * @param string $minute YmdHi
     */
    public static function report(Redis $Redis, string $minute)
    {
        if ( ! $Redis->set(static::key("lock:report-$minute"), 1, ['NX', 'EX' => 120])) {
            return;
        }
        $stat = static::stat($Redis, $minute);
        $snap = static::snapshot($Redis);
        trace(['minute' => $minute] + $snap + $stat, 'info', static::logCategory());

        $sent = $stat['sent'] ?? 0;
        $fail = $stat['fail'] ?? 0;
        if ( ! empty($stat['expired'])) {
            static::alarm($Redis, 'expired', static::TITLE . '排队超过 ' . static::ttl() . " 秒没处理、已丢弃 {$stat['expired']} 条", $snap, $stat, $minute);
        }
        if ( ! empty($stat['lost'])) {
            static::alarm($Redis, 'lost', static::TITLE . "有 {$stat['lost']} 条放回队列失败或消费进程退出时没处理完，可能丢失", $snap, $stat, $minute);
        }
        if ( ! empty($stat['rejected'])) {
            static::alarm($Redis, 'rejected', static::TITLE . "排不下，已拒绝 {$stat['rejected']} 次请求", $snap, $stat, $minute);
        }
        // 个别失败（号码、证件错误等）不告警
        if ($fail >= 10 && $fail * 10 >= $sent + $fail) {
            static::alarm($Redis, 'fail', static::TITLE . "重试用尽后仍失败 {$fail} 条（成功 {$sent} 条）", $snap, $stat, $minute);
        }
    }

    /**
     * 检查配置，有问题告警。配置改了要重启才生效，消费进程启动时查一次就够（全集群告警去重）
     *  - 子类 configProblems() 的业务检查
     *  - 有过期时间时，queue_wait 关闭或不小于过期时间：积压时排上队的会排到过期、被丢弃
     * @param Redis $Redis
     * @return array 问题列表
     */
    public static function checkConfig(Redis $Redis): array
    {
        $cfg = static::cfg();
        $problems = static::configProblems();
        $queueWait = intval($cfg['queue_wait'] ?? 30);
        $ttl = static::ttl();
        if ($ttl > 0 && ($cfg['queue'] ?? true) && ($queueWait <= 0 || $queueWait >= $ttl)) {
            $problems[] = "queue_wait（{$queueWait}）要大于 0 且小于过期时间（{$ttl}），否则积压时排上队的会排到过期、被丢弃";
        }
        $problems && static::alarm($Redis, 'config', static::TITLE . '配置有问题：' . implode('；', $problems));
        return $problems;
    }

    /*************** 内部 ***************/

    /**
     * 本队列的 Redis key
     * @param string $name alive、busy、stat:{YmdHi}、lock:{名称}
     * @return string
     */
    public static function key(string $name): string
    {
        return 'async:' . static::NAME . ":$name";
    }

    /**
     * 清空本进程内的状态，测试用
     */
    public static function reset()
    {
        unset(static::$state[static::class]);
    }

    /**
     * 阈值配置
     * @return array
     */
    protected static function cfg(): array
    {
        return static::CFG ? (config(static::CFG) ?: []) : [];
    }

    /**
     * 本进程内的状态（引用）
     * @return array
     */
    protected static function &st(): array
    {
        if ( ! isset(static::$state[static::class])) {
            static::$state[static::class] = [
                'pending' => [], 'flushAt' => 0, 'expireMinute' => '',
                'alive' => false, 'aliveCheckAt' => 0, 'busy' => false, 'busyCheckAt' => 0,
                'flushTimer' => false, 'sender' => false,
            ];
        }
        return static::$state[static::class];
    }

    /**
     * 告警：同类 ALARM_INTERVAL 秒内全集群只发一条，附上当前指标。告警失败只记日志
     * @param Redis $Redis
     * @param string $type
     * @param string $content
     * @param array $snap snapshot()
     * @param array $stat stat()
     * @param string $minute 统计对应的分钟，默认当前分钟
     */
    protected static function alarm(Redis $Redis, string $type, string $content, array $snap = [], array $stat = [], string $minute = '')
    {
        $lines = [$content];
        $snap && $lines[] = "当前：积压 {$snap['len']} 条，最早一条已等 {$snap['wait']} 秒，消费进程 {$snap['senders']} 个";
        if ($stat) {
            $minute = $minute ?: date('YmdHi');
            $lines[] = substr($minute, 8, 2) . ':' . substr($minute, 10, 2) . ' 这一分钟：' . static::describe($stat);
        }
        // 日志会去掉换行，用分号隔开
        trace_limit('async_alarm_' . static::NAME . "_$type", implode('；', $lines), 'error');
        try {
            if ($Redis->set(static::key("lock:alarm-$type"), 1, ['NX', 'EX' => static::ALARM_INTERVAL])) {
                static::notify($type, '【' . static::TITLE . '队列】' . implode("\n", $lines));
            }
        } catch (\Throwable $e) {
            trace_limit('async_alarm_fail_' . static::NAME, static::TITLE . "队列告警发送失败：{$e->getMessage()}", 'error');
        }
    }

    /**
     * 统计转成告警里看得懂的文字
     * @param array $stat stat()
     * @return string
     */
    protected static function describe(array $stat): string
    {
        $names = [
            'queued' => '入队', 'direct' => '当场处理', 'rejected' => '被拒', 'sent' => '成功', 'fail' => '重试用尽仍失败',
            'expired' => '过期丢弃', 'lost' => '可能丢失', 'retry' => '失败重试', 'len_max' => '最大积压', 'wait_max' => '最长等待(秒)',
        ];
        $parts = [];
        foreach ($names as $field => $name) {
            empty($stat[$field]) or $parts[] = "$name {$stat[$field]}";
        }
        // 按云商的成功数、失败数、超时数
        $cloudNames = ['sent' => '成功', 'fail' => '失败', 'timeout' => '超时'];
        foreach ($stat as $field => $n) {
            if (preg_match('/^(sent|fail|timeout)_(.+)$/', $field, $m)) {
                $parts[] = "{$cloudNames[$m[1]]}($m[2]) $n";
            }
        }
        return $parts ? implode('，', $parts) : '无';
    }
}
