<?php

namespace WonderGame\EsUtility\Common\CloudLib\IdAuth;

use WonderGame\EsUtility\Common\Exception\HttpParamException;

/**
 * 上海联麓-身份证二要素认证（姓名+身份证号）
 * @document https://www.shlianlu.com/console/document/api_4_4 (身份证二要素)
 */
class Shlianlu extends Base
{
    /**
     * 企业ID
     * @var string
     */
    protected $mchId = '';

    /**
     * Appid
     * @var string
     */
    protected $appId = '';

    /**
     * 签名密钥key
     * @var string
     */
    protected $key = '';

    /**
     * 接口地址
     * @var string
     */
    protected $url = 'https://apis.shlianlu.com/identification/idCard/auth';

    /**
     * 加密方式，固定 MD5
     * @var string
     */
    protected $signType = 'MD5';

    /**
     * 账户/配置类故障状态码（联麓短信、实名共用同一套），不会自行恢复，需要人工在控制台处理
     * 其余非 00 状态码（01 参数、05 号码格式、07 数量超限、08 频率限制、09 服务内部错误等）属于临时或入参问题
     * @document https://www.shlianlu.com/console/document/api_4_4
     */
    const STATUS_FATAL = [
        '02' => '数字签名错误',
        '03' => '企业ID有误',
        '04' => '余额不足',
        '13' => '账户不存在或已停用',
        '16' => '无匹配模板',
        '20' => '签名不存在',
        '22' => '模板有误或没有审核',
        '23' => '时间戳差异过大',
        '38' => '签名参数错误',
        '39' => '签名格式错误',
        '40' => '非法的IP地址',
        '41' => '产品未开通',
        '42' => '账号无配置',
        '43' => '未配置效验通道',
    ];

    public function checkIdCard($uid, $card, $realname)
    {
        // dev/压测环境只转投、不调真实云商（按次收费）
        if ($this->forwardToPres(['uid' => $uid, 'card' => $card, 'realname' => $realname])) {
            return true;
        }

        // 13位毫秒时间戳，签名与请求体共用同一个值
        $timeStamp = (string)(int)(microtime(true) * 1000);

        $body = [
            'MchId' => $this->mchId,
            'AppId' => $this->appId,
            'name' => $realname,
            'idNum' => $card,
            'TimeStamp' => $timeStamp,
            'SignType' => $this->signType,
        ];
        $body['Signature'] = $this->makeSignature($body);

        $end = http_tracker('SDK:ID_AUTH_SHLIANLU', [
            'url' => $this->url,
            'method' => 'POST',
            'params' => $body,
        ]);

        $resp = hcurl($this->url, $body, 'json', ['Accept' => 'application/json'], ['resultType' => '', 'retryTimes' => 0]);

        $httpCode = $resp->getStatusCode();
        $end($resp->getBody(), $httpCode);

        $this->isHttpOk($httpCode);

        $this->_result = $resp->json(true);

        // 非 00 一律视为云商异常，切换下一家（接口层失败不产生认证记录，也不收费）
        // 正常返回字符串 '00'，个别网关会返回数字 0 或 '4' 这类未补零的值，统一按两位数处理
        $status = (string)($this->_result['status'] ?? '');
        $statusOk = $status !== '' && intval($status) === 0;
        if ( ! $statusOk || empty($this->_result['success'])) {
            if ($status !== '') {
                $status = str_pad($status, 2, '0', STR_PAD_LEFT);
            }
            $msg = self::STATUS_FATAL[$status] ?? ($this->_result['message'] ?? '请求失败');
            $label = ($status === '' ? '无状态码' : $status) . " {$msg}";
            // 账户类故障会一直失败，且每单都转嫁给其他云商（费用也跟着转移），必须让人知道
            if (isset(self::STATUS_FATAL[$status])) {
                notice_limit(
                    "shlianlu_idauth_{$status}",
                    "联麓实名认证账户异常[{$label}]，已自动切换其他云商，请尽快在联麓控制台处理"
                );
            }
            throw new HttpParamException('请求失败', self::REQUEST_ERR);
        }

        // 认证状态 result：01 认证一致，02 不一致，03 不确定，04 认证失败
        return ($this->_result['data']['result'] ?? '') === '01';
    }

    protected function isHttpOk($httpCode)
    {
        if ($httpCode !== 200) {
            throw new HttpParamException('请求失败', self::REQUEST_ERR);
        }
    }

    /**
     * 生成数字签名
     * 拼接串：AppId=XXX&MchId=XXX&SignType=MD5&TimeStamp=XXX&idNum=XXX&name=XXX&key=XXX
     * MD5 加密后转大写
     * @param array $body
     * @return string
     */
    protected function makeSignature(array $body)
    {
        $str = sprintf(
            'AppId=%s&MchId=%s&SignType=%s&TimeStamp=%s&idNum=%s&name=%s&key=%s',
            $body['AppId'],
            $body['MchId'],
            $body['SignType'],
            $body['TimeStamp'],
            $body['idNum'],
            $body['name'],
            $this->key
        );
        return strtoupper(md5($str));
    }
}
