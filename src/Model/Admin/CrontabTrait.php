<?php


namespace WonderGame\EsUtility\Model\Admin;


trait CrontabTrait
{
	protected function setBaseTraitProptected()
	{
		$this->autoTimeStamp = true;
	}

	protected function setServerAttr($data)
	{
		return is_array($data) ? implode(',', $data) : $data;
	}

	protected function setSysAttr($data)
	{
		return is_array($data) ? implode(',', $data) : $data;
	}

	protected function getServerAttr($value, $alldata)
	{
		return $this->getIntArray($value);
	}

	protected function getSysAttr($value, $alldata)
	{
		return $this->getIntArray($value);
	}

	protected function getIntArray($value)
	{
		if (is_string($value)) {
			$array = explode(',', $value);
			return array_map('intval', $array);
		} else {
			return $value;
		}
	}

	protected function setArgsAttr($data)
	{
		$result = [];
		if ( ! empty($data) && is_array($data)) {
			foreach ($data as $value) {
				// 不要空值
				if (empty($value['key']) || empty($value['value'])) {
					continue;
				}
				// 需要将双引号替换成单引号，否则eval解析失败
				$value['value'] = str_replace('"', '\'', $value['value']);
				$result[$value['key']] = $value['value'];
			}
		}
		return json_encode($result);
	}

	protected function getArgsAttr($data)
	{
		$json = json_decode($data, true);
		return $json ? $json : [];
	}

    protected function _after_write($res = false)
    {
        $this->delRedisKey($this->getCacheKey());
    }

    protected function _after_delete($res)
    {
        $this->delRedisKey($this->getCacheKey());
    }

    // 全表缓存
    abstract protected function getCacheKey();

    public function getAvailables()
    {
        $key = $this->getCacheKey();
        $Redis = defer_redis();

        if ($cache = $Redis->get($key)) {
            $arr = json_decode($cache, true);
            if (is_array($arr)) {
                return $arr;
            }
        }

        $array = $this->where('status', [0, 2], 'IN')->ormToCollection();
        $Redis->set($key, json_encode($array, JSON_UNESCAPED_UNICODE));
        return $array;
    }

    public function getCrontab($where = [], $columns = ['sys', 'zone', 'server'])
    {
        $result = [];
        $data = $this->getAvailables();
        foreach ($data as &$value)
        {
            $continue = false;
            foreach ($columns as $col)
            {
                if (isset($value[$col]) && is_string($value[$col])) {
                    $value[$col] = explode(',', $value[$col]);
                }
                $is = (isset($where[$col]) && ( ! is_array($value[$col]) || ! in_array($where[$col], $value[$col]) ));
                if ($is) {
                    $continue = true;
                    break;
                }
            }
            if ($continue) {
                continue;
            }
            $result[] = $value;
        }
        return $result;
    }
}
