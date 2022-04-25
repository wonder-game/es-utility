<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use WonderGame\EsUtility\Common\Http\Code;

trait GameTrait
{
	protected function _search()
	{
		$where = [];
		$filter = $this->filter();
		if ( ! empty($filter['gameid'])) {
			$where['id'] = [$filter['gameid'], 'IN'];
		}
		
		if (isset($this->get['status']) && $this->get['status'] !== '') {
			$where['status'] = $this->get['status'];
		}
		if ( ! empty($this->get['name'])) {
			$where['name'] = ["%{$this->get['name']}%", 'like'];
		}
		return $where;
	}
	
	public function gkey()
	{
		$rand = [
			'logkey' => mt_rand(10, 20),
			'paykey' => mt_rand(30, 40)
		];
		if ( ! isset($this->get['column']) || ! isset($rand[$this->get['column']])) {
			return $this->error(Code::ERROR_OTHER);
		}
		
		$sign = uniqid($rand[$this->get['column']]);
		
		$this->success($sign);
	}
}
