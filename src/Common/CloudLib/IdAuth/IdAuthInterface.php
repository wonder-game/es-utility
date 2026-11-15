<?php

namespace WonderGame\EsUtility\Common\CloudLib\IdAuth;

interface IdAuthInterface
{
    /**
     * 身份证号与真实姓名校验
     * @param string|int $uid
     * @param string $card 身份证
     * @param string $realname 真实姓名
     * @return bool true-校验通过，false-不通过
     */
    public function checkIdCard($uid, $card, $realname);
}
