<?php

namespace Linkunyuan\EsUtility\HttpTracker;

use EasySwoole\Tracker\Point;
use EasySwoole\Tracker\SaveHandlerInterface;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Redis\Redis;

class SaveHandler implements SaveHandlerInterface
{
    protected $config = [
        'queue' => 'Report-Tracker',
        'redis-name' => 'report',
    ];

    public function __construct($cfg = [])
    {
        $cfg && $this->config = array_merge($this->config, $cfg);
    }

    /**
     * @param Point|null $point
     * @param array|null $globalArg
     * @return bool
     */
    function save(?Point $point,?array $globalArg = []):bool
    {
        if ($array = Point::toArray($point)) {
            try {
                RedisPool::invoke(function (Redis $redis) use ($array) {
                    foreach ($array as $value)
                    {
                        $redis->lPush($this->config['queue'], json_encode($value, JSON_UNESCAPED_UNICODE));
                    }
                }, $this->config['redis-name']);
            }
            catch (\Exception | \Throwable $e)
            {
                \Linkunyuan\EsUtility\trace($e->getMessage(), 'error');
                return false;
            }
        }
        return true;
    }
}
