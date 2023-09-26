<?php

namespace WonderGame\EsUtility\Consumer;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\Redis\Redis;

trait ProcessInfoTrait
{
    protected function consume($data = [], Redis $redis = null)
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
