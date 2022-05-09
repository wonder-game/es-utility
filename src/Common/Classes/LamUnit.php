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
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\Http\GlobalParam\Hook;
use EasySwoole\Http\GlobalParamHook;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\I18N\I18N;
use EasySwoole\ORM\DbManager;
use WonderGame\EsNotify\EsNotify;

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
		if ($globalParamHook instanceof Hook) {
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
		if (stripos($lang, 'zh') !== false) {
			if (stripos($lang, 'tw') !== false || stripos($lang, 'hk') !== false) {
				I18N::getInstance()->setLanguage('Tw');
			} else {
				I18N::getInstance()->setLanguage('Cn');
			}
		} elseif (stripos($lang, 'en') !== false) {
			I18N::getInstance()->setLanguage('En');
		}
	}

    public static function registerI18n()
    {
        $languages = config('LANGUAGES') ?: [];
        foreach ($languages as $lang => $language)
        {
            $className = $language['class'];
            if ( ! class_exists($className)) {
                continue;
            }
            I18N::getInstance()->addLanguage(new $className(), $lang);
            if (isset($language['default']) && $language['default'] === true) {
                I18N::getInstance()->setDefaultLanguage($lang);
            }
        }
        if (($iniLang = get_cfg_var('env.language')) && in_array($iniLang, array_keys($languages))) {
            I18N::getInstance()->setDefaultLanguage($iniLang);
        }
    }

    public static function setI18n(Request $request, $headerKey = 'accept-language')
    {
        if ($request->hasHeader($headerKey)) {
            $langage = $request->getHeader($headerKey);
            if (is_array($langage)) {
                $langage = current($langage);
            }
            $languages = config('LANGUAGES') ?: [];
            foreach ($languages as $lang => $value) {
                if(preg_match($value['match'], $langage)) {
                    I18N::getInstance()->setLanguage($lang);
                    break;
                }
            }
        }
    }

	// 将yapi中的通用参数标识符转换为具体的通用参数数组
	static public function utilityParam(Request $request, $key = '一堆通用参数！！')
	{
		// 获取IP
		$utility['ip'] = ip($request);

		if ($comval = $request->getRequestParam($key)) {
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
		if ( ! $request->getRequestParam('dtorid')) {
			$utility['dtorid'] = $request->getRequestParam('os') == 1 ? 3 : 4;
		}

		// 包序号（版本序号）
		if ( ! $request->getRequestParam('versioncode')) {
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
		$params = $method == 'GET' ? $request->getQueryParams() : $request->getParsedBody();
		if (is_array($array)) {
			if ($merge) {
				$params = $array + $params;
			} else {
				$params += $array;
			}
		}

		if ($unset) {
			is_array($unset) or $unset = explode(',', $unset);
			foreach ($unset as $v) {
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

		$envkeydata = $envkeydata ?: [];
		// 下文环境中可以通过 $field 这个量的值来判断是否解密成功
		$envkeydata[$field] = (bool)$envkeydata;

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

	/**
	 * 注册Crontab
	 * @return void
	 */
	public static function registerCrontab()
	{
		$Crontab = \EasySwoole\EasySwoole\Crontab\Crontab::getInstance();
		$Crontab->addTask(\WonderGame\EsUtility\Crontab\Crontab::class);
	}

	/**
	 * 注册自定义进程
	 * @param array $jobs
	 * @param array $config
	 * @return void
	 */
	public static function registerConsumer(array $jobs, array $config = [])
	{
		$group = config('SERVER_NAME') . '.my';
		foreach ($jobs as $value) {

			$proName = $group . '.' . $value['name'];

			$class = $value['class'];
			if (empty($class) || ! class_exists($class)) {
				continue;
			}
			$psnum = intval($value['psnum'] ?? 1);

			for ($i = 0; $i < $psnum; ++$i) {
				$cfg = array_merge([
					'processName' => $proName . '.' . $i,
					'processGroup' => $group,
					'arg' => $value,
					'enableCoroutine' => true,
				], $config);
				$processConfig = new \EasySwoole\Component\Process\Config($cfg);
				\EasySwoole\Component\Process\Manager::getInstance()->addProcess(new $class($processConfig));
			}
		}
	}

	public static function registerNotify()
	{
		if ($esNotify = config('ES_NOTIFY')) {
			foreach ($esNotify as $name => $cfg) {
				EsNotify::getInstance()->register($cfg, $name);
			}
		}
	}

    public static function registerWebSocketServer(EventRegister $register, array $events = [])
    {
        $config = new \EasySwoole\Socket\Config();
        $config->setType(\EasySwoole\Socket\Config::WEB_SOCKET);
        $config->setParser(new \WonderGame\EsUtility\WebSocket\Parser());

        $dispatch = new \EasySwoole\Socket\Dispatcher($config);
        $register->set(
            EventRegister::onMessage,
            function (\Swoole\Websocket\Server $server, \Swoole\WebSocket\Frame $frame) use ($dispatch) {
                $dispatch->dispatch($server, $frame->data, $frame);
            }
        );
        if ($events) {
            foreach ($events as $event => $item) {
                $register->add($event, $item);
            }
        }
    }

    public static function dbQueryCall(\EasySwoole\ORM\Db\Result $result, \EasySwoole\Mysqli\QueryBuilder $builder)
    {
        $sql = $builder->getLastQuery();
        if (empty($sql))
        {
            return;
        }
        trace($sql, 'info', 'sql');

        // 不记录的SQL，表名
        $logtable = config('NOT_WRITE_SQL.table');
        foreach($logtable as $v)
        {
            if (
                strpos($sql, "`$v`")
                ||
                // 支持  XXX*这种模糊匹配
                (strpos($v, '*') && strpos($sql, '`' . str_replace('*', '', $v)))
            )
            {
                return;
            }
        }
        // 不记录的SQL，正则
        $not = config('NOT_WRITE_SQL.pattern');
        foreach ($not as $pattern)
        {
            if (preg_match($pattern, $sql))
            {
                return;
            }
        }

        /** @var \App\Model\Log $Log */
        $Log = model('Log');
        $Log->sqlWriteLog($sql);
    }

    public static function eventInitialize()
    {
        // 注册异常处理器
        \EasySwoole\EasySwoole\Trigger::getInstance(new \WonderGame\EsUtility\Common\Classes\ExceptionTrigger());

        Config::getInstance()->loadDir(EASYSWOOLE_ROOT . '/App/Common/Config');

        // mysql连接池
        $mysqlCfg = config('MYSQL');
        foreach ($mysqlCfg as $mname => $mvalue)
        {
            $MysqlConfig = new \EasySwoole\ORM\Db\Config($mvalue);
            DbManager::getInstance()->addConnection(new \EasySwoole\ORM\Db\Connection($MysqlConfig), $mname);
        }

        DbManager::getInstance()->onQuery(function (
            \EasySwoole\ORM\Db\Result $result,
            \EasySwoole\Mysqli\QueryBuilder $builder,
            $start) {
            LamUnit::dbQueryCall($result, $builder);
        });

        //redis连接池注册
        $redisCfg = config('REDIS');
        foreach ($redisCfg as $rname => $rvalue)
        {
            $RedisConfig = new \EasySwoole\Redis\Config\RedisConfig($rvalue);
            \EasySwoole\RedisPool\RedisPool::getInstance()->register($RedisConfig, $rname);
        }

        // 全局onRequest回调
        \EasySwoole\Component\Di::getInstance()->set(
            \EasySwoole\EasySwoole\SysConst::HTTP_GLOBAL_ON_REQUEST,
            function (Request $request, Response $response) {
                // 自定义协程单例Request
                CtxRequest::getInstance()->request = $request;

                LamUnit::setI18n($request);
                return true;
            }
        );

        LamUnit::registerI18n();
    }

    public static function eventMainServerCreate(EventRegister $register)
    {
        if (!is_env('test'))
        {
            LamUnit::registerCrontab();
        }

        // 热重载
        LamUnit::hotReload();

        LamUnit::registerNotify();
    }
}
