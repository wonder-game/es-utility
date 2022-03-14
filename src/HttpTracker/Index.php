<?php

namespace Linkunyuan\EsUtility\HttpTracker;

use EasySwoole\Tracker\PointContext;

use Linkunyuan\EsUtility\HttpTracker\SaveHandler;

class Index extends PointContext
{
    public function __construct()
    {
        $this->enableAutoSave()->setSaveHandler(new SaveHandler());
    }

    public static function formatEndData($httpCode, $data = [])
    {
        if (is_string($data) && ($array = json_decode($data, true)))
        {
            $data = $array;
        }
        return ['httpStatusCode' => $httpCode, 'data' => $data ?: []];
    }
}
