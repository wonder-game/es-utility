<?php

namespace WonderGame\EsUtility\Common\CloudLib\Email;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Ses\V20201002\Models\SendEmailRequest;
use TencentCloud\Ses\V20201002\SesClient;

/**
 * composer require tencentcloud/ses
 */
class Tencent extends Base
{
    protected $secretId = '';

    protected $secretKey = '';

    /**
     *  发件人，邮箱格式
     * @var string
     */
    protected $fromEmailAddress = '';

    protected $subject = '';

    protected $templateId = '';

    /**
     * 收件人
     * @var string
     */
    protected $destination = [];

    /**
     * 模板变量
     * @var string
     */
    protected $templateData = '';

    /**
     * 地域：
     * @document https://cloud.tencent.com/document/api/213/15692#.E5.9C.B0.E5.9F.9F.E5.88.97.E8.A1.A8
     * 要顺便检查下该地域的推送域名是否存在,格式为 ses.[region].tencentcloudapi.com
     * @document https://cloud.tencent.com/document/api/1288/51034
     * @var string
     */
    protected $region = '';

    public function send($to = [], array $params = [], bool $ingo = false)
    {
        $type = $params['type'];
        $parentId = $ingo ? ($params['parentId'] ?: '') : null;
        unset($params['type'], $params['parentId']);

        settype($to, 'array');

        try {
            $Request = new SendEmailRequest();
            $Request->fromJsonString(json_encode([
                'FromEmailAddress' => $this->fromEmailAddress,
                'Destination' => $to ?: $this->destination,
                'Subject' => $this->subject,
                'Template' => [
                    // 腾讯云的此参数要求为int
                    'TemplateID' => (int)is_array($this->templateId) ? ($this->templateId[$type] ?? $this->templateId['-1']) : $this->templateId,
                    'TemplateData' => json_encode($params),
                ],
            ]));

            $endFn = http_tracker('SDK:EMAIL', [
                'url' => '__TENCENT_EMAIL__',
                'POST' => $Request->serialize(),
                'method' => 'POST',
            ], $parentId);

            $Credential = new Credential($this->secretId, $this->secretKey);
            $Client = new SesClient($Credential, $this->region);

            $resp = $Client->SendEmail($Request);

            $str = $resp->toJsonString();
            $array = json_decode($str, true);

            $endFn($array);

            if (isset($array['Error'])) {
                trace("腾讯云邮件发送失败1: $str", 'error');
                return false;
            }

            return true;
        } catch (TencentCloudSDKException $e) {
            $msg = "腾讯云邮件发送失败2: " . $e->__toString();
            is_callable($endFn) && $endFn($msg, $e->getCode());
            trace($msg, 'error');
            notice($msg);
            return false;
        }
    }
}
