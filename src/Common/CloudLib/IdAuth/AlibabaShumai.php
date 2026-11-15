<?php

namespace WonderGame\EsUtility\Common\CloudLib\IdAuth;

use WonderGame\EsUtility\Common\Exception\HttpParamException;

/**
 * 阿里云市场-数脉科技
 *
 * 接口
 * @dicument https://market.aliyun.com/products/57000002/cmapi026109.html?spm=5176.2020520132.101.4.75f37218cY5Ygk#sku=yuncode20109000025
 * 调试
 * @console https://market.aliyun.com/apimarket/detail/cmapi026109?spm=5176.2020520132.101.5.75f37218cY5Ygk
 *
 */
class AlibabaShumai extends AlibabaCloudStore
{
    protected $url = 'https://eid.shumaidata.com/eid/check';

    protected $cardName = 'idcard';

    protected $userName = 'name';

    protected $method = 'POST';

    protected function isSuccess($result)
    {
        if (empty($result) || ! isset($result['code'])) {
            throw new HttpParamException('请求失败', self::REQUEST_ERR);
        }

        // code=0表示成功，res：核验结果状态码，1 一致；2 不一致；3 无记录
        return $result['code'] == '0' && $result['result']['res'] == '1';
    }

    protected function isHttpOk($httpCode)
    {
        if ($httpCode !== 200) {
            throw new HttpParamException('请求失败');
        }
    }
}
