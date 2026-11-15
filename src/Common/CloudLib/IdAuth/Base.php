<?php

namespace WonderGame\EsUtility\Common\CloudLib\IdAuth;

abstract class Base implements IdAuthInterface
{
    // 请求失败标识，可选择切换云商
    const REQUEST_ERR = 460;

    protected $gameid;

    protected $_result = [];

    /** @var callable|null 取游戏配置的回调 function(int $gameid): array，由使用方的框架注册 */
    protected static $gameConfigResolver;

    public function __construct($gameid)
    {
        $this->gameid = $gameid;

        $this->setConfig();
    }

    /**
     * 注册取游戏配置的回调，个别云商的密钥、备案号配在游戏上（如 Nppa），公共库不直接依赖业务模型
     * 使用方在框架初始化时调用一次：function (int $gameid): array { return 游戏配置; }
     * @param callable $resolver
     */
    public static function setGameConfigResolver(callable $resolver)
    {
        static::$gameConfigResolver = $resolver;
    }

    /**
     * 当前游戏配置，没注册回调时返回空数组
     * @return array
     */
    protected function gameConfig(): array
    {
        return static::$gameConfigResolver ? (array)call_user_func(static::$gameConfigResolver, $this->gameid) : [];
    }

    public function getResult()
    {
        return $this->_result;
    }

    protected function setConfig()
    {
        /*
         * $array 示例 [
            [0] => App
            [1] => Common
            [2] => IdAuth
            [3] => AlibabaBcustomer
        ]
         */
        $array = explode('\\', static::class);
        $name = ucfirst(end($array));

        $config = config("ID_AUTH.$name") ?: [];

        // 允许按游戏覆盖配置值
        if ( ! empty($config[$this->gameid]) && is_array($config[$this->gameid])) {
            $config = $config[$this->gameid] + $config;
        }

        foreach ($config as $key => $val) {
            if (property_exists($this, $key)) {
                $this->$key = $val;
            }
        }
    }
}
