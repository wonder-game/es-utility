<?php

namespace WonderGame\EsUtility\Common\CloudLib\Dns;

interface DnsInterface
{
    /**
     * 获取域名的解析列表
     * @param string $type 解析类型,默认A类
     * @param int $limit
     * @return mixed
     */
    function list(string $type = 'A', int $limit = 100): array;

    /**
     * 添加域名解析记录
     * @param array $array
     * @return mixed
     */
    function create(array $array);

    /**
     * 修改域名解析记录
     * @param array $array
     * @return mixed
     */
    function modify(array $array);

    /**
     * 删除域名解析记录
     * @param array $array
     * @return mixed
     */
    function delete(array $array);

    /**
     * 设置主域名
     * @param $domain
     * @return self
     */
    function setDomain($domain);
}
