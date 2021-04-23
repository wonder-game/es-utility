<?php
/**
 * 日志处理
 *
 * @author 林坤源
 * @version 1.0.4 最后修改时间 2020年11月22日
 */

namespace Linkunyuan\EsUtility\Classes;


use EasySwoole\Component\Context\ContextManager;
use EasySwoole\Log\LoggerInterface;
use Swoole\Coroutine;

class LamLog implements LoggerInterface
{

    private $logDir;

    public function __construct(string $logDir = null)
    {
        $this->logDir = $logDir ? : '';
    }

    public function log($msg, int $level = self::LOG_LEVEL_INFO, string $category = 'debug'):string
    {
        $str = $this->_preAct($msg, $level, $category, 'log');

        // 协程环境，注册defer
        if (Coroutine::getCid() > 0)
        {
            Coroutine::defer(function () {
                $this->save();
            });
        }
        // 非协程环境，直接save
        else {
            $this->save();
        }

        return $str;
    }

    public function console($msg, int $level = self::LOG_LEVEL_INFO, string $category = 'debug')
    {
        $str = $this->_preAct($msg, $level, $category, 'console');
        fwrite(STDOUT, Coroutine::getCid() . "\t$str\n");
    }

    // 保存日志
    public function save()
    {
        empty($this->logDir) && $this->logDir = config('LOG_DIR');
        $dir = $this->logDir . '/' . date('ym');
        is_dir($dir) or @ mkdir($dir);

        foreach ([$this->logKey, $this->cateKey, $this->levelKey] as $key)
        {
            // context[$cid][$key][$cate][] = $value
            $cot = $this->context()->get($key);
            if (empty($cot) || !is_array($cot))
            {
                continue;
            }

            foreach ($cot as $l => $v)
            {
                $fname = $key == $this->logKey ? '' : "-{$l}";
                file_put_contents("$dir/" . date('d') . $fname . ".log", "\n" . implode("\n", $v) . "\n", FILE_APPEND|LOCK_EX);
            }
            $this->context()->unset($key);
        }

        return true;
    }

    private function _preAct($msg, int & $level = self::LOG_LEVEL_INFO, string $category = 'console', string $func = 'log')
    {

        if( ! is_scalar($msg))
        {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }
        $msg = str_replace(["\n","\r"], '', $msg);
        $date = date('Y-m-d H:i:s');
        $level = $this->levelMap($level);

        $str = "[$date][$category][$level]$msg";

        if($func == 'log')
        {
            if (in_array($level, config('LOG.apart_level')))
            {
                $this->merge($level, $str, $this->levelKey);
            }
            elseif (in_array($category, config('LOG.apart_category')))
            {
                $this->merge($category, $str, $this->cateKey);
            }
            else {
                $this->merge('log', $str);
            }

        }

        return $str;
    }

    private function levelMap(int $level)
    {
        switch ($level)
        {
            case self::LOG_LEVEL_INFO:
                return 'info';
            case self::LOG_LEVEL_NOTICE:
                return 'notice';
            case self::LOG_LEVEL_WARNING:
                return 'warning';
            case self::LOG_LEVEL_ERROR:
                return 'error';
            default:
                return 'unknown';
        }
    }


    /***************** Context上下文管理 *************************/

    /**
     * 普通日志
     * @var string
     */
    protected $logKey = 'log.log';

    /**
     * 需要独立文件的level日志
     * @var string
     */
    protected $levelKey = 'log.level';

    /**
     * 需要独立文件的category日志
     * @var string
     */
    protected $cateKey = 'log.cate';

    /**
     * 获取管理器对象
     * @return ContextManager
     */
    protected function context(): ContextManager
    {
        return ContextManager::getInstance();
    }

    /**
     * 完整实例： context[$cid][$log][$key][] = $value
     * @param $key
     * @param $value
     * @param string $log
     */
    protected function merge($key, $value, $log = '')
    {
        try {
            $log = $log ?: $this->logKey;
            $array = $this->context()->getContextArray() ?? [];
            $array[$log][$key][] = $value;
            $this->context()->set($log, $array[$log]);
        }
        catch (\EasySwoole\Component\Context\Exception\ModifyError $e)
        {
            // key与注册的log处理器名字冲突，暂时不做处理，丢掉这种日志
        }
    }
}
