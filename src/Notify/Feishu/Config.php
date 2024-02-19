<?php

namespace WonderGame\EsUtility\Notify\Feishu;

use WonderGame\EsUtility\Notify\Interfaces\ConfigInterface;
use WonderGame\EsUtility\Notify\Interfaces\NotifyInterface;
use EasySwoole\Spl\SplBean;

class Config extends SplBean implements ConfigInterface
{
    /**
     * WebHook
     * @var string
     */
    protected $url = '';

    /**
     * 密钥
     * @var string
     */
    protected $signKey = '';

    /**
     * 要@哪些人（Open ID 或 User ID）, true-所有人,false-谁也不鸟
     * @var array|bool
     */
    protected $at = false;

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setSignKey($signKey)
    {
        $this->signKey = $signKey;
    }

    public function getSignKey()
    {
        return $this->signKey;
    }

    public function setAt($at)
    {
        $this->at = $at;
    }

    public function getAt()
    {
        return $this->at;
    }

    public function getNotifyClass(): NotifyInterface
    {
        return new Notify($this);
    }
}
