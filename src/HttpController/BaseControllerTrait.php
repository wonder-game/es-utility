<?php

namespace WonderGame\EsUtility\HttpController;

use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\Redis\Redis;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Exception\WarnException;
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

    /**
     * @var mixed rawContent
     */
    protected $raw = '';

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
        $request = $this->request();
        /* @var Controller $this */
        $this->get = $request->getQueryParams();
        
        $post = $request->getParsedBody();
        if (empty($post)) {
            $post = $this->json();
        }
        $this->post = is_array($post) ? $post : [];

        /**
         * 处理部分通用参数数组
         *
         * !!!!!! 注意，此处往get和post和input加了些特殊参数，意味着它们不再等同于request对象的数据了 !!!!!!
         * 在一些情景下（如加解密码、验签……），如需原数据，可以通过$this->>request()对象的以下方法获取
         * getQueryParams()、getParsedBody()、getRequestParam()
         * getSwooleRequest()->rawContent()、getSwooleRequest()->post、getBody()->__toString()
         * 或者去 raw属性拿（见下文代码）
         */
        $utility = $request->getMethod() == 'GET' ? $this->get : $this->post;
        // 包序号（版本序号）
        empty($utility['versioncode']) && $utility['versioncode'] = 1;
        // 有些包的参数名写错成android了
        isset($utility['android']) && ! isset($utility['androidid']) && $utility['androidid'] = $utility['android'];
        // 不要信任客户端传的IP！！！有需要用可以去原生数据拿
        $utility['ip'] = ip($request);
        $request->getMethod() == 'GET' ? $this->get = $utility : $this->post = $utility;

        $this->input = array_merge($this->get, $this->post);

        //  $request->getSwooleRequest()->rawContent()也可以
        $this->raw = $request->getBody()->__toString();
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

    /** 检测是否为rsa解密数据（如本地开发环境则直接为true）
     * @param array|null $input
     * @return bool
     */
    protected function _isRsaDecode($input = null)
    {
        $input = is_null($input) ? $this->input : $input;
        return ! empty($input[config('RSA.key')]) || is_env('dev');
    }

    /**
     * 检测是否至少符合Jwt或RSA
     * @param array|null $input
     * @return array
     */
    protected function _isJwtOrRsa($input = [], $category = 'pay')
    {
        try {
            // 先尝试JWT
            $data = verify_token([], 'uid', $input);
        } catch (HttpParamException $e) {
            // 记日志
            trace('jwt验证不通过：【' . $e->getMessage() . '】将进行rsa检测。', 'error', $category);
            // 如果不是rsa加密数据并且非本地开发环境
            if ( ! $this->_isRsaDecode($input)) {
                throw new HttpParamException('密文有误:', Code::CODE_UNAUTHORIZED);
            }
        }

        unset($data['token']);
        return $data;
    }

    protected function onException(\Throwable $throwable): void
    {
        if ($throwable instanceof HttpParamException) {
            $message = $throwable->getMessage();
        } elseif ($throwable instanceof WarnException) {
            $message = $throwable->getMessage();
            $task = \EasySwoole\EasySwoole\Task\TaskManager::getInstance();
            $task->async(new \WonderGame\EsUtility\Task\Error(
                    [
                        'message' => $message,
                        'file' => $throwable->getFile(),
                        'line' => $throwable->getLine(),
                    ], $throwable->getData())
            );
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

    protected function error(int $code, $msg = null, $result = [])
    {
        $this->writeJson($code, $result, $msg);
        return false;
    }

    protected function writeJson($statusCode = 200, $result = null, $msg = null)
    {
        /* @var Controller $this */
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
        /* @var Controller $this */
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
        /* @var Controller $this */
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

    // 兼容多种客户端
    protected function isHttpAjax()
    {
        return $this->request()->getHeaderLine('x-requested-with') === 'XMLHttpRequest';
    }

    protected function getStaticClassName()
    {
        $array = explode('\\', static::class);
        return end($array);
    }

    protected function actionNotFoundName()
    {
        /* @var Controller $this */
        return $this->actionNotFoundPrefix . $this->getActionName();
    }

    /**
     * 去除了公共前缀的 $this->getAllowMethodReflections() key列表
     * @param null $call
     * @return array|false[]|int[]|string[]
     */
    protected function getAllowMethods($call = null)
    {
        /* @var Controller $this */
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
        /* @var Controller $this */
        $actionName = $this->actionNotFoundName();
        // 仅调用public，避免与普通方法混淆
        $publics = $this->getAllowMethodReflections();

        if (isset($publics[$actionName])) {
            $this->$actionName();
        } else {
            parent::actionNotFound($action);
        }
    }

    /**
     * 接口限流，redis计数
     * @param Redis $redis
     * @param string $cfgKey
     * @param $input
     * @param $isWhite 是否白名单，白名单不受限制
     * @return \Closure
     * @throws HttpParamException
     */
    protected function requestLimit(Redis $redis, string $cfgKey, $input = [], $isWhite = false)
    {
        // 配置参考
        /*'REQUEST_LIMIT' => [
            'user_reg' => [
                'ip' => [
                    'interval' => 86400,
                    'times' => 5,
                    'limit_msg' => '此ip注册数已达上限',
                    'limit_code' => 422
                ],
                'devid' => [
                    'interval' => 86400,
                    'times' => 3,
                    'limit_msg' => '此设备注册数已达上限',
                    'limit_code' => 423
                ],
            ],
        ];*/
        $input = $input ?: $this->input;
        $config = config("REQUEST_LIMIT.$cfgKey");
        if ($config) {
            foreach ($config as $lk => $lv) {
                if (!isset($input[$lk])) {
                    continue;
                }
                $lkey = "request_limit_{$cfgKey}_{$lk}_$input[$lk]";
                if ($redis->exists($lkey)) {
                    // 请求开始时还未自增
                    if (!$isWhite && $redis->get($lkey) >= $lv['times']) {
                        throw new HttpParamException($lv['limit_msg'], $lv['limit_code']);
                    }
                } else {
                    $redis->setEx($lkey, $lv['interval'], 0);
                }
            }
        }

        // 计数自增
        return function () use ($config, $input, $redis, $cfgKey) {
            if ($config) {
                foreach ($config as $lk => $lv) {
                    if (isset($input[$lk])) {
                        $key = "request_limit_{$cfgKey}_{$lk}_$input[$lk]";
                        // 防止时间设置过短，key已过期则不理，下次重新开始计数
                        if ($redis->exists($key)) {
                            $redis->incr($key);
                        }
                    }
                }
            }
        };
    }

    /**
     * Redis分布式锁，处理多台机器批量收到相同请求的问题，主要处理异常请求，不设白
     * @param Redis $redis
     * @param string $cfgKey
     * @param array $input
     * @return void
     * @throws HttpParamException
     */
    protected function requestLock(Redis $redis, string $cfgKey, array $input = [])
    {
        // 配置参考
        /*'REQUEST_LOCK' => [
            'create_order' => [
                'uid' => [
                    'required' => true,
                    'interval' => 3,
                    'limit_msg' => '操作过于频繁，请稍后再试',
                    'limit_code' => 422,
                ],
            ],
        ],*/
        $input = $input ?: $this->input;
        $config = config("REQUEST_LOCK.$cfgKey");
        if (empty($config)) {
            return;
        }

        $time = time();
        foreach ($config as $lk => $lv) {
            if (!isset($input[$lk]) || $input[$lk] === '') {
                if ($lv['required']) {
                    // 必传
                    throw new HttpParamException(lang(Dictionary::PARAMS_ERROR), $lv['limit_code']);
                } else {
                    // 可选
                    continue;
                }
            }
            $lkey = "request_lock_{$cfgKey}_{$lk}_$input[$lk]";

            // PS：有玩家key一直没被删除，增加时间校验机制，超时则删key重新抢锁
            $last = $redis->get($lkey);
            if ($last && is_numeric($last) && $time - $last > $lv['interval']) {
                $redis->del($lkey);
            }

            $isLock = $redis->setNx($lkey, $time);
            if (!$isLock) {
                throw new HttpParamException($lv['limit_msg'], $lv['limit_code']);
            }
            // 设置有效期
            $redis->expire($lkey, $lv['interval']);
        }
    }
}
