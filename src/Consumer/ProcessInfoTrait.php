<?php

namespace WonderGame\EsUtility\Consumer;

use EasySwoole\ORM\AbstractModel;

trait ProcessInfoTrait
{
    protected function consume($data = '')
    {
        $data = json_decode($data, true);
        if ( ! $data) {
            return;
        }

        /** @var AbstractModel $model */
        $model = model('ProcessInfo');
        $model->data($data)->save();
    }
}
