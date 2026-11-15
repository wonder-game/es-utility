<?php

namespace WonderGame\EsUtility\Common\Classes;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * 进程内 L1 缓存（每个 worker 一份，协程间共享）
 * 解决热点大 key：所有请求都整块读同一个 key（如 sysinfo、包配置）时，单个 key 会打满 Redis 网卡，分片也没用
 *  - TTL 到期前直接返回；到期后只有一个协程回源（单飞），其余协程先用旧值，没有旧值时等它的结果（最多 1 秒）
 *  - 回源失败时继续使用旧值（最多 STALE 秒），Redis/MySQL 抖动时不会全部打过去
 * 注意：返回的是数组副本（PHP 写时复制），调用方修改不会影响缓存；对象（如 sysinfo 的 SplArray）是共享的，只能读不能改
 */
class LocalCache
{
    /** 过期后旧值最多再用多久（秒） */
    const STALE = 60;

    /** 最多缓存的 key 数，超过后先清过期项，仍超过则清空 */
    const MAX_KEYS = 20000;

    /** @var array key => [value, expireAt] */
    protected static $data = [];

    /** @var Channel[] 正在回源的 key */
    protected static $loading = [];

    /** @var int 上次清理过期项的时间 */
    protected static $lastGc = 0;

    /**
     * @param string $key
     * @param int $ttl 秒
     * @param callable $loader 回源函数，返回值会被缓存（包括 false、null、空数组）
     * @return mixed
     * @throws \Throwable 没有旧值可用且回源失败时抛出原异常
     */
    public static function remember(string $key, int $ttl, callable $loader)
    {
        $now = time();
        $hit = self::$data[$key] ?? null;
        if ($hit !== null && $hit[1] > $now) {
            return $hit[0];
        }
        $hasStale = $hit !== null && $hit[1] + self::STALE > $now;

        // 非协程环境（CLI 脚本等）不做单飞
        if (Coroutine::getCid() < 0) {
            return self::load($key, $ttl, $loader, $hasStale ? $hit : null);
        }

        // 已有协程在回源：有旧值先用旧值，没有则等结果
        if (isset(self::$loading[$key])) {
            if ($hasStale) {
                return $hit[0];
            }
            self::$loading[$key]->pop(1.0);
            $item = self::$data[$key] ?? null;
            if ($item !== null && $item[1] > $now) {
                return $item[0];
            }
            // 回源失败或超时，自己查一次
            return $loader();
        }

        $chan = self::$loading[$key] = new Channel(1);
        try {
            return self::load($key, $ttl, $loader, $hasStale ? $hit : null);
        } finally {
            unset(self::$loading[$key]);
            // 唤醒所有等待的协程
            $chan->close();
        }
    }

    public static function set(string $key, $value, int $ttl)
    {
        $now = time();
        // 每分钟清一次过期项：只按条数清理的话，访问过的大配置会一直留在内存里（单个可能几百 KB），worker 内存只涨不降
        if (count(self::$data) >= self::MAX_KEYS || $now - self::$lastGc >= 60) {
            self::$lastGc = $now;
            self::gc();
        }
        self::$data[$key] = [$value, $now + $ttl];
    }

    public static function delete(string $key)
    {
        unset(self::$data[$key]);
    }

    protected static function load(string $key, int $ttl, callable $loader, ?array $stale)
    {
        try {
            $value = $loader();
        } catch (\Throwable $e) {
            if ($stale === null) {
                throw $e;
            }
            // 回源失败继续用旧值，并顺延几秒，避免每个请求都去重试
            self::$data[$key][1] = time() + min($ttl, 5);
            trace_limit("local_cache_$key", "L1 缓存回源失败，继续使用旧值：$key {$e->getMessage()}", 'error');
            return $stale[0];
        }
        self::set($key, $value, $ttl);
        return $value;
    }

    protected static function gc()
    {
        $now = time();
        foreach (self::$data as $key => $item) {
            if ($item[1] + self::STALE <= $now) {
                unset(self::$data[$key]);
            }
        }
        if (count(self::$data) >= self::MAX_KEYS) {
            self::$data = [];
        }
    }
}
