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

    public function send($to = [], array $params = [], bool $ingo = false)
    {
        $this->phoneNumberSet = is_string($to) ? [$to] : $to;

        $type = $params['type'];
        $parentId = $ingo ? ($params['parentId'] ?: '') : null;
        unset($params['type'], $params['parentId']);

        $params = array_values(array_map('strval', $params));
        $this->templateParamSet = $params;

        try {
            $Request = new SendSmsRequest();
            $Request->fromJsonString(json_encode([
                'PhoneNumberSet' => $this->phoneNumberSet,
                'SmsSdkAppId' => $this->smsSdkAppId,
                'TemplateId' => is_array($this->templateId) ? ($this->templateId[$type] ?? $this->templateId['-1']) : $this->templateId,
                'SignName' => $this->signName ?: null,
                'TemplateParamSet' => $this->templateParamSet,
            ]));

            $endFn = http_tracker('SDK:SMS', [
                'url' => '__TENCENT_SMS__',
                'POST' => $Request->serialize(),
                'method' => 'POST',
            ], $parentId);

            $Credential = new Credential($this->secretId, $this->secretKey);
            $Client = new SmsClient($Credential, $this->region);

            // 注意：以下代码可在开发模式下请根据需要开启或关闭
            if (is_env('dev')) {
                return true;
            }

            $resp = $Client->SendSms($Request);

            $str = $resp->toJsonString();
            $array = json_decode($str, true);

            if (isset($array['Error'])) {
                notice("腾讯云发送失败1: $str");
                return false;
            }

            $endFn($array);

            return true;
        } catch (TencentCloudSDKException $e) {
            notice($msg = '腾讯云短信发送失败2: ' . $e->__toString());
            is_callable($endFn) && $endFn($msg, 431);
            return false;
        }
    }
}
