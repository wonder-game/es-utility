<?php

namespace WonderGame\EsUtility\Common\CloudLib\Captcha;

interface CaptchaInterface
{
    /**
     * 人机核验
     * @param string $verifyParam
     * @return bool
     */
    function verify($verifyParam): bool;
}
