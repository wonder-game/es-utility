<?php

namespace WonderGame\EsUtility\Crontab\Drive;

use EasySwoole\ORM\AbstractModel;
use WonderGame\EsUtility\Model\Admin\CrontabTrait;

class Orm implements Interfaces
{
    protected function getOrm(): AbstractModel
    {
        $className = config('CRONTAB.orm');
        return new $className();
    }

    public function list(): array
    {
        $where = config('CRONTAB.where');
        $class = $this->getOrm();

        // 有实现缓存方法
        if (method_exists($class, 'getCrontab')) {
            return $class->getCrontab($where);
        }

        $class->where('status', [0, 2], 'IN')->where($where);
        return $class->all();
    }

    public function update(int $id, int $status)
    {
        $class = $this->getOrm();
        return $class->update(['status' => $status], ['id' => $id]);
    }
}
