<?php

namespace WonderGame\EsUtility\Common\CloudLib\Storage;

use EasySwoole\Spl\SplBean;

abstract class Base extends SplBean implements StorageInterface
{
    protected function getClassName()
    {
        $arr = explode('\\', static::class);
        return end($arr);
    }
}
