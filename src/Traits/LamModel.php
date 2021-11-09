<?php
/**
 * 通用模型组件
 *
 * @author 林坤源
 * @version 1.0.2 最后修改时间 2020年10月21日
 */
namespace Linkunyuan\EsUtility\Traits;

use EasySwoole\ORM\DbManager;

trait LamModel
{
	protected  $_error = [];
	public $pkType = 'int'; // 主键值的数据类型 int|string

	/** 提供给修改器的数据 */
	public $asiData = []; // 解决EasySwoole\ORM\AbstractModel::setAttr的alldata不统一的问题

	/**
	 * 单条记录write操作之后是否自动缓存
	 * @var bool
	 */
	public $awaCache = false; // after write and cache
	public $awaCacheExpire = 7 * 24* 3600; // 单条记录的默认缓存时间

	public $redisPoolname = ''; // redis连接池的标识
	public $redisDb = null; // 默认redis库

	public $destroyWhere = []; // 执行删除数据时的where值

	public $gameid = '';


	public function __construct($data = [], $tabname = '', $gameid = '')
	{
		$this->_initialize();
		$tabname != '' &&  $this->tableName($tabname);
		$this->gameid = $gameid;
		parent::__construct($data);
	}

	// 初始化
	protected function _initialize()
	{
		$this->autoTimeStamp = true;
		$this->createTime = 'instime';
		$this->updateTime = 'updtime';

		$config = $this->_traitCfg();
		foreach ($config as $k => $v)
		{
			$this->$k = $v;
		}
	}

	// 覆盖trait中的属性值
	protected function _traitCfg()
	{
		return [];
	}

	public function getError()
	{
		return $this->_error;
	}

	public function setError($err = [])
	{
		$this->_error = $err;
		return $this;
	}

	public function setAutoTimeStamp($auto = false)
	{
		$this->autoTimeStamp = $auto;
		return $this;
	}

	public function setCreateTime($instime = 'instime')
	{
		$this->createTime = $instime;
		return $this;
	}

	public function setUpdateTime($updtime = 'updtime')
	{
		$this->updateTime = $updtime;
		return $this;
	}

