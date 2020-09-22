<?php
/**
 * 通用模型组件
 *
 * @author 林坤源
 * @version 1.0.0 最后修改时间 2020年08月14日
 */
namespace Linkunyuan\EsUtility\Traits;

trait LamModel
{
	protected  $_error = [];

	public function __construct()
	{
		$this->_initialize();
		parent::__construct();
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

	/**
	 * 通过唯一健值从缓存中获取或设置信息
	 * @param mixed $id 唯一标识值 或者 [字段名=>值]
	 * @param mixed $data 传空代表读取数据（默认）；null代表删除数据;其他有值代表写入
	 * @return array
	 */
	public function cacheInfo($id = 0, $data = '')
	{
		return $this->_cacheInfo($id, null, null, null, $data);
	}

	/**
	 * 通过唯一健值从缓存中获取或设置信息
	 * @param mixed $id 唯一标识值 或者 [字段名=>值]
	 * @param int $dbnum 要选择的redis库编号
	 * @param array $options redis其它配置
	 * @param string $prefix key的前缀，默认为取本模型的name属性，最终key的格式类似 库名.Game-66
	 * @param mixed $data 传空代表读取数据（默认）；null代表删除数据;其他有值代表写入
	 * @return array|bool|number
	 */
	protected function _cacheInfo($id = 0, $dbnum = null, $options = null, $prefix = null, $data = '')
	{
		$isarray = is_array($id);
		list($Redis, $key, $pk, $id) = $this->redisAndKey($id, $dbnum, $options, $prefix);

		$con = true;

		// 删除缓存
		if(is_null($data))
		{
			return $Redis->rm($key); // 返回删除缓存的条数
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
				$con = $this->_getByUnique($pk, $id);
				$data = & $con; // 随后会写入缓存
			}

			isset($con['extend']) && ! is_array($con['extend']) && $con['extend'] = json_decode($con['extend'], true);
		}

		// 存入缓存
		if ($data !== '' && $data)
		{
			if ($isarray)
			{
				$realKey = $this->getPk();
				//将值设为 QUOTE:表主键
				$Redis->set($key, 'QUOTE:' . $data[$realKey], $this->awaCacheExpire);
			}
			else
			{
				$Redis->set($key, $data, $this->awaCacheExpire);
			}
		}

		return $con;
	}

	/**
	 * 通过唯一键返回数据
	 * @return array
	 */
	protected function _getByUnique($pk = 'id', $id = 0)
	{
		$data = $this->where("$pk='$id'")->find();
		return $data ? $data->toArray() : [];
	}

	/**
	 * 返回redis对象和某条数据的key
	 * @param mixed $id 唯一标识值 或者 [字段名=>唯一值]
	 * @param int $dbnum 要选择的redis库编号
	 * @param array $options redis配置
	 * @param string $prefix key的前缀，默认为取本模型的name属性，最终key的格式类似 Game-66
	 * @return array [redis对象, 某条数据的key]
	 */
	public function redisAndKey($id = 0, $dbnum = null, $options = null, $prefix = null)
	{
		$pk = is_array($id) ? key($id) : $this->getPk(); // 唯一字段名
		is_array($pk) && $pk = $pk[0];

		// [字段名=>唯一值]
		if(is_array($id))
		{
			$id = current($id); // 唯一值
		}
		// 直接传主键值
		else
		{
			('int' == $this->pkType) && ($id = (int)$id);
		}

		$id or $id = $this->data[$pk] ?? 0; // 无值则尝试从$this->data中取

		// 缓存对象
		$options = is_array($options) ? $options : config('cache');
		is_null($dbnum) && $dbnum = $this->redisDb;
		is_numeric($dbnum) && $options['select'] = $dbnum; // 切换到指定序号
		$Redis = Cache::connect($options);

		// 缓存前缀
		$key = (is_null($prefix) ? ($this->db()->getConfig())['database'] . '.' . $this->name : $prefix) . '-' . $id;

		return [$Redis, $key, $pk, $id];
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

	/**
	 * 数据写入前对extend字段的值进行处理
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
		return false;
	}

	protected function _setExtensionAttr($extension = [], $alldata = [], $relation = [], $encode = true)
	{
		// 确保$extension为数组
		if(is_scalar($extension))
		{
			$extension = json_decode($extension, true);
		}

		//$arr = input('extend/a', []);
		settype($extension, 'array');
		//$extension = array_merge_multi($extension, $arr);

		// 特殊处理 $extension = ['exp', '.....'];
		if( ! empty($extension[0]) && $extension[0] == 'exp')
		{
			return $extension;
		}


		//循环判断$_POST['extend']中的各个键值，若为数字键值，则unset掉
		foreach($extension as $keyext => $vext)
		{
			if(is_numeric($keyext) && $vext != 'exp') {
				unset($extension[$keyext]);
			}
		}

		if(empty($extension) && empty($alldata['__extend']))
		{
			return false;
		}

		// print_r($extension);
		if( ! empty($alldata['__extend']) && $alldata['__extend'] != 'N;')
		{
			$__extend = json_decode($alldata['__extend'], true);
			is_array($__extend) && $extension = array_merge_multi($__extend, $extension);
		}
		// halt($extension);
		return $encode ? json_encode($extension) : $extension;
	}

	protected function setIpAttr($ip = '', $alldata = [])
	{
		return is_numeric($ip) ? $ip : ip2long($ip);
	}
}
