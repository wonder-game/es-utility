<?php
/**
 * 测试类
 *
 * @author 林坤源
 * @version 1.0.2 最后修改时间 2020年10月21日
 */

namespace Linkunyuan\EsUtility\Classes;


use EasySwoole\Http\GlobalParamHook;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Http\GlobalParam\Hook;
use EasySwoole\I18N\I18N;

class LamUnit
{
	// 收到HTTP请求时触发处理
	static public function onRequest(Request $request, Response $response, Hook $globalParamHook = null)
	{
		// 将yapi中的通用参数标识符转换为具体的通用参数数组
		self::utilityParam($request);

		// 解密
		self::decrypt($request);

        /* 替换全局变量（将值写入$_GET,$_POST,$_COOKIE... ） */
        if ($globalParamHook instanceof Hook)
        {
            // easyswoole 3.3的写法
            //GlobalParamHook::getInstance()->onRequest($request, $response);
            // easyswoole 3.4的写法
            $globalParamHook->onRequest($request, $response);
        }

        // 上面这一行之后才开始可以使用$_GET->toArray(),  $_POST->toArray()   ...，不过仍建议尽量少使用这种php-fpm风格的写法！！！    可能$_SERVER->toArray()会例外，因为格式变异太大


		// 设置默认语言
		// print_r($_SERVER);  // HTTP_ACCEPT_LANGUAGE => zh-CN,zh;q=0.9
        // $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

        // print_r($request->getHeaders());     // 'accept-language' => [0=>'zh-CN,zh;q=0.9']
        $lang = $request->getHeader('accept-language')[0];

		if(stripos($lang, 'zh') !== false)
		{
			if(stripos($lang, 'tw') !== false || stripos($lang, 'hk') !== false)
			{
				I18N::getInstance()->setLanguage('Tw');
			}
		}
		elseif(stripos($lang, 'en') !== false)
		{
			I18N::getInstance()->setLanguage('En');
		}
	}

	// 将yapi中的通用参数标识符转换为具体的通用参数数组
	static public function utilityParam(Request $request, $key = '一堆通用参数！！')
	{
		// 获取IP
		$utility['ip'] = ip($request);

		if($comval = $request->getRequestParam($key))
		{
			$comval = json_decode($comval, true);
			$utility += [
				'gameid' => 0,
				'sdkver' => 'Utility-sdkver',
				'devid' => 'Utility-devid',
				'pkgbnd' => 'com.pkgbnd.Utility',
				'imei' => 'Utility-imei',
				'os' => 0,
				'osver' => '12',
				'exmodel' => 'Utility-Huawei P40',
				'creqtime' => time()
			];

			is_array($comval) && $utility = array_merge($utility, $comval);
		}

		// 销售渠道
		if( ! $request->getRequestParam('dtorid'))
		{
			$utility['dtorid'] = $request->getRequestParam('os') == 1 ? 3 : 4;
		}

		// 包序号（版本序号）
		if( ! $request->getRequestParam('versioncode'))
		{
			$utility['versioncode'] = 1;
		}

		self::withParams($request, $utility, false, $key);
	}

	/**
	 * @param Request $request
	 * @param array $array 要合并的数据
	 * @param bool $merge 是否覆盖掉原参数的值
	 * @param string|array $unset 要删除的量
	 */
	static public function withParams(Request $request, $array = [], $merge = true, $unset = '')
	{
		$method = $request->getMethod();
		// $_GET or $_POST
		$params =  $method == 'GET' ?  $request->getQueryParams(): $request->getParsedBody();
		if(is_array($array))
		{
			if($merge)
			{
				$params = $array + $params;
			}else
			{
				$params += $array;
			}
		}

		if($unset)
		{
			is_array($unset) or $unset = explode(',', $unset);
			foreach($unset as $v)
			{
				unset($params[$v]);
			}
		}

		$method == 'GET' ? $request->withQueryParams($params) : $request->withParsedBody($params);
	}

	// 解密
	static public function decrypt(Request $request, $field = 'envkeydata')
	{
		$cipher = $request->getRequestParam($field);
		$envkeydata = LamOpenssl::getInstance()->decrypt($cipher);
		$array = json_decode($envkeydata, true);
		($array && $envkeydata = $array) or parse_str($envkeydata, $envkeydata);

		$envkeydata = $envkeydata ? : [];
		// 下文环境中可以通过 $field 这个量的值来判断是否解密成功
		$envkeydata[$field] = (bool) $envkeydata;

		self::withParams($request, $envkeydata, true);

		return $envkeydata;
	}
}
