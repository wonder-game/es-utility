<?php

namespace WonderGame\EsUtility\Common\Languages;

use EasySwoole\I18N\I18N;

class Languages
{
    public static function register($language = [], $default = null)
    {
        foreach ($language as $lang => $className)
        {
            I18N::getInstance()->addLanguage(new $className(), $lang);
        }

        if (is_null($default) || !in_array($default, array_keys($language)))
        {
            $default = get_cfg_var('env.language');
        }

        //设置默认语言包
        self::setDefaultLanguage($default);
    }

    public static function setDefaultLanguage($default)
    {
        $default && I18N::getInstance()->setDefaultLanguage($default);
    }
}
