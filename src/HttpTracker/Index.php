<?php

namespace WonderGame\EsUtility\HttpTracker;

use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Tracker\PointContext;

use WonderGame\EsUtility\HttpTracker\SaveHandler;

class Index extends PointContext
{
    public function __construct(array $handleConfig = [])
    {
        $this->enableAutoSave()->setSaveHandler(new SaveHandler($handleConfig));
    }

    public static function startArgsRequest(Request $request, array $merge = [])
    {
        return array_merge([
            'url' => $request->getUri()->__toString(),
            'ip' => ip($request),
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'server_name' => config('SERVNAME'),
            'header' => $request->getHeaders(),
//            'server' => $request->getServerParams(),
            'GET' => $request->getQueryParams(),
            'POST' => $request->getParsedBody() ?: json_decode($request->getBody()->__toString(), true),
        ], $merge);
    }

    public static function endArgsResponse(Response $response, array $merge = [])
    {
        $data = $response->getBody()->__toString();
        if (is_string($data) && ($array = json_decode($data, true)))
        {
            $data = $array;
        }
        return ['httpStatusCode' => $response->getStatusCode(), 'data' => $data] + $merge;
    }
}
