<?php

namespace WonderGame\EsUtility\Notify\Feishu;

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
     * @document https://open.feishu.cn/document/client-docs/bot-v3/add-custom-bot
     * 自定义机器人的频率控制和普通应用不同，为 100 次/分钟，5 次/秒
     * @param MessageInterface $message
     * @return void
     */
    public function does(MessageInterface $message)
    {
        $data = $message->fullData();

        $url = $this->Config->getUrl();
        $secret = $this->Config->getSignKey();

        $timestamp = time();

        $sign = base64_encode(hash_hmac('sha256', '', $timestamp . "\n" . $secret, true));

        $data['timestamp'] = $timestamp;
        $data['sign'] = $sign;

        $client = new HttpClient($url);

        // 支持文本(text)、富文本(textarea)、群名片(share_chat)、图片(image)、消息卡片(interactive)消息类型

        $response = $client->postJson(json_encode($data));
        $json = json_decode($response->getBody(), true);

        if ($json['code'] !== 0)
        {
            // todo 异常处理
        }
    }
}
