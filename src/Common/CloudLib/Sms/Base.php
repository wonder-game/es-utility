<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sms;

use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements SmsInterface
{
    /**
     * 转地区码
     * 由业务自行调用处理，统一封装的话会无法一次发送多个地区的号码
     * @param $numbers
     * @param $code
     * @return string[]
     */
    public static function addAreaCode($numbers = [], $code = '86')
    {
        if (is_string($numbers)) {
            $numbers = [$numbers];
        }

        return array_map(function ($value) use ($code) {
            return "+$code" . ltrim($value, '+');
        }, $numbers);
    }
}
