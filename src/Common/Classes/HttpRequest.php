<?php

namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\HttpClient\Bean\Response;
use EasySwoole\HttpClient\HttpClient;
use Error;
use Exception;
use Swoole\Coroutine;

class HttpRequest
{
    /**
     * 统一处理请求
     * @param string $type 请求类型：hCurl 或 curl
     * @param string|array $url string时为要请求的完整网址和路径；数组时为便捷传参方式
     * @param array|string $data 请求参数，xml提交为string
     * @param string $method 提交方式：get|post|xml|json|put|delete|head|options|trace|patch
     * @param array $header 请求头
     * @param array $cfg 配置  resultType,retryCallback,retryTimes
     * @param array $option 其它属性
     * @throws Exception|Error
     */
    public function request($type, $url = '', $data = [], $method = 'post', $header = [], $cfg = [], $option = [])
    {
        is_array($url) && extract($url);
        if ( ! is_scalar($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            $e = '发起请求失败！非法url:' . json_encode(func_get_args());
            trace($e, 'error');
            throw new Error($e);
        }

        // 默认配置
        $defaults = [
            'resultType' => 'json',
            'retryCallback' => function ($code) {
                return in_array($code, [200, 302, 303, 307]);
            },
            'retryTimes' => 3,
        ];
        $cfg = array_merge($defaults, $cfg);

        $method = strtolower($method);

        try {
            $sendType = "_$type";
            if ( ! method_exists($this, $sendType)) {
                throw new Error("未知的请求类型：$sendType");
            }
            $response = $this->$sendType($url, $data, $method, $header, $option);
        } catch (Exception $e) {
            $err = "{$url}请求失败！信息为：{$e->getMessage()} 传参为：" . json_encode(func_get_args());
            trace($err, 'error');
            throw new Exception($err, $e->getCode());
        }

        if ($response instanceof Response) {
            $responseBody = $response->getBody();
            $code = $response->getStatusCode();
        } else {
            $responseBody = $response['response'];
            $code = $response['code'];
        }

        // 自动重试
        if (is_callable($cfg['retryCallback']) && ! $cfg['retryCallback']($code)) {
            if ($cfg['curt'] < $cfg['retryTimes']) {
                Coroutine::sleep(0.5);
                return $this->request($type, $url, $data, $method, $header, ['curt' => ++$cfg['curt']] + $cfg, $option);
            }
            $err = "{$url}响应失败！状态码为：{$code} 传参为：" . json_encode(func_get_args());
            trace($err, 'error');
            throw new Exception($err);
        }

        switch ($cfg['resultType']) {
            case 'xml':
                return $this->xml($responseBody);
            case 'json':
                return json_decode($responseBody, true);
            case 'body':
            default:
                return $responseBody;
        }
    }

    /**
     * 发送请求
     * @param string $url 请求地址
     * @param array|string $data 请求数据
     * @param string $method 请求方法
     * @param array $header 请求头
     * @param array $option 其它选项
     * @return Response
     * @throws Exception
     */
    protected function _hCurl($url, $data, $method, $header, $option)
    {
        $client = new HttpClient($url);
        if (stripos($url, 'https://') === 0) {
            $client->setEnableSSL();
        }
        $client->setClientSettings($option + ['keepAlive' => true], false);
        $client->setHeaders($header, false, false);

        $calls = [
            'get' => function ($data) use ($client) {
                $client->setQuery($data);
                return $client->get();
            },
            'xml' => 'postXml',
            'json' => function ($data) use ($client) {
                is_array($data) and $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                return $client->postJson($data);
            },
            'download' => function ($data) use ($client, $url, $option) {
                return $client->download($url, $option['offset'] ?? 0, HttpClient::METHOD_GET, $data);
            },
        ];

        $func = $calls[$method] ?? $method;
        return is_string($func) ? $client->$func($data) : $func($data);
    }

    /**
     * 发送请求
     * @param string $url 请求地址
     * @param array|string $data 请求数据
     * @param string $method 请求方法
     * @param array $header 请求头
     * @param array $option 其它选项
     * @return array
     * @throws Exception
     */
    protected function _curl($url, $data, $method, $header, $option)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        switch ($method) {
            case 'get':
                $url .= '?' . http_build_query($data);
                curl_setopt($ch, CURLOPT_URL, $url);
                break;
            case 'post':
            case 'put':
            case 'delete':
            case 'patch':
            case 'options':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
                break;
            case 'head':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
                break;
            case 'xml':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                $header['Content-Type'] = 'text/xml';
                break;
            case 'json':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
                $header['Content-Type'] = 'application/json';
                break;
            default:
                $e = "不支持的请求方式：$method";
                trace($e, 'error');
                throw new Error($e);
        }

        if ($header) {
            $headerArray = array_map(function ($key, $value) {
                return "$key: $value";
            }, array_keys($header), $header);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        }

        foreach ($option as $key => $val) {
            curl_setopt($ch, $key, $val);
        }

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch), curl_errno($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['response' => $response, 'code' => $httpCode];
    }

    /**
     * 获取xml格式的内容
     * @see https://www.w3.org/TR/2008/REC-xml-20081126/#charsets - XML charset range
     * @see http://php.net/manual/en/regexp.reference.escape.php - escape in UTF-8 mode
     * @param string $body 内容
     * @return array|object
     */
    private function xml(string $body)
    {
        $backup = libxml_disable_entity_loader(true);

        $xml = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]+/u', '', $body);

        $result = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_NOCDATA | LIBXML_NOBLANKS);

        libxml_disable_entity_loader($backup);

        return (array)$result;
    }
}