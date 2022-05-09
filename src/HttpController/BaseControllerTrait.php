<?php

namespace WonderGame\EsUtility\HttpController;

use EasySwoole\EasySwoole\Core;
use EasySwoole\Http\AbstractInterface\Controller;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;

/**
 * @extends Controller
 */
trait BaseControllerTrait
{
	private $langsConstants = [];

	public function __construct()
	{
		parent::__construct();

		$this->setLanguageConstants();
	}

	protected function setLanguageConstants()
	{
		$dictionary = config('CLASS_DICTIONARY');
		if ( ! $dictionary || ! class_exists($dictionary)) {
			$appLanguage = '\\App\\Common\\Languages\\Dictionary';
			$dictionary = class_exists($appLanguage) ? $appLanguage : Dictionary::class;
		}
		$objClass = new \ReflectionClass($dictionary);
		$this->langsConstants = $objClass->getConstants();
	}

	protected function getLanguageConstants()
	{
		return $this->langsConstants;
	}

	protected function _isRsa($input = [], $header = [], $category = 'pay')
	{
		// 则要求JWT要符合规则
		$data = verify_token($input, $header, 'operid');

		// 如果不是rsa加密数据并且非本地开发环境
		if (empty($input['envkeydata']) && ! empty($data['INVERTOKEN']) && ! is_env('dev')) {
			trace('密文有误:' . var_export($input, true), 'error', $category);
			return false;
		}

		unset($data['token']);

		return $data;
	}

	protected function onException(\Throwable $throwable): void
	{
		$message = Core::getInstance()->runMode() !== 'produce'
			? $throwable->getMessage()
			: Dictionary::BASECONTROLLERTRAIT_1;

		// 交给异常处理器
		\EasySwoole\EasySwoole\Trigger::getInstance()->throwable($throwable);
		$this->error($throwable->getCode() ?: Code::CODE_INTERNAL_SERVER_ERROR, $message);
	}

	protected function success($result = null, $msg = null)
	{
		$this->writeJson(Code::CODE_OK, $result, $msg);
	}

	protected function error(int $code, $msg = null)
	{
		$this->writeJson($code, [], $msg);
	}

	protected function writeJson($statusCode = 200, $result = null, $msg = null)
	{
		if ( ! $this->response()->isEndResponse()) {

			if (is_null($msg)) {
				$msg = Code::getReasonPhrase($statusCode);
			} elseif ($msg && in_array($msg, $this->langsConstants)) {
				$msg = lang($msg);
			}

			$data = [
				'code' => $statusCode,
				'result' => $result,
				'message' => $msg ?? ''
			];
			$this->response()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			$this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
			// 浏览器对axios隐藏了http错误码和异常信息，如果程序出错，通过业务状态码告诉客户端
			$this->response()->withStatus(Code::CODE_OK);
			return true;
		} else {
			return false;
		}
	}

	protected function writeUpload($url, $code = 200, $msg = '')
	{
		if ( ! $this->response()->isEndResponse()) {

			$data = [
				'code' => $code,
				'url' => $url,
				'message' => $msg
			];
			$this->response()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			$this->response()->withHeader('Content-type', 'application/json;charset=utf-8');
			$this->response()->withStatus(Code::CODE_OK);
			return true;
		} else {
			return false;
		}
	}

	protected function isMethod($method)
	{
		return strtoupper($this->request()->getMethod()) === strtoupper($method);
	}

	protected function getStaticClassName()
	{
		$array = explode('\\', static::class);
		return end($array);
	}
}
