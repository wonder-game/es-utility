<?php

namespace WonderGame\EsUtility\Common\Classes;


use EasySwoole\Component\CoroutineSingleTon;
use EasySwoole\Http\Request;
use EasySwoole\Socket\Bean\Caller;
use Swoole\Coroutine;

/**
 * 通用协程单例对象
 * Class MyCoroutine
 * @package App\Common\Classes
 */
class CtxRequest
{
    use CoroutineSingleTon;

    /**
     * Request对象
     * @var Request|null
     */
    protected $request = null;

    /**
     * @var null | Caller
     */
    protected $caller = null;

    protected $operinfo = [];

    public function getOperinfo(): array
    {
        return $this->operinfo;
    }

    public function withOperinfo(array $operinfo = []): void
    {
        if ($this->request instanceof Request) {
            $this->request->withAttribute('operinfo', $operinfo);
        }
        $this->operinfo = $operinfo;
    }

    /*************** 协程内判断方法，备选方案，在不方便调$server->connection_info($fd);的场景使用 *************/
    public function isHttp(): bool
    {
        return $this->request instanceof Request;
    }

    public function isWebSocket(): bool
    {
        return $this->caller instanceof Caller;
    }

    public function __set($name, $value)
    {
        $name = strtolower($name);
        $this->{$name} = $value;
    }

    public function __get($name)
    {
        $name = strtolower($name);
        if (property_exists($this, $name)) {
            return $this->{$name};
        } else {
            $cid = Coroutine::getCid();
            throw new \Exception("[cid:{$cid}]CtxRequest Not Exists Protected: $name");
        }
    }
}
