<?php

namespace WonderGame\EsUtility\Common\CloudLib\IdAuth;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Faceid\V20180301\FaceidClient;
use TencentCloud\Faceid\V20180301\Models\IdCardVerificationRequest;
use WonderGame\EsUtility\Common\Exception\HttpParamException;


/**
 * 腾讯云-身份信息认证
 *
 * @document https://console.cloud.tencent.com/api/explorer?Product=faceid&Version=2018-03-01&Action=IdCardVerification
 *
 * composer require tencentcloud/faceid
 */
class Tencent extends Base
{
    protected $secretId = '';

    protected $secretKey = '';

    public function checkIdCard($uid, $card, $realname)
    {
        try {
            // 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
            $cred = new Credential($this->secretId, $this->secretKey);
            // 实例化一个http选项，可选的，没有特殊需求可以跳过
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("faceid.tencentcloudapi.com");

            // 实例化一个client选项，可选的，没有特殊需求可以跳过
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            // 实例化要请求产品的client对象,clientProfile是可选的
            $client = new FaceidClient($cred, "", $clientProfile);

            // 实例化一个请求对象,每个接口都会对应一个request对象
            $req = new IdCardVerificationRequest();

            $params = [
                'IdCard' => $card,
                'Name' => $realname
            ];

            $end = http_tracker('SDK:ID_AUTH_TENCENT', [
                'url' => '__TENCENT_URL__',
                'params' => $req->serialize(),
                'method' => 'POST',
            ]);

            $req->fromJsonString(json_encode($params));

            // 返回的resp是一个IdCardVerificationResponse的实例，与请求对象对应
            $resp = $client->IdCardVerification($req);
            $this->_result = json_decode($resp->toJsonString(), true);
            $end($resp->toJsonString(), 200);

            /**
             * 认证结果码，收费情况如下。
             * 收费结果码：
             * 0: 姓名和身份证号一致
             * -1: 姓名和身份证号不一致
             * 不收费结果码：
             * -2: 非法身份证号（长度、校验位等不正确）
             * -3: 非法姓名（长度、格式等不正确）
             * -4: 证件库服务异常
             * -5: 证件库中无此身份证记录
             * -6: 权威比对系统升级中，请稍后再试
             * -7: 认证次数超过当日限制
             * 示例值：0
             */
            if ($resp->Result == '-7') {
                throw new HttpParamException('接口请求上限', self::REQUEST_ERR);
            }
            // 证件库服务异常、系统升级中：腾讯侧故障，不是校验结果，换下一家
            if (in_array($resp->Result, ['-4', '-6'])) {
                throw new HttpParamException("腾讯云实名服务异常: {$resp->Result}", self::REQUEST_ERR);
            }
            return $resp->Result == '0';
        } catch (TencentCloudSDKException $e) {
            $end($e->__toString(), $e->getCode());
            throw new HttpParamException('请求失败', self::REQUEST_ERR);
        }
    }
}
