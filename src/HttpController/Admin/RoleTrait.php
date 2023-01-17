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

    public function _options($return = false)
    {
        $options = $this->Model->order('sort', 'asc')->field(['id', 'name'])->where('status', 1)->all();
        $result = [];
        foreach ($options as $option) {
            $result[] = [
                'label' => $option->getAttr('name'),
                'value' => $option->getAttr('id'),
            ];
        }
        return $return ? $result : $this->success($result);
    }

    public function _showAdmin($return = false)
    {
        $id = $this->get['id'];
        $Admin = model_admin('admin');
        $list = $Admin->getAdminByRid($id);
        return $return ? $list : $this->success($list);
    }
}
