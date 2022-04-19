<?php
/**
 * 测试类
 *
 * @author 林坤源
 * @version 1.0.2 最后修改时间 2020年10月21日
 */

namespace WonderGame\EsUtility\Common\Classes;


use EasySwoole\Command\Color;
use EasySwoole\EasySwoole\Command\Utility;
use EasySwoole\EasySwoole\ServerManager;
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

        /**************** todo 多语言是在App实现的，最好不放在公共部分, key最好能够枚举而不是字符串 *******************/
		if(stripos($lang, 'zh') !== false)
		{
			if(stripos($lang, 'tw') !== false || stripos($lang, 'hk') !== false)
			{
				I18N::getInstance()->setLanguage('Tw');
			} else {
                I18N::getInstance()->setLanguage('Cn');
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

    public static function hotReload()
    {
        // 只允许在开发环境运行
        if (is_env('dev')) {
            $watchConfig = config('HOT_RELOAD_DIR') ?: [EASYSWOOLE_ROOT . '/App'];

            $watcher = new \EasySwoole\FileWatcher\FileWatcher();
            // // 设置监控规则和监控目录
            foreach ($watchConfig as $dir) {
                $watcher->addRule(new \EasySwoole\FileWatcher\WatchRule($dir));
            }

            $watcher->setOnChange(function (array $list) {
                echo PHP_EOL . PHP_EOL . Color::warning(' Worker进程重启，检测到以下文件变更: ') . PHP_EOL;

                foreach ($list as $item) {
                    $scanType = is_file($item) ? 'file' : (is_dir($item) ? 'dir' : '未知');
                    echo Utility::displayItem("[$scanType]", $item) . PHP_EOL;
                }
                $Server = ServerManager::getInstance()->getSwooleServer();

                // worker进程reload不会触发客户端的断线重连，但是原来的fd已经不可用了
                foreach ($Server->connections as $fd) {
                    // 不要在 close 之后写清理逻辑。应当放置到 onClose 回调中处理
                    $Server->close($fd);
                }

                $Server->reload();

                echo Color::success('Worker进程启动成功 ') . PHP_EOL;
                echo Color::red('请自行区分 Master 和 Worker 程序 !!!!!!!!!!') . PHP_EOL . PHP_EOL;
            });

            $watcher->setOnException(function (\Throwable $throwable) {

                echo PHP_EOL . Color::danger('Worker进程重启失败: ') . PHP_EOL;
                echo Utility::displayItem("[message]", $throwable->getMessage()) . PHP_EOL;
                echo Utility::displayItem("[file]", $throwable->getFile() . ', 第 ' . $throwable->getLine() . ' 行') . PHP_EOL;

                echo Color::warning('trace:') . PHP_EOL;
                if ($trace = $throwable->getTrace()) {
                    // 简单打印就行
                    var_dump($trace);
//                    foreach ($trace as $key => $item)
//                    {
//                        echo Utility::displayItem("$key-----------------------", $item) . PHP_EOL;
//                        foreach ($item as $ik => $iv)
//                        {
//                            echo Utility::displayItem("[$ik]", $iv) . PHP_EOL;
//                        }
//                        echo Utility::displayItem("$key-----------------------", $item) . PHP_EOL;
//                    }
                }
            });
            $watcher->attachServer(ServerManager::getInstance()->getSwooleServer());
        }
    }
}
