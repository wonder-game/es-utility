<?php

namespace WonderGame\EsUtility\HttpTracker;

use EasySwoole\Http\Message\Status;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Tracker\PointContext;

class Index extends PointContext
{
    public function __construct(array $handleConfig = [])
    {
        $this->enableAutoSave()->setSaveHandler(new SaveHandler($handleConfig));
    }

    public static function startArgsRequest(Request $request, array $merge = [])
    {
        libxml_use_internal_errors(true);
        $_body = $request->getBody()->__toString() ?: $request->getSwooleRequest()->rawContent();
        $arr = [
            'url' => $request->getUri()->__toString(),
            'ip' => ip($request),
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'server_name' => config('SERVNAME'),
            'header' => $request->getHeaders(),
//            'server' => $request->getServerParams(),
        ];

        // 有值才记录
        $extArr = [
            'GET' => $request->getQueryParams(),
            'POST' => $request->getParsedBody(),
            'JSON' => json_decode($_body, true),
            // 主要是记录微信支付回调
            'XML' => json_decode(json_encode(simplexml_load_string($_body, 'SimpleXMLElement', LIBXML_NOCDATA)), true)
        ];

        foreach ($extArr as $key => $val) {
            if ( ! empty($val)) {
                $arr[$key] = $val;
            }
        }

        return array_merge($arr, $merge);
    }

    public static function endArgsResponse(Response $response, array $merge = [])
    {
        $data = $response->getBody()->__toString();
        if (is_string($data) && ($array = json_decode($data, true))) {
            $data = $array;
        }
        $code = $response->getStatusCode();

        // 302重定向，则记录Location地址
        if ($code === Status::CODE_MOVED_TEMPORARILY) {
            $data = $response->getHeader('Location');
        }
        return ['httpStatusCode' => $code, 'data' => $data] + $merge;
    }
}
