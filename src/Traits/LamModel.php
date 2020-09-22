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
