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
}
