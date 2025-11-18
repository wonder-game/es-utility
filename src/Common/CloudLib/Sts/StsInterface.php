<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sts;

interface StsInterface
{
    /**
     * 获取临时访问密钥
     * @param mixed $policy 权限策略
     * @param int $expire 有效时间，单位秒
     * @return Response
     */
    public function get($policy, $expire = 1800): Response;
}
