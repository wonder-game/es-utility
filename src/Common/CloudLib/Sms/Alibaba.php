<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sms;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use Darabonba\OpenApi\Models\Config;

/**
 * composer require alibabacloud/dysmsapi-20170525
 */
class Alibaba extends Base
{
    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    protected $endpoint = 'dysmsapi.aliyuncs.com';

    protected $signName = '';

    protected $templateCode = '';

    protected $phoneNumbers = '';

    protected $templateParam = '';

    public function send($to = [], array $params = [], bool $ingo = false)
    {
        $type = $params['type'];
        $parentId = $ingo ? ($params['parentId'] ?: '') : null;
        unset($params['type'], $params['parentId']);

        $this->phoneNumbers = implode(',', is_string($to) ? [$to] : $to);
        $this->templateParam = json_encode($params);

        $log = repeat_array_keys(get_object_vars($this), ['accessKeyId', 'accessKeySecret'], 5);
        $endFn = http_tracker('SDK:SMS', [
            'url' => '__ALI_SMS__',
            'POST' => $log,
            'method' => 'POST',
        ], $parentId);

        try {
            $Runtime = new RuntimeOptions();
            $Request = new SendSmsRequest([
                'phoneNumbers' => $this->phoneNumbers,
                'signName' => $this->signName,
                'templateCode' => is_array($this->templateCode) ? ($this->templateCode[$type] ?? $this->templateCode['-1']) : $this->templateCode,
                'templateParam' => $this->templateParam
            ]);
            $Config = new Config([
                "accessKeyId" => $this->accessKeyId,
                "accessKeySecret" => $this->accessKeySecret
            ]);
            // 访问的域名
            $Config->endpoint = $this->endpoint;
            $Client = new Dysmsapi($Config);

            // 注意：以下代码可在开发模式下请根据需要开启或关闭
            if (is_env('dev')) {
                $endFn('env: dev ok');
                return true;
            }

            $resp = $Client->sendSmsWithOptions($Request, $Runtime);
            $arr = $resp->toMap();
            $endFn($arr, 200);

            $isSuccess = $resp->body->code === 'OK';

            if ( ! $isSuccess) {
                notice('阿里云短信发送失败: ' . $resp->body->message);
            }
            return $isSuccess;
        } catch (\Exception $error) {
            if ( ! ($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }

            is_callable($endFn) && $endFn($error->__toString(), $error->getCode());
            notice('阿里云短信发送失败: ' . $error->__toString());
            return false;
        }
    }
}
