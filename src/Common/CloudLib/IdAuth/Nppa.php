<?php

namespace WonderGame\EsUtility\Common\CloudLib\IdAuth;

use WonderGame\EsUtility\Common\Exception\HttpParamException;

/**
 * 国家新闻出版署
 */
class Nppa extends Base
{
    const CIPHER = 'aes-128-gcm';

    // 实名认证接口
    const CHECK_ID_CARD_URL = 'https://api.wlc.nppa.gov.cn/idcard/authentication/check';

    // 实名认证结果查询接口
    const QUERY_ID_CARD_ASYNC_RESULT_URL = 'http://api2.wlc.nppa.gov.cn/idcard/authentication/query';

    // 游戏用户行为数据上报接口
    const REPORT_USER_BEHAVIOR_URL = 'http://api2.wlc.nppa.gov.cn/behavior/collection/loginout';

    protected $errCode = [
        // ****************************** 系统异常
        // 请求成功
        'ERR_CODE_OK' => 0,
        // 系统错误
        'ERR_CODE_SYS_ERROR' => 1001,
        // 请求的资源不存在
        'ERR_CODE_SYS_REQ_RESOURCE_NOT_EXIST' => 1002,
        // 请求方式错误
        'ERR_CODE_SYS_REQ_METHOD_ERROR' => 1003,
        // 请求核心参数缺失
        'ERR_CODE_SYS_REQ_HEADER_MISS_ERROR' => 1004,
        // 请求IP地址非法
        'ERR_CODE_SYS_REQ_IP_ERROR' => 1005,
        // 请求超出流量限制
        'ERR_CODE_SYS_REQ_BUSY_ERROR' => 1006,
        // 请求过期
        'ERR_CODE_SYS_REQ_EXPIRE_ERROR' => 1007,
        // 请求方身份非法
        'ERR_CODE_SYS_REQ_PARTNER_ERROR' => 1008,
        // 请求方权限未启用
        'ERR_CODE_SYS_REQ_PARTNER_AUTH_DISABLE' => 1009,
        // 请求方无该接口权限
        'ERR_CODE_SYS_REQ_AUTH_ERROR' => 1010,
        // 请求方身份核验错误
        'ERR_CODE_SYS_REQ_PARTNER_AUTH_ERROR' => 1011,
        // 请求报文核验失败
        'ERR_CODE_SYS_REQ_PARAM_CHECK_ERROR' => 1012,

        // ******************************* 实名认证异常
        // 身份证号格式校验失败
        'ERR_CODE_BUS_AUTH_IDNUM_ILLEGAL' => 2001,
        // 实名认证条目已达上限
        'ERR_CODE_BUS_AUTH_RESOURCE_LIMIT' => 2002,
        // 无该编码提交的实名认证记录
        'ERR_CODE_BUS_AUTH_CODE_NO_AUTH_RECODE' => 2003,
        // 编码已经被占用
        'ERR_CODE_BUS_AUTH_CODE_ALREADY_IN_USE' => 2004,

        // ******************************  用户行为数据上报异常
        // 行为数据部分上报失败
        'ERR_CODE_BUS_COLL_PARTIAL_ERROR' => 3001,
        // 行为数据为空
        'ERR_CODE_BUS_COLL_BEHAVIOR_NULL_ERROR' => 3002,
        // 行为数据超出条目数量限制
        'ERR_CODE_BUS_COLL_OVER_LIMIT_COUNT' => 3003,
        // 行为数据编码错误
        'ERR_CODE_BUS_COLL_NO_INVALID' => 3004,
        // 行为发生时间错误
        'ERR_CODE_BUS_COLL_BEHAVIOR_TIME_ERROR' => 3005,
        // 用户类型无效
        'ERR_CODE_BUS_COLL_PLAYER_MODE_INVALID' => 3006,
        // 行为类型无效
        'ERR_CODE_BUS_COLL_BEHAVIOR_MODE_INVALID' => 3007,
        // 缺失PI（用户唯一标识）值
        'ERR_CODE_BUS_COLL_PLAYERID_MISS' => 3008,
        // 缺失DI（设备标识）值
        'ERR_CODE_BUS_COLL_DEVICEID_MISS' => 3009,
        // PI（用户唯一标识）值无效
        'ERR_CODE_BUS_COLL_PLAYERID_INVALID' => 30010,
    ];

