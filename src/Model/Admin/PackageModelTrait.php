<?php

namespace WonderGame\EsUtility\Model\Admin;

use EasySwoole\ORM\AbstractModel;
use WonderGame\EsUtility\Model\BaseModelTrait;

trait PackageModelTrait
{
    protected function setBaseTraitProtected()
    {
        $this->sort = ['sort' => 'asc', 'id' => 'desc'];
    }

    public function getPackageAll($where = [])
    {
        /* @var AbstractModel|BaseModelTrait $this */
        if ($where) {
            $this->where($where);
        }
        return $this->setOrder()->all();
    }

    public function getPackageKeyValue()
    {
        $all = $this->getPackageAll();

        $pkg = [];
        foreach ($all as $value) {
            $pkg[$value['pkgbnd']] = $value['name'];
        }
        return $pkg;
    }
}
