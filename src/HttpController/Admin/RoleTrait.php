<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use EasySwoole\ORM\AbstractModel;

trait RoleTrait
{
    protected function __search()
    {
        $where = [];

        empty($this->get['name']) or $where['name'] = ["%{$this->get['name']}%", 'like'];
        isset($this->get['status']) && $this->get['status'] !== '' && $where['status'] = $this->get['status'];

        return $this->_search($where);
	}

	protected function __after_index($items, $total)
	{
		// 处理超级管理员菜单权限
		/** @var AbstractModel $Menu */
		$Menu = model_admin('Menu');
		$allMenu = $Menu->column('id');

		foreach ($items as $key => &$val) {
			if ($val instanceof AbstractModel) {
				$val = $val->toArray();
			}
			if (is_super($val['id'])) {
				$val['menu'] = $allMenu;
			} else {
				if (is_string($val['menu'])) {
					$val['menu'] = explode(',', $val['menu']);
				}
				// 转int
				$val['menu'] = array_map('intval', $val['menu']);
				// 过滤0值(数据库menu字段默认值)
				$val['menu'] = array_filter($val['menu']);
			}
		}
		return parent::__after_index($items, $total);
	}

    public function _options($return = false)
    {
        $options = $this->Model->order('sort', 'asc')->field(['id', 'name'])->all();
        $result = [];
        foreach ($options as $option) {
            $result[] = [
                'label' => $option->getAttr('name'),
                'value' => $option->getAttr('id'),
            ];
        }
        return $return ? $result : $this->success($result);
    }
}
