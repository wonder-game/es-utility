<?php

namespace WonderGame\EsUtility\Common\Logs;

use EasySwoole\Log\LoggerInterface;

/**
 * 日志处理器
 */
class Handler implements LoggerInterface
{
    /**
     * 本地降级使用的保存目录
     * @var string
     */
    protected $logDir = '';

    public function log($msg, int $logLevel = self::LOG_LEVEL_INFO, string $category = 'debug'): string
    {
        $logLevel = $this->levelMap($logLevel);
        $Item = new Item([
            'message' => $msg,
            'level' => $logLevel,
            'category' => $category
        ]);

        $this->save($Item);
        return $Item->getWriteStr();
    }

    public function console($msg, int $logLevel = self::LOG_LEVEL_INFO, string $category = 'debug')
    {
        $logLevel = $this->levelMap($logLevel);
        $Item = new Item([
            'message' => $msg,
            'level' => $logLevel,
            'category' => $category
        ]);
        $str = $Item->getWriteStr();
        fwrite(STDOUT, "$str\n");
    }

    public function save(Item $Item)
    {
        empty($this->logDir) && $this->logDir = config('LOG.dir');
        $dir = $this->logDir . '/' . date('ym');
        is_dir($dir) or @ mkdir($dir, 0777, true);

        $apartLevel = config('LOG.apart_level');
        $apartCategory = config('LOG.apart_category');

        $name = '';
        if (in_array($Item->level, $apartLevel)) {
            $name = '-' . $Item->level;
        } elseif (in_array($Item->category, $apartCategory)) {
            $name = '-' . $Item->category;
        }

        $d = date('d');
        $str = $Item->getWriteStr();
        file_put_contents("$dir/{$d}{$name}.log", "$str\n", FILE_APPEND | LOCK_EX);
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
}
