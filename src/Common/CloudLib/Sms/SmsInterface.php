<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sms;

/**
 * 发送短信
 */
interface SmsInterface
{
    /**
     * 发送短信
     * @param string|number|array $to 发送的号码
     * @param array $params 短信模板参数
     * @param bool $ingo 是否在go函数中执行
     * @return mixed
     */
    function send($to = [], array $params = [], bool $ingo = false);
}
