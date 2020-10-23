<?php
namespace Linkunyuan\EsUtility;

use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;

if ( ! function_exists('Linkunyuan\EsUtility\model')) {
	/**
	 * 实例化Model
	 * @param string    $name Model名称
	 * @return \App\Model\$model
	 */
	function model($name = '', $data = [])
	{
		//static $instance = [];
		$guid = $name = parse_name($name, 1);

//		if (isset($instance[$guid])) {
//			return $instance[$guid];
//		}

		$gameid = '';
		// 实例化XXX_gid模型
		if(strpos($name, ':'))
		{
			list($name, $gameid) = explode(':', $name);
		}

		$class = "\\App\\Model\\$name";
		$model = null;

		if (class_exists($class)) {
			try{
				$model = new $class($data, $gameid != '' ? parse_name($name,0, false) . "_$gameid" : '');
			}
			catch (\Exception $e)
			{
				// TODO
				var_dump($e->getMessage());
			}
		} else {
			// TODO
			//throw new \Exception("模型不存在:$class");
		}
		//$instance[$guid] = $model;
		return $model;
	}
}

if ( ! function_exists('Linkunyuan\EsUtility\config')) {
	/**
	 * 获取和设置配置参数
	 * @param string|array  $name 参数名
	 * @param mixed         $value 参数值
	 * @return mixed
	 */
	function config($name = '', $value = null)
	{
		$Config = Config::getInstance();
		if (is_null($value) && is_string($name))
		{
			return $Config->getConf($name);
		} else {
			return $Config->setConf($name, $value);
		}
	}
}

if ( ! function_exists('Linkunyuan\EsUtility\trace')) {
	/**
	 * 记录日志信息
	 * @param string|array $log log信息 支持字符串和数组
	 * @param string $level 日志级别
	 * @param string $category 日志类型
	 * @return void
	 */
	function trace($log = '', $level = 'info', $category = 'debug')
	{
		is_scalar($log) or $log = json_encode($log, JSON_UNESCAPED_UNICODE);
		Logger::getInstance()->$level($log, $category);//记录error级别日志并输出到控制台
	}
}

if ( ! function_exists('Linkunyuan\EsUtility\parse_name')) {
	/**
	 * 字符串命名风格转换
	 * @param string $name 字符串
	 * @param integer $type 转换类型  0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
	 * @param bool $ucfirst 首字母是否大写（驼峰规则）
	 * @return string
	 */
	function parse_name($name, $type = 0, $ucfirst = true)
	{
		if ($type) {
			$name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
				return strtoupper($match[1]);
			}, $name);
			return $ucfirst ? ucfirst($name) : lcfirst($name);
		} else {
			return strtolower(trim(preg_replace('/[A-Z]/', '_\\0', $name), '_'));
		}
	}
}


if ( ! function_exists('Linkunyuan\EsUtility\array_merge_multi')) {
	/**
	 * 多维数组合并（支持多数组）
	 * @return array
	 */
	function array_merge_multi (...$args)
	{
		$array = [];
		foreach ( $args as $arg )
		{
			if ( is_array($arg) )
			{
				foreach ( $arg as $k => $v )
				{
					if ( is_array($v) )
					{
						$array[$k] = isset($array[$k]) ? $array[$k] : [];
						$array[$k] = array_merge_multi($array[$k], $v);
					} else {
						$array[$k] = $v;
					}
				}
			}
		}
		return $array;
	}
}


