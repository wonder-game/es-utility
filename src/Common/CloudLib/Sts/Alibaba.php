<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sts;

use EasySwoole\HttpClient\HttpClient;

/**
 * 不安装Composer依赖
 *  composer require alibabacloud/sts-20150401
 *  composer require alibabacloud/client
 * @document https://help.aliyun.com/zh/oss/developer-reference/use-temporary-access-credentials-provided-by-sts-to-access-oss?spm=5176.21213303.J_ZGek9Blx07Hclc3Ddt9dg.1.670c2f3dzzj3IV&scm=20140722.S_help@@%E6%96%87%E6%A1%A3@@100624._.ID_help@@%E6%96%87%E6%A1%A3@@100624-RL_%E4%B8%B4%E6%97%B6%E5%AF%86%E9%92%A5-LOC_2024SPHelpResult-OR_ser-PAR1_213e04f017631078574016218e21cb-V_4-PAR3_o-RE_new7-P0_0-P1_0#b8aeaf6650mnz
 * @document https://help.aliyun.com/zh/ram/developer-reference/sts-sdk-overview?spm=a2c4g.11186623.0.0.755d26edZsPb8u#reference-w5t-25v-xdb
 * @document https://next.api.aliyun.com/api/Sts/2015-04-01/AssumeRole?RegionId=cn-hangzhou&params={%22DurationSeconds%22:14400,%22Policy%22:%22dsddd%22,%22RoleArn%22:%22sss%22,%22RoleSessionName%22:%22fdsafdsaf%22,%22ExternalId%22:%225435%22,%22SourceIdentity%22:%2254353%22}&tab=DEMO&lang=PHP
 * /
 *
 * RAM用户权限： 需要包含AliyunSTSAssumeRoleAccess权限（创建临时密钥）、oss全读写权限、policy额外权限
 * RAM角色： cos权限就可以了
 */
class Alibaba extends Base
{
    protected $accessKeyId = '';

    protected $accessKeySecret = '';

    protected $endpoint = '';

    /**
     * 注意是RAM的角色，不是用户！
     * 新增角色，授予oss全读写权限，建完角色在基本信息里面就有ARN，可以直接复制
     * acs:ram::<account_id>:role/<role_name>
     * @document https://help.aliyun.com/zh/ram/support/faq-about-ram-roles-and-sts-tokens?spm=api-workbench.api_explorer.0.0.2256d5a7S6jF3d
     * @var string
     */
    protected $roleArn = '';

    protected $regionId = 'cn-guangzhou';

    /**
     * 通过REST API获取阿里云STS临时密钥
     * @param mixed $policy 权限策略
     * @param int $expire 有效期
     * @return array
     * @throws \Exception
     */
    public function get($policy, $expire = 1800): Response
    {
        $params = [
            'Format' => 'JSON',
            'Version' => '2015-04-01',
            'AccessKeyId' => $this->accessKeyId,
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => uniqid(),
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Action' => 'AssumeRole',
            'RoleArn' => $this->roleArn,
            'RoleSessionName' => $this->getName(),
            // DurationSeconds的最小/最大值为15分钟/1小时。
            'DurationSeconds' => $expire,
            'Policy' => $policy
        ];

        // 4. 计算签名
        $params['Signature'] = $this->computeSignature($params, 'POST');

        // 5. 发送POST请求
        $url = "https://sts.{$this->regionId}.aliyuncs.com/";
        $client = new HttpClient($url);
        $response = $client->post($params)->getBody();
        $result = json_decode($response, true);

        // 6. 处理响应
        if (isset($result['Code'])) {
            trace(var_export($result, true), 'error');
            throw new \Exception("STS接口错误: {$result['Code']} - {$result['Message']}");
        }

        if (!isset($result['Credentials'])) {
            trace(var_export($result, true), 'error');
            throw new \Exception("获取临时密钥失败: " . json_encode($result));
        }

        return new Response([
            'token' => $result['Credentials']['SecurityToken'],
            'tmpSecretId' => $result['Credentials']['AccessKeyId'],
            'tmpSecretKey' => $result['Credentials']['AccessKeySecret'],
            'expiredTime' => $result['Credentials']['Expiration'],
            'requestId' => $result['RequestId'],
        ]);
    }

    /**
     * 计算阿里云API签名
     * @param array $params 请求参数
     * @param string $method 请求方法
     * @return string 签名结果
     */
    protected function computeSignature(array $params, string $method): string
    {
        // 1. 排序参数
        ksort($params);

        // 2. 拼接参数
        $canonicalizedQueryString = '';
        foreach ($params as $key => $value) {
            $canonicalizedQueryString .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
        }
        $canonicalizedQueryString = ltrim($canonicalizedQueryString, '&');

        // 3. 构建待签名字符串
        $stringToSign = strtoupper($method) . '&%2F&' . $this->percentEncode($canonicalizedQueryString);

        // 4. 计算HMAC-SHA1签名
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret . '&', true));

        return $signature;
    }

    /**
     * URL编码（符合阿里云规范）
     * @param string $str 待编码字符串
     * @return string 编码结果
     */
    protected function percentEncode(string $str): string
    {
        $res = urlencode($str);
        $res = preg_replace('/\+/', '%20', $res);
        $res = preg_replace('/\*/', '%2A', $res);
        $res = preg_replace('/%7E/', '~', $res);
        return $res;
    }
}
