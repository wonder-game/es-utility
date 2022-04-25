<?php

namespace WonderGame\EsUtility\Model;

trait PackageModelTrait
{
	protected function setBaseTraitProptected()
	{
		$this->sort = ['sort' => 'asc', 'id' => 'desc'];
	}
	
	public function getPackageAll($where = [])
	{
		if ($where) {
			$this->where($where);
		}
		return $this->setOrder()->all();
	}
	
	public function getPackageKeyValue()
	{
		$all = $this->getPackageAll();
		
		$pkg = [];
		foreach ($all as $key => $value) {
			$pkg[$value['pkgbnd']] = $value['name'];
		}
		return $pkg;
	}
}
