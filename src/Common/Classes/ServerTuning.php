<?php

namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;
use Swoole\Process;
use Swoole\Server;

/**
 * 高并发下的 worker 进程调优（目前只用于 sdk 模块）
 *  - HTTP worker 的连接池上限、取连接超时、MySQL 超时（task 进程不改，定时任务可能有长查询）
 *  - 按请求数（带随机抖动）和内存回收 worker，代替 Swoole 的 max_request
 *    dispatch_mode=3 下各 worker 请求数几乎一致，用 max_request 会整机同时重启
 */
class ServerTuning
{
    /** @var bool 当前进程是否为 HTTP worker（非 task 进程） */
    protected static $isHttpWorker = false;

    /** @var int 本 worker 的回收阈值（请求数） */
    protected static $maxRequest = 0;

    /** @var int 本 worker 的内存回收阈值（字节） */
    protected static $maxMemory = 0;

    /** @var int 已处理的请求数 */
    protected static $requestCount = 0;

    /** @var bool 是否已发起回收 */
    protected static $recycling = false;

    /** @var callable|null 预热模型结构的回调，由使用方的框架注册（公共库不依赖业务模型） */
    protected static $warmupModels;

    /**
     * 注册预热回调：worker 启动时在协程里调用一次，用来加载热点表结构、预热查询等
     * 公共库本身不碰业务模型，需要预热的项目在框架初始化时注册：function () { ... }
     * @param callable $warmup
     */
    public static function setWarmupModels(callable $warmup)
    {
        self::$warmupModels = $warmup;
    }

    public static function isHttpWorker(): bool
    {
        return self::$isHttpWorker;
    }

    public static function onWorkerStart(Server $server, int $workerId)
    {
        if ($server->taskworker) {
            return;
        }
        self::$isHttpWorker = true;

        $cfg = config('HTTP_POOL') ?: [];
        $getObjectTimeout = floatval($cfg['getObjectTimeout'] ?? 1.0);

        foreach ($cfg['redis'] ?? [] as $name => $max) {
            $pool = RedisPool::getInstance()->getPool($name);
            if ($pool && $max > 0) {
                self::limitPool($pool->getConfig(), $max, $getObjectTimeout);
            }
        }

        foreach ($cfg['mysql'] ?? [] as $name => $max) {
            $connection = DbManager::getInstance()->getConnection($name);
            if ( ! $connection) {
                continue;
            }
            /** @var \EasySwoole\ORM\Db\Config $config */
            $config = $connection->getConfig();
            $max > 0 && self::limitPool($config, $max, $getObjectTimeout);
            empty($cfg['mysqlTimeout']) or $config->setTimeout(intval($cfg['mysqlTimeout']));
        }

        // 回收阈值随机 ±50%，同机 worker 错峰重启
        // 用 random_int：mt_rand 的种子在 fork 前就定了的话，各 worker 会抽到同一个数，等于没错峰
        // 配置见 App/Common/Config/framework.php 的 WORKER_RECYCLE（与 SETTING.max_request 是替代关系，sdk 的 max_request 固定为 0）
        $recycleCfg = config('WORKER_RECYCLE') ?: [];
        $maxRequest = intval($recycleCfg['maxRequest'] ?? 0);
        self::$maxRequest = $maxRequest > 0 ? random_int(intval($maxRequest * 0.5), intval($maxRequest * 1.5)) : 0;
        self::$maxMemory = intval($recycleCfg['maxMemoryMb'] ?? 0) * 1024 * 1024;

        ReportQueue::enable();

        // 预热：提前建好最小连接数、加载热点表结构，新 worker 的第一批请求不用现场建连、并发执行 SHOW FULL COLUMNS
        go_safe(function () use ($cfg) {
            foreach (array_keys($cfg['redis'] ?? []) as $name) {
                ($pool = RedisPool::getInstance()->getPool($name)) && $pool->keepMin();
            }
            foreach (array_keys($cfg['mysql'] ?? []) as $name) {
                ($connection = DbManager::getInstance()->getConnection($name)) && $connection->__getClientPool()->keepMin();
            }
            // 业务模型的热点表结构由使用方注册（setWarmupModels），公共库不依赖业务模型
            self::$warmupModels && call_user_func(self::$warmupModels);
        });
    }

    public static function onWorkerExit(Server $server, int $workerId)
    {
        ReportQueue::stop();
    }

    /**
     * 每个请求结束后调用，达到阈值时让当前 worker 平滑退出（与 max_request 走同一套 reload_async 流程，由 manager 拉起新进程）
     */
    public static function afterRequest()
    {
        if ( ! self::$isHttpWorker || self::$recycling) {
            return;
        }
        ++self::$requestCount;
        if (
            (self::$maxRequest > 0 && self::$requestCount >= self::$maxRequest)
            || (self::$maxMemory > 0 && memory_get_usage() >= self::$maxMemory)
        ) {
            self::$recycling = true;
            trace(sprintf('worker 回收：请求数 %d，内存 %s', self::$requestCount, memory_convert(memory_get_usage())), 'info', 'worker');
            Process::kill(getmypid(), SIGTERM);
        }
    }

    /**
     * @param \EasySwoole\Pool\Config $config
     * @param int $max
     * @param float $getObjectTimeout
     */
    protected static function limitPool($config, int $max, float $getObjectTimeout)
    {
        // minObjectNum 必须小于 maxObjectNum
        if ($config->getMinObjectNum() >= $max) {
            $config->setMinObjectNum(max(0, $max - 1));
        }
        $config->setMaxObjectNum($max);
        $config->setGetObjectTimeout($getObjectTimeout);
    }
}
