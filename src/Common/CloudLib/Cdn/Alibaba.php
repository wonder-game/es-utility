<?php

namespace WonderGame\EsUtility\Common\CloudLib\Cdn;

use AlibabaCloud\SDK\Cdn\V20180510\Cdn;
use AlibabaCloud\SDK\Cdn\V20180510\Models\PushObjectCacheRequest;
use AlibabaCloud\SDK\Cdn\V20180510\Models\RefreshObjectCachesRequest;
use AlibabaCloud\Tea\Exception\TeaError;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use Darabonba\OpenApi\Models\Config;

/**
 * @document https://help.aliyun.com/zh/cdn/developer-reference/api-cdn-2018-05-10-refreshobjectcaches?spm=a2c4g.11186623.0.0.424f604bO4xuaV
 * Api调试：https://api.aliyun.com/api/Cdn/2018-05-10/PushObjectCache?params={%22ObjectPath%22:%22%2Faa%2Fd%2Faaa.apk%22,%22Area%22:%22domestic%22}
 * secret: https://help.aliyun.com/document_detail/311677.html
 *
 * composer require alibabacloud/cdn-20180510 3.1.2
 */
class Alibaba extends Base
{
    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    /**
     * Endpoint 请参考 https://api.aliyun.com/product/Cdn
     * @var string
     */
    protected $endpoint = 'cdn.aliyuncs.com';

    /**
     * 使用AK&SK初始化账号Client
     * @param string $accessKeyId
     * @param string $accessKeySecret
     * @return Cdn Client
     */
    public function createClient(){
        $config = new Config([
            'accessKeyId' => $this->accessKeyId,
            'accessKeySecret' => $this->accessKeySecret,
            'endpoint' => $this->endpoint
        ]);
        return new Cdn($config);
    }

    /**
     * @param string|array $path 刷新URL，格式为加速域名或刷新的文件或目录,多个URL之间使用换行符（\n）或（\r\n）分隔。
     *                              示例值http://example.com/image/1.png\nhttp://aliyundoc.com/image/2.png
     * @param string $type File：文件刷新。
     *                     Directory：目录刷新。目录刷新采用强制删除目录的处理方式，后续有用户访问目录下的资源时，CDN节点将会回源站获取最新的文件。
     *                     Regex：正则刷新。
     *                     IgnoreParams：去参数刷新。去参数指的是去除请求URL中?及?之后的参数，去参数刷新指的是用户先通过接口提交去参数后的URL，然后用户提交的待刷新URL将会与已缓存资源的URL进行去参数匹配，如果已缓存资源的URL去参数以后与待刷新URL匹配，那么CDN节点将对缓存资源执行刷新处理。
     * @param bool $force true-刷新全部资源，false-刷新变更资源
     * @return void
     */
    public function reload(array $path = [], string $type = '', bool $force = false)
    {
        $type = $type ?: 'File';
        $path = implode('\n', $path);

        $client = $this->createClient();
        $refreshObjectCachesRequest = new RefreshObjectCachesRequest([
            'objectType' => $type,
            'objectPath' => $path,
            'force' => true,
        ]);
        $runtime = new RuntimeOptions([]);
        try {
            $client->refreshObjectCachesWithOptions($refreshObjectCachesRequest, $runtime);
        }
        catch (\Exception $error)
        {
            if (!($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            $msg = "阿里云CDN刷新失败，path=$path, tyep=$type, error=" . $error->__toString();
            trace($msg, 'error');
            throw $error;
        }
    }

    /**
     * @param $path 预热URL，格式为加速域名/预热的文件。
                        多个URL之间用换行符（\n）或（\r\n）分隔，ObjectPath的单条长度最长为1024个字符。
                        示例值: example.com/image/1.png\nexample.org/image/2.png
     * @param $area 预热区域。取值： domestic：仅中国内地。 overseas：全球（不包含中国内地）
     * @return void
     */
    public function push($path = [], $area = '')
    {
        $area = $area ?: 'domestic';
        is_array($path) && $path = implode('\n', $path);
        $client = $this->createClient();
        $pushObjectCacheRequest = new PushObjectCacheRequest([
            'objectPath' => $path,
            'area' => $area
        ]);
        $runtime = new RuntimeOptions([]);
        try {
            $client->pushObjectCacheWithOptions($pushObjectCacheRequest, $runtime);
        }
        catch (\Exception $error)
        {
            if (!($error instanceof TeaError)) {
                $error = new TeaError([], $error->getMessage(), $error->getCode(), $error);
            }
            $msg = "阿里云CDN预热失败，path=$path, tyep=$area, error=" . $error->__toString();
            trace($msg, 'error');
            throw $error;
        }
    }
}
