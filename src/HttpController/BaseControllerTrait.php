<?php

namespace WonderGame\EsUtility\HttpController;

use EasySwoole\EasySwoole\Core;
use EasySwoole\Http\AbstractInterface\Controller;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;

/**
 * @extends Controller
 */
trait BaseControllerTrait
{
    /**
     * onRequest GET 参数
     * @var array
     */
    protected $get = [];

    /**
     * onRequest POST 参数
     * @var array
     */
    protected $post = [];

    /**
     * onRequest GET + POST 参数
     * @var array
     */
    protected $input = [];

	private $langsConstants = [];

    protected $actionNotFoundPrefix = '_';

	public function __construct()
	{
		parent::__construct();

		$this->setLanguageConstants();
	}

    protected function onRequest(?string $action): ?bool
    {
        $this->requestParams();
        return parent::onRequest($action);
    }

    protected function requestParams()
    {
        $this->get = $this->request()->getQueryParams();

        $post = $this->request()->getParsedBody();
        if (empty($post)) {
            $post = $this->json();
        }
        $this->post = is_array($post) ? $post : [];
        $this->input = array_merge($this->get, $this->post);
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
        if ($throwable instanceof HttpParamException) {
            $message = $throwable->getMessage();
        } else {
            $message = ! is_env('produce') ? $throwable->getMessage() : lang(Dictionary::BASECONTROLLERTRAIT_1);
            // 交给异常处理器
            \EasySwoole\EasySwoole\Trigger::getInstance()->throwable($throwable);
        }
		$this->error($throwable->getCode() ?: Code::CODE_INTERNAL_SERVER_ERROR, $message);
	}

    protected function success($result = null, $msg = null)
    {
        return $this->writeJson(Code::CODE_OK, $result, $msg);
    }

    protected function error(int $code, $msg = null)
    {
        $this->writeJson($code, [], $msg);
        return false;
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
				'msg' => $msg ?? ''
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
				'msg' => $msg
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

    protected function isHttpGet()
    {
        return $this->isMethod('GET');
    }

    protected function isHttpPost()
    {
        return $this->isMethod('POST');
    }

	protected function getStaticClassName()
	{
		$array = explode('\\', static::class);
		return end($array);
	}

    protected function actionNotFoundName()
    {
        return $this->actionNotFoundPrefix . $this->getActionName();
    }

    /**
     * 去除了公共前缀的 $this->getAllowMethodReflections() key列表
     * @param null $call
     * @return array|false[]|int[]|string[]
     */
    protected function getAllowMethods($call = null)
    {
        return array_map(
            function ($val) use ($call) {
                if (strpos($val, $this->actionNotFoundPrefix) === 0) {
                    $val = substr($val, strlen($this->actionNotFoundPrefix));
                }
                return (is_callable($call) || (is_string($call) && function_exists($call))) ? $call($val) : $val;
            },
            array_keys($this->getAllowMethodReflections())
        );
    }

    /**
     * @param string|null $action
     */
    protected function actionNotFound(?string $action)
    {
        $actionName = $this->actionNotFoundName();
        // 仅调用public，避免与普通方法混淆
        $publics = $this->getAllowMethodReflections();

        if (isset($publics[$actionName])) {
            try {

                $this->$actionName();

            } catch (HttpParamException $e) {
                $this->error(Code::ERROR_OTHER, $e->getMessage());
            } catch (\Exception $e) {
                $this->error(Code::CODE_INTERNAL_SERVER_ERROR, $e->getMessage());
            }
        } else {
            parent::actionNotFound($action);
        }
    }
}
