<?php

namespace WonderGame\EsUtility\Common\Classes;

/**
 * 可动态实例化RSA加密类
 */
class Openssl
{
    /**
     * @var resource 私钥类
     */
    private $_privateKey;

    /**
     * @var resource 公钥类
     */
    private $_publicKey;

    public function __construct($prvfile, $pubfile)
    {
        if ( ! extension_loaded('openssl')) {
            throw new \RuntimeException('php需要openssl扩展支持');
        }

        if ($prvfile) {
            $prvResource = is_file($prvfile) ? file_get_contents($prvfile) : $prvfile;
            $this->_privateKey = openssl_pkey_get_private($prvResource);
        }
        if ($pubfile) {
            $pubResource = is_file($pubfile) ? file_get_contents($pubfile) : $pubfile;
            $this->_publicKey = openssl_pkey_get_public($pubResource);
        }

        if ( ! $this->_privateKey || ! $this->_publicKey) {
            throw new \RuntimeException('私钥或者公钥不可用');
        }
    }


    // 公钥加密
    public function publicEncrypt($data)
    {
        return $this->encrypt($data, 'public');
    }

    // 公钥解密
    public function publicDecrypt($data)
    {
        return $this->decrypt($data, 'public');
    }

    // 私钥加密
    public function privateEncrypt($data)
    {
        return $this->encrypt($data, 'private');
    }

    // 私钥解密
    public function privateDecrypt($data)
    {
        return $this->decrypt($data, 'private');
    }

    // 加密
    public function encrypt($data = '', $type = 'public')
    {
        $crypto = $encrypt = '';
        $func = "openssl_{$type}_encrypt";
        $key = "_{$type}Key";
        foreach (str_split($data, 117) as $chunk) {
            $func($chunk, $encrypt, $this->$key);
            $crypto .= $encrypt;
        }
        $crypto = $this->urlsafeB64encode($crypto);
        return $crypto;
    }

    // 解密
    public function decrypt($data = '', $type = 'private')
    {
        $crypto = $decrypt = '';
        $func = "openssl_{$type}_decrypt";
        $key = "_{$type}Key";
        foreach (str_split($this->urlsafeB64decode($data), 128) as $chunk) {
            $func ($chunk, $decrypt, $this->$key);
            $crypto .= $decrypt;
        }
        return $crypto;
    }

    //加密码时把特殊符号替换成URL可以带的内容
    public function urlsafeB64encode($string)
    {
        $string = base64_encode($string);
        $string = str_replace(['+', '/', '='], ['-', '_', ''], $string);
        return $string;
    }

    //解密码时把转换后的符号替换特殊符号
    public function urlsafeB64decode($string)
    {
        $string = str_replace(['-', '_', ' ', '\n'], ['+', '/', '+', ''], $string);
        $mod4 = strlen($string) % 4;
        if ($mod4) {
            $string .= substr('====', $mod4);
        }
        return base64_decode($string);
    }
}