	/**
	 * data中一定要有主键
	 * @access public
	 * @param array $unique 唯一性约束的条件
	 * @return integer|false
	 */
	public function replace($unique = [])
	{
        return $this->data($unique)->duplicate($unique)->save();
	}


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
	protected function _cacheInfo($id = 0,  $prefix = null, $data = '')
	{
		$isarray = is_array($id);
		list($Redis, $key, $pk, $id, $condition) = $this->redisAndKey($id, $prefix);

		$con = true;

		// 删除缓存
		if(is_null($data))
		{
			return $Redis->del($key); // 返回删除缓存的条数
		}
		// 读取
		elseif($data === '')
		{
			// 先从缓存取
			$con = $Redis->get($key);

			//如果取出的数据是字符串 QUOTE:数字 该数字为表主键
			if (is_string($con) && strpos($con, 'QUOTE:') === 0)
			{
				//将$key替换为表主键重新获取
				$con = $this->cacheInfo(explode(":",$con)[1]);
			}

			// 没有记录，则尝试从数据表里读取
			if( ! $con)
			{
				$con = $this->_getByUnique($pk, $id, $condition);
				$data = & $con; // 随后会写入缓存
			}

			is_scalar($con) && $con = json_decode($con, true);

			isset($con['extension']) && ! is_array($con['extension']) && ($con['extension'] = json_decode($con['extension'], true));
		}

		// 存入缓存
		if ($data !== '' && $data)
		{
			if ($isarray)
			{
				$realKey = $this->schemaInfo()->getPkFiledName();
				//将值设为 QUOTE:表主键
				$Redis->set($key, 'QUOTE:' . $data[$realKey], $this->awaCacheExpire);
				$this->cacheInfo($data[$realKey], $data);
			}
			else
			{
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
		$data = $this->where([$pk=>$id])->get();
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
		if($isarray)
		{
			$condition = $id;
			$id = current($id); // 唯一值
		}
		// 直接传主键值
		else
		{
			('int' == $this->pkType) && ($id = (int)$id);
		}

		$id or $id = $this->data[$pk] ?? 0; // 无值则尝试从$this->data中取

		// 返回redis句柄资源
		$Redis = defer_redis($this->redisPoolname, $this->redisDb);

		ksort($condition);

		// 缓存前缀
		$key = (is_null($prefix) ? $this->getTableName() : $prefix)
			. '-'
			. ($isarray ? '>' : '')
			. ($isarray && count($condition)>1 ? md5(json_encode($condition)) : $id);

		return [$Redis, $key, $pk, $id, $condition];
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


	/*-------------------------- 字段获取器 --------------------------*/

	protected function getExtensionAttr($extension = '', $alldata = [])
	{
		return is_array($extension) ? $extension : json_decode($extension, true);
	}

	protected function getIpAttr($ip = '', $alldata = [])
	{
		return is_numeric($ip) ? long2ip($ip) : $ip;
	}

	/*-------------------------- 字段修改器 --------------------------*/

	protected function setInstimeAttr($instime = '', $alldata = [])
	{
		// TODO 本来不应该写成$_POST的，但easywoole的orm的setAttr()方法写得不合理，没有传原始的全部数据进来！！
		if(isset($_POST['instime']))
		{
			return is_numeric($_POST['instime']) ? $_POST['instime'] : strtotime($_POST['instime']);
		}
		return $instime;
	}


	/**
	 * 数据写入前对extension字段的值进行处理
	 *
	 * @access protected
	 * @param array $extension 原数据
	 * @param bool $encode 是否强制编码
	 * @return string 处理后的值
	 */
	protected function setExtensionAttr($extension = [], $alldata = [], $relation = [], $encode = true)
	{
		$extension = $this->_setExtensionAttr($extension, $alldata, $relation, $encode);
		// halt($extension);
		if($extension !== false)
		{
			return $extension;
		}
		return '{}';
	}

	protected function _setExtensionAttr($extension = [], $alldata = [], $relation = [], $encode = true)
	{
		// 确保$extension为数组
		if(is_scalar($extension))
		{
			$extension = json_decode($extension, true);
		}

		settype($extension, 'array');

		// 特殊处理 $extension = ['.....', 'exp'];  注意与ThinkPHP不同的是值在第一位操作符是在第二位！！
		if( ! empty($extension[1]) && $extension[1] == 'exp')
		{
			return ['[F]'=>[$extension[0]]];
		}

		//循环判断$_POST['extension']中的各个键值，若为数字键值，则unset掉
		foreach($extension as $keyext => $vext)
		{
			if(is_numeric($keyext) && $vext != 'exp') {
				unset($extension[$keyext]);
			}
		}

		if(empty($extension) && empty($this->asiData['__extension']))
		{
			return false;
		}

		if( ! empty($this->asiData['__extension']) && $this->asiData['__extension'] != 'N;')
		{
			$__extension = json_decode($this->asiData['__extension'], true);
			is_array($__extension) && $extension = array_merge_multi($__extension, $extension);
		}
		// halt($extension);
		return $encode ? json_encode($extension) : $extension;
	}

	protected function setIpAttr($ip = '', $alldata = [])
	{
		return is_numeric($ip) ? $ip : ip2long($ip);
	}


	/*-------------------------- overwrite --------------------------*/
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
		if($res && $this->awaCache)
		{
		    // 此处去掉协程，原因是特殊场景需要复用连接池连接时会有问题，https://wiki.swoole.com/wiki/page/963.html
            $data = $this->getOriginData();
            $pk = $this->schemaInfo()->getPkFiledName();
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
		$where = & $this->destroyWhere;
		if($where && $res && $this->awaCache)
		{
			// 如果条件仅为主键
			if(is_numeric($where) || (is_array($where) && count($where) == 1 && key($where) == $this->schemaInfo()->getPkFiledName()))
			{
				// 直接取出主键值
				$where = is_numeric($where) ? $where : current($where);
			}
			// 删除缓存
			$this->cacheInfo($where, null);
		}
		$where = [];
	}
}
