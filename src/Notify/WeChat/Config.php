<?php

namespace WonderGame\EsUtility\Notify\WeChat;

use EasySwoole\Spl\SplBean;
use WonderGame\EsUtility\Notify\Interfaces\ConfigInterface;
use WonderGame\EsUtility\Notify\Interfaces\NotifyInterface;

/**
 * @document https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Template_Message_Interface.html
 *
 * 更新公告：https://mp.weixin.qq.com/cgi-bin/announce?action=getannouncement&announce_id=11680142498cInTZ&version=&lang=zh_CN&token=2080631067
 *
 */
class Config extends SplBean implements ConfigInterface
{
    /**
     * 微信公众平台后台的 appid
     * @var string
     */
    protected $appId = '';

    /**
     * 微信公众平台后台配置的 AppSecret
     * @var string
     */
    protected $appSecret = '';

    /**
     * 微信公众平台后台配置的 Token
     * @var string
     */
    protected $token = '';

    /**
     * 注册WeChat实例时追加的配置
     * @var array
     */
    protected $append = [];

    /**
     * 发送给谁, openid[]
     * @var array
     */
    protected $toOpenid = [];

    /**
     * 点击后跳转地址
     * @var string
     */
    protected $url = '';

    public function setAppId($appId)
    {
        $this->appId = $appId;
    }

    public function getAppId()
    {
        return $this->appId;
    }

    public function setAppSecret($appSecret)
    {
        $this->appSecret = $appSecret;
    }

    public function getAppSecret()
    {
        return $this->appSecret;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setAppend(array $append = [])
    {
        $this->append = $append;
    }

    public function getAppend()
    {
        return $this->append;
    }

    public function setToOpenid($openid)
    {
        if (is_string($openid)) {
            $openid = [$openid];
        }
        $this->toOpenid = $openid;
    }

    public function getToOpenid()
    {
        return $this->toOpenid;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getNotifyClass(): NotifyInterface
    {
        return new Notify($this);
    }
}
