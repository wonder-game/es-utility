<?php

namespace WonderGame\EsUtility\Common\CloudLib\IdAuth;

use EasySwoole\HttpClient\HttpClient;
use WonderGame\EsUtility\Common\Exception\HttpParamException;

/**
 * 阿里云-第三方应用市场
 * 子类为各商家
 */
abstract class AlibabaCloudStore extends Base
{
    protected $url = '';

    protected $appCode = '';


    //*********************** 以下key不用配置,由子类重写

    protected $cardName = '';

    protected $userName = '';

    protected $method = 'GET';

    public function checkIdCard($uid, $card, $realname)
    {
        $params = [
            $this->cardName => $card,
            $this->userName => $realname,
        ];

        $end = http_tracker('SDK:ID_AUTH_ALI', [
            'url' => $this->url,
            'method' => strtoupper($this->method),
            'params' => $params,
        ]);

        $resp = hcurl($this->url, $params, $this->method, [
            'Authorization' => "APPCODE {$this->appCode}",
        ], ['resultType' => '', 'retryTimes' => 0]);

        $httpCode = $resp->getStatusCode();
        $end($resp->getBody(), $httpCode);

        $this->isHttpOk($httpCode);

        return $this->isSuccess($this->_result = $resp->json(true));
    }

    /**
     * 成功状态判断
     * 如果此运营商异常，需要调用下一个运营商，请抛HttpParamException异常，异常码为460
     * @param $result
     * @return bool true-匹配成功，false-匹配失败
     */
    abstract protected function isSuccess($result);

    protected function setRequest(HttpClient $httpClient)
    {

    }

    protected function isHttpOk($httpCode)
    {
        if ($httpCode !== 200) {
            throw new HttpParamException('请求失败', self::REQUEST_ERR);
        }
    }
}
