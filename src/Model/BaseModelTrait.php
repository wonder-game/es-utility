<?php

namespace WonderGame\EsUtility\Model;

use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;

/**
 * @extends AbstractModel
 */
trait BaseModelTrait
{
	protected $gameid = '';

	protected $sort = ['id' => 'desc'];

	/*************** 以下为原LamModel属性 ***************/
	protected $_error = [];
	protected $awaCache = false;
	protected $awaCacheExpire = 7 * 24 * 3600; // 单条记录的默认缓存时间
	protected $redisPoolname = 'default'; // redis连接池的标识
	protected $destroyWhere = []; // 执行删除数据时的where值

	public function __construct($data = [], $tabname = '', $gameid = '')
	{
		// $tabname > $this->tableName > $this->_getTable()
		$tabname && $this->tableName = $tabname;
		if ( ! $this->tableName) {
			$this->tableName = $this->_getTable();
		}

		$this->gameid = $gameid;

//        $this->autoTimeStamp = false;
		$this->createTime = 'instime';
		$this->updateTime = false;
		$this->setBaseTraitProptected();

		parent::__construct($data);
	}

	protected function setBaseTraitProptected()
	{
	}

	/**
	 * 获取表名，并将将Java风格转换为C的风格
	 * @return string
	 */
	protected function _getTable()
	{
		$name = basename(str_replace('\\', '/', get_called_class()));
		return parse_name($name);
	}

	public function getPk()
	{
		return $this->schemaInfo()->getPkFiledName();
	}

	protected function getExtensionAttr($extension = '', $alldata = [])
	{
		return is_array($extension) ? $extension : json_decode($extension, true);
	}

	/**
	 * 数据写入前对extension字段的值进行处理
	 * @access protected
	 * @param array $extension 原数据
	 * @param bool $encode 是否强制编码
	 * @return string 处理后的值
	 */
	protected function setExtensionAttr($extension = [], $alldata = [])
	{
        // QueryBuilder::func 等结构
        if (is_array($extension) && in_array(array_key_first($extension), ['[I]', '[F]', '[N]'])) {
            return $extension;
        }
		if (is_string($extension)) {
			$extension = json_decode($extension, true);
			if ( ! $extension) {
				return json_encode(new \stdClass());
			}
		}
		return json_encode($extension);
	}

	protected function getIpAttr($ip = [], $data = [])
	{
		return is_numeric($ip) ? long2ip($ip) : $ip;
	}

	protected function setIpAttr($ip, $data = [])
	{
		return is_numeric($ip) ? $ip : ip2long($ip);
	}

	protected function setInstimeAttr($instime, $all)
	{
		return is_numeric($instime) ? $instime : strtotime($instime);
	}

	public function scopeIndex()
	{
		return $this;
	}

	public function setOrder(array $order = [])
	{
		$sort = $this->sort;
		// 'id desc'
		if (is_string($sort)) {
			list($sortField, $sortValue) = explode(' ', $sort);
			$order[$sortField] = $sortValue;
		} // ['sort' => 'desc'] || ['sort' => 'desc', 'id' => 'asc']
		else if (is_array($sort)) {
			// 保证传值的最高优先级
			foreach ($sort as $k => $v) {
				if ( ! isset($order[$k])) {
					$order[$k] = $v;
				}
			}
		}

		foreach ($order as $key => $value) {
			$this->order($key, $value);
		}
		return $this;
	}

	/**
	 * 不修改配置的情况下，all结果集转Collection，文档： http://www.easyswoole.com/Components/Orm/toArray.html
	 * @param bool $toArray
	 * @return array|bool|\EasySwoole\ORM\Collection\Collection|\EasySwoole\ORM\Db\Cursor|\EasySwoole\ORM\Db\CursorInterface
	 * @throws \EasySwoole\ORM\Exception\Exception
	 * @throws \Throwable
	 */
	public function ormToCollection($toArray = true)
	{
		$result = $this->all();
		if ( ! $result instanceof \EasySwoole\ORM\Collection\Collection) {
			$result = new \EasySwoole\ORM\Collection\Collection($result);
		}
		return $toArray ? $result->toArray() : $result;
	}

