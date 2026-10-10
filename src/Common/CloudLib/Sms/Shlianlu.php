<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sms;

use EasySwoole\HttpClient\HttpClient;

/**
 * 上海联麓 模板短信
 * @document https://www.shlianlu.com/console/document/api_4_4
 */
class Shlianlu extends Base
{
    /**
     * 企业ID
     * @var string
     */
    protected $mchId = '';

    /**
     * Appid
     * @var string
     */
    protected $appId = '';

    /**
     * 签名密钥key
     * @var string
     */
    protected $key = '';

    /**
     * 公共参数版本号
     * @var string
     */
    protected $version = '1.2.0';

    /**
     * 模板id，支持标量或按验证码type区分的数组
     * @var string|array
     */
    protected $templateId = '';

    /**
     * 接口地址
     * @var string
     */
    protected $endpoint = 'https://apis.shlianlu.com/sms/trade/template/send';

    /**
     * 短信类型，本接口固定值 3
     * @var string
     */
    protected $type = '3';

    /**
     * 加密方式，固定 MD5
     * @var string
     */
    protected $signType = 'MD5';

    public function send($to = [], array $params = [], bool $ingo = false)
    {
        $type = $params['type'];
        $parentId = $ingo ? ($params['parentId'] ?: '') : null;
        unset($params['type'], $params['parentId']);

        $phoneNumberSet = is_string($to) ? [$to] : $to;
        $templateParamSet = array_values(array_map('strval', $params));
        $templateId = is_array($this->templateId) ? ($this->templateId[$type] ?? $this->templateId['-1']) : $this->templateId;

        // 13位毫秒时间戳，签名与请求体共用同一个值
        $timeStamp = (string)(int)(microtime(true) * 1000);

        $body = [
            'MchId' => $this->mchId,
            'AppId' => $this->appId,
            'Version' => $this->version,
            'Type' => $this->type,
            'PhoneNumberSet' => $phoneNumberSet,
            'TemplateId' => (string)$templateId,
            'TemplateParamSet' => $templateParamSet,
            'TimeStamp' => $timeStamp,
            'SignType' => $this->signType,
        ];
        $body['Signature'] = $this->makeSignature($body);

        // 日志脱敏：隐藏key
        $log = $body;
        $endFn = http_tracker('SDK:SMS', [
            'url' => '__SHLIANLU_SMS__',
            'POST' => $log,
            'method' => 'POST',
        ], $parentId);

        try {
            // 注意：以下代码可在开发模式下请根据需要开启或关闭
            if (is_env('dev')) {
                $endFn('env: dev ok');
                return true;
            }

            $client = new HttpClient($this->endpoint);
            $response = $client->postJson(json_encode($body), ['Accept' => 'application/json']);
            $str = $response->getBody();
            $result = json_decode($str, true);

            $endFn($result, 200);

            $isSuccess = isset($result['status']) && $result['status'] === '00';
            if ( ! $isSuccess) {
                notice('联麓短信发送失败: ' . $str);
            }
            return $isSuccess;
        } catch (\Exception $error) {
            $msg = '联麓短信发送失败: ' . $error->__toString();
            is_callable($endFn) && $endFn($msg, $error->getCode());
            notice($msg);
            return false;
        }
    }

    /**
     * 生成数字签名
     * 拼接串：AppId=xxx&MchId=xxx&SignType=MD5&TemplateId=xxx&TimeStamp=xxx&Type=3&Version=xxx&key=xxx
     * MD5 加密后转大写
     * @param array $body
     * @return string
     */
    protected function makeSignature(array $body)
    {
        $str = sprintf(
            'AppId=%s&MchId=%s&SignType=%s&TemplateId=%s&TimeStamp=%s&Type=%s&Version=%s&key=%s',
            $body['AppId'],
            $body['MchId'],
            $body['SignType'],
            $body['TemplateId'],
            $body['TimeStamp'],
            $body['Type'],
            $body['Version'],
            $this->key
        );
        return strtoupper(md5($str));
    }
}
