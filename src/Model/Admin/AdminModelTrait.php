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

        $array['gid'] = isset($array['gid']) && $array['gid'] !== '' ? intval($array['gid']) : '';

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

    public function getAdminByRid($rid, $where = [])
    {
        if ($where) {
            $this->where($where);
        }
        return $this->field(['id', 'username', 'realname', 'avatar'])->where('rid', $rid)->where('status', 1)->order('id')->all();
    }
}
