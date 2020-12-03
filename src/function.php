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
	 * @return void|bool
	 */
	function trace($log = '', $level = 'info', $category = 'debug')
	{
		is_scalar($log) or $log = json_encode($log, JSON_UNESCAPED_UNICODE);
		return Logger::getInstance()->$level($log, $category);
	}
}

if ( ! function_exists('Linkunyuan\EsUtility\defer_redis')) {
	/**
	 * 返回redis句柄资源
	 * @param string $poolname 标识
	 * @param number $db 数据库编号
	 * @return \EasySwoole\Redis\Redis
	 */
	function defer_redis($poolname = '', $db = null)
	{
		$poolname = $poolname ? : config('REDIS.poolname');
		$db = is_numeric($db) ? $db : config('REDIS.db');
		// defer方式获取连接
		$Redis  = \EasySwoole\RedisPool\Redis::defer($poolname);
		$Redis->select($db); // 切换到指定序号
		return $Redis;
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

if ( ! function_exists('Linkunyuan\EsUtility\listdate')) {
	/**
	 * 返回两个日期之间的具体日期或月份
	 *
	 * @param string|int $beginday 开始日期，格式为Ymd或者Y-m-d
	 * @param string|int $endday 结束日期，格式为Ymd或者Y-m-d
	 * @param int $type 类型 1：日； 2：月； 3：季； 4：年
	 * @return array
	 */
	function listdate($beginday, $endday, $type = 2)
	{
		$dif = difdate($beginday, $endday, $type != 2);

		// 季
		if($type == 3)
		{
			// 开始的年份, 结束的年份
			$arry = [date('Y', strtotime($beginday)), date('Y', strtotime($endday))];
			// 开始的月份, 结束的月份
			$arrm = [date('m', strtotime($beginday)), date('m', strtotime($endday))];
			$arrym = [];

			$quarter = ['04','07',10,'01'];
			$come = false; // 入栈的标识
			$by = $arry[0]; // 开始的年份
			do
			{
				foreach($quarter as $k => $v)
				{
					if($arrm[0] < $v || $k == 3)
					{
						$come = true;
					}

					$key = substr($by, 2) . str_pad($k+1, 2, '0', STR_PAD_LEFT);

					// 下一年度
					if($k == 3)
					{
						++$by;
					}

					if($come)
					{
						$arr[$key] = $by . $v . '01'; // p1803=>strtotime(20181001)
					}
				}
			}while($by<=$arry[1]);
		}
		// 年
		elseif($type == 4)
		{
			$begintime = substr($beginday, 0, 4);
			for($i = 0; $i<=$dif; ++$i)
			{
				$arr[$begintime-1] = $begintime . '0101'; // p2018=>strtotime(20190101)
				++$begintime;
			}
		}
		else
		{
			// 日期 p180302=>strtotime(20180304)
			if($type === true || $type == 1)
			{
				$format = 'Y-m-d';
				$unit = 'day';
				$d = '';
			}
			// 月份 p1803=>strtotime(20180401)
			elseif($type === false || $type == 2)
			{
				$format = 'Y-m';
				$unit = 'month';
				$d = '01';
			}

			$begintime = strtotime(date($format, strtotime($beginday)));
			for($i = 0; $i<=$dif; ++$i)
			{
				$key = strtotime("+$i $unit" , $begintime);
				$format = str_replace('-','', $format);
				$arr[date(strtolower($format), $key-3600*24)] = date(ucfirst($format), $key) . $d;
			}
		}
		return $arr;
	}
}


if ( ! function_exists('Linkunyuan\EsUtility\difdate')) {
	/**
	 * 计算两个日期相差多少天或多少月
	 */
	function difdate($beginday, $endday, $d = false)
	{
		$beginstamp = strtotime($beginday);
		$endstamp = strtotime($endday);

		// 相差多少个月
		if( ! $d)
		{
			list($date_1['y'], $date_1['m']) = explode('-', date('Y-m', $beginstamp));
			list($date_2['y'], $date_2['m']) = explode('-', date('Y-m', $endstamp));
			return ($date_2['y'] - $date_1['y']) * 12 + $date_2['m'] - $date_1['m'];
		}

		// 相差多少天
		return ceil( ($endstamp-$beginstamp) / (3600*24) );
	}
}






