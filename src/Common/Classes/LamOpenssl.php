<?php
/**
 * openssl加解密
 */


namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Component\Singleton;

class LamOpenssl
{
    private static $instance = [];
    private $_privateKey = ''; // 私钥
    private $_publicKey = ''; // 公钥

    /**
     * @param mixed ...$args
     * @return static
     */
    static function getInstance(...$args)
    {
        $key = $args[2] ?? 'default';

        if ( ! isset(static::$instance[$key])) {
            static::$instance[$key] = new static(...$args);
        }
        return static::$instance[$key];
    }

    /**
     * LamRsa constructor.
     * @param string $prvfile 私钥文件
     * @param string $pubfile 公钥文件
     */
    public function __construct($prvfile, $pubfile)
    {
        if ( ! extension_loaded('openssl')) {
            throw new \RuntimeException('php需要openssl扩展支持');
        }
        if ( ! is_file($prvfile) || ! is_file($pubfile)) {
            throw new \RuntimeException('找不到密钥文件');
        }

        $this->_privateKey = openssl_pkey_get_private(file_get_contents($prvfile));
        $this->_publicKey = openssl_pkey_get_public(file_get_contents($pubfile));

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