    protected $appId;

    protected $secretKey;

    protected $bizId; // 备案识别码

    public function setConfig()
    {
        // 备案识别码配置在game设置中
        $game = $this->gameConfig();
        $this->bizId = $game['bizId'] ?? '';

        // 游戏中配置了防沉迷账户公司主体，sysinfo表中配置了对应的appId和secretKey
        $company = $game['nppa_company'] ?? '';
        $sysInfo = sysinfo('nppa_real_name') ?? [];
        if (isset($sysInfo[$company])) {
            $this->appId = $sysInfo[$company]['appId'];
            $this->secretKey = $sysInfo[$company]['secretKey'];
        }
    }

    protected function encrypt(string $data)
    {
        $cipher = self::CIPHER;
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $secretKeyBin = hex2bin($this->secretKey);
        $encryptData = openssl_encrypt($data, $cipher, $secretKeyBin, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $encryptData . $tag);
    }

    protected function decrypt(string $data): ?string
    {
        $cipher = self::CIPHER;
        $r = base64_decode($data);
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = substr($r, 0, $ivlen);
        $tag = substr($r, -16);
        $ciphertext = substr($r, $ivlen, -16);
        $secretKey_bin = hex2bin($this->secretKey);
        $original_plaintext = openssl_decrypt($ciphertext, $cipher, $secretKey_bin, OPENSSL_RAW_DATA, $iv, $tag);
        if ($original_plaintext === false) {
            return null;
        }
        return $original_plaintext;
    }

    protected function headers(): array
    {
        return [
            'appId' => $this->appId,
            'bizId' => $this->bizId,
            'timestamps' => time() * 1000,
            'Content-Type' => 'application/json; charset=utf-8'
        ];
    }

    protected function sign(array $headers, array $query, string $body)
    {
        $data = array_merge($headers, $query);
        ksort($data);
        $string_parts = [];
        foreach ($data as $key => $value) {
            if ($key !== 'sign' && $key != 'Content-Type') {
                $string_parts[] = $key . $value;
            }
        }
        $string = implode('', $string_parts);
        $string = $this->secretKey . $string . $body;
        return hash('sha256', $string);
    }

    public function checkIdCard($uid, $card, $realname)
    {
        if (empty($this->appId)) {
            throw new HttpParamException('appId is empty', self::REQUEST_ERR);
        }
        if (empty($this->bizId)) {
            throw new HttpParamException('bidId is empty', self::REQUEST_ERR);
        }

        $params = [
            'ai' => md5($uid),
            'name' => $realname,
            'idNum' => $card,
        ];
        $headers = $this->headers();
        $body = json_encode($params);
        $encrypted_body = $this->encrypt($body);
        $json_body = json_encode(['data' => $encrypted_body]);
        $headers['sign'] = $this->sign($headers, [], $json_body);

        $url = self::CHECK_ID_CARD_URL;
        $end = http_tracker('SDK:ID_AUTH_NPPA', [
            'url' => $url,
            'method' => 'POST',
            'params' => $params,
            'header' => $headers,
            'json_body' => $json_body
        ]);

        $resp = hcurl($url, $json_body,'post', $headers, ['resultType' => '', 'retryTimes' => 0]);

        $body = $resp->getBody();
        $data = json_decode($body, true);
        $end($data, $resp->getStatusCode());

        $requestOk = $data && is_array($data) && isset($data['errcode']) && $data['errcode'] === $this->errCode['ERR_CODE_OK'];
        if ( ! $requestOk) {
            throw new HttpParamException('请求失败', self::REQUEST_ERR);
        }

        // 0：认证成功，1：认证中，2：认证失败
        return isset($data['data']['result']['status']) && in_array($data['data']['result']['status'], [0, 1]);
    }
}
