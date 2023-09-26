<?php

namespace WonderGame\EsUtility\Common\Logs;

use EasySwoole\Spl\SplBean;
use Swoole\Coroutine;
use WonderGame\EsUtility\Common\Classes\DateUtils;

class Item extends SplBean
{
    public $level = '';

    public $category = '';

    public $message = '';

    /************* 以下为自动完成的私有属性，勿传 ************/

    private $cid = -1;
    private $time = 0;
    private $date = '';

    protected function initialize(): void
    {
        parent::initialize();
        $this->cid = Coroutine::getCid();

        if ( ! is_scalar($this->message)) {
            $this->message = json_encode($this->message, JSON_UNESCAPED_UNICODE);
        }
        $this->message = str_replace(["\n", "\r"], '', $this->message);

        // 产生日志的时间
        $this->time = time();
        $this->date = date(DateUtils::FULL, $this->time);
        // 不在东8区，则记录东8区时间
        $tznInt = intval(substr((int)date('O'), 0, -2));
        if ($tznInt !== 8) {
            $this->date .= ', +8区: ' . date(DateUtils::FULL, DateUtils::getTimeZoneStamp($this->time, 'PRC'));
        }
    }

    public function getWriteStr()
    {
        return "[cid={$this->cid}][{$this->date}][{$this->category}][{$this->level}]{$this->message}";
    }

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
        throw new \Exception(__CLASS__ . ' Not fount property : ' . $name);
    }
}
