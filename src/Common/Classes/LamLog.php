<?php
/**
 * 日志处理
 *
 * @author 林坤源
 * @version 1.0.4 最后修改时间 2020年11月22日
 */

namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Log\LoggerInterface;
use Swoole\Coroutine;

class LamLog implements LoggerInterface
{
    private $logDir;
    // 日志内容数组
    private $conArr = [];
    // 非独立日志的存储key
    private $dfKey = 'dfLog';

    // 立即写日志的分类标识
    const CATE_IMMEDIATE = 'immediate';

    public function __construct(string $logDir = null)
    {
        $this->logDir = $logDir ?: '';
    }

    public function log($msg, int $logLevel = self::LOG_LEVEL_INFO, string $category = 'debug'): string
    {
        $str = $this->_preAct($msg, $logLevel, $category, 'log');

        // 立即写入
        if ($category === self::CATE_IMMEDIATE || Coroutine::getCid() < 0) {
            $this->save();
        } // 协程defer时写入
        else {
            Coroutine::defer(function () {
                $this->save();
            });
        }

        return $str;
    }

    public function console($msg, int $logLevel = self::LOG_LEVEL_INFO, string $category = 'debug')
    {
        $str = $this->_preAct($msg, $logLevel, $category, 'console');
        $cid = Coroutine::getCid();
        fwrite(STDOUT, "[CID=$cid]$str\n");
    }

    // 保存日志
    public function save()
    {
        empty($this->logDir) && $this->logDir = config('LOG.dir');
        $dir = $this->logDir . '/' . date('ym');
        is_dir($dir) or @ mkdir($dir, 0777, true);

        // conArr[$cid][$key][] = $value
        foreach ((array)$this->conArr[Coroutine::getUid()] as $key => $value) {
            if (empty($value) || ! is_array($value)) {
                continue;
            }

            $fname = $key == $this->dfKey ? '' : "-$key";

            file_put_contents("$dir/" . date('d') . $fname . ".log", "\n" . implode("\n", $value) . "\n", FILE_APPEND | LOCK_EX);
        }

        unset($this->conArr[Coroutine::getUid()]);

        return true;
    }

    private function _preAct($msg, int &$logLevel = self::LOG_LEVEL_INFO, string $category = 'console', string $func = 'log')
    {
        if ( ! is_scalar($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }
        $msg = str_replace(["\n", "\r"], '', $msg);

        // 不在东8区则拼接上东8区的时间
        $tznInt = intval(substr((int)date('O'), 0, -2));
        $time = time();
        if ($tznInt !== 8) {
            $date = "{$tznInt}区: " . date(DateUtils::FULL, $time);
            $date .= ', +8区: ' . date(DateUtils::FULL, DateUtils::getTimeZoneStamp($time, 'PRC'));
        } else {
            $date = date(DateUtils::FULL, $time);
        }

        $logLevel = $this->levelMap($logLevel);

        $str = "[$date][$category][$logLevel]$msg";

        if ($func == 'log') {
            if (in_array($logLevel, config('LOG.apart_level'))) {
                $this->merge($logLevel, $str);
            } elseif (in_array($category, config('LOG.apart_category'))) {
                $this->merge($category, $str);
            } else {
                $this->merge($this->dfKey, $str);
            }
        }

        return $str;
    }

    private function levelMap(int $level)
    {
        switch ($level) {
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

    /**
     * 完整实例： conArr[$cid][$key][] = $value
     * @param string $key
     * @param string $value
     */
    protected function merge($key = '', $value = '')
    {
        $this->conArr[Coroutine::getUid()][$key][] = $value;
    }
}
