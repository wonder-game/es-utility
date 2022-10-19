<?php

namespace WonderGame\EsUtility\Common\OrmCache;

use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

/**
 * 集合缓存
 */
trait Sets
{
    use Events;

    protected $redisPoolName = 'default';

    protected $memberKey = 'member-%s';

    protected $memberField = '';

    protected $memberWhere = [];

    /**
     * 为空时设置的空元素,如不设置sadd命令可能执行出错
     * @var string
     */
    protected $memberEmptyValue = 'test';

    protected function _getCacheKey()
    {
        $table = $this->getTableName();
        return sprintf($this->memberKey, $table);
    }

    protected function _getCacheData()
    {
        if ($this->memberWhere) {
            $this->where($this->memberWhere);
        }
        return $this->column($this->memberField);
    }

    public function cacheSAdd()
    {
        RedisPool::invoke(function (Redis $redis) {
            $key = $this->_getCacheKey();
            $rows = $this->_getCacheData();
            if (empty($rows) || ! is_array($rows)) {
                if (empty($this->memberEmptyValue)) {
                    throw new \Exception("cacheSAdd Empty Table Value: $key");
                }
                $rows = [$this->memberEmptyValue];
            }
            $redis->sAdd($key, ...$rows);
        }, $this->redisPoolName);
    }

    public function cacheDel()
    {
        return RedisPool::invoke(function (Redis $redis) {
            $key = $this->_getCacheKey();
            return $redis->del($key);
        }, $this->redisPoolName);
    }

    public function cacheIsMember($value)
    {
        return RedisPool::invoke(function (Redis $redis) use ($value) {
            $key = $this->_getCacheKey();
            if ( ! $redis->exists($key)) {
                $this->cacheSAdd();
            }
            return $redis->sIsMember($key, $value);
        }, $this->redisPoolName);
    }

    protected function _after_cache()
    {
        $this->cacheDel();
    }

    protected function _after_write($res)
    {
        $res && $this->_after_cache();
    }

    protected function _after_delete($res)
    {
        $res && $this->_after_cache();
    }
}
