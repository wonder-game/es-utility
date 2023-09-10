<?php

namespace WonderGame\EsUtility\HttpController\Admin;

trait LogLoginTrait
{
    protected function __search()
    {
        $filter = $this->filter();

        $where = ['instime' => [[$filter['begintime'], $filter['endtime']], 'between']];
        empty($this->get['uid']) or $where['concat(uid," ",name)'] = ["%{$this->get['uid']}%", 'like'];

        return $this->_search($where);
    }

    protected function __after_index($items, $total)
    {
        foreach ($items as &$value) {
            $value->relation = $value->relation ?? [];
        }

        return parent::__after_index($items, $total);
    }
}
