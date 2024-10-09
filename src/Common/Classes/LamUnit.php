<?php
/**
 * 测试类
 *
 * @author 林坤源
 * @version 1.0.2 最后修改时间 2020年10月21日
 */

namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Http\Request;
use EasySwoole\I18N\I18N;

class LamUnit
{
    public static function setI18n(Request $request, $headerKey = 'accept-language', $paramKey = 'lang')
    {
        // 请求参数的优先级高于头信息
        if ( ! $langage = $request->getRequestParam($paramKey) ?: $request->getHeader($headerKey)) {
            return;
        }
        is_array($langage) && $langage = current($langage);
        $languages = config('LANGUAGES') ?: [];
        foreach ($languages as $lang => $value) {
            // 回调
            if (is_callable($value['match'])) {
                $match = $value['match']($langage);
                if ($match === true) {
                    I18N::getInstance()->setLanguage($lang);
                    break;
                }
            } // 正则
            elseif (is_string($value['match']) && preg_match($value['match'], $langage)) {
                I18N::getInstance()->setLanguage($lang);
                break;
            }
        }
    }

    /**
     * @param Request $request
     * @param array $array 要合并的数据
     * @param bool $merge 是否覆盖掉原参数的值
     * @param string|array $unset 要删除的量
     * @param array $extend 扩展量（优先级最高的）
     */
    static public function withParams(Request $request, $array = [], $merge = true, $unset = '', $extend = [])
    {
        $method = $request->getMethod();
        $params = $method == 'GET' ? $request->getQueryParams() : $request->getParsedBody();
        if (is_array($array)) {
            if ($merge) {
                $params = $array + $params;
            } else {
                $params += $array;
            }
        }

        $params = array_merge($params, $extend);

        if ($unset) {
            is_array($unset) or $unset = explode(',', $unset);
            foreach ($unset as $v) {
                unset($params[$v]);
            }
        }

        $method == 'GET' ? $request->withQueryParams($params) : $request->withParsedBody($params);
    }

    /**
     * 解密
     * 注意当请求参数中有envkeydata时本方法会改写$request()对象的数据
     */
    static public function decrypt(Request $request, $field = 'envkeydata')
    {
        $cipher = $request->getRequestParam($field);
        // 参数里没有$field这个量
        if (is_null($cipher)) {
            return null;
        }
        $envkeydata = LamOpenssl::getInstance()->decrypt($cipher);
        $array = json_decode($envkeydata, true);
        ($array && $envkeydata = $array) or parse_str($envkeydata, $envkeydata);

        $envkeydata = $envkeydata ?: [];
        // 下文环境中可以通过 $field 这个量的值来判断是否解密成功
        $envkeydata[$field] = (bool)$envkeydata;

        self::withParams($request, $envkeydata, true);

        return $envkeydata;
    }
}
