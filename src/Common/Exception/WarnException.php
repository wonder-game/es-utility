<?php

namespace WonderGame\EsUtility\Common\Exception;

use WonderGame\EsUtility\Common\Http\Code;

class WarnException extends \Exception
{
    protected $data = [];

    /**
     * @param string $message 用户可见
     * @param int $code
     * @param array $data
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = Code::ERROR_OTHER, array $data = [], Throwable $previous = null)
    {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    public function getData()
    {
        return $this->data;
    }
}
