<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use EasySwoole\ORM\AbstractModel;

/**
 * @property \App\Model\Admin\Crontab $Model
 */
trait CrontabTrait
{
	protected function __search()
	{
		$where = [];
		foreach (['status'] as $col) {
            isset($this->get[$col]) && is_numeric($this->get[$col]) && $where[$col] = $this->get[$col];
		}
        empty($this->get['name']) or $where['concat(name," ",eclass," ",method)'] = ["%{$this->get['name']}%", 'like'];

		return $this->_search($where);
	}

    public function edit()
    {
        if ($this->isHttpGet()) {
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
