<?php

namespace WonderGame\EsUtility\Common\CloudLib\Dns;

use WonderGame\EsUtility\Common\Exception\HttpParamException;

abstract class Base implements DnsInterface
{
    /**
     * 主域名
     * @var string
     */
    protected $domain = '';

    public function setDomain($domain)
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * 过滤无效参数、检查必传参数
     * @param $array
     * @param $columns
     * @return array|mixed
     * @throws HttpParamException
     */
    protected function filter($array = [], $columns = [])
    {
        if ( ! empty($columns)) {
            $tmp = [];
            foreach ($columns as $column)
            {
                if ( ! isset($array[$column])) {
                    throw new HttpParamException("缺少必传参数: $column");
                }
                $tmp[$column] = $array[$column];
            }
            $array = $tmp;
        }
        return $array;
    }
}
