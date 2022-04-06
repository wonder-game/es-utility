<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use WonderGame\EsUtility\Common\Http\Code;

trait PackageTrait
{
    protected function _search()
    {
        $filter = $this->filter();
        // 如果分配了包权限但没有分配该包所属的游戏权限，同样是看不到此包的
        foreach (['gameid', 'pkgbnd'] as $col)
        {
            if (!empty($filter[$col]))
            {
                $this->Model->where($col, $filter[$col], 'IN');
            }
        }
        if (isset($this->get['name']))
        {
            $name = "%{$this->get['name']}%";
            $this->Model->where("(name like ? or pkgbnd like ?)", [$name, $name]);
        }
        return false;
    }

    public function addPost()
    {
        $pkgbnd = $this->post['pkgbnd'];
        $count = $this->Model->where('pkgbnd', $pkgbnd)->count();
        if ($count > 0)
        {
            return $this->error(Code::ERROR_OTHER, 'pkgbnd已存在： ' . $pkgbnd);
        }
        parent::addPost();
    }

    public function gkey()
    {
        $rand = [
            'logkey' => mt_rand(50, 60),
            'paykey' => mt_rand(70, 80)
        ];
        if (!isset($this->get['column']) || !isset($rand[$this->get['column']]))
        {
            return $this->error(Code::ERROR_OTHER);
        }

        $sign = uniqid($rand[$this->get['column']]);

        $this->success($sign);
    }

    protected function _afterEditGet($data)
    {
        if (is_array($data['extension']['uwp']['productid'])) {
            $data['extension']['uwp']['productid'] = $this->unformatKeyValue($data['extension']['uwp']['productid']);
        }

        if (is_array($data['extension']['adjust']['event'])) {
            $data['extension']['adjust']['event'] = $this->unformatKeyValue($data['extension']['adjust']['event']);
        }
        if (is_string($data['extension']['qzf']['pf'])) {
            $data['extension']['qzf']['pf'] = explode(',', $data['extension']['qzf']['pf']);
        }
        return $data;
    }

    protected function _writeBefore()
    {
        if (is_array($this->post['extension']['qzf']['pf']))
        {
            $this->post['extension']['qzf']['pf'] = implode(',', $this->post['extension']['qzf']['pf']);
        }
        $this->post['extension']['uwp']['productid'] = $this->formatKeyValue($this->post['extension']['uwp']['productid']);
        $this->post['extension']['adjust']['event'] = $this->formatKeyValue($this->post['extension']['adjust']['event']);
    }

    // 单纯的保存Key-Value类型
    public function saveKeyValue($id = 0, $name = '', $kv = [])
    {
        $kv = $this->formatKeyValue($this->post['kv']);
        $model = $this->Model->where('id', $this->post['id'])->get();
        $extension = $model->getAttr('extension');

        // 由a.b.c 组装成 ['a']['b']['c']
        $name = "['" . str_replace('.',"']['", $this->post['name']) . "']";
        eval("\$extension$name = " . var_export($kv, true)  . ';');

        $model->extension = $extension;
        $model->update();
        $this->success();
    }
    protected function formatKeyValue($kv = [])
    {
        foreach($kv as $arr)
        {
            if (empty($arr['Key']) || empty($arr['Value']))
            {
                continue;
            }
            $data[$arr['Key']] = $arr['Value'];
        }
        return $data?:[];
    }
    protected function unformatKeyValue($kv = [])
    {
        foreach ($kv as $key => $value)
        {
            $result[] = [
                'Key' => $key,
                'Value' => $value
            ];
        }
        return $result?:[];
    }

    // 检查pkgbnd是否已存在了
    public function pkgbndExist()
    {
        $pkgbnd = $this->get['pkgbnd'];
        if (empty($pkgbnd)) {
            return $this->error(Code::ERROR_OTHER, 'pkgbnd为空！');
        }
        $count = $this->Model->where('pkgbnd', $pkgbnd)->count();
        $this->success(['count' => $count]);
    }
}
