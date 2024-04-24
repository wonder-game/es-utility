<?php

namespace WonderGame\EsUtility\Common\CloudLib\Captcha;

use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements CaptchaInterface
{
    // 云验证码验证失败的状态码，需要与客户端配置一致
    const FAIL_CODE = 2000;
}
