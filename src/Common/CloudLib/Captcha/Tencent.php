<?php

namespace WonderGame\EsUtility\Common\CloudLib\Captcha;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Captcha\V20190722\CaptchaClient;
use TencentCloud\Captcha\V20190722\Models\DescribeCaptchaResultRequest;
use TencentCloud\Captcha\V20190722\Models\DescribeCaptchaMiniResultRequest;

/**
 * @document https://cloud.tencent.com/document/product/1110/36841
 * composer require tencentcloud/captcha:"^3.0"
 */
class Tencent extends Base
{
    protected $secretId = '';

    protected $secretKey = '';

    protected $captchaType = 9;

    protected $appid = '';
    protected $appSecretKey = '';

    /**
     * 是否微信小程序
     * @var bool
     */
    protected $mini = false;

    /**
     * @param array $verifyParam
     *   // ret         Int       验证结果，0：验证成功。2：用户主动关闭验证码。
        // ticket      String    验证成功的票据，当且仅当 ret = 0 时 ticket 有值。
        // appid       String    验证码应用ID。
        // bizState    Any       自定义透传参数。
        // randstr     String    本次验证的随机串，后续票据校验时需传递该参数。
        // verifyDuration     Int   验证码校验接口耗时（ms）。
        // actionDuration     Int   操作校验成功耗时（用户动作+校验完成）(ms)。
        // sid     String   链路sid。
     * @return bool
     */
    public function verify($verifyParam): bool
    {
        try {
            // 实例化一个认证对象，入参需要传入腾讯云账户 SecretId 和 SecretKey，此处还需注意密钥对的保密
            // 代码泄露可能会导致 SecretId 和 SecretKey 泄露，并威胁账号下所有资源的安全性。以下代码示例仅供参考，建议采用更安全的方式来使用密钥，请参见：https://cloud.tencent.com/document/product/1278/85305
            // 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
            $cred = new Credential($this->secretId, $this->secretKey);
            // 实例化一个http选项，可选的，没有特殊需求可以跳过
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("captcha.tencentcloudapi.com");

            // 实例化一个client选项，可选的，没有特殊需求可以跳过
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            // 实例化要请求产品的client对象,clientProfile是可选的
            $client = new CaptchaClient($cred, "", $clientProfile);

            $params = [
                'UserIp' => ip(),
                'CaptchaAppId' => intval($this->appid),
                'AppSecretKey' => $this->appSecretKey,
                'CaptchaType' => $this->captchaType,
                'Ticket' => $verifyParam['ticket'],
            ];

            // 微信小程序
            if ($this->mini) {
                $req = new DescribeCaptchaMiniResultRequest();

            } else {
                // 实例化一个请求对象,每个接口都会对应一个request对象
                $req = new DescribeCaptchaResultRequest();
                $params['Randstr'] = $verifyParam['randstr'];
            }

            $endFn = http_tracker('SDK:captcha', [
                'url' => '__TENCENT_CAPTCHA__',
                'params' => repeat_array_keys($params, ['AppSecretKey']),
                'method' => 'POST',
            ]);

            $req->fromJsonString(json_encode($params));

            if ($this->mini) {
                // 返回的resp是一个DescribeCaptchaMiniResultResponse的实例，与请求对象对应
                $resp = $client->DescribeCaptchaMiniResult($req);
            } else {
                // 返回的resp是一个DescribeCaptchaResultResponse的实例，与请求对象对应
                $resp = $client->DescribeCaptchaResult($req);
            }

            $endFn(json_decode($resp->toJsonString(), true));

            // https://cloud.tencent.com/document/product/1110/48499
            return $resp->getCaptchaCode() === 1;

        } catch (TencentCloudSDKException $e) {
            $errmsg = '腾讯云captcha验证码发送失败：异常Code为:' . $e->getErrorCode() . '，原因为：' . $e->__toString();

            is_callable($endFn) && $endFn($errmsg, $e->getErrorCode());
            trace($errmsg, 'error');
            dingtalk_text("$errmsg, 请及时处理异常");

            // 出现异常建议认为验证通过，优先保证业务可用，然后尽快排查异常原因。
            return true;
        }
    }
}
