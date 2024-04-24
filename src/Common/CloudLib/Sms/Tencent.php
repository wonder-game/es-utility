<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sms;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use TencentCloud\Sms\V20210111\SmsClient;

/**
 * composer require tencentcloud/sms
 */
class Tencent extends Base
{
    protected $secretId = '';

    protected $secretKey = '';

    protected $smsSdkAppId = '';

    protected $templateId = '';

    protected $signName = '';

    protected $phoneNumberSet = [];

    protected $templateParamSet = '';

    /**
     * 地域：
     * @document https://cloud.tencent.com/document/api/213/15692#.E5.9C.B0.E5.9F.9F.E5.88.97.E8.A1.A8
     * 要顺便检查下该地域的推送域名是否存在,格式为 ses.[region].tencentcloudapi.com
     * @document https://cloud.tencent.com/document/api/1288/51055
     * @var string
     */
    protected $region = '';

    public function send($to = [], array $params = [])
    {
        $this->phoneNumberSet = is_string($to) ? [$to] : $to;

        $params = array_values(array_map('strval', $params));
        $this->templateParamSet = $params;

        try {
            $Request = new SendSmsRequest();
            $Request->fromJsonString(json_encode([
                'PhoneNumberSet' => $this->phoneNumberSet,
                'SmsSdkAppId' => $this->smsSdkAppId,
                'TemplateId' => $this->templateId,
                'SignName' => $this->signName ?: null,
                'TemplateParamSet' => $this->templateParamSet,
            ]));

            $endFn = http_tracker('SDK:SMS', [
                'url' => '__TENCENT_SMS__',
                'POST' => $Request->serialize(),
                'method' => 'POST',
            ]);

            $Credential = new Credential($this->secretId, $this->secretKey);
            $Client = new SmsClient($Credential, $this->region);

            $resp = $Client->SendSms($Request);

            $str = $resp->toJsonString();
            $array = json_decode($str, true);

            $isSuccess = ! isset($array['Error']);
            if ( ! $isSuccess) {
                trace("腾讯云{$this->callName}发送失败1: $str", 'error');
            }

            $endFn($array);

            return $isSuccess;

        } catch (TencentCloudSDKException $e) {
            $msg = "腾讯云短信发送失败2: " . $e->__toString();
            trace($msg, 'error');
            is_callable($endFn) && $endFn($msg, 431);
            return false;
        }
    }
}
