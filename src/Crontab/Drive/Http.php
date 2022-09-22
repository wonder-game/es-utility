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
        $data = array_merge(['status' => [[0, 2], 'IN']], config('CRONTAB.post') ?: []);
        $body = $this->body($data);
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
        $rsa = config('RSA');
        $openssl = LamOpenssl::getInstance($rsa['private'], $rsa['public']);
        return [$rsa['key'] => $openssl->encrypt(json_encode($data))];
    }
}
