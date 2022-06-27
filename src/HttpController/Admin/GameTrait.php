<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Languages\Dictionary;

/**
 * @property \App\Model\Admin\Game $Model
 */
trait GameTrait
{
	protected function __search()
	{
		$where = [];

		$filter = $this->filter();
        empty($filter['gameid']) or $where['id'] = [$filter['gameid'], 'IN'];
        isset($this->get['status']) && $this->get['status'] !== '' && $where['status'] = $this->get['status'];
        empty($this->get['name']) or $where['name'] = ["%{$this->get['name']}%", 'like'];

		return $this->_search($where);
	}

    public function _gkey($return = false)
	{
		$rand = [
			'logkey' => mt_rand(10, 20),
			'paykey' => mt_rand(30, 40)
		];
		if ( ! isset($this->get['column']) || ! isset($rand[$this->get['column']])) {
			throw new HttpParamException(lang(Dictionary::PARAMS_ERROR));
		}

		$sign = uniqid($rand[$this->get['column']]);

		return $return ? $sign : $this->success($sign);
	}

    public function _options($return = false)
    {
        $options = $this->Model->where('status', 1)->order('sort', 'asc')->field(['id', 'name'])->all();
        $result = [];
        foreach ($options as $option) {
            $result[] = [
                'label' => $option->getAttr('name'),
                'value' => $option->getAttr('id'),
            ];
        }
        return $return ? $result : $this->success($result);
    }
}
