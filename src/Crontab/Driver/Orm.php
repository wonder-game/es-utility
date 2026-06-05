<?php

namespace WonderGame\EsUtility\Crontab\Driver;

use EasySwoole\ORM\AbstractModel;
use WonderGame\EsUtility\Crontab\Crontab;
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

        $class->where('status', [Crontab::STATUS_ENABLED, Crontab::STATUS_ONCE, Crontab::STATUS_RUNNOW], 'IN')->where($where);
        return $class->all();
    }

    public function update(int $id, int $status)
    {
        $class = $this->getOrm();
        return $class->update(['status' => $status], ['id' => $id]);
    }
}
