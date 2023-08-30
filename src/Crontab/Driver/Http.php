<?php

namespace WonderGame\EsUtility\Crontab\Driver;

use EasySwoole\HttpClient\HttpClient;
use WonderGame\EsUtility\Common\Classes\LamOpenssl;

class Http implements Interfaces
{
    public function list(): array
    {
        $url = rtrim(config('CRONTAB.url'), '/') . '/api/crontab/list';
        $HttpClient = new HttpClient($url);
        //echo $url;
        $body = $this->body(config('CRONTAB.post') ?: []);
        //print_r($body);
        $Resp = $HttpClient->post($body);

        $json = $Resp->getBody();

        if ($Resp->getStatusCode() !== 200) {
            throw new \Exception("Network Error: $json");
        }

        $array = json_decode($json, true);
        if ( ! is_array($array) || $array['code'] !== 200) {
            throw new \Exception("Response Error: $json");
        }
        return $array['result'] ?? [];
    }

    public function update(int $id, int $status)
    {
        $url = rtrim(config('CRONTAB.url'), '/') . '/update';
        $HttpClient = new HttpClient($url);
        $body = $this->body([
            'id' => $id,
            'status' => $status
        ]);
        $Resp = $HttpClient->post($body);

        $json = $Resp->getBody();

        if ($Resp->getStatusCode() !== 200) {
            trace("$url: Network Error: $json", 'error');
            return false;
        }

        $array = json_decode($json, true);
        if ( ! is_array($array) || $array['code'] !== 200) {
            trace("$url Response Error: $json", 'error');
            return false;
        }
        return true;
    }

    protected function body($data = [])
    {
        switch (strtolower($data['encry'] = config('CRONTAB.encry'))) {
            case 'rsa':
                $rsaConfig = config('RSA');
                $openssl = LamOpenssl::getInstance($rsaConfig['private'], $rsaConfig['public']);
                return [
                    'encry' => $data['encry'],
                    $rsaConfig['key'] => $openssl->encrypt(json_encode($data))
                ];

            case 'md5':
                $data['time'] = time();
                $data['sign'] = sign($data['encry'] . $data['time']);
                return $data;

            default:
                throw new \Exception('Error configuration: CRONTAB.encry');
        }
    }
}
