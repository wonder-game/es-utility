<?php

namespace WonderGame\EsUtility\Model\Admin;

use EasySwoole\ORM\AbstractModel;

trait RoleModelTrait
{
	protected function setBaseTraitProptected()
	{
		$this->autoTimeStamp = true;
		$this->sort = ['sort' => 'asc', 'id' => 'asc'];
	}

	protected function setMenuAttr($data, $alldata)
	{
		if (is_array($data)) {
			$data = implode(',', $data);
		}
		// 超级管理员永远返回*
		if (isset($alldata['id']) && is_super($alldata['id'])) {
			return '*';
		}
		return $data;
	}

	protected static function onBeforeDelete(AbstractModel $model)
	{
		// 超级管理员不可删除
		return ! is_super($model['id']);
	}

	public function getRoleListAll()
	{
		// 如果id不连续，indexBy返回给客户端就是一个json
		return $this->where('status', 1)->setOrder()->field(['id', 'name', 'menu'])->all();
	}
}
