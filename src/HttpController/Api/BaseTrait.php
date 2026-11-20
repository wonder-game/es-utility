<?php

namespace WonderGame\EsUtility\HttpController\Api;

use WonderGame\EsUtility\Common\Classes\LamOpenssl;

trait BaseTrait
{
    protected $rsa = [];

    protected function onRequest(?string $action): ?bool
    {
        return parent::onRequest($action)
            && ($this->input['encry'] == 'md5' ? $this->_checkMd5Sign() : $this->_checkRsaSign());
    }

    protected function _checkRsaSign()
    {
        $secret = $this->input[config('RSA.key')];
        if ( ! $secret) {
            return false;
        }
        $data = LamOpenssl::getInstance()->decrypt($secret);
        $this->rsa = json_decode($data, true);
        return is_array($this->rsa);
    }

    protected function _checkMd5Sign()
    {
        /* @var \EasySwoole\Http\AbstractInterface\Controller $this */
        $this->rsa = $this->input;
        // 验签取原始参数，不用 $this->input（可能被 httpOnRequestFunc 改写过），见 request_raw_params
        return lan_sign_verify(request_raw_params($this->request()));
    }
}
