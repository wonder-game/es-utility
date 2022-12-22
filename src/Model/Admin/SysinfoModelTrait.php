<?php

namespace WonderGame\EsUtility\Model\Admin;

use WonderGame\EsUtility\Common\OrmCache\SplArray;

trait SysinfoModelTrait
{
    use SplArray;

    protected function setBaseTraitProptected()
    {
        $this->splWhere = ['status' => 1];
        $this->splFieldKey = 'varname';
        $this->splFieldValue = 'value';
    }

    protected function setValueAttr($value, $all)
    {
        return $this->setValue($value, $all['type'], false);
    }

    protected function getValueAttr($value, $all)
    {
        return $this->setValue($value, $all['type'], true);
    }

    protected function setValue($value, $type, $decode = true)
    {
        if ($type == 0) {
            $value = intval($value);
        } else if ($type == 1) {
            $value = strval($value);
        } else {
            if ($decode) {
                $json = json_decode($value, true);
            } elseif (is_array($value)) {
                $json = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $json && $value = $json;
        }
        return $value;
    }
}
