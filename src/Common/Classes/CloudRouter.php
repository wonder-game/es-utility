<?php

namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\RedisPool\RedisPool;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * 多云商调用顺序（短信、邮件、实名认证等共用）
 *
 * driver 两种写法：
 *   ['Tencent', 'Tencent', 'Alibaba']   列表：按顺序尝试（所有请求都先打第一家）
 *   ['Tencent' => 6, 'Shlianlu' => 4]   权重：按权重随机选首发，失败后在剩下的里再按权重选；权重为 0 的只做兜底
 *
 * 云商配置里可加 'qps' => N（各机器合计，按云商的 QPS 配额来填，0 或不填不限）：
 * 本秒已超的云商先跳过，其他云商都失败了再拿来兜底
 * 某云商连续失败（挂了、欠费、被限流）会被熔断一小段时间，期间同样排到最后，避免每个请求都先等它超时
 */
class CloudRouter
{
    /**
     * 本次请求的云商尝试顺序
     * @param array|string $drivers
     * @param int $start 列表写法时从第几个开始（兼容实名的 real_idx），权重写法忽略
     * @return array
     */
    public static function order($drivers, int $start = 0): array
    {
        $drivers = (array)$drivers;
        if (array_values($drivers) === $drivers) {
            return array_slice($drivers, $start);
        }

        $order = $fallback = $weights = [];
        foreach ($drivers as $cloud => $weight) {
            if (intval($weight) > 0) {
                $weights[$cloud] = intval($weight);
            } else {
                $fallback[] = $cloud;
            }
        }
        // 不放回的加权随机
        while ($weights) {
            $rand = random_int(1, array_sum($weights));
            foreach ($weights as $cloud => $weight) {
                if (($rand -= $weight) <= 0) {
                    $order[] = $cloud;
                    unset($weights[$cloud]);
                    break;
                }
            }
        }
        return array_merge($order, $fallback);
    }

    /**
     * 按 order() 的顺序调用，$call 返回 true 即停止
     * 熔断中（本进程内连续失败，见 Breaker）或本秒超 qps 的云商排到最后，其他云商都失败了再拿来兜底
     * @param array $order order() 的结果
     * @param string $scene 场景名，如 sms、email、idauth
     * @param callable|null $qpsOf function(string $cloud): int 取云商的 qps 配置；不传则不按 qps 排序，也不访问 Redis
     * @param callable $call function(string $cloud): bool 调用云商，true 表示成功、不再往后
     * @param string $pool qps 计数用的 Redis 连接池
     * @return bool 是否有云商调用成功
     */
    public static function each(array $order, string $scene, ?callable $qpsOf, callable $call, string $pool = 'default'): bool
    {
        $deferred = [];
        foreach ($order as $cloud) {
            if ( ! Breaker::allow("cloud-$scene-$cloud") || ($qpsOf && self::overQps($scene, $cloud, intval($qpsOf($cloud)), $pool))) {
                $deferred[] = $cloud;
                continue;
            }
            if (self::tryCall($scene, $cloud, $call)) return true;
        }
        foreach ($deferred as $cloud) {
            if (self::tryCall($scene, $cloud, $call)) return true;
        }
        return false;
    }

    /**
     * each() 的增强版：每次调用可限时，并回报每家云商的结果
     * @param string $scene 场景名
     * @param array $order order() 的结果
     * @param callable $call function(string $cloud): bool；里面没捕获的异常会原样往外抛（不再换下一家），要换家的异常请自己捕获后返回 false
     * @param array $opt
     *   - timeout float 单次调用超时秒数，0 不限。超时按失败处理，换下一家。
     *     每次调用放进独立子协程：一是能限时（超时取消子协程，开了 Hook 的进程里挂着的请求会立即报错退出；
     *     没开 Hook 的进程里调用是阻塞的，go() 返回时已有结果，限时不起作用但也无副作用）；
     *     二是云商驱动用 http_tracker 在当前协程建链路起始节点，同一协程第二次建会抛异常，换家兜底时从第二家起都是假失败
     *   - observe callable function(string $cloud, string $status) 每次调用后回报，status 为 ok|fail|timeout，用于按云商统计
     *   - qps callable 同 each() 的 $qpsOf
     *   - pool string 同 each() 的 $pool
     * @return array [是否成功, 成功的云商]
     */
    public static function call(string $scene, array $order, callable $call, array $opt = []): array
    {
        $timeout = floatval($opt['timeout'] ?? 0);
        $observe = $opt['observe'] ?? null;
        $by = '';
        $ok = self::each($order, $scene, $opt['qps'] ?? null, function ($cloud) use ($scene, $call, $timeout, $observe, &$by) {
            $status = self::invoke($scene, $cloud, $call, $timeout);
            $observe && $observe($cloud, $status);
            $status === 'ok' && $by = $cloud;
            return $status === 'ok';
        }, $opt['pool'] ?? 'default');
        return [$ok, $by];
    }

    /**
     * 云商本秒调用量是否已超上限（各机器合计）
     * @param string $scene
     * @param string $cloud
     * @param int $qps
     * @param string $pool
     * @return bool
     */
    public static function overQps(string $scene, string $cloud, int $qps, string $pool = 'default'): bool
    {
        if ($qps <= 0) {
            return false;
        }
        $key = "cloud-qps-$scene-$cloud-" . time();
        try {
            // 用 invoke 而不是 defer：单次 incr，用完立刻归还连接，不把连接占到这个协程结束
            // 连接池满或未注册时 invoke 抛 PoolEmpty，下面兜住，按「未超限」处理
            return (bool)RedisPool::invoke(function ($Redis) use ($key, $qps) {
                $count = intval($Redis->incr($key));
                // 每秒一个新 key，第一次计数时设过期：比先 SET NX EX 再 INCR 少一次往返
                $count === 1 && $Redis->expire($key, 2);
                return $count > $qps;
            }, $pool);
        } catch (\Throwable $e) {
            // 计数失败不影响调用，日志限频避免连接池打满时刷屏
            trace_limit("cloud_qps_count_fail_$scene", "云商 qps 计数失败：$scene $cloud {$e->getMessage()}", 'error');
            return false;
        }
    }

    protected static function tryCall(string $scene, string $cloud, callable $call): bool
    {
        $ok = (bool)$call($cloud);
        $ok ? Breaker::success("cloud-$scene-$cloud") : Breaker::fail("cloud-$scene-$cloud");
        return $ok;
    }

    /**
     * 调一家云商，见 call() 的 timeout 说明
     * @return string ok|fail|timeout
     * @throws \Throwable $call 里没捕获的异常
     */
    protected static function invoke(string $scene, string $cloud, callable $call, float $timeout): string
    {
        if ($timeout <= 0 || Coroutine::getCid() <= 0) {
            return $call($cloud) ? 'ok' : 'fail';
        }
        $chan = new Channel(1);
        $cid = go(function () use ($chan, $call, $cloud) {
            try {
                // 推数组而不是 bool：pop() 超时也返回 false，推 false 的话「调用失败」和「云商没返回」就分不出来了
                $chan->push([(bool)$call($cloud), null]);
            } catch (\Throwable $e) {
                $chan->push([false, $e]);
            }
        });
        $ret = $chan->pop($timeout);
        if ($ret === false) {
            $cid && Coroutine::cancel($cid);
            trace_limit("cloud_timeout_{$scene}_$cloud", "云商 {$timeout} 秒未返回，按失败处理：$scene $cloud", 'error');
            return 'timeout';
        }
        if ($ret[1]) {
            throw $ret[1];
        }
        return $ret[0] ? 'ok' : 'fail';
    }
}
