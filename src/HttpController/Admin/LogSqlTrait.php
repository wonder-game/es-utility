<?php

namespace WonderGame\EsUtility\HttpController\Admin;

trait LogSqlTrait
{
    protected function __search()
    {
        $filter = $this->filter();

        $where = ['instime' => [[$filter['begintime'], $filter['endtime']], 'between']];
        if (isset($this->get['admid'])) {
            $where['admid'] = $this->get['admid'];
        }

        // 为保持下方多个like放在SQL后面
        $where && $this->Model->where($where);

        if ($type = $this->get['type'] ?? '') {
            $this->Model->where("content like '$type%'");
        }

        if ($content = $this->get['content'] ?? '') {
            foreach (explode('&', $content) as $v) {
                $this->Model->where("content like '%$v%'");
            }
        }
        return null;
    }

    protected function __after_index($items = [], $total = 0, $summer = [])
    {
        foreach ($items as &$value) {
            $value->relation = $value->relation ?? [];
        }

        return parent::__after_index($items, $total, $summer);
    }
}
