<?php

namespace WonderGame\EsUtility\Common\CloudLib\Storage;

use EasySwoole\Oss\Tencent\Config;
use EasySwoole\Oss\Tencent\Exception\ServiceResponseException;
use EasySwoole\Oss\Tencent\OssClient;

/**
 * composer require easyswoole/oss
 */
class Tencent extends Base
{
    /**
     * Cos Client
     * @var OssClient
     */
    private $client;

    protected $secretId;
    protected $secretKey;
    protected $region;
    protected $bucket;

    protected function initialize(): void
    {
        $config = new Config([
            'secretId' => $this->secretId,
            'secretKey' => $this->secretKey,
            'region' => $this->region,
            'bucket' => $this->bucket
        ]);
        $this->client = new OssClient($config);
    }

    /**
     * 设置桶，默认取配置
     * @param string $bucket
     * @return static
     */
    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
        return $this;
    }

    public function upload($file, $key, $options = [])
    {
        if ( ! is_file($file)) {
            throw new \Exception("file {$file} not exists");
        }
        $stream = fopen($file, 'rb');

        try {
            $result = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $stream
            ]);

        } catch (ServiceResponseException $e) {
            trace(sprintf('%s, %s', $e, $e->getCosErrorType()), 'error');
        } catch (\Exception $e) {
            trace($e->getMessage(), 'error');
        } finally {
            is_resource($stream) && fclose($stream);
        }
        if ( ! empty($e)) {
            throw  $e;
        }
        return $result['Location'];
    }

    public function delete($key, $options = [])
    {
        try {
            $result = $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => ltrim($key, '/'),
            ]);
            return $result;
        } catch (\Exception $e) {
            trace($e->getMessage(), 'error');
            throw $e;
        }
    }

    public function uploadPart($file, $key, $partSize = 10 * 1024 * 1024, $options = [])
    {
        try {
            $body = fopen($file, 'rb');
            $this->client->upload($this->bucket, $key, $body, ['PartSize' => $partSize] + $options);
        } catch (ServiceResponseException $e) {
            trace(sprintf('%s, %s', $e, $e->getCosErrorType()), 'error');
        } catch (\Exception $e) {
            trace($e->getMessage(), 'error');
        } finally {
            is_resource($body) && fclose($body);
        }
        if ( ! empty($e)) {
            throw  $e;
        }
    }

    function doesObjectExist($key, $options = [])
    {
        try {
            return $this->client->doesObjectExist($this->bucket, ltrim($key, '/'));
        } catch (\Exception $e) {
            trace($e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * 复制OSS对象
     * @param $formKey
     * @param $toKey
     * @param $options
     * @return void
     * @throws \Exception
     * @document https://cloud.tencent.com/document/product/436/64284
     */
    public function copy($formKey, $toKey, $options = [])
    {
        try {
            $copySource = [$this->bucket, 'cos', $this->region, 'myqcloud.com/' . ltrim($formKey, '/')];
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $toKey,
                'CopySource' => implode('.', $copySource)
            ]);
        } catch (\Exception $e) {
            trace($e->__toString(), 'error');
            throw $e;
        }
    }

    /**
     * 重命名OSS对象
     * @param $formKey
     * @param $toKey
     * @param $options
     * @return void
     * @throws \Exception
     * @document https://cloud.tencent.com/document/product/436/64284
     */
    public function rename($formKey, $toKey, $options = [])
    {
        $this->copy($formKey, $toKey, $options);
        $this->delete($formKey, $options);
    }

    /**
     * 客户端直传对象存储,一般用于超超大文件
     * 1. 对象存储需要开放允许跨域 ：  安全管理 -> 允许跨域设置 *
     * 2. 对象存储需要设置Policy权限： 给cos子账号允许对象存储操作
     * @param $expire
     * @return array
     * @throws \TencentCloud\Common\Exception\TencentCloudSDKException
     */
    public function stsUpload($expire = 3600)
    {
        $Sts = new \WonderGame\EsUtility\Common\CloudLib\Sts\Tencent($this->toArray());

        $policy = [
            'version' => '2.0',
            'statement' => [
                [
                    'effect' => 'allow',
                    // 那些资源权限
                    'resource' => '*',
                    // https://cloud.tencent.com/document/product/436/65935
                    'action' => [
                        // 最小粒度原则，不给所有权限
                        //'name/cos:*'

                        // 基础上传（小文件）
                        'name/cos:PutObject',

                        // 分片上传（大文件必选）
                        'name/cos:InitiateMultipartUpload',  // 初始化分片
                        'name/cos:UploadPart',               // 上传分片
                        'name/cos:CompleteMultipartUpload',  // 完成分片
                        'name/cos:AbortMultipartUpload',     // 取消分片（可选，建议保留）

                        // 断点续传
                        'name/cos:ListMultipartUploads',
                        'name/cos:ListParts',

                        // 删除权限
                        'name/cos:DeleteObject',

                        // 无需计算文件md5权限，SDK内部会校验处理
                    ],
                ],
            ],
        ];

        $stsResponse = $Sts->get(json_encode($policy), $expire);
        $data = $stsResponse->toArray();

        // 除了基本的密钥信息之外，还需要给客户端返回对象存储信息
        $data['bucket'] = $this->bucket;
        $data['driver'] = $this->getClassName();

        $data['region'] = $this->region;

        return $data;
    }
}
