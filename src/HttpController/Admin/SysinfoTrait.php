<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use WonderGame\EsUtility\Common\Exception\HttpParamException;

/**
 * @property \App\Model\Admin\Sysinfo $Model
 */
trait SysinfoTrait
{
    protected function __search()
    {
        $where = [];
        isset($this->get['status']) && $this->get['status'] !== '' && $where['status'] = $this->get['status'];
        foreach (['varname', 'remark'] as $col) {
            empty($this->get[$col]) or $where[$col] = ["%{$this->get[$col]}%", 'like'];
        }

        return $this->_search($where);
    }

    public function _add($return = false)
    {
        if ($this->isHttpPost()) {
            $this->__writeBefore();
            $count = $this->Model->where('varname', $this->post['varname'])->count();
            if ($count > 0) {
                throw new HttpParamException('varname exist: ' . $this->post['varname']);
            }
        }
        return parent::_add($return);
    }

    public function _edit($return = false)
    {
        if ($this->isHttpPost()) {
            $this->__writeBefore();
        }
        return parent::_edit($return);
    }

    protected function __writeBefore()
    {
        $post = $this->post;
        if (empty($post['varname']) || empty($post['value']) || ! isset($post['type'])) {
            throw new HttpParamException('Params invalid');
        }
    }
}
