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
        Coroutine::defer(function () {
            $this->save();
        });

        $str = $this->_preAct($msg, $level, $category, 'log');

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

        $context = $this->getAll();
        if (empty($context))
        {
            return false;
        }
        foreach ($context as $key => $value)
        {
            if (empty($value))
            {
                continue;
            }
            if (is_array($value))
            {
                $value = implode("\n", $value);
            }

            list(, $level, $category) = explode('.', $key);

            $fname = '';
            // 独立文件的日志
            if (in_array($level, config('LOG.apart_level')))
            {
                $fname = "-{$level}";
            }
            elseif (in_array($category, config('LOG.apart_category')))
            {
                $fname = "-{$category}";
            }

            file_put_contents("$dir/" . date('d') . $fname . '.log', "\n" . $value . "\n", FILE_APPEND | LOCK_EX);
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
            $this->merge("log.{$level}.{$category}", $str);
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

    protected $contextKey = 'log';

    protected function merge($key, $value)
    {
        $context = ContextManager::getInstance()->getContextArray() ?? [];
        $context[$this->contextKey][$key][] = $value;
        ContextManager::getInstance()->set($this->contextKey, $context[$this->contextKey]);
    }

    protected function getAll()
    {
        $array = ContextManager::getInstance()->getContextArray() ?? [];
        return $array[$this->contextKey] ?? [];
    }
}
