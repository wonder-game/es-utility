<?php

namespace WonderGame\EsUtility\Crontab\Drive;

use EasySwoole\HttpClient\HttpClient;
use WonderGame\EsUtility\Common\Classes\LamOpenssl;

class Http implements Interfaces
{
    public function list(): array
    {
        $url = rtrim(config('CRONTAB.url'), '/') . '/list';
        $HttpClient = new HttpClient($url);
        $body = $this->body(config('CRONTAB.post') ?: []);
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
        $config = config('CRONTAB');
        switch (strtolower($data['encry'] = $config['encry']['type'] ?? '')) {
            case 'rsa':
                $rsaConfig = $config['encry']['rsa'] ?? config('RSA');
                if (empty($rsaConfig)) {
                    throw new \Exception('Missing configuration: CRONTAB.encry.rsa');
                }
                $openssl = LamOpenssl::getInstance($rsaConfig['private'], $rsaConfig['public']);
                return [
                    'encry' => $data['encry'],
                    $rsaConfig['key'] => $openssl->encrypt(json_encode($data))
                ];
            case 'md5':
                if (empty($config['encry']['md5']['key'])) {
                    throw new \Exception('Missing configuration: CRONTAB.encry.md5');
                }
                $time = time();
                $data['time'] = $time;

                $sign = $data['encry'] . $time . $config['encry']['md5']['key'];
                $data['sign'] = md5($sign);
                return $data;
            default:
                throw new \Exception('Error configuration: CRONTAB.encry.type');
        }
    }
}
