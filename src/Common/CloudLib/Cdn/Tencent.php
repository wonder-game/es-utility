<?php

namespace WonderGame\EsUtility\Common\CloudLib\Cdn;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Cdn\V20180606\CdnClient;
use TencentCloud\Cdn\V20180606\Models\PurgePathCacheRequest;
use TencentCloud\Cdn\V20180606\Models\PurgeUrlsCacheRequest;
use TencentCloud\Cdn\V20180606\Models\PushUrlsCacheRequest;

/**
 * composer require tencentcloud/cdn
 * document: https://console.cloud.tencent.com/api/explorer?Product=cdn&Version=2018-06-06&Action=PushUrlsCache
 */
class Tencent extends Base
{
    protected $SecretId = '';

    protected $SecretKey = '';

    protected $endPoint = "cdn.tencentcloudapi.com";

    /**
     * @Description create client
     * @Date 2025/6/19
     * @return CdnClient
     */
    protected function createClient()
    {
        // 实例化一个认证对象，入参需要传入腾讯云账户 SecretId 和 SecretKey，此处还需注意密钥对的保密
        // 代码泄露可能会导致 SecretId 和 SecretKey 泄露，并威胁账号下所有资源的安全性
        // 以下代码示例仅供参考，建议采用更安全的方式来使用密钥
        // 请参见：https://cloud.tencent.com/document/product/1278/85305
        // 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
        $cred = new Credential($this->SecretId, $this->SecretKey);
        // 使用临时密钥示例
        // $cred = new Credential("SecretId", "SecretKey", "Token");
        // 实例化一个http选项，可选的，没有特殊需求可以跳过
        $httpProfile = new HttpProfile();
        $httpProfile->setEndpoint($this->endPoint);

        // 实例化一个client选项，可选的，没有特殊需求可以跳过
        $clientProfile = new ClientProfile();
        $clientProfile->setHttpProfile($httpProfile);
        // 实例化要请求产品的client对象,clientProfile是可选的
        $client = new CdnClient($cred, "", $clientProfile);

        return $client;
    }

    /**
     * @Description 刷新CDN
     * @Date 2025/6/19
     * @param array $path 示例值：["https://qq.com/index.html"]
     * @param string $type File：文件刷新 (PurgeUrlsCache 用于批量提交 URL 进行刷新，根据 URL 中域名的当前加速区域进行对应区域的刷新)
     *                     Directory：目录刷新 (用于批量提交目录刷新，根据域名的加速区域进行对应区域的刷新)
     * @param bool $force true-刷新全部资源，false-刷新变更资源 (仅当type为Directory时生效)
     * @return false|string|void
     */
    public function reload(array $path = [], string $type = '', bool $force = false)
    {
        try {
            $type = $type ?: 'File';

            $client = $this->createClient();

            $params = [];

            if ($type == 'File')
            {
                $params['Urls'] = $path;
                $req = new PurgeUrlsCacheRequest();
                $req->fromJsonString(json_encode($params));

                // 返回的resp是一个PurgeUrlsCacheResponse的实例，与请求对象对应
                $resp = $client->PurgeUrlsCache($req);
            }
            elseif ($type == 'Directory')
            {
                $params['Paths'] = $path;
                $params['FlushType'] = $force ? 'delete' : 'flush';
                $req = new PurgePathCacheRequest();
                $req->fromJsonString(json_encode($params));

                // 返回的resp是一个PurgePathCache的实例，与请求对象对应
                $resp = $client->PurgePathCache($req);
            }
            else
            {
                throw new \Exception("error type");
            }

            // 输出json格式的字符串回包
            return $resp->toJsonString();
        } catch(TencentCloudSDKException $e) {
            trace(sprintf("刷新cdn失败：%s", $e->getMessage()), 'error');
            throw $e;
        }
    }

    /**
     * @Description 预热CDN
     * @Date 2025/6/19
     * @param $path 示例值：["https://qq.com/index.html"]
     * @param string $area 预热生效区域 mainland：预热至境内节点 overseas：预热至境外节点 global：预热全球节点 不填充情况下，默认为 mainland， URL 中域名必须在对应区域启用了加速服务才能提交对应区域的预热任务
     * @return false|string|void
     */
    public function push($path = [], $area = '')
    {
       try {
           $client = $this->createClient();

           // 实例化一个请求对象,每个接口都会对应一个request对象
           $req = new PushUrlsCacheRequest();

           $params = [
               'Urls' => $path
           ];
           if (!empty($area)) $params['Area'] = $area;
           $req->fromJsonString(json_encode($params));

           // 返回的resp是一个PushUrlsCacheResponse的实例，与请求对象对应
           $resp = $client->PushUrlsCache($req);

           return $resp->toJsonString();
       } catch(TencentCloudSDKException $e) {
           trace(sprintf("预热cdn失败：%s", $e->getMessage()), 'error');
           throw $e;
       }
    }
}
