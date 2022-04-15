<?php

namespace WonderGame\EsUtility\HttpController\Admin;

trait CrontabTrait
{
    protected function _search()
    {
        $where = [];
        foreach (['status'] as $col)
        {
            if (isset($this->get[$col]) && $this->get[$col] !== '')
            {
                $where[$col] = $this->get[$col];
            }
        }
        if (!empty($this->get['name']))
        {
            $this->Model->where("(name like '%{$this->get['name']}%' OR eclass like '%{$this->get['name']}%' OR method like '%{$this->get['name']}%')");
        }

        return $where;
    }

    protected function _afterEditGet($data)
    {
        $tmp = [];
        if ($json = $data['args'])
        {
            foreach ($json as $key => $value)
            {
                $tmp[] = [
                    'key' => $key,
                    'value' => $value
                ];
            }
        }
        $data['args'] = $tmp;

        return $data;
    }
}
