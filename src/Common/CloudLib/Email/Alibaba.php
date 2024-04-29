<?php

namespace WonderGame\EsUtility\Common\CloudLib\Email;

use AlibabaCloud\SDK\Dm\V20151123\Dm;
use AlibabaCloud\SDK\Dm\V20151123\Models\SingleSendMailRequest;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use Darabonba\OpenApi\Models\Config;

/**
 * composer require alibabacloud/dm-20151123
 * @document https://help.aliyun.com/document_detail/29444.html?spm=a2c4g.29443.0.0.5bfa4b41Fus03q
 */
class Alibaba extends Base
{
    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    protected $endpoint = 'dm.aliyuncs.com';

    protected $accountName = '';

    protected $subject = '';

    protected $htmlBody = '';

    protected $addressType = 0;

    protected $toAddress = '';

    public function send($to = [], array $params = [], bool $ingo = false)
    {
        $parentId = $ingo ? ($params['parentId'] ?: '') : null;
        unset($params['parentId']);

        if (is_string($to)) {
            $to = [$to];
        }
        $this->toAddress = implode(',', $to);
        $this->htmlBody = sprintf($this->htmlBody, ...$params);

        $log = repeat_array_keys(get_object_vars($this), ['accessKeyId', 'accessKeySecret'], 5);
        $endFn = http_tracker('SDK:EMAIL', [
            'url' => '__ALI_EMAIL__',
            'POST' => $log,
            'method' => 'POST',
        ], $parentId);

        try {
            $Runtime = new RuntimeOptions();
            $Request = new SingleSendMailRequest([
                'accountName' => $this->accountName,
                'addressType' => $this->addressType,
                'toAddress' => $this->toAddress,
                'subject' => $this->subject,
                'htmlBody' => $this->htmlBody,
            ]);
            $Config = new Config([
                "accessKeyId" => $this->accessKeyId,
                "accessKeySecret" => $this->accessKeySecret
            ]);
            // 访问的域名
            $Config->endpoint = $this->endpoint;
            $Client = new Dm($Config);

            $resp = $Client->singleSendMailWithOptions($Request, $Runtime);
            if ($resp->body->code != 'OK') {
                notice('阿里云邮件发送失败1: ' . $resp->body->message);
            }
            $arr = $resp->toMap();
            $endFn($arr, 200);
            return true;
        } catch (\Exception $error) {
            if ( ! ($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            $endFn($error->toString(), $error->getCode());
            trace("阿里云邮件发送失败: " . $error->toString(), 'error');
            return false;
        }
    }
}
