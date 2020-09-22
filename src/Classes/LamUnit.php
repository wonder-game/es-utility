<?php
/**
 * 测试类
 */

namespace Linkunyuan\EsUtility\Classes;


use EasySwoole\Http\Request;

class LamUnit
{
	// 将yapi中的通用参数标识符转换为具体的通用参数数组
	static public function utilityParam(Request $request)
	{
		$method = $request->getMethod();
		$params =  $method== 'GET' ?  $request->getQueryParams(): $request->getParsedBody();
		// $_SERVER
		$server = $request->getServerParams();

		// 获取IP
		isset($params['ip']) or $params['ip'] = $server['remote_addr'];

		if($request->getRequestParam($key = '一堆通用参数！！'))
		{
			unset($params[$key]);
			$utility = [
				'gameid' => 0,
				'sdkver' => 'Utility-sdkver',
				'devid' => 'Utility-devid',
				'pkgbnd' => 'com.pkgbnd.Utility',
				'imei' => 'Utility-imei',
				'os' => 0,
				'osver' => '12',
				'exmodel' => 'Utility-Huawei P40',
			];

			$params += $utility;
		}

		$method== 'GET' ? $request->withQueryParams($params) : $request->withParsedBody($params);

	}
}
