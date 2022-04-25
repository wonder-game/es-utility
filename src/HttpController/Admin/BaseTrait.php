<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use EasySwoole\ORM\AbstractModel;
use WonderGame\EsUtility\Common\Classes\DateUtils;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;
use WonderGame\EsUtility\HttpController\BaseControllerTrait;

/**
 * @extends BaseControllerTrait
 */
trait BaseTrait
{
	/** @var AbstractModel $Model */
	protected $Model;
	
	/**
	 * 实例化模型类
	 *   1.为空字符串自动实例化
	 *   2.为null不实例化
	 *   3.为true实例化游戏模型
	 *   4.不为空字符串，实例化指定模型
	 * @var string
	 */
	protected $modelName = '';
	
	protected $get = [];
	
	protected $post = [];
	
	protected function onRequest(?string $action): bool
	{
		return $this->_initialize();
	}
	
	protected function _initialize()
	{
		// 设置组件属性
		$this->setBaseTraitProptected();
		// 请求参数
		$this->requestParams();
		// 实例化模型
		return $this->instanceModel();
	}
	
	protected function setBaseTraitProptected()
	{
	}
	
	protected function getAuthorization()
	{
		$tokenKey = config('TOKEN_KEY');
		if ( ! $this->request()->hasHeader($tokenKey)) {
			return false;
		}
		
		$authorization = $this->request()->getHeader($tokenKey);
		if (is_array($authorization)) {
			$authorization = current($authorization);
		}
		return $authorization;
	}
	
	protected function success($result = null, $msg = null)
	{
		// 合计行antdv的rowKey
		$name = config('fetchSetting.footerField');
		// 合计行为二维数组
		if (isset($result[$name]) && is_array($result[$name])) {
			$date = date(DateUtils::YmdHis);
			$result[$name] = array_map(function ($value) use ($date) {
				$value['key'] = strval($value['key'] ?? ($date . uniqid(true)));
				return $value;
			}, $result[$name]);
		}
		$this->writeJson(Code::CODE_OK, $result, $msg);
	}
	
	protected function instanceModel()
	{
		if ( ! is_null($this->modelName)) {
			$className = ucfirst($this->getStaticClassName());
			
			if ($this->modelName === '') {
				$this->Model = model($className);
			} // 需要gameid的模型
			elseif ($this->modelName === true) {
//                trace('--get:' . var_export($this->get, true) . ', --post:' . var_export($this->post, true));
				if ((isset($this->get['gameid']) && $this->get['gameid'] !== '')
					|| isset($this->post['gameid']) && $this->post['gameid'] !== '') {
					$gameid = $this->get['gameid'] ?? $this->post['gameid'];
					$this->Model = model($className . ':' . $gameid);
				} else {
					// gamid必传，否则会报错
					$this->error(Code::ERROR_OTHER, Dictionary::ADMIN_BASETRAIT_1);
					return false;
				}
			} else {
				$this->Model = model($this->modelName);
			}
		}
		return true;
	}
	
	protected function requestParams()
	{
		$this->get = $this->request()->getQueryParams();
		
		$post = $this->request()->getParsedBody();
		if (empty($post)) {
			$post = $this->json();
		}
		$this->post = $post ?: [];
	}
	
	/**
	 * [1 => 'a', 2 => 'b', 4 => 'c']
	 * 这种数组传给前端会被识别为object
	 * 强转为typescript数组
	 * @param array $array
	 * @return array
	 */
	protected function toArray($array = [])
	{
		$result = [];
		foreach ($array as $value) {
			$result[] = $value;
		}
		return $result;
	}
	
	// 零值元素转为空字符
	protected function zeroToEmpty($array = [], $filterCols = [], $setCols = true, $toArray = true)
	{
		$defaultValue = '';
		$result = [];
		foreach ($array as $key => $value) {
			$row = [];
			foreach ($value as $k => $v) {
				if (in_array($k, $filterCols)) {
					$row[$k] = $v;
					continue;
				}
				$row[$k] = (((is_bool($setCols) && $setCols) || (is_array($setCols) && in_array($k, $setCols))) && $v == 0) ? $defaultValue : $v;
			}
			
			if ($toArray) {
				$result[] = $row;
			} else {
				$result[$key] = $row;
			}
		}
		return $result;
	}
}