	/**
	 * 删除rediskey
	 * @param mixed ...$key
	 */
	public function delRedisKey(...$key)
	{
		$redis = RedisPool::defer();
		$redis->del($key);
	}


	/************** 合并原LamModel方法 *******************/

	/**
	 * 更新缓存
	 */
	public function resetCache()
	{
		$this->_resetCache();
	}

	/**
	 * 通过主键值从缓存中删除信息
	 * @param mixed $id 唯一标识值 或者 [字段名=>值]
	 * @param string $prefix key的前缀，默认为取本模型的tableName属性的值，最终key的格式类似 game-66 或 game->lamson
	 * @return array
	 */
	protected function _resetCache($id = 0, $prefix = null)
	{
		// 对某一条记录进行 删、改的操作时，默认只删除该记录的缓存
		$this->cacheInfo($id, null);
		// 自动生成新缓存
		$this->awaCache && $this->cacheInfo($id);
	}

	/**
	 * 从缓存中取出数据，并将extension中的数据合并到主数据
	 * @param int $id
	 * @return array|bool|number
	 */
	public function mergeExt($id = 0)
	{
		$data = $this->_cacheInfo($id);
		isset($data['extension']) && is_array($data['extension']) && $data += $data['extension'];
		unset($data['extension']);
		return $data;
	}

	/**
	 * 通过唯一健值从缓存中获取或设置信息
	 * @param mixed $id 唯一标识值 或者 [字段名=>值]
	 * @param mixed $data 传空代表读取数据（默认）；null代表删除数据;其他有值代表写入
	 * @return array
	 */
	public function cacheInfo($id = 0, $data = '')
	{
		return $this->_cacheInfo($id, null, $data);
	}

	/**
	 * 通过唯一健值从缓存中获取或设置信息
	 * @param mixed $id 唯一标识值 或者 [字段名=>值]
	 * @param string $prefix key的前缀，默认为取本模型的tableName属性的值，最终key的格式类似 game-66 或 game->lamson
	 * @param mixed $data 传空代表读取数据（默认）；null代表删除数据;其他有值代表写入
	 * @return array|bool|number
	 */
	protected function _cacheInfo($id = 0, $prefix = null, $data = '')
	{
		$isarray = is_array($id);
        /* @var $Redis \EasySwoole\Redis\Redis */
		list($Redis, $key, $pk, $id, $condition) = $this->redisAndKey($id, $prefix);

		$con = true;

		// 删除缓存
		if (is_null($data)) {
			return $Redis->del($key); // 返回删除缓存的条数
		} // 读取
		elseif ($data === '') {
			// 先从缓存取
			$con = $Redis->get($key);

			//如果取出的数据是字符串 QUOTE:数字 该数字为表主键
			if (is_string($con) && strpos($con, 'QUOTE:') === 0) {
				//将$key替换为表主键重新获取
				$con = $this->cacheInfo(explode(":", $con)[1]);
			}

			// 没有记录，则尝试从数据表里读取
			if ( ! $con) {
				$con = $this->_getByUnique($pk, $id, $condition);
				$data = &$con; // 随后会写入缓存
			}

			is_scalar($con) && $con = json_decode($con, true);

			isset($con['extension']) && ! is_array($con['extension']) && ($con['extension'] = json_decode($con['extension'], true));
		}

		// 存入缓存
		if ($data !== '' && $data) {
			if ($isarray) {
				$realKey = $this->schemaInfo()->getPkFiledName();
				//将值设为 QUOTE:表主键
				$Redis->set($key, 'QUOTE:' . $data[$realKey], $this->awaCacheExpire);
				$this->cacheInfo($data[$realKey], $data);
			} else {
				$Redis->set($key, is_scalar($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE), $this->awaCacheExpire);
			}
		}

		return is_scalar($con) ? json_decode($con, true) : $con;
	}

