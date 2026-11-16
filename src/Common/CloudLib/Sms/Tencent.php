<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sms;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use TencentCloud\Sms\V20210111\SmsClient;

/**
 * composer require tencentcloud/sms
 * @document https://cloud.tencent.com/document/api/382/55981
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

            // 注意：以下代码可在开发模式或压测场景下请根据需要开启或关闭
            if (is_env('dev') || is_stress_test()) {
                $endFn('env: dev|stress Tencent ok');
                return true;
            }

            $resp = $Client->SendSms($Request);

            $str = $resp->toJsonString();
            $array = json_decode($str, true);

            $endFn($array);

            if (isset($array['Error'])) {
                notice_limit(__METHOD__ . ':' . __LINE__, "腾讯云发送失败1: $str");
                return false;
            }

            // 号码级失败（限流、模板/签名不对、号码有问题等）是 HTTP 200 且没有 Error，结果只在 SendStatusSet 里，
            // 只看 Error 会把失败当成功：队列不重试、统计记成 sent、用户收不到短信
            $statusSet = $array['SendStatusSet'] ?? [];
            if ( ! $statusSet) {
                notice_limit(__METHOD__ . ':' . __LINE__ . ':empty', "腾讯云发送失败3: 返回里没有 SendStatusSet $str");
                return false;
            }
            foreach ($statusSet as $item) {
                $code = $item['Code'] ?? '';
                if ($code !== 'Ok') {
                    notice_limit(
                        __METHOD__ . ':' . __LINE__ . ':' . $code,
                        "腾讯云发送失败3: {$code} {$item['Message']} 号码:" . ($item['PhoneNumber'] ?? '')
                    );
                    return false;
                }
            }
            return true;
        } catch (TencentCloudSDKException $e) {
            $msg = '腾讯云短信发送失败2: ' . $e->__toString();
            is_callable($endFn) && $endFn($msg, $e->getCode());
            notice_limit(__METHOD__ . ':' . __LINE__, $msg);
            return false;
        }
    }
}
