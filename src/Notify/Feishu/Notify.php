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
     * @document https://open.feishu.cn/document/client-docs/bot-v3/add-custom-bot#%E6%94%AF%E6%8C%81%E5%8F%91%E9%80%81%E7%9A%84%E6%B6%88%E6%81%AF%E7%B1%BB%E5%9E%8B%E8%AF%B4%E6%98%8E
     * 自定义机器人的频率控制和普通应用不同，为 100 次/分钟，5 次/秒
     * @param MessageInterface $message
     * @return void|array
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

        // 支持文本(text)、富文本(textarea)、群名片(share_chat)、图片(image)、消息卡片(interactive)消息类型
        return hcurl($url, $data, 'json');
    }

    /**
     * @document https://open.feishu.cn/document/server-docs/im-v1/message/create?appId=cli_a6d2f4aa8ef2500b
     * 接口频率限制 1000 次/分钟、50 次/秒
     * @param MessageInterface $message
     * @return void
     */
    public function sendUser(MessageInterface $message, $union_id)
    {
        $message->setInner(false);
        $sendParams = $message->fullData();
        $url = 'https://open.feishu.cn/open-apis/im/v1/messages?receive_id_type=union_id';
        $headers = [
            'Content-Type' => HttpClient::CONTENT_TYPE_APPLICATION_JSON,
            'Authorization' => 'Bearer ' . $message->sendUserToken(),
        ];
        $sendParams['receive_id'] = $union_id;
        $sendParams['content'] = json_encode($sendParams['content']); // 实际上要二次encode,下面还有一次

        $response = $this->postRequest($url, $headers, json_encode($sendParams));
        $result = json_decode($response, true);

        return $result;
    }

    // TODO 待集成优化
    protected function postRequest($urlString, $customerHeader, $body)
    {
        $url = $urlString;
        $con = curl_init($url);
        // 设置连接超时时间为5秒
        curl_setopt($con, CURLOPT_CONNECTTIMEOUT, 5);
        // 设置读取超时时间为10秒
        curl_setopt($con, CURLOPT_TIMEOUT, 10);
        curl_setopt($con, CURLOPT_POST, true);
        curl_setopt($con, CURLOPT_POSTFIELDS, $body);
        $headerArray = [];
        foreach ($customerHeader as $key => $value) {
            $headerArray[] = "$key: $value";
        }
        curl_setopt($con, CURLOPT_HTTPHEADER, $headerArray);
        curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($con);
        curl_close($con);
        return $response;
    }
}
