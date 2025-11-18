<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sts;

use EasySwoole\Spl\SplBean;

class Response extends SplBean
{
    /**
     * @var string token。token长度和绑定的策略有关，最长不超过4096字节。
     */
    protected $token;

    /**
     * @var string 临时证书密钥ID。最长不超过1024字节。
     */
    protected $tmpSecretId;

    /**
     * @var string 临时证书密钥Key。最长不超过1024字节。
     */
    protected $tmpSecretKey;

    /**
     * @var integer 临时访问凭证有效的时间，返回 Unix 时间戳，精确到秒
     */
    protected $expiredTime;

    /**
     * @var int 密钥生效时间，由程序自动生成
     */
    protected $startTime;

    /**
     * @var 云商RequestId，返回方便定位问题
     */
    protected $requestId;

    protected function initialize(): void
    {
        $this->startTime = time();
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getTmpSecretId()
    {
        return $this->tmpSecretId;
    }

    public function getTmpSecretKey()
    {
        return $this->tmpSecretKey;
    }

    public function getExpiredTime()
    {
        return $this->expiredTime;
    }
}
