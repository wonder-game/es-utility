<?php

namespace WonderGame\EsUtility\Notify\DingTalk;

use EasySwoole\HttpClient\HttpClient;
use WonderGame\EsUtility\Notify\Interfaces\ConfigInterface;
use WonderGame\EsUtility\Notify\Interfaces\MessageInterface;
use WonderGame\EsUtility\Notify\Interfaces\NotifyInterface;

class Notify implements NotifyInterface
{
    /**
     * @var Config
     */
    protected $Config = null;

    public function __construct(ConfigInterface $Config)
    {
        $this->Config = $Config;
    }

    /**
     * @document https://open.dingtalk.com/document/group/custom-robot-access
     * 每个机器人每分钟最多发送20条消息到群里，如果超过20条，会限流10分钟
     * @param MessageInterface $message
     * @return void|array
     */
    public function does(MessageInterface $message)
    {
        $data = $message->fullData();

        $url = $this->Config->getUrl();
        $secret = $this->Config->getSignKey();

        // 签名 &timestamp=XXX&sign=XXX
        $timestamp = round(microtime(true), 3) * 1000;

        $sign = utf8_encode(urlencode(base64_encode(hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true))));

        $url .= "&timestamp={$timestamp}&sign={$sign}";

        // 支持文本 (text)、链接 (link)、markdown(markdown)、ActionCard、FeedCard消息类型

        $json = hcurl($url, $data, 'json');
        if ($json['errcode'] !== 0) {
            // todo 异常处理
        }
        return $json;
    }

    public function sendUser(MessageInterface $message, $union_id)
    {

    }
}
