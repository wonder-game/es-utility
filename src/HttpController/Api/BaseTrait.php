<?php

namespace WonderGame\EsUtility\HttpController\Api;

use WonderGame\EsUtility\Common\Classes\LamOpenssl;

trait BaseTrait
{
    protected $rsa = [];

    protected function onRequest(?string $action): ?bool
    {
        return parent::onRequest($action) && $this->getRsaParams();
    }

    protected function getRsaParams()
    {
        $rsaConfig = config('RSA');
        $secret = $this->input[$rsaConfig['key']];
        if ( ! $secret) {
            return false;
        }
        $data = LamOpenssl::getInstance($rsaConfig['private'], $rsaConfig['public'])->decrypt($secret);
        $this->rsa = json_decode($data, true);
        return is_array($this->rsa);
    }
}
