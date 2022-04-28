<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use WonderGame\EsUtility\Common\Http\Code;

trait PackageTrait
{
	protected function _search()
	{
		$filter = $this->filter();
		// 如果分配了包权限但没有分配该包所属的游戏权限，同样是看不到此包的
		foreach (['gameid', 'pkgbnd'] as $col) {
			if ( ! empty($filter[$col])) {
				$this->Model->where($col, $filter[$col], 'IN');
			}
		}
		if (isset($this->get['name'])) {
			$name = "%{$this->get['name']}%";
			$this->Model->where("(name like ? or pkgbnd like ?)", [$name, $name]);
		}
		return false;
	}

	public function addPost()
	{
		$pkgbnd = $this->post['pkgbnd'];
		$count = $this->Model->where('pkgbnd', $pkgbnd)->count();
		if ($count > 0) {
			return $this->error(Code::ERROR_OTHER, 'pkgbnd已存在： ' . $pkgbnd);
		}
		parent::addPost();
	}

	// 单纯的保存Key-Value类型
	public function saveKeyValue($id = 0, $name = '', $kv = [])
	{
		$kv = $this->formatKeyValue($this->post['kv']);
		$model = $this->Model->where('id', $this->post['id'])->get();
		$extension = $model->getAttr('extension');

		// 由a.b.c 组装成 ['a']['b']['c']
		$name = "['" . str_replace('.', "']['", $this->post['name']) . "']";
		eval("\$extension$name = " . var_export($kv, true) . ';');

		$model->extension = $extension;
		$model->update();
		$this->success();
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

	protected function formatKeyValue($kv = [])
	{
		$data = [];
		foreach ($kv as $arr) {
			if (empty($arr['Key']) || empty($arr['Value'])) {
				continue;
			}
			$data[$arr['Key']] = $arr['Value'];
		}
		return $data;
	}

	protected function unformatKeyValue($kv = [])
	{
		$result = [];
		foreach ($kv as $key => $value) {
			$result[] = [
				'Key' => $key,
				'Value' => $value
			];
		}
		return $result;
	}

    public function options()
    {
        // 除了extension外的所有字段
        $options = $this->Model->order('gameid', 'desc')->order('sort', 'asc')->field(['id', 'name', 'gameid', 'pkgbnd', 'os', 'sort'])->all();
        $this->success($options);
    }
}
