<?php

namespace WonderGame\EsUtility\Notify\DingTalk\Message;

use EasySwoole\Spl\SplBean;
use WonderGame\EsUtility\Notify\Interfaces\MessageInterface;

abstract class Base extends SplBean implements MessageInterface
{
    /**
     * 手机号
     * @var array
     */
    protected $atMobiles = [];

    /**
     * Userid
     * @var array
     */
    protected $atUserIds = [];

    protected $isAtAll = false;

    public function getAtText($text = '')
    {
        foreach (['atMobiles', 'atUserIds'] as $item) {
            foreach ($this->{$item} as $tel) {
                $text .= ' @' . $tel;
            }
        }
        return $text;
    }

    public function getServerText($text = '')
    {
        return $text . PHP_EOL . implode(PHP_EOL, [
                '系统：' . APP_MODULE,
                '服务器：' . config('SERVNAME'),
                '时间：' . date('Y年m月d日 H:i:s')
            ]);
    }
}
