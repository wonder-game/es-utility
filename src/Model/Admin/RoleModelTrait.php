<?php

namespace WonderGame\EsUtility\Model\Admin;

use EasySwoole\ORM\AbstractModel;

trait RoleModelTrait
{
	protected function setBaseTraitProtected()
	{
        $this->autoTimeStamp = true;
		$this->sort = ['sort' => 'asc', 'id' => 'asc'];
	}

    protected static function onBeforeDelete(AbstractModel $model)
    {
        // 超级管理员不可删除
        return ! is_super($model['id']);
    }

	protected function setMenuAttr($data)
	{
		return is_array($data) ? implode(',', $data) : $data;
	}

	protected function setGameidAttr($data)
    {
        return $this->setMenuAttr($data);
    }

    protected function setPkgbndAttr($data)
    {
        return $this->setMenuAttr($data);
    }

    protected function setAdidAttr($data)
    {
        return $this->setMenuAttr($data);
    }

    protected function getGameidAttr($data)
    {
        if (is_string($data)) {
            $data = $data === '' ? [] : array_map('intval', explode(',', $data));
        }
        return $data;
    }

    protected function getPkgbndAttr($data)
    {
        if (is_string($data)) {
            $data = $data === '' ? [] : explode(',', $data);
        }
        return $data;
    }

    protected function getAdidAttr($data)
    {
        return $this->getPkgbndAttr($data);
    }

    protected function getMenuAttr($value, $data)
    {
        if (is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        }
        return array_filter(array_map('intval', $value));
    }

    public function getRoleListAll()
    {
        // 如果id不连续，indexBy返回给客户端就是一个json
        return $this->where('status', 1)->setOrder()->field(['id', 'name', 'menu'])->all();
    }
}
