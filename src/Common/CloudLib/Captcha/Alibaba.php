<?php

namespace WonderGame\EsUtility\Common\CloudLib\Captcha;

use AlibabaCloud\SDK\Captcha\V20230305\Captcha;
use AlibabaCloud\SDK\Captcha\V20230305\Models\VerifyIntelligentCaptchaRequest;
use AlibabaCloud\SDK\Captcha\V20230305\Models\VerifyIntelligentCaptchaResponse;
use AlibabaCloud\Tea\Exception\TeaError;
use Darabonba\OpenApi\Models\Config;
use Exception;


/**
 * 阿里云验证码2.0
 * https://help.aliyun.com/zh/captcha/captcha2-0/user-guide/server-integration?spm=a2c4g.11186623.0.0.629866baFilAKr
 *
 * composer require alibabacloud/captcha20230305
 */
class Alibaba extends Base
{
    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    protected $endpoint = 'captcha.cn-shanghai.aliyuncs.com';

    /**
     * 场景id
     * @var string
     */
    protected $sceneId = '';

    /**
     * 连接超时时间，毫秒
     * @var int
     */
    protected $connectTimeout = 5000;

    /**
     * 读超时时间，毫秒
     * @var int
     */
    protected $readTimeout = 5000;

    public function verify($verifyParam): bool
    {
        $cfgarr = [];
        foreach (['accessKeyId', 'accessKeySecret', 'endpoint', 'connectTimeout', 'readTimeout'] as $col) {
            $cfgarr[$col] = $this->$col;
        }

        try {

            $config = new Config($cfgarr);

            $client = new Captcha($config);

            $request = new VerifyIntelligentCaptchaRequest([
                'captchaVerifyParam' => $verifyParam,
                'sceneId' => $this->sceneId
            ]);

            $endFn = http_tracker('SDK:captcha', [
                'url' => '__ALI_captcha__',
                'config' => repeat_array_keys($cfgarr, ['accessKeySecret']),
                'params' => $request,
                'method' => 'POST',
            ]);

            /** @var VerifyIntelligentCaptchaResponse $resp */
            $resp = $client->verifyIntelligentCaptcha($request);

            $endFn($resp->toMap());

            return $resp->body->result->verifyResult;
        } catch (Exception $error) {
            if (!($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }

            $errmsg = '阿里云captcha验证码发送失败：异常Code为:' . $error->getCode() . '，原因为：' . $error->getMessage();
            is_callable($endFn) && $endFn($errmsg, $error->getCode());
            trace($errmsg, 'error');
            notice($errmsg);

            // 出现异常建议认为验证通过，优先保证业务可用，然后尽快排查异常原因。
            return true;
        }
    }
}
