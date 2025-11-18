<?php

namespace WonderGame\EsUtility\Common\CloudLib\Sts;

use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements StsInterface
{
    public function getName()
    {
        $uniqid = uniqid();
        $ymd = date('Ymd');
        return "sts-$ymd-$uniqid";
    }
}
