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
		// $_GET or $_POST
		$params =  $method== 'GET' ?  $request->getQueryParams(): $request->getParsedBody();
		// $_SERVER
		$server = $request->getServerParams();

		// 获取IP
		isset($params['ip']) or $params['ip'] = $server['remote_addr'];

		if($comval = $request->getRequestParam($key = '一堆通用参数！！'))
		{
			$comval = json_decode($comval, true);
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

			is_array($comval) && $utility = array_merge($utility, $comval);

			unset($params[$key]);
			$params += $utility;
		}

		$method== 'GET' ? $request->withQueryParams($params) : $request->withParsedBody($params);

	}
}
