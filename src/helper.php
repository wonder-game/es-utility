<?php

if ( ! function_exists('model')) {
	/**
	 * 实例化Model
	 * @param string    $name Model名称
	 * @return \App\Model\$model
	 */
	function model($name = '')
	{
		return \Linkunyuan\EsUtility\model($name);
	}
}

if ( ! function_exists('config')) {
	/**
	 * 获取和设置配置参数
	 * @param string|array  $name 参数名
	 * @param mixed         $value 参数值
	 * @return mixed
	 */
	function config($name = '', $value = null)
	{
		return \Linkunyuan\EsUtility\config($name, $value);
	}
}

if ( ! function_exists('trace')) {
	/**
	 * 记录日志信息
	 * @param string|array $log log信息 支持字符串和数组
	 * @param string $level 日志级别
	 * @param string $category 日志类型
	 * @return void|bool
	 */
	function trace($log = '', $level = 'info', $category = 'debug')
	{
		return \Linkunyuan\EsUtility\trace($log, $level, $category);
	}
}

if ( ! function_exists('defer_redis')) {
	/**
	 * 返回redis句柄资源
	 * @param string $poolname 标识
	 * @param number $db 数据库编号
	 * @return \EasySwoole\Redis\Redis
	 */
	function defer_redis($poolname = '', $db = null)
	{
		return \Linkunyuan\EsUtility\defer_redis($poolname, $db);
	}
}


if ( ! function_exists('parse_name')) {
	/**
	 * 字符串命名风格转换
	 * @param string $name 字符串
	 * @param integer $type 转换类型  0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
	 * @param bool $ucfirst 首字母是否大写（驼峰规则）
	 * @return string
	 */
	function parse_name($name, $type = 0, $ucfirst = true)
	{
		return \Linkunyuan\EsUtility\parse_name($name, $type, $ucfirst);
	}
}

if ( ! function_exists('array_merge_multi')) {
	/**
	 * 多维数组合并（支持多数组）
	 * @return array
	 */
	function array_merge_multi (...$args)
	{
		return \Linkunyuan\EsUtility\array_merge_multi(...$args);
	}
}

if ( ! function_exists('listdate')) {
	/**
	 * 返回两个日期之间的具体日期或月份
	 *
	 * @param string|int $beginday 开始日期，格式为Ymd或者Y-m-d
	 * @param string|int $endday 结束日期，格式为Ymd或者Y-m-d
	 * @param int $type 类型 1：日； 2：月； 3：季； 4：年
	 * @return array
	 */
	function listdate ($beginday, $endday, $type = 2)
	{
		return \Linkunyuan\EsUtility\listdate($beginday, $endday, $type);
	}
}

if ( ! function_exists('difdate')) {
	/**
	 * 计算两个日期相差多少天或多少月
	 */
	function difdate ($beginday, $endday, $d = false)
	{
		return \Linkunyuan\EsUtility\difdate($beginday, $endday, $d);
	}
}

if ( ! function_exists('verify_token')) {
	/**
	 * 验证jwt并读取用户信息
	 */
	function verify_token ($orgs = [], $header = [], $key = 'uid')
	{
		return \Linkunyuan\EsUtility\verify_token($orgs, $header, $key);
	}
}

if ( ! function_exists('ip')) {
	/**
	 * 验证jwt并读取用户信息
	 */
	function ip ($request)
	{
		return \Linkunyuan\EsUtility\ip($request);
	}
}

if ( ! function_exists('lang')) {
	function lang($const = '')
	{
		return \Linkunyuan\EsUtility\lang($const);
	}
}
