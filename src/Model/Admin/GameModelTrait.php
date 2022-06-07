<?php

namespace WonderGame\EsUtility\Model\Admin;


trait GameModelTrait
{
	protected function setBaseTraitProptected()
	{
		$this->autoTimeStamp = true;
		$this->sort = ['sort' => 'asc', 'id' => 'desc'];
	}

	public static function onAfterInsert($model, $res)
	{
		if ($res) {
			$model->_delCache();
			$model->__after_write('create');
		}
	}

	public static function onAfterUpdate($model, $res)
	{
		if ($res) {
			$model->_delCache();
			$model->__after_write('update');
		}
	}

	protected function _delCache()
	{
	}

	public function getGameAll($where = [])
	{
		if ($where) {
			$this->where($where);
		}
		return $this->where('status', 1)->setOrder()->all('id');
	}

	/**
	 * 获取id => name 键值对
	 * @param array $where
	 * @return array|null
	 * @throws \EasySwoole\ORM\Exception\Exception
	 * @throws \Throwable
	 */
	public function getKeyVlaueByid($idArray = [])
	{
		if ($idArray) {
			$this->where(['id' => [$idArray, 'in']]);
		}
		$all = $this->field('id,name')->indexBy('id');
		if ($all) {
			$all = array_map(function ($value) {
				return $value['name'];
			}, $all);
		}
		return $all;
	}

	protected function getExtensionAttr($extension = '', $all = [])
	{
		$extension = is_array($extension) ? $extension : json_decode($extension, true);
		// 强类型转换
		if (isset($extension['type'])) {
			$extension['type'] = intval($extension['type']);
		}
		if (isset($extension['h5sdk']['gameid'])) {
			$extension['h5sdk']['gameid'] = intval($extension['h5sdk']['gameid']);
		}
		if (isset($extension['h5sdk']['isshow'])) {
			$extension['h5sdk']['isshow'] = intval($extension['h5sdk']['isshow']);
		}
		if (isset($extension['h5sdk']['isshowmnlogo'])) {
			$extension['h5sdk']['isshowmnlogo'] = intval($extension['h5sdk']['isshowmnlogo']);
		}
		if (isset($extension['mtn']['switch'])) {
			$extension['mtn']['switch'] = intval($extension['mtn']['switch']);
		}
		return $extension;
	}
}
