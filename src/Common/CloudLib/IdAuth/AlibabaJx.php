<?php

namespace WonderGame\EsUtility\Common\CloudLib\IdAuth;

use WonderGame\EsUtility\Common\Exception\HttpParamException;

/**
 * 阿里云市场-精选
 *
 * 接口
 * @dicument https://market.aliyun.com/products/57000002/cmapi00048657.html?spm=5176.2020520132.101.3.357272184RDy11#sku=yuncode4265700001
 * 调试
 * @console https://market.console.aliyun.com/imageconsole/index.htm#/bizlist?spm=5176.6660585.57-top.i1.4f8a7992JWGhOK&_k=rcpuh7
 *
 */
class AlibabaJx extends AlibabaCloudStore
{
    protected $url = 'http://id2meta.market.alicloudapi.com/id2meta';

    protected $cardName = 'identifyNum';

    protected $userName = 'userName';

    protected function isSuccess($result)
    {
        if (empty($result) || ! isset($result['code'])) {
            throw new HttpParamException('请求失败', self::REQUEST_ERR);
        }
        // bizCode 1-校验一致，2-校验不一致，3-查无记录
        return isset($result['data']['bizCode']) && $result['data']['bizCode'] == 1;
    }
}
