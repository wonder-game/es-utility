<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use WonderGame\EsUtility\Common\Http\Code;

trait SysinfoTrait
{
    protected function _search()
    {
        $where = [];
        if (isset($this->get['status']) && $this->get['status'] !== '')
        {
            $where['status'] = $this->get['status'];
        }
        foreach (['varname', 'remark'] as $col)
        {
            if (!empty($this->get[$col]))
            {
                $where[$col] = ["%{$this->get[$col]}%", 'like'];
            }
        }
        return $where;
    }

    protected function _writeBefore()
    {
        $post = $this->post;
        if (empty($post['varname']) || empty($post['value']) || !isset($post['type']))
        {
            return $this->error(Code::ERROR_OTHER);
        }
    }
}
