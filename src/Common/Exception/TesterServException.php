<?php

namespace WonderGame\EsUtility\Common\Exception;

use WonderGame\EsUtility\Common\Http\Code;

class TesterServException extends \Exception
{
    public function __construct($message = "", $code = Code::ERROR_OTHER, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
