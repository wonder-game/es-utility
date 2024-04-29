<?php

namespace WonderGame\EsUtility\Common\CloudLib\Storage;

use EasySwoole\Oss\AliYun\Config;
use EasySwoole\Oss\AliYun\Core\OssException;
use EasySwoole\Oss\AliYun\Core\OssUtil;
use EasySwoole\Oss\AliYun\OssClient;
use EasySwoole\Oss\AliYun\OssConst;

/**
 * composer require easyswoole/oss
 */
class Alibaba extends Base
{
    /**
     * @var null | OssClient
     */
    protected $client = null;

    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    protected $endpoint = '';

    protected $bucket = '';

    protected function initialize(): void
    {
        try {

            $config = new Config([
                'accessKeyId' => $this->accessKeyId,
                'accessKeySecret' => $this->accessKeySecret,
                'endpoint' => $this->endpoint,
            ]);
            $this->client = new OssClient($config);

        } catch (OssException $e) {
            trace($e->__toString(), 'error');
            throw $e;
        }
    }

    public function doesObjectExist($key, $options = [])
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $key, $options);
        } catch (OssException $e) {
            trace($e->__toString(), 'error');
            throw $e;
        }
    }

    public function upload($file, $key, $options = [])
    {
        try {
            $this->client->uploadFile($this->bucket, $key, $file);
        } catch (OssException $e) {
            trace($e->__toString(), 'error');
            throw $e;
        }
    }

    public function delete($key, $options = [])
    {
        try {
            $this->client->deleteObject($this->bucket, $key, $options);
        } catch (OssException $e) {
            trace($e->__toString(), 'error');
            throw $e;
        }
    }

    /**
     * @document https://help.aliyun.com/zh/oss/user-guide/multipart-upload-12?spm=a2c4g.11186623.0.0.5ff17b87QSvl4E
     */
    public function uploadPart($file, $key, $partSize = 10 * 1024 *1024, $options = [])
    {
        // 初始化一个分块上传事件, 也就是初始化上传Multipart, 获取upload id
        try {
            $upload_id = $this->client->initiateMultipartUpload($this->bucket, $key);
        } catch (OssException $e) {
            trace("OSS分片上传初始化失败: " . $e->__toString(), 'error');
            throw $e;
        }

        // 上传分片
        $uploadFilesize = filesize($file);
        $pieces = $this->client->generateMultiuploadParts($uploadFilesize, $partSize);
        $responseUploadPart = [];
        $uploadPosition = 0;
        $isCheckMd5 = true;
        foreach ($pieces as $i => $piece) {
            $fromPos = $uploadPosition + (integer)$piece[OssConst::OSS_SEEK_TO];
            $toPos = (integer)$piece[OssConst::OSS_LENGTH] + $fromPos - 1;
            $upOptions = [
                OssConst::OSS_FILE_UPLOAD => $file,
                OssConst::OSS_PART_NUM => ($i + 1),
                OssConst::OSS_SEEK_TO => $fromPos,
                OssConst::OSS_LENGTH => $toPos - $fromPos + 1,
                OssConst::OSS_CHECK_MD5 => $isCheckMd5,
            ];
            if ($isCheckMd5) {
                $upOptions[OssConst::OSS_CONTENT_MD5] = OssUtil::getMd5SumForFile($file, $fromPos, $toPos);
            }
            $upOptions = array_merge($upOptions, $options);

            //2. 将每一分片上传到OSS
            try {
                $responseUploadPart[] = $this->client->uploadPart($this->bucket, $key, $upload_id, $upOptions);
            } catch (OssException $e) {
                trace("OSS分片上传分片失败: " . $e->__toString(), 'error');
                throw $e;
            }
        }
        $upload_parts = [];
        foreach ($responseUploadPart as $i => $eTag) {
            $upload_parts[] = [
                'PartNumber' => $i + 1,
                'ETag' => $eTag,
            ];
        }

        try {
            $listPartsInfo = $this->client->listParts($this->bucket, $key, $upload_id);
            trace("OSS上传的分片列表：" . var_export($listPartsInfo, true));
            if (empty($listPartsInfo)) {
                throw new OssException("分片失败， 分片列表为空");
            }
        } catch (OssException $e) {
            trace("OSS分片失败: " . $e->__toString(), 'error');
            throw $e;
        }

        try {
            $this->client->completeMultipartUpload($this->bucket, $key, $upload_id, $upload_parts);
        } catch (OssException $e) {
            trace("OSS分片complete调用失败: " . $e->__toString(), 'error');
            throw $e;
        }
    }
}
