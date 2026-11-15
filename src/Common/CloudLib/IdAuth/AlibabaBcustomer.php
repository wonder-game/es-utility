<?php

namespace WonderGame\EsUtility\Common\CloudLib\IdAuth;

use WonderGame\EsUtility\Common\Exception\HttpParamException;

/**
 * @document https://market.aliyun.com/detail/cmapi00040094?spm=5176.2020520132.0.0.31957218g9osRP#sku=yuncode34094000024
 */

class AlibabaBcustomer extends AlibabaCloudStore
{
    protected $url = 'http://bcustomer.market.alicloudapi.com/id/check';

    protected $cardName = 'cardNo';

    protected $appCode = "";

    protected $userName = 'realName';

    protected $method = 'GET';

    protected function isSuccess($result)
    {
        if (empty($result) || ! isset($result['error_code'])) {
            throw new HttpParamException('请求失败', self::REQUEST_ERR);
        }
        if ($result['error_code'] != 0) {
            throw new HttpParamException($result['reason'], self::REQUEST_ERR);
        }

        if ($result['result']['isok'] === true) {
            return true;
        } else {
            return false;
        }
    }
}
