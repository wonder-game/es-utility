<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sms;

/**
 * 发送短信
 */
interface SmsInterface
{
    /**
     * 发送短信
     * @param array $to 发送的号码
     * @param array $params 短信模板参数
     * @return mixed
     */
    function send($to = [], array $params = []);
}
