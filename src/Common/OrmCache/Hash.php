<?php

namespace WonderGame\EsUtility\Common\OrmCache;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

/**
 * Hash缓存，适用于全表缓存
 */
trait Hash
{
    use Events;

    protected $redisPoolName = 'default';

    protected $hashKey = 'hash-%s';

    /**
     * hash key 字段
     * @var string
     */
    protected $hashFieldKey = 'id';

    protected $hashWhere = [];

    protected function _getCacheKey()
    {
        $table = $this->getTableName();
        return sprintf($this->hashKey, $table);
    }

    protected function _rowEncode($array)
    {
        return json_encode($array, JSON_UNESCAPED_UNICODE);
    }

    protected function _rowDecode($string)
    {
        return is_string($string) ? json_decode_ext($string) : $string;
    }

    protected function _getCacheData()
    {
        if ($this->hashWhere) {
            $this->where($this->hashWhere);
        }
        $data = $this->indexBy($this->hashFieldKey) ?: [];
        return array_map(function ($data) {
            return $this->_rowEncode($data);
        }, $data);
    }

    protected function _chkHashKey(Redis $redis, $key)
    {
        if ( ! $redis->exists($key)) {
            // set全部
            $all = $this->_getCacheData();
            $all && $redis->hMSet($key, $all);
        }
    }

    public function cacheHGet($field)
    {
        return RedisPool::invoke(function (Redis $redis) use ($field) {
            $key = $this->_getCacheKey();

            $this->_chkHashKey($redis, $key);

            if ( ! $redis->hExists($key, $field)) {
                return false;
            }

            $data = $redis->hGet($key, $field);
            return $this->_rowDecode($data);
        }, $this->redisPoolName);
    }

    public function cacheHGetAll()
    {
        return RedisPool::invoke(function (Redis $redis) {
            $key = $this->_getCacheKey();

            $this->_chkHashKey($redis, $key);

            $data = $redis->hGetAll($key) ?: [];

            return array_map(function ($data) {
                return $this->_rowDecode($data);
            }, $data);
        }, $this->redisPoolName);
    }

    public function cacheDel()
    {
        return RedisPool::invoke(function (Redis $redis) {
            return $redis->del($this->_getCacheKey());
        }, $this->redisPoolName);
    }

    public function _after_cache()
    {
        $this->cacheDel();
    }
}
