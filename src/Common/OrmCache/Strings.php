<?php

namespace WonderGame\EsUtility\Common\OrmCache;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;

/**
 * 字符串缓存，适用于单行缓存
 * @extends AbstractModel
 */
trait Strings
{
    use Events;

    /**
     * 有效期, s
     * @var float|int
     */
    protected $expire = 7 * 86400;

    /**
     * 防雪崩偏移, s
     * @var float|int
     */
    protected $expireOffset = 12 * 3600;

    /**
     * 穿透标识
     * @var string
     */
    private $penetrationSb = 'PENETRATION';

    /**
     * 布隆过滤，true-开启，对于大表谨慎开启！！
     * @var bool
     */
    protected $bloom = false;

    /**
     * 布隆过滤，集合key
     * @var string
     */
    protected $bloomKey = 'bloom-%s';

    /**
     * 布隆过滤，集合缓存字段
     * @var string
     */
    protected $bloomField = 'id';

    /**
     * 布隆过滤，集合缓存条件
     * @var array
     */
    protected $bloomWhere = [];

    /**
     * 连接池
     * @var string
     */
    protected $redisPoolName = 'default';

    /**
     * 前缀，null则获取数据表名
     * @var null
     */
    protected $prefix = null;

    /**
     * 将数组key进行md5
     * @var bool
     */
    protected $isMd5 = true;

    /**
     * 将extension数据合并到主数据
     * @var bool
     */
    protected $mergeExt = false;

    /**
     * key连接符
     * @var string
     */
    protected $joinSb = '-';

    /**
     * 主键标识
     * @var string
     */
    private $primarySb = 'QUOTE:';

    /**
     * 懒惰模式，数据发生变化时，仅删除缓存key，不主动set缓存
     * @var bool
     */
    protected $lazy = true;

    /**
     * todo 延迟双删
     * @var bool
     */
    protected $delay = false;

    protected function _getCacheKey($id)
    {
        /* @var AbstractModel $this */
        if (is_array($id)) {
            ksort($id);
            $str = urldecode(http_build_query($id));
            $id = $this->isMd5 ? md5($str) : $str;
        }
        $prefix = is_null($this->prefix) ? $this->getTableName() : $this->prefix;
        return $prefix . $this->joinSb . $id;
    }

    /**
     * @param string|array $id 主键值 | ['key' => 'value']
     * @return array
     */
    protected function _getByUnique($id)
    {
        /* @var AbstractModel $this */
        /** @var AbstractModel $data */
        $data = $this->get($id);
        return $data ? $data->toArray() : [];
    }

    protected function _getBloomData()
    {
        /* @var AbstractModel $this */
        if ($this->bloomWhere) {
            $this->where($this->bloomWhere);
        }
        return $this->column($this->bloomField);
    }

    protected function _getBloomKey()
    {
        /* @var AbstractModel $this */
        $tableName = $this->getTableName();
        return sprintf($this->bloomKey, $tableName);
    }

    protected function _mergeExt($data)
    {
        isset($data['extension']) && is_array($data['extension']) && $data += $data['extension'];
        unset($data['extension']);
        return $data;
    }

    protected function _getPk()
    {
        /* @var AbstractModel $this */
        $pk = $this->schemaInfo()->getPkFiledName();
        // todo 联合主键，暂主观认为第一个字段为唯一标识，后续得补充条件
        is_array($pk) && $pk = $pk[0];
        return $pk;
    }

    public function bloomSet()
    {
        RedisPool::invoke(function (Redis $redis) {
            $rows = $this->_getBloomData();
            $rows = ($rows && is_array($rows)) ? $rows : [];
            $key = $this->_getBloomKey();
            $redis->sAdd($key, ...$rows);
        }, $this->redisPoolName);
    }

    public function bloomDel()
    {
        return RedisPool::invoke(function (Redis $redis) {
            $key = $this->_getBloomKey();
            return $redis->del($key);
        }, $this->redisPoolName);
    }

