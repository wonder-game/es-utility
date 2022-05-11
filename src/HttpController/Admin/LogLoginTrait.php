<?php

namespace WonderGame\EsUtility\HttpController\Admin;

trait LogLoginTrait
{
	protected function __search()
	{
		$filter = $this->filter();

		$where = ['instime' => [[$filter['begintime'], $filter['endtime']], 'between']];
		if (isset($this->get['uid'])) {
			$uid = $this->get['uid'];
			$this->Model->where("(uid=? OR name like ? )", [$uid, "%{$uid}%"]);
		}
		return $where;
	}

	protected function __after_index($items, $total)
	{
		foreach ($items as &$value) {
			$value->relation = $value->relation ?? [];
		}

		return parent::__after_index($items, $total);
	}
}
