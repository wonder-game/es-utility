<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use EasySwoole\ORM\AbstractModel;

trait CrontabTrait
{
	protected function __search()
	{
		$where = [];
		foreach (['status'] as $col) {
			if (isset($this->get[$col]) && $this->get[$col] !== '') {
				$where[$col] = $this->get[$col];
			}
		}
		if ( ! empty($this->get['name'])) {
			$this->Model->where("(name like '%{$this->get['name']}%' OR eclass like '%{$this->get['name']}%' OR method like '%{$this->get['name']}%')");
		}

		return $where;
	}

    public function edit()
    {
        if ($this->isMethod('GET')) {
            $this->success($this->fmtKeyValue($this->_edit(true)));
        } else {
            return $this->_edit();
        }
    }

	protected function fmtKeyValue($data)
	{
		$tmp = [];
		if ($json = $data['args']) {
			foreach ($json as $key => $value) {
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
