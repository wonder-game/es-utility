<?php

namespace WonderGame\EsUtility\Notify\Feishu\Message;

use EasySwoole\HttpClient\HttpClient;
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
        if ($this->isAtAll === true) {
            $at = [
                [
                    'tag' => 'at',
                    'user_id' => 'all',
                    'user_name' => '所有人',
                ]
            ];
        } else if (is_array($this->isAtAll)) {
            foreach ($this->atUserID as $key => $value) {
                if ( ! in_array($key, $this->isAtAll)) continue;
                $at[] = [
                    'tag' => 'at',
                    'user_id' => $value['id'],
                    'user_name' => $value['name'],
                ];
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

    public function getImageKey($img, $pool = 'admin')
    {
        $tenant_access_token = $this->tenantAccessToken($pool);
        $headers = [
            'Content-Type' => HttpClient::CONTENT_TYPE_FORM_DATA,
            'Authorization' => "Bearer {$tenant_access_token}",
        ];
        $sendParams = [
            'image_type' => 'message',
            'image' => curl_file_create($img),
        ];

        $response = $this->postRequest('https://open.feishu.cn/open-apis/im/v1/images', $headers, $sendParams);
        $result = json_decode($response, true);

        if (isset($result['code']) && $result['code'] == 0) {
            return $result['data']['image_key'];
        } else {
            return '';
        }
    }

    public function tenantAccessToken($pool = 'admin')
    {
        $appId = config('ES_NOTIFY.feishu.appId');
        $appSecret = config('ES_NOTIFY.feishu.appSecret');
        $key = "tenant_access_token-{$appId}";
        $Redis = defer_redis($pool);
        $token = $Redis->get($key);
        if ( ! empty($token)) {
            return $token;
        }

        $sendParams = [
            'app_id' => $appId,
            'app_secret' => $appSecret,
        ];
        $HttpClient = new HttpClient('https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal');
        $HttpClient->setClientSetting('keep_alive', true);
        $response = $HttpClient->postJson(json_encode($sendParams));
        $body = $response->getBody();
        $result = json_decode($body, true);
        if (isset($result['code']) && $result['code'] == 0) {
            $Redis->setEx($key, $result['expire'] - 60, $result['tenant_access_token']);
            return $result['tenant_access_token'];
        }
        return '';
    }

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
