<?php

namespace WonderGame\EsUtility\Common\OrmCache;

trait Events
{
    public static function onAfterInsert($model, $res)
    {
        $model->_after_write($res);
    }

    public static function onAfterUpdate($model, $res)
    {
        $model->_after_write($res);
    }

    public static function onAfterDelete($model, $res)
    {
        $model->_after_delete($res);
    }

    protected function _after_write($res) {}

    protected function _after_delete($res) {}
}
