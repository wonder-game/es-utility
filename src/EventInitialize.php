<?php

namespace WonderGame\EsUtility;

use EasySwoole\EasySwoole\Config;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\I18N\I18N;
use EasySwoole\ORM\DbManager;
use EasySwoole\Spl\SplBean;
use EasySwoole\Trigger\TriggerInterface;
use WonderGame\EsUtility\Common\Classes\CtxRequest;
use WonderGame\EsUtility\Common\Classes\ExceptionTrigger;
use WonderGame\EsUtility\Common\Classes\LamUnit;

class EventInitialize extends SplBean
{
    /**
     * @var TriggerInterface
     */
    protected $ExceptionTrigger = null;

    /**
     * @var string[]
     */
    protected $configDir = null;

    /**
     * @var array
     */
    protected $mysqlConfig = null;

    protected $redisConfig = null;

    protected $mysqlOnQueryOpen = null;
    protected $mysqlOnQueryFunc = [
        '_before_func' => null, // 前置
        '_save_sql' => null, // 自定义保存
        '_after_func' => null, // 后置
    ];

    protected $languageConfig = null;

    protected $httpOnRequestOpen = null;
    protected $httpOnRequestFunc = [
        '_before_func' => null, // 前置
        '_after_func' => null, // 后置
    ];

    /**
     * 设置属性默认值
     * @return void
     */
    protected function initialize(): void
    {
        if ( is_null($this->ExceptionTrigger)) {
            $this->ExceptionTrigger = ExceptionTrigger::class;
        }
        if (is_null($this->configDir)) {
            $this->configDir = [EASYSWOOLE_ROOT . '/App/Common/Config'];
        }
        if (is_string($this->configDir)) {
            $this->configDir = [$this->configDir];
        }
        if (is_null($this->mysqlConfig)) {
            $this->mysqlConfig = config('MYSQL');
        }
        if (is_null($this->redisConfig)) {
            $this->redisConfig = config('REDIS');
        }
        if (is_null($this->mysqlOnQueryOpen)) {
            $this->mysqlOnQueryOpen = true;
        }
        if (is_null($this->languageConfig)) {
            $this->languageConfig = config('LANGUAGES') ?: [];
        }
        if (is_null($this->httpOnRequestOpen)) {
            $this->httpOnRequestOpen = true;
        }
    }

    public function run()
    {
        $this->registerConfig();
        $this->registerExceptionTrigger();
        $this->registerMysqlPool();
        $this->registerRedisPool();
        $this->registerMysqlOnQuery();
        $this->registerI18n();
        $this->registerHttpOnRequest();
    }

    /**
     * 注册异常处理器
     * @return void
     */
    protected function registerExceptionTrigger()
    {
        if ($this->ExceptionTrigger && class_exists($this->ExceptionTrigger)) {
            $className = $this->ExceptionTrigger;
            $class = new $className();
            \EasySwoole\EasySwoole\Trigger::getInstance($class);
        }
    }

    /**
     * 加载项目配置
     * @return void
     */
    protected function registerConfig()
    {
        $dirs = $this->configDir;
        if ( ! is_array($dirs)) {
            return;
        }
        foreach ($dirs as $dir) {
            Config::getInstance()->loadDir($dir);
        }
    }

    /**
     * 注册MySQL连接池
     * @return void
     */
    protected function registerMysqlPool()
    {
        $config = $this->mysqlConfig;
        if ( ! is_array($config)) {
            return;
        }
        foreach ($config as $mname => $mvalue)
        {
            DbManager::getInstance()->addConnection(
                new \EasySwoole\ORM\Db\Connection(new \EasySwoole\ORM\Db\Config($mvalue)),
                $mname
            );
        }
    }

    /**
     * 注册Redis连接池
     * @return void
     * @throws \EasySwoole\RedisPool\Exception\Exception
     * @throws \EasySwoole\RedisPool\RedisPoolException
     */
    protected function registerRedisPool()
    {
        $config = $this->redisConfig;
        if ( ! is_array($config)) {
            return;
        }
        foreach ($config as $rname => $rvalue)
        {
            \EasySwoole\RedisPool\RedisPool::getInstance()->register(
                new \EasySwoole\Redis\Config\RedisConfig($rvalue),
                $rname
            );
        }
    }

    /**
     * 注册MySQL全局OnQuery回调
     * @return void
     */
    protected function registerMysqlOnQuery()
    {
        if ( ! $this->mysqlOnQueryOpen) {
            return;
        }
        DbManager::getInstance()->onQuery(
            function (\EasySwoole\ORM\Db\Result $result, \EasySwoole\Mysqli\QueryBuilder $builder, $start) {
                // 前置
                if (is_callable($this->mysqlOnQueryFunc['_before_func'])) {
                    // 返回false不继续运行
                    if ($this->mysqlOnQueryFunc['_before_func']($result, $builder, $start) === false) {
                        return;
                    }
                }
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

                if (is_callable($this->mysqlOnQueryFunc['_save_sql'])) {
                    $this->mysqlOnQueryFunc['_save_sql']($sql);
                } else {
                    /** @var \App\Model\Log $Log */
                    $Log = model('Log');
                    $Log->sqlWriteLog($sql);
                }

                // 后置
                if (is_callable($this->mysqlOnQueryFunc['_after_func'])) {
                    $this->mysqlOnQueryFunc['_after_func']($result, $builder, $start);
                }
            }
        );
    }

    /**
     * 注册I18n国际化
     * @return void
     */
    protected function registerI18n()
    {
        $languages = $this->languageConfig;
        if ( ! is_array($languages)) {
            return;
        }
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
        // ini优先级比Config.default高
        if (($iniLang = get_cfg_var('env.language')) && in_array($iniLang, array_keys($languages))) {
            I18N::getInstance()->setDefaultLanguage($iniLang);
        }
    }

    /**
     * 注册Http全局Request回调
     * @return void
     */
    protected function registerHttpOnRequest()
    {
        if ( ! $this->httpOnRequestOpen) {
            return;
        }
        \EasySwoole\Component\Di::getInstance()->set(
            \EasySwoole\EasySwoole\SysConst::HTTP_GLOBAL_ON_REQUEST,
            function (Request $request, Response $response) {
                // 前置
                if (is_callable($this->httpOnRequestFunc['_before_func'])) {
                    // 返回false终止本次Request
                    if ($this->httpOnRequestFunc['_before_func']($request, $response) === false) {
                        return false;
                    }
                }
                // 自定义协程单例Request
                CtxRequest::getInstance()->request = $request;

                LamUnit::setI18n($request);

                // 后置
                if (is_callable($this->httpOnRequestFunc['_after_func'])) {
                    $return = $this->httpOnRequestFunc['_after_func']($request, $response);
                    // 如果返回bool，则直接使用
                    if (is_bool($return)) {
                        return $return;
                    }
                }
                return true;
            }
        );
    }
}
