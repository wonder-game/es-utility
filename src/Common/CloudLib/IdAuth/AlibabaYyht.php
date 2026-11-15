<?php

namespace WonderGame\EsUtility\Common\CloudLib\IdAuth;

use EasySwoole\HttpClient\HttpClient;
use WonderGame\EsUtility\Common\Exception\HttpParamException;

/**
 * 阿里云市场-云艺互通
 *
 * 接口
 * @dicument https://market.aliyun.com/products/57000002/cmapi025518.html?spm=5176.2020520132.101.12.75f37218cY5Ygk#sku=yuncode1951800002
 * 调试
 * @console https://market.aliyun.com/apimarket/detail/cmapi025518?spm=5176.2020520132.101.13.75f37218cY5Ygk
 *
 */
class AlibabaYyht extends AlibabaCloudStore
{
    protected $url = 'https://idenauthen.market.alicloudapi.com/idenAuthentication';

    protected $cardName = 'idNo';

    protected $userName = 'name';

    protected $method = 'POST';

    protected function isSuccess($result)
    {
        // 0010-系统维护中
        if (empty($result) || ! isset($result['respCode']) || $result['respCode'] == '0010') {
            throw new HttpParamException('请求失败', self::REQUEST_ERR);
        }

        // 0000-身份证信息匹配
        // 0001-开户名不能为空
        // 0002-开户名不能包含特殊字符
        // 0004-身份证号格式错误
        // 0007-无此身份证号码
        // 0008-身份证信息不匹配
        // 00010-系统维护，请稍后再试
        return $result['respCode'] == '0000';
    }

    protected function setRequest(HttpClient $httpClient)
    {
        $httpClient->setHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8', false);
    }
}