	/**
	 * 通过唯一键返回数据
	 * @return array
	 */
	protected function _getByUnique($pk = 'id', $id = 0, $condition = [])
	{
		$data = $this->where([$pk => $id])->get();
		return $data ? $data->toArray() : [];
	}

	/**
	 * 返回redis对象和某条数据的key
	 * @param mixed $id 唯一标识值 或者 [字段名=>唯一值]
	 * @param string $prefix key的前缀，默认为取本模型的tableName属性的值，最终key的格式类似 game-66 或 game->lamson
	 * @return array [redis对象, 某条数据的key]
	 */
	public function redisAndKey($id = 0, $prefix = null)
	{
		$isarray = is_array($id);
		$condition = [];

		$pk = $isarray ? key($id) : $this->schemaInfo()->getPkFiledName(); // 唯一字段名
		is_array($pk) && $pk = $pk[0];

		// [字段名=>唯一值]
		if ($isarray) {
			$condition = $id;
			$id = current($id); // 唯一值
		} // 直接传主键值
		else {
			('int' == $this->pkType) && ($id = (int)$id);
		}

		$id or $id = $this->getAttr($pk) ?? 0; // 无值则尝试从$this->data中取

		// 返回redis句柄资源
		$Redis = defer_redis($this->redisPoolname);

		ksort($condition);

		// 缓存前缀
		$key = (is_null($prefix) ? $this->getTableName() : $prefix)
			. '-'
			. ($isarray ? '>' : '')
			. ($isarray && count($condition) > 1 ? md5(json_encode($condition)) : $id);

		return [$Redis, $key, $pk, $id, $condition];
	}

	/*-------------------------- overwrite --------------------------*/
	/**
	 * todo 此处有坑，若使用 $model->where('xxx')->destroy() 链式调用, 则destroyWhere获取不到值
	 * @param $where
	 * @param $allow
	 * @return bool|int
	 * @throws \EasySwoole\ORM\Exception\Exception
	 * @throws \Throwable
	 */
	public function destroy($where = null, $allow = false)
	{
		$this->destroyWhere = $where;
		return parent::destroy($where, $allow);
	}


	/*-------------------------- 模型事件 --------------------------*/

	public static function onAfterInsert($model, $res)
	{
		$model->_after_write($res);
	}

	public static function onAfterUpdate($model, $res)
	{
		$model->_after_write($res);
	}

	protected function _after_write($res = false)
	{
		// 存入缓存
		if ($res && $this->awaCache) {
			// 此处去掉协程，原因是特殊场景需要复用连接池连接时会有问题，https://wiki.swoole.com/wiki/page/963.html
			$data = $this->getOriginData();
			$pk = $this->schemaInfo()->getPkFiledName();

			// todo 联合主键场景
			is_array($pk) && $pk = $pk[0];
			isset($data[$pk]) && $this->cacheInfo($data[$pk], $this->_getByUnique($pk, $data[$pk]));
		}
	}


	public static function onAfterDelete($model, $res)
	{
		$model->_after_delete($res);
	}

	protected function _after_delete($res)
	{
		$where = &$this->destroyWhere;
		if ($where && $res && $this->awaCache) {
			// 如果条件仅为主键
			if (is_numeric($where) || (is_array($where) && count($where) == 1 && key($where) == $this->schemaInfo()->getPkFiledName())) {
				// 直接取出主键值
				$where = is_numeric($where) ? $where : current($where);
			}
			// 删除缓存
			$this->cacheInfo($where, null);
		}
		$where = [];
	}

	// 开启事务
	public function startTrans()
	{
		DbManager::getInstance()->startTransaction($this->getQueryConnection());
	}

	public function commit()
	{
		DbManager::getInstance()->commit($this->getQueryConnection());

	}

	public function rollback()
	{
		DbManager::getInstance()->rollback($this->getQueryConnection());
	}
}
