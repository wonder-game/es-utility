<?php

namespace WonderGame\EsUtility\Model\Admin;

trait SysinfoModelTrait
{
	// 缓存key
	abstract public function getCacheKey(): string;

	protected function setValueAttr($value, $all)
	{
		return $this->setValue($value, $all['type'], false);
	}

	protected function getValueAttr($value, $all)
	{
		return $this->setValue($value, $all['type'], true);
	}

	protected function setValue($value, $type, $decode = true)
	{
		if ($type == 0) {
			$value = intval($value);
		} else if ($type == 1) {
			$value = strval($value);
		} else {
			if ($decode) {
				$json = json_decode($value, true);
			} elseif (is_array($value)) {
				$json = json_encode($value, JSON_UNESCAPED_UNICODE);
			}
			$json && $value = $json;
		}
		return $value;
	}

	protected function _after_write($res = false)
	{
		$this->delRedisKey($this->getCacheKey());
	}

	protected function _after_delete($res)
	{
		$this->delRedisKey($this->getCacheKey());
	}
}
