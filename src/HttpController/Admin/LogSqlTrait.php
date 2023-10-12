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

        $content = $this->get['content'] ?? '';
        $type = $this->get['type'] ?? '';
        $type = strtoupper($type);
        if ($type && ! $content) {
            $where['content'] = ["$type%", 'like'];
        } elseif ($content && ! $type) {
            $where['content'] = ["%{$content}%", 'like'];
        }

        // 为保持下方多个like放在SQL后面
        $where && $this->Model->where($where);

        if ($content && $type) {
            $this->Model->where("(content like ? and content like ?)", ["$type%", "%$content%"]);
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
