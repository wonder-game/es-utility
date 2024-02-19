<?php

namespace WonderGame\EsUtility\Notify\Feishu\Message;

use EasySwoole\Spl\SplBean;
use WonderGame\EsUtility\Notify\Interfaces\MessageInterface;

abstract class Base extends SplBean implements MessageInterface
{
    /**
     * OpenID
     * @var array
     */
    protected $atOpenID = [];

    /**
     * UserID
     * @var array
     */
    protected $atUserID = [];

    protected $isAtAll = false;

    public function getAtText($text = '')
    {
        switch ($this->isAtAll) {
            case true:
                $text .= '<at user_id="all">所有人</at>';
                break;
            case false:
                break;
            default:
                foreach ($this->atUserID as $id => $name) {
                    $text .= '<at user_id="' . $id . '">' . $name . '</at>';
                }
        }

        return $text;
    }

    public function getAtArray()
    {
        $at = [];
        switch ($this->isAtAll) {
            case true:
                $at = [
                    [
                        'tag' => 'at',
                        'user_id' => 'all',
                        'user_name' => '所有人',
                    ]
                ];
                break;
            case false:
                break;
            default:
                foreach (['atOpenID', 'atUserID'] as $item) {
                    foreach ($this->{$item} as $id => $name) {
                        $at[] = [
                            'tag' => 'at',
                            'user_id' => $id,
                            'user_name' => $name,
                        ];
                    }
                }
        }

        return $at;
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
