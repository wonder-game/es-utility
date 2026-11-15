<?php

namespace WonderGame\EsUtility\Common\Classes;

/**
 * 进程级简易熔断器
 * 同一标识连续失败 FAILS 次后打开，OPEN_SECONDS 秒内 allow() 返回 false；到期后放行一个请求试探，成功即关闭
 * 只在本进程内计数，不跨进程，不需要任何外部存储
 */
class Breaker
{
    const FAILS = 5;

    const OPEN_SECONDS = 10;

    /** @var array 标识 => 连续失败次数 */
    protected static $fails = [];

    /** @var array 标识 => 熔断到期时间 */
    protected static $openUntil = [];

    public static function allow(string $key): bool
    {
        if ( ! isset(self::$openUntil[$key])) {
            return true;
        }
        $now = time();
        if ($now < self::$openUntil[$key]) {
            return false;
        }
        // 半开：只放行一个试探请求，其余请求继续降级；试探成功由 success() 关闭，失败由 fail() 重新打开
        // 不能直接清掉 openUntil，否则到期瞬间所有并发请求都会放行，一起打到还没恢复的依赖上
        self::$openUntil[$key] = $now + self::OPEN_SECONDS;
        self::$fails[$key] = self::FAILS - 1;
        return true;
    }

    public static function success(string $key)
    {
        unset(self::$fails[$key], self::$openUntil[$key]);
    }

    public static function fail(string $key)
    {
        self::$fails[$key] = (self::$fails[$key] ?? 0) + 1;
        if (self::$fails[$key] >= self::FAILS) {
            self::$openUntil[$key] = time() + self::OPEN_SECONDS;
            trace_limit("breaker_$key", "熔断打开：{$key}，连续失败 " . self::$fails[$key] . ' 次，' . self::OPEN_SECONDS . ' 秒内直接降级', 'error');
        }
    }
}