    public function bloomIsMember($value)
    {
        return RedisPool::invoke(function (Redis $redis) use ($value) {
            $key = $this->_getBloomKey();
            if ( ! $redis->exists($key)) {
                $this->bloomSet();
            }
            return $redis->sIsMember($key, $value);
        }, $this->redisPoolName);
    }

    /**
     * @param string|array $id 主键值 | ['key' => 'value']
     * @param array $data
     * @param bool $bloom 删集合
     * @return mixed|null
     */
    public function cacheSet($id, $data = [], $bloom = false)
    {
        return RedisPool::invoke(function (Redis $redis) use ($id, $data, $bloom) {
            $key = $this->_getCacheKey($id);

            if (is_array($id)) {
                $pk = $this->_getPk();
                if (is_array($data) && ! empty($data) && isset($data[$pk])) {
                    $this->cacheSet($data[$pk], $data);
                    $value = $this->primarySb . $data[$pk];
                } else {
                    $value = $this->penetrationSb;
                }
            } else {
                is_array($data) && ($encode = json_encode($data, JSON_UNESCAPED_UNICODE)) && $data = $encode;
                $value = $data ?: $this->penetrationSb;
            }

            $bloom && $this->bloom && $this->bloomDel();

            mt_srand();
            $expire = mt_rand($this->expire - $this->expireOffset, $this->expire + $this->expireOffset);
            return $redis->setEx($key, $expire, $value);
        }, $this->redisPoolName);
    }

    public function cacheGet($id, $mergeExt = null)
    {
        return RedisPool::invoke(function (Redis $redis) use ($id, $mergeExt) {

            if ($this->bloom) {
                $bloomKey = $id;
                if (is_array($bloomKey)) {
                    if (empty($bloomKey[$this->bloomField])) {
                        return false;
                    }
                    $bloomKey = $bloomKey[$this->bloomField];
                }
                $isMember = $this->bloomIsMember($bloomKey);
                if ( ! $isMember) {
                    return false;
                }
            }

            $key = $this->_getCacheKey($id);
            $data = $redis->get($key);
            // 存储的是主键,则使用主键再次获取
            if (is_string($data) && strpos($data, $this->primarySb) === 0) {
                // 再次获取时无需校验了
                $bloom = $this->bloom;
                $this->bloom = false;
                $data = $this->cacheGet(explode(':', $data)[1]);
                $this->bloom = $bloom;
            }
            // 没有数据，从数据表获取
            if (is_null($data) || $data === false) {
                $data = $this->_getByUnique($id);
                if (empty($data)) {
                    $data = $this->penetrationSb;
                }
                $this->cacheSet($id, $data);
            }
            if ($data === $this->penetrationSb) {
                return false;
            }
            is_string($data) && $data = json_decode_ext($data);
            (is_null($mergeExt) ? $this->mergeExt : $mergeExt) && $data = $this->_mergeExt($data);
            return $data;
        }, $this->redisPoolName);
    }

    public function cacheDel($id)
    {
        return RedisPool::invoke(function (Redis $redis) use ($id) {
            $key = $this->_getCacheKey($id);
            $status = $redis->del($key);

            $this->bloom && $this->bloomDel();

            return $status;
        }, $this->redisPoolName);
    }


    /*-------------------------- 模型事件 --------------------------*/

    protected function _after_cache()
    {
        /* @var AbstractModel $this */
        $data = $this->toArray();
        $pk = $this->_getPk();

        // 新增时没有id
        if ( ! isset($data[$pk])) {
            $insertId = $this->lastQueryResult()->getLastInsertId();
            $insertId && $data[$pk] = $insertId;
        }

        if (isset($data[$pk])) {
            $this->lazy ? $this->cacheDel($data[$pk]) : $this->cacheSet($data[$pk], $data);
        }
        if ($this->bloom) {
            $this->bloomDel();
            ! $this->lazy && $this->bloomSet();
        }
    }
}
