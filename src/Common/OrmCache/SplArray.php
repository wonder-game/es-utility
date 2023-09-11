<?php

namespace WonderGame\EsUtility\Common\OrmCache;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

/**
 * 将数据表缓存为SplArray类型
 */
trait SplArray
{
    use Events;

    /**
     * 有效期, s
     * @var float|int
     */
    protected $expire = 7 * 86400;

    protected $redisPoolName = 'default';

    protected $splKey = 'spl-array-%s';

    protected $splWhere = [];

    /**
     * 默认规则为两个字段键值对，也可重写 _getSplArray
     * @var string
     */
    protected $splFieldKey = '';
    protected $splFieldValue = '';

    protected function _getCacheKey()
    {
        /* @var AbstractModel $this */
        $table = $this->getTableName();
        return sprintf($this->splKey, $table);
    }

    protected function _getSplArray()
    {
        /* @var AbstractModel $this */
        if ($this->splWhere) {
            $this->where($this->splWhere);
        }
        $all = $this->all();

        $result = [];
        /** @var AbstractModel $item */
        foreach ($all as $item) {
            $result[$item->getAttr($this->splFieldKey)] = $item->getAttr($this->splFieldValue);
        }
        unset($all, $item);

        return new \EasySwoole\Spl\SplArray($result);
    }

    /**
     * 示例用法： Model->cacheSpl('key1.key2.key3')
     * @document http://www.easyswoole.com/Components/Spl/splArray.html
     * @param string|true|null $key true-直接返回SplArray对象，非true取值与 SplArray->get 相同
     * @param string|null $default 默认值
     * @return mixed|null
     */
    public function cacheSpl($key = null, $default = null)
    {
        /* @var \EasySwoole\Spl\SplArray $Spl */
        $Spl = RedisPool::invoke(function (Redis $redis) {
            $key = $this->_getCacheKey();

            $data = $redis->get($key);
            if ($data !== false && ! is_null($data)) {
                $slz = unserialize($data);
                if ($slz instanceof \EasySwoole\Spl\SplArray) {
                    return $slz;
                }
            }

            $Spl = $this->_getSplArray();
            $redis->setEx($key, $this->expire, serialize($Spl));
            return $Spl;

        }, $this->redisPoolName);
        return $key === true ? $Spl : $Spl->get($key, $default);
    }

    public function cacheDel()
    {
        return RedisPool::invoke(function (Redis $redis) {
            $key = $this->_getCacheKey();
            return $redis->del($key);
        }, $this->redisPoolName);
    }

    protected function _after_cache()
    {
        $this->cacheDel();
    }
}
