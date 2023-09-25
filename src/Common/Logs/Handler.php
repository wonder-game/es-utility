<?php

namespace WonderGame\EsUtility\Common\Logs;

use EasySwoole\Component\Timer;
use EasySwoole\Log\LoggerInterface;
use Swoole\Coroutine;

class Handler implements LoggerInterface
{
    /**
     * @var array Item[]
     */
    protected $list = [];

    protected $times = 10;

    protected $writeing = false;

    /**
     * @param string|null $logDir 日志主目录
     * @params int $times
     */
    public function __construct(string $logDir = null, $times = 10)
    {
        $this->logDir = $logDir ?: '';
        $times && $this->times = $times;
    }

    public function __destruct()
    {
        $this->run();
    }

    public function log($msg, int $logLevel = self::LOG_LEVEL_INFO, string $category = 'debug'): string
    {
        $logLevel = $this->levelMap($logLevel);
        $Item = new Item([
            'message' => $msg,
            'level' => $logLevel,
            'category' => $category
        ]);
        $this->list[] = $Item;

        if (Coroutine::getCid() < 0) {
            $this->run();
        } else {
            Coroutine::defer(function () {
                $this->run();
            });
            if (count($this->list) >= $this->times) {
                $this->run();
            }
        }
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

    public function run()
    {
        if ($this->writeing) {
            return;
        }
        $this->writeing = true;

        empty($this->logDir) && $this->logDir = config('LOG.dir');
        $dir = $this->logDir . '/' . date('ym');
        is_dir($dir) or @ mkdir($dir, 0777, true);

        if (empty($this->list)) {
            return;
        }

        $apartLevel = config('LOG.apart_level');
        $apartCategory = config('LOG.apart_category');

        // $apart-独立记录， $arr-通用记录
        $apart = $arr = [];
        /** @var Item $Item */
        foreach ($this->list as $key => $Item) {

            $str = $Item->getWriteStr();

            if (in_array($Item->level, $apartLevel)) {
                $apart[$Item->level][] = $str;
            } elseif (in_array($Item->category, $apartCategory)) {
                $apart[$Item->category][] = $str;
            } else {
                $arr[] = $str;
            }

            unset($this->list[$key]);
        }
        $this->list = [];
        $this->writeing = false;

        foreach ($apart as $name => $values) {
            $this->save($dir, $values, $name);
        }
        $arr && $this->save($dir, $arr);
    }

    protected function save($dir, $values, $name = '')
    {
        if (empty($values)) {
            return;
        }
        $name = $name ? "-$name" : '';

        $d = date('d');
        file_put_contents("$dir/{$d}{$name}.log", "\n" . implode("\n", $values) . "\n", FILE_APPEND | LOCK_EX);
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
