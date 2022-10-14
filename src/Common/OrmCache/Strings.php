<?php

namespace WonderGame\EsUtility\Common\OrmCache;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Redis\Redis;

/**
 * 字符串缓存，适用于单行缓存
 */
trait Strings
{
    use Events;

    /**
     * 有效期
     * @var float|int
     */
    protected $expire = 7 * 86400;

    /**
     * 连接池
     * @var string
     */
    protected $redisPool = 'default';

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
    protected $mergeExt = true;

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
     * 穿透标识
     * @var string
     */
    private $penetrationSb = '--PENETRATION--';

    /**
     * 布隆过滤，集合 字段
     * @var bool | string
     */
    protected $bloomColumn = false;

    /**
     * 懒惰模式，数据发生变化时，仅删除缓存key，不主动set缓存
     * @var bool
     */
    protected $lazy = true;

    /**
     * 设置以上protected属性
     * @return void
     */
    protected function setCacheTraitProtected()
    {

    }

    protected function _getCacheKey($id)
    {
        if (is_array($id)) {
            ksort($id);
            $str = implode($this->joinSb, array_values($id));
            $id = $this->isMd5 ? md5($str) : $str;
        }
        $prefix = $this->prefix ?: $this->getTableName();
        return $prefix . $this->joinSb . $id;
    }

    /**
     * @param $id 主键值 | ['key' => 'value']
     * @return array
     */
    protected function _getByUnique($id)
    {
        /** @var AbstractModel $data */
        $data = $this->get($id);
        return $data ? $data->toArray() : [];
    }

    protected function _mergeExt($data) {
        isset($data['extension']) && is_array($data['extension']) && $data += $data['extension'];
        unset($data['extension']);
        return $data;
    }

    protected function _getPk()
    {
        $pk = $this->schemaInfo()->getPkFiledName();
        // todo 联合主键，暂主观认为第一个字段为唯一标识，后续得补充条件
        is_array($pk) && $pk = $pk[0];
        return $pk;
    }

    /**
     * @param $id 主键值 | ['key' => 'value']
     * @param $data
     * @return mixed|null
     */
    public function cacheSet($id, $data = [])
    {
        return RedisPool::invoke(function (Redis $redis) use ($id, $data) {
            $key = $this->_getCacheKey($id);

            mt_srand();
            $offset = 12 * 3600;
            $expire = mt_rand($this->expire - $offset, $this->expire + $offset);

            if (is_array($id)) {
                $pk = $this->_getPk();
                if (is_array($data) && ! empty($data) && $data[$pk]) {
                    $this->cacheSet($data[$pk], $data);
                    $value = $this->primarySb . $data[$pk];
                } else {
                    $value = $this->penetrationSb;
                }
            } else {
                is_array($data) && ($encode = json_encode($data, JSON_UNESCAPED_UNICODE)) && $data = $encode;
                $value = $data ?: $this->penetrationSb;
            }
            return $redis->setEx($key, $expire, $value);
        }, $this->redisPool);
    }

    public function cacheGet($id)
    {
        return RedisPool::invoke(function (Redis $redis) use ($id) {
            $key = $this->_getCacheKey($id);
            $data = $redis->get($key);
            // 存储的是主键,则使用主键再次获取
            if (is_string($data) && strpos($data, $this->primarySb) === 0) {
                $data = $this->cacheGet(explode(':', $data)[1]);
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
            $this->mergeExt && $data = $this->_mergeExt($data);
            return $data;
        }, $this->redisPool);
    }

    public function cacheDel($id)
    {
        return RedisPool::invoke(function (Redis $redis) use ($id) {
            $key = $this->_getCacheKey($id);
            return $redis->del($key);
        });
    }


    /*-------------------------- 模型事件 --------------------------*/

    protected function _after_write($res = false)
    {
        // 存入缓存
        if ($res) {
            // 此处去掉协程，原因是特殊场景需要复用连接池连接时会有问题，https://wiki.swoole.com/wiki/page/963.html
            $data = $this->toArray();

            $pk = $this->_getPk();

            // 新增时没有id
            if ( ! isset($data[$pk])) {
                $data[$pk] = $this->lastQueryResult()->getLastInsertId();
            }

            if ($data[$pk]) {
                $this->lazy ? $this->cacheDel($data[$pk]) : $this->cacheGet($data[$pk]);
            }
        }
    }

    protected function _after_delete($res)
    {
        if ($res) {
            // 请使用ORM链式操作对象删除，否则无法拿到data，另外，批量删除也无法拿到单行数据！！！只有受影响行数
            $data = $this->toArray();
            $pk = $this->_getPk();
            if ($data[$pk]) {
                $this->lazy ? $this->cacheDel($data[$pk]) : $this->cacheGet($data[$pk]);
            }
        }
    }

}
