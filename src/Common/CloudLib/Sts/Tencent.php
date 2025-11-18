<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sts;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Sts\V20180813\Models\GetFederationTokenRequest;
use TencentCloud\Sts\V20180813\Models\GetFederationTokenResponse;
use TencentCloud\Sts\V20180813\StsClient;

/**
 * composer require tencentcloud/sts
 * @document https://cloud.tencent.com/document/product/436/14048#case2
 * @document https://console.cloud.tencent.com/api/explorer?Product=sts&Version=2018-08-13&Action=GetFederationToken
 */
class Tencent extends Base
{
    protected $secretId;
    protected $secretKey;
    protected $region;

    public function get($policy, $expire = 1800): Response
    {
        try {
            $cred = new Credential($this->secretId, $this->secretKey);
            // 使用临时密钥示例
            // $cred = new Credential("SecretId", "SecretKey", "Token");
            // 实例化一个http选项，可选的，没有特殊需求可以跳过
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint('sts.tencentcloudapi.com');

            // 实例化一个client选项，可选的，没有特殊需求可以跳过
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            // 实例化要请求产品的client对象,clientProfile是可选的
            $client = new StsClient($cred, $this->region, $clientProfile);

            // 实例化一个请求对象,每个接口都会对应一个request对象
            $req = new GetFederationTokenRequest();

            $params = [
                'Name' => $this->getName(),
                'Policy' => $policy,
                'DurationSeconds' => $expire
            ];
            $req->fromJsonString(json_encode($params));

            // 返回的resp是一个GetFederationTokenResponse的实例，与请求对象对应
            /** @var GetFederationTokenResponse $resp */
            $resp = $client->GetFederationToken($req);

            return new Response([
                'token' => $resp->getCredentials()->getToken(),
                'tmpSecretId' => $resp->getCredentials()->getTmpSecretId(),
                'tmpSecretKey' => $resp->getCredentials()->getTmpSecretKey(),
                'expiredTime' => $resp->getExpiredTime(),
                'requestId' => $resp->getRequestId()
            ]);
        }
        catch(TencentCloudSDKException $e) {
            trace("STS ERROR: " . $e->getMessage(), 'error');
            throw $e;
        }
    }
}
