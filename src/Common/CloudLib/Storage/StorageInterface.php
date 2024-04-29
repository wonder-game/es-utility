<?php

namespace WonderGame\EsUtility\Common\CloudLib\Storage;

interface StorageInterface
{
    /**
     * 判断云端文件是否已存在
     * @param $key
     * @param $options
     * @return mixed
     */
    function doesObjectExist($key, $options = []);

    /**
     * 简单上传，上传文件到云端
     * @param string $file 本地文件绝对路径
     * @param string $key 云端相对路径
     * @param array $options
     * @return mixed
     */
    function upload($file, $key, $options = []);

    /**
     * 分片上传
     * @param string $file 文件本地绝对路径
     * @param string $key 云端相对路径
     * @param int $partSize 分片大小
     * @param $options
     * @return mixed
     */
    function uploadPart($file, $key, $partSize = 10 * 1024 * 1024, $options = []);

    /**
     * 删除云端文件
     * @param string $key 云端相对路径
     * @param array $options
     */
    function delete($key, $options = []);
}
