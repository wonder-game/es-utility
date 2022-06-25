<?php

namespace WonderGame\EsUtility\Model\Admin;

use EasySwoole\Mysqli\QueryBuilder;

trait AdminModelTrait
{
	/**
	 * 保存登录日志，新项目将log表统一命名规则，自己实现记录日志的操作
	 *   实现示例: $model->data($data)->save();
	 * @param $data
	 * @return mixed
	 */
	abstract public function signInLog($data = []);

	protected function setBaseTraitProptected()
	{
		$this->autoTimeStamp = true;
		$this->sort = ['sort' => 'asc', 'id' => 'asc'];
	}

	protected function setPasswordAttr($password = '', $alldata = [])
	{
		if ($password != '') {
			return password_hash($password, PASSWORD_DEFAULT);
		}
		return false;
	}

	protected function getExtensionAttr($extension = '', $alldata = [])
	{
		$array = is_array($extension) ? $extension : json_decode($extension, true);

		if (isset($array['gameids']) && is_string($array['gameids'])) {
			$array['gameids'] = explode(',', $array['gameids']);
		}

		// 如果是超级管理员可能是空数组或字符串
		$array['gameids'] = array_map('intval', $array['gameids'] ?? []);
		// package和gameid固定返回数组，gameid转整型
		foreach (['gameids', 'pkgbnd'] as $col) {
			$colValue = $array[$col] ?? [];
			if (is_string($colValue)) {
				$colValue = explode(',', $colValue);
			}
			$array[$col] = $colValue;
		}
		// 强类型限制, 游戏id有0
		$array['gid'] = ! empty($array['gid']) ? intval($array['gid']) : '';

		return $array;
	}

	/**
	 * 关联Role分组模型
	 * @return array|mixed|null
	 * @throws \Throwable
	 */
	public function relation()
	{
		return $this->hasOne(find_model('Admin\Role'), null, 'rid', 'id');
	}
}
