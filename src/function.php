<?php

use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Http\Request;
use EasySwoole\I18N\I18N;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\Db\MysqliClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\Redis\Redis;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Spl\SplArray;
use WonderGame\EsUtility\Common\Classes\CtxRequest;
use WonderGame\EsUtility\Common\Classes\HttpRequest;
use WonderGame\EsUtility\Common\Classes\LamJwt;
use WonderGame\EsUtility\Common\Classes\LamOpenssl;
use WonderGame\EsUtility\Common\Classes\Mysqli;
use WonderGame\EsUtility\Common\CloudLib\Captcha\CaptchaInterface;
use WonderGame\EsUtility\Common\CloudLib\Cdn\CdnInterface;
use WonderGame\EsUtility\Common\CloudLib\Dns\DnsInterface;
use WonderGame\EsUtility\Common\CloudLib\Email\EmailInterface;
use WonderGame\EsUtility\Common\CloudLib\Sms\SmsInterface;
use WonderGame\EsUtility\Common\CloudLib\Storage\StorageInterface;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\OrmCache\Strings;
use WonderGame\EsUtility\HttpTracker\Index as HttpTracker;
use WonderGame\EsUtility\Notify\DingTalk\Message\Markdown;
use WonderGame\EsUtility\Notify\DingTalk\Message\Text;
use WonderGame\EsUtility\Notify\EsNotify;
use WonderGame\EsUtility\Notify\Feishu\Message\Card;
use WonderGame\EsUtility\Notify\Feishu\Message\Text as FeishuText;
use WonderGame\EsUtility\Notify\Feishu\Message\Textarea;
use WonderGame\EsUtility\Notify\WeChat\Message\Notice;


if ( ! function_exists('is_super')) {
    /**
     * 是否超级管理员
     * @param int $rid
     * @return bool
     */
    function is_super($rid = null)
    {
        $super = sysinfo('super');
        return $super && is_array($super) && in_array($rid, $super);
    }
}


if ( ! function_exists('find_model')) {
    /**
     * @param string $name
     * @param bool $throw
     * @return string|null
     * @throws Exception
     */
    function find_model($name, $throw = true)
    {
        if ( ! $namespaces = config('MODEL_NAMESPACES')) {
            $namespaces = ['\\App\\Model'];
        }

        foreach ($namespaces as $namespace) {
            $className = rtrim($namespace, '\\') . '\\' . ucfirst($name);
            if (class_exists($className)) {
                return $className;
            }
        }

        if ($throw) {
            throw new \Exception("Class Not Found: $name");
        }
        return null;
    }
}

if ( ! function_exists('model')) {
    /**
     * 实例化Model
     * @param string $name Model名称
     * @param array $data
     * @param bool|numeric $inject bool:注入连接, numeric: 注入连接并切换到指定时区
     * @return AbstractModel|Strings
     */
    function model(string $name = '', array $data = [], $inject = false)
    {
        // 允许传递多级命名空间
        $space = '';
        $name = str_replace('/', '\\', $name);
        if (strpos($name, '\\')) {
            $list = explode('\\', $name);
            $name = array_pop($list);
            $space = implode('\\', array_map('ucfirst', $list)) . '\\';
        }

        $name = parse_name($name, 1);

        $subid = '';
        // 实例化XXX_xx模型
        if (strpos($name, ':')) {
            list($name, $subid) = explode(':', $name);
        }
        $tableName = $subid != '' ? parse_name($name, 0, false) . "_$subid" : '';

        $className = find_model($space . $name);

        /** @var AbstractModel $model */
        $model = new $className($data, $tableName, $subid);

        // 注入连接(连接池连接)
        if (is_bool($inject) && $inject) {
            $connectName = $model->getConnectionName();
            /** @var MysqliClient $Client */
            $Client = DbManager::getInstance()->getConnection($connectName)->defer();
            $Client->connectionName($connectName);
            $model->setExecClient($Client);
        } // 注入连接(新连接) + 切换时区
        else if (is_numeric($inject)) {
            // 请不要从连接池获取连接, 否则连接回收后会污染连接池
            $connectName = $model->getConnectionName();
            $Client = new Mysqli($connectName);
            $Client->setTimeZone($inject);
            $model->setExecClient($Client);
            \Swoole\Coroutine::defer(function () use ($Client) {
                $Client->close();
            });
        }
        return $model;
    }
}

if ( ! function_exists('model_admin')) {
    function model_admin(string $name = '', array $data = [], $inject = false)
    {
        return model('Admin\\' . ucfirst($name), $data, $inject);
    }
}

if ( ! function_exists('model_log')) {
    function model_log(string $name = '', array $data = [], $inject = false)
    {
        return model('Log\\' . ucfirst($name), $data, $inject);
    }
}

if ( ! function_exists('model_pay')) {
    function model_pay(string $name = '', array $data = [], $inject = false)
    {
        return model('Pay\\' . ucfirst($name), $data, $inject);
    }
}

if ( ! function_exists('model_sdk')) {
    function model_sdk(string $name = '', array $data = [], $inject = false)
    {
        return model('Sdk\\' . ucfirst($name), $data, $inject);
    }
}

if ( ! function_exists('model_service')) {
    function model_service(string $name = '', array $data = [], $inject = false)
    {
        return model('Service\\' . ucfirst($name), $data, $inject);
    }
}

if ( ! function_exists('model_attr')) {
    function model_attr(string $name = '', array $data = [], $inject = false)
    {
        return model('Attr\\' . ucfirst($name), $data, $inject);
    }
}

if ( ! function_exists('model_media')) {
    function model_media(string $name = '', array $data = [], $inject = false)
    {
        return model('Media\\' . ucfirst($name), $data, $inject);
    }
}

if ( ! function_exists('model_oper')) {
    function model_oper(string $name = '', array $data = [], $inject = false)
    {
        return model('Oper\\' . ucfirst($name), $data, $inject);
    }
}

if ( ! function_exists('config')) {
    /**
     * 获取和设置配置参数
     * @param string|array $name 参数名
     * @param mixed $value 参数值
     * @return mixed
     */
    function config($name = '', $value = null)
    {
        $Config = Config::getInstance();
        if (is_null($value) && is_string($name)) {
            return $Config->getConf($name);
        } else {
            return $Config->setConf($name, $value);
        }
    }
}


if ( ! function_exists('trace')) {
    /**
     * 记录日志信息，协程defer时写入
     * @param string|array $log log信息 支持字符串和数组
     * @param string $level 日志级别
     * @param string $category 日志类型
     * @return void|bool
     */
    function trace($log = '', $level = 'info', $category = 'debug')
    {
        is_scalar($log) or $log = json_encode($log, JSON_UNESCAPED_UNICODE);
        return Logger::getInstance()->$level($log, $category);
    }
}

if ( ! function_exists('trace_immediate')) {
    /**
     * 记录日志信息,立即写入
     * @param string|array $log log信息 支持字符串和数组
     * @param string $level 日志级别
     * @return void|bool
     */
    function trace_immediate($log = '', $level = 'info')
    {
        return trace($log, $level, 'immediate');
    }
}


if ( ! function_exists('defer_redis')) {
    /**
     * 返回redis句柄资源
     * @param string $poolname 标识
     * @return Redis
     */
    function defer_redis($poolname = 'default')
    {
        // defer方式获取连接
        return RedisPool::defer($poolname);
    }
}


if ( ! function_exists('parse_name')) {
    /**
     * 字符串命名风格转换
     * @param string $name 字符串
     * @param integer $type 转换类型  0-将Java风格转换为C的风格，即：驼峰=>下划线； 1-将C风格转换为Java的风格，即：下划线=>驼峰
     * @param bool $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    function parse_name($name, $type = 0, $ucfirst = true)
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        } else {
            return strtolower(trim(preg_replace('/[A-Z]/', '_\\0', $name), '_'));
        }
    }
}


if ( ! function_exists('array_merge_multi')) {
    /**
     * 多维数组合并（支持多数组）
     * @return array
     */
    function array_merge_multi(...$args)
    {
        $array = [];
        foreach ($args as $arg) {
            if (is_array($arg)) {
                foreach ($arg as $k => $v) {
                    $array[$k] = is_array($v) ? array_merge_multi($array[$k] ?? [], $v) : $v;
                }
            }
        }
        return $array;
    }
}


if ( ! function_exists('array_sort_multi')) {
    /**
     * 二维数组按某字段排序
     */
    function array_sort_multi($data = [], $field = '', $direction = SORT_DESC, $fmt = true, $filterCols = [])
    {
        if ( ! $data) return [];
        $arrsort = [];
        foreach ($data as $uniqid => &$row) {
            foreach ($row as $key => &$value) {
                $fmt && ! in_array($key, $filterCols) && $value = format_number($value, 2, true);
                $arrsort[$key][$uniqid] = str_replace('%', '', $value);
            }
            unset($value);
        }
        unset($row);
        if ($direction) {
            array_multisort($arrsort[$field], $direction, $data);
        }
        return $data;
    }
}

if ( ! function_exists('format_number')) {
    /**
     * 格式化数字或百分比
     * @param numeric|string $num 要处理的数字或百分比
     * @param numeric $prec 小数点后的精度
     * @param bool $multiply 如果是百分比是否要乘以100
     * @return numeric|string
     */
    function format_number($num, $prec = 2, $multiply = true)
    {
        if ( ! is_scalar($num)) {
            return $num;
        }
        $num = trim($num);
        $percent = false;

        // 百分比
        if (preg_match('/[0-9.]+%$/', $num)) {
            $percent = true;
            $num = ($multiply ? 100 : 1) * (float)$num;
        }
        is_numeric($num) && $num = str_replace('.00', '', sprintf("%01.{$prec}f", $num));
        return $num . ($percent ? '%' : '');
    }
}

if ( ! function_exists('listdate')) {
    /**
     * 返回两个日期之间的具体日期或月份
     *
     * @param string|int $beginday 开始日期，格式为Ymd或者Y-m-d
     * @param string|int $endday 结束日期，格式为Ymd或者Y-m-d
     * @param string|int|bool $type 类型: 1|%d|%a|true-日； 2|%m|false-月； 3-季； 4|%y-年
     * @param string $format value的格式
     * @param string $keyfmt key的格式
     * @return array
     */
    function listdate($beginday, $endday, $type = 2, $format = 'Ymd', $keyfmt = 'ymd')
    {
        $type = is_numeric($type) ? (int)$type : $type;
        $dif = difdate($beginday, $endday, ! ($type === 2 || $type === '%m' || $type === false));
        $arr = [];
        // 季
        if ($type === 3) {
            // 开始的年份, 结束的年份
            $arry = [date('Y', strtotime($beginday)), date('Y', strtotime($endday))];
            // 开始的月份, 结束的月份
            $arrm = [date('m', strtotime($beginday)), date('m', strtotime($endday))];

            $quarter = ['04', '07', 10, '01'];
            $come = false; // 入栈的标识
            $by = $arry[0]; // 开始的年份
            do {
                foreach ($quarter as $k => $v) {
                    if ($arrm[0] < $v || $k == 3) {
                        $come = true;
                    }

                    $key = substr($by, 2) . str_pad($k + 1, 2, '0', STR_PAD_LEFT);

                    // 下一年度
                    if ($k == 3) {
                        ++$by;
                    }

                    if ($come) {
                        $arr[$key] = $by . $v . '01'; // p1803=>strtotime(20181001)
                    }
                }
            } while ($by <= $arry[1]);
        } // 年
        elseif ($type === 4 || $type === '%y') {
            $begintime = substr($beginday, 0, 4);
            for ($i = 0; $i <= $dif; ++$i) {
                $arr[$begintime - 1] = $begintime . '0101'; // p2018=>strtotime(20190101)
                ++$begintime;
            }
        } else {
            // 日期 p180302=>strtotime(20180303)
            if ($type === true || $type === 1 || $type === '%d') {
                $unit = 'day';
            } // 月份 p1803=>strtotime(20180401)
            else {
                $unit = 'month';
            }

            $begintime = strtotime(date($format, strtotime($beginday)));
            for ($i = 0; $i <= $dif; ++$i) {
                $key = strtotime("+$i $unit", $begintime);
                $arr[date($keyfmt ?: 'ymd', $key - 3600 * 24)] = date($format, $key);
            }
            // 如果想要将下标变为自增
            if ( ! $keyfmt) {
                $arr = array_values($arr);
            }
        }
        return $arr;
    }
}


if ( ! function_exists('difdate')) {
    /**
     * 计算两个日期之间的间隔(天、月、年等)
     *
     * @param string $beginday 开始时间
     * @param string|null $endday 结束时间
     * @param string|bool $format 间隔类型：%y-年；%m|false-月；%a|true-天；
     * @return int
     */
    function difdate($beginday, $endday, $format = '%a')
    {
        if (is_bool($format)) {
            $format = $format === true ? '%a' : '%m';
        }
        /** @var DateInterval $interval */
        $interval = date_diff(date_create($beginday), date_create($endday));
        return $format === '%m' ? (($interval->format('%y') * 12) + $interval->format('%m')) : $interval->format($format);
    }
}

if ( ! function_exists('get_token')) {
    /**
     * 生成jwt值并返回
     * @param array|int $id
     * @param string|null $expire 有效期（秒）
     * @return string
     */
    function get_token($id, $expire = null)
    {
        return LamJwt::getToken(is_array($id) ? $id : ['id' => $id], '', $expire);
    }
}

if ( ! function_exists('verify_token')) {
    /**
     * 验证jwt并读取用户信息
     * @param array $header jwt所在的数组，传[]则自动从头信息中获取
     * @param string|null|bool $key 为string类型时会校验这个key值是否非空；为null或false或空时直接返回token
     * @param array $orgs 如果有传则会进行二次校验
     */
    function verify_token($header = [], $key = 'uid', $orgs = [])
    {
        $header or $header = CtxRequest::getInstance()->request->getHeaders();

        if ( ! $token = $header[config('ENCRYPT.jwtkey')][0] ?? '') {
            throw new HttpParamException('缺少token', Code::CODE_BAD_REQUEST);
        }
        if ( ! $key) {
            return $token;
        }
        // 验证JWT
        $jwt = LamJwt::verifyToken($token, '', false);
        is_array($jwt['data']) && $jwt['data'] = $jwt['data'] + ($jwt['data']['data'] ?? []);
        if ($jwt['status'] != 1 || empty($jwt['data'][$key])) {
            throw new HttpParamException('jwt有误', Code::CODE_UNAUTHORIZED);
        }
        // 二次校验
        if ($orgs && (empty($orgs[$key]) || $jwt['data'][$key] != $orgs[$key])) {
            throw new HttpParamException("jwt的 $key 不符:" . ($jwt['data'][$key] ?? ''), Code::CODE_PRECONDITION_FAILED);
        }

        $jwt['data']['token'] = $token;
        return $jwt['data'];
    }
}


if ( ! function_exists('ip')) {
    /**
     * 获取http客户端ip
     * @param null $Request
     * @return false|string
     */
    function ip($Request = null)
    {
        // Request继承 \EasySwoole\Http\Message\Message 皆可
        if ( ! $Request instanceof \EasySwoole\Http\Request) {
            $Request = \WonderGame\EsUtility\Common\Classes\CtxRequest::getInstance()->request;
            if (empty($Request)) {
                return false;
            }
        }

        $ip = ($xForwardedFor = $Request->getHeaderLine('x-forwarded-for')) ? $xForwardedFor : $Request->getHeaderLine('x-real-ip');

        if (empty($ip)) {
            $servers = $Request->getServerParams();
            if ( ! empty($servers['remote_addr'])) {
                $ip = $servers['remote_addr'];
            }
        }

        $arr = explode(';', $ip);
        foreach ($arr as $item) {
            if ($item = trim($item)) {
                $itemArr = explode(',', $item);
                foreach ($itemArr as $value) {
                    if (($value = trim($value)) && ! in_array($value, ['unknown'])) {
                        return $value;
                    }
                }
            }
        }

        return false;
    }
}


if ( ! function_exists('lang')) {
    function lang($const = '')
    {
        return I18N::getInstance()->translate($const);
    }
}

if ( ! function_exists('notice')) {
    function notice($content = '', $title = null, $name = 'default')
    {
        // 富文本
        if ($title) {
            config('ES_NOTIFY.driver') == 'feishu' ? feishu_textarea($title, $content, true, $name) : dingtalk_markdown($title, $content, true, $name);
        } else {
            config('ES_NOTIFY.driver') == 'feishu' ? feishu_text($content, true, $name) : dingtalk_text($content, true, $name);
        }
    }
}

if ( ! function_exists('wechat_notice')) {
    function wechat_notice($content = '', $name = 'default')
    {
        EsNotify::getInstance()->doesOne('weChat', new Notice([
            'templateId' => config("ES_NOTIFY.weChat.$name.tplId.notice"),
            'content' => $content,
        ]), $name);
    }
}


if ( ! function_exists('wechat_warning')) {
    function wechat_warning($content = '', $name = 'default')
    {
        EsNotify::getInstance()->doesOne('weChat', new Notice([
            'templateId' => config("ES_NOTIFY.weChat.$name.tplId.warning"),
            'content' => $content,
        ]), $name);
    }
}


if ( ! function_exists('dingtalk_text')) {
    function dingtalk_text($content = '', $at = true, $name = 'default')
    {
        EsNotify::getInstance()->doesOne('dingTalk', new Text([
            'content' => $content,
            'isAtAll' => $at
        ]), $name);
    }
}


if ( ! function_exists('dingtalk_markdown')) {
    function dingtalk_markdown($title = '', $text = '', $at = true, $name = 'default')
    {
        if (is_array($text)) {
            $arr = ['### **' . $title . '**'];
            foreach ($text as $key => $value) {
                $exp = strpos(strtolower($key), 'exp') === 0;
                $arr[] = $exp ? $value : "- $key: $value";
            }
            $text = implode(" \n\n ", $arr);
        }
        EsNotify::getInstance()->doesOne('dingTalk', new Markdown([
            'title' => $title,
            'text' => $text,
            'isAtAll' => $at
        ]), $name);
    }
}


if ( ! function_exists('feishu_text')) {
    function feishu_text($content = '', $at = true, $name = 'default')
    {
        EsNotify::getInstance()->doesOne('feishu', new FeishuText([
            'content' => $content,
            'isAtAll' => $at
        ]), $name);
    }
}


if ( ! function_exists('feishu_textarea')) {
    function feishu_textarea($title = '', $content = '', $at = true, $name = 'default')
    {
        EsNotify::getInstance()->doesOne('feishu', new Textarea(array_merge([
            'content' => $content,
            'isAtAll' => $at
        ], $title && is_string($title) ? ['title' => $title] : [])), $name);
    }
}


if ( ! function_exists('feishu_card')) {
    function feishu_card($title = '', $content = '', $at = true, $name = 'default')
    {
        EsNotify::getInstance()->doesOne('feishu', new Card(array_merge([
            'content' => $content,
            'isAtAll' => $at
        ], $title && is_string($title) ? ['title' => $title] : [])), $name);
    }
}


if ( ! function_exists('array_to_std')) {
    function array_to_std(array $array = [])
    {
        $func = __FUNCTION__;
        $std = new \stdClass();
        foreach ($array as $key => $value) {
            $std->{$key} = is_array($value) ? $func($value) : $value;
        }
        return $std;
    }
}


if ( ! function_exists('convertip')) {
    /**
     * 官方网站　 http://www.cz88.net　请适时更新ip库
     * 按照ip地址返回所在地区
     * @param string $ip ip地址  如果为空就使用当前请求ip
     * @param string $ipdatafile DAT文件完整路径
     * @return string 广东省广州市 电信  或者  - Unknown
     *
     */
    function convertip($ip = '', $ipdatafile = '')
    {
        $ipdatafile = $ipdatafile ?: config('IPDAT_PATH');
        $ip = $ip ?: ip();
        if (empty($ip)) {
            return '- Empty';
        }
        if (is_numeric($ip)) {
            $ip = long2ip($ip);
        }
        if ( ! $fd = @fopen($ipdatafile, 'rb')) {
            return '- Invalid IP data file';
        }

        $ip = explode('.', $ip);
        $ipNum = $ip[0] * 16777216 + $ip[1] * 65536 + $ip[2] * 256 + $ip[3];

        if ( ! ($DataBegin = fread($fd, 4)) || ! ($DataEnd = fread($fd, 4))) return '';
        @$ipbegin = implode('', unpack('L', $DataBegin));
        if ($ipbegin < 0) $ipbegin += pow(2, 32);
        @$ipend = implode('', unpack('L', $DataEnd));
        if ($ipend < 0) $ipend += pow(2, 32);
        $ipAllNum = ($ipend - $ipbegin) / 7 + 1;

        $BeginNum = $ip2num = $ip1num = 0;
        $ipAddr1 = $ipAddr2 = '';
        $EndNum = $ipAllNum;

        while ($ip1num > $ipNum || $ip2num < $ipNum) {
            $Middle = intval(($EndNum + $BeginNum) / 2);

            fseek($fd, $ipbegin + 7 * $Middle);
            $ipData1 = fread($fd, 4);
            if (strlen($ipData1) < 4) {
                fclose($fd);
                return '- System Error';
            }
            $ip1num = implode('', unpack('L', $ipData1));
            if ($ip1num < 0) $ip1num += pow(2, 32);

            if ($ip1num > $ipNum) {
                $EndNum = $Middle;
                continue;
            }

            $DataSeek = fread($fd, 3);
            if (strlen($DataSeek) < 3) {
                fclose($fd);
                return '- System Error';
            }
            $DataSeek = implode('', unpack('L', $DataSeek . chr(0)));
            fseek($fd, $DataSeek);
            $ipData2 = fread($fd, 4);
            if (strlen($ipData2) < 4) {
                fclose($fd);
                return '- System Error';
            }
            $ip2num = implode('', unpack('L', $ipData2));
            if ($ip2num < 0) $ip2num += pow(2, 32);

            if ($ip2num < $ipNum) {
                if ($Middle == $BeginNum) {
                    fclose($fd);
                    return '- Unknown';
                }
                $BeginNum = $Middle;
            }
        }

        $ipFlag = fread($fd, 1);
        if ($ipFlag == chr(1)) {
            $ipSeek = fread($fd, 3);
            if (strlen($ipSeek) < 3) {
                fclose($fd);
                return '- System Error';
            }
            $ipSeek = implode('', unpack('L', $ipSeek . chr(0)));
            fseek($fd, $ipSeek);
            $ipFlag = fread($fd, 1);
        }

        if ($ipFlag == chr(2)) {
            $AddrSeek = fread($fd, 3);
            if (strlen($AddrSeek) < 3) {
                fclose($fd);
                return '- System Error';
            }
            $ipFlag = fread($fd, 1);
            if ($ipFlag == chr(2)) {
                $AddrSeek2 = fread($fd, 3);
                if (strlen($AddrSeek2) < 3) {
                    fclose($fd);
                    return '- System Error';
                }
                $AddrSeek2 = implode('', unpack('L', $AddrSeek2 . chr(0)));
                fseek($fd, $AddrSeek2);
            } else {
                fseek($fd, -1, SEEK_CUR);
            }

            while (($char = fread($fd, 1)) != chr(0))
                $ipAddr2 .= $char;

            $AddrSeek = implode('', unpack('L', $AddrSeek . chr(0)));
            fseek($fd, $AddrSeek);

            while (($char = fread($fd, 1)) != chr(0))
                $ipAddr1 .= $char;
        } else {
            fseek($fd, -1, SEEK_CUR);
            while (($char = fread($fd, 1)) != chr(0))
                $ipAddr1 .= $char;

            $ipFlag = fread($fd, 1);
            if ($ipFlag == chr(2)) {
                $AddrSeek2 = fread($fd, 3);
                if (strlen($AddrSeek2) < 3) {
                    fclose($fd);
                    return '- System Error';
                }
                $AddrSeek2 = implode('', unpack('L', $AddrSeek2 . chr(0)));
                fseek($fd, $AddrSeek2);
            } else {
                fseek($fd, -1, SEEK_CUR);
            }
            while (($char = fread($fd, 1)) != chr(0))
                $ipAddr2 .= $char;
        }
        fclose($fd);

        if (preg_match('/http/i', $ipAddr2)) {
            $ipAddr2 = '';
        }
        return iconv('GBK', 'UTF-8', "$ipAddr1 $ipAddr2");
    }
}


if ( ! function_exists('area')) {
    /**
     * @param string $ip
     * @param int|null $num 为数字时返回地区数组中的一个成员；否则返回整个数组
     * @return array|string [国家, 地区, 网络商]  或者其中一个成员
     */
    function area($ip = '', $num = 'all')
    {
        $str = convertip($ip);
        if (preg_match('/中国|北京市|上海市|天津市|重庆市|河北省|山西省|辽宁省|吉林省|黑龙江省|江苏省|浙江省|安徽省|福建省|江西省|山东省|河南省|湖北省|湖南省|广东省|海南省|四川省|贵州省|云南省|陕西省|甘肃省|青海省|台湾省|香港|澳门|内蒙古|广西|宁夏|新疆|西藏/', $str)) {
            // 业务需求：非大陆需要单独记录
            if (preg_match('/台湾|香港|澳门/', $str)) {
                $str = '中国' . mb_substr(trim($str), 0, 2);
            } else {
                // 可根据业务需求，添加后缀字，例如大陆
                $str = '中国' . config('INLAND') . " $str";
            }
        }
        $arr = explode(' ', $str);
        // 删除国家外的多余内容
        foreach (['美国' => '美国', '加拿大' => '加拿大', '荷兰' => '荷兰', '法属' => '法国', '荷属' => '荷兰', '美属' => '美国', '德国' => '德国', '日本' => '日本', '俄罗斯' => '俄罗斯', '南非' => '南非', '欧洲' => '欧洲地区', '泰国' => '泰国', '英国' => '英国', '韩国' => '韩国'] as $k => $v) {
            if (stripos($arr[0], $k) === 0) {
                $arr[0] = $v;
                break;
            }
        }

        return is_numeric($num) ? $arr[$num] : $arr;
    }
}

if ( ! function_exists('geo')) {
    /**
     * 将IP解析为地区数据
     * @install composer require czdb/searcher
     * @github https://github.com/tagphi/czdb_searcher_php
     * @website https://cz88.net/
     * @param string $ip
     * @param int|string $num 为数字时返回地区数组中的一个成员；否则返回整个数组
     * @return string|array
     */
    function geo($ip = '', $num = 'all')
    {
        /**
         * db_file_ipv4: ipv4文件路径
         * db_file_ipv6: ipv6文件路径
         * key：密钥
         * query_type（可选）: BTREE(默认) | MEMORY,查询模式，见DbSearcher类常量
         *
         */
        if ( ! $config = config('CZ88')) {
            trace('geo函数 config empty', 'error');
            return is_numeric($num) ? '' : [];
        }

        try {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                // ipv6
                $dbFile = $config['db_file_ipv6'];
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // ipv4
                $dbFile = $config['db_file_ipv4'];
            } else {
                trace("ip invalid: $ip", 'error');
                return is_numeric($num) ? '' : [];
            }

            $queryType = $config['query_type'] ?? \Czdb\DbSearcher::QUERY_TYPE_BTREE;
            $dbSearcher = new \Czdb\DbSearcher($dbFile, $queryType, $config['key']);
            $region = $dbSearcher->search($ip);
            $dbSearcher->close();

            // 注意分隔符一定要用–，最好是复制粘贴，以防写错！！！

            // ip解析示例：
            // ["美国–新泽西州–伯灵顿", "Comcast有线通信股份有限公司"]
            // ["中国–广东–深圳", "电信"]
            // ["中国–台湾", "中华电信]
            // ["中国–香港", "城市电讯有限公司"]
            // ["中国–澳门", "澳门电讯"]

            $arr = explode("\t", $region);
            // 业务需求，港澳台跟大陆一样保持在第一级
            $str = str_replace(['中国–台湾', '中国–香港', '中国–澳门', '中国–'], ['中国台湾–台湾', '中国香港–香港', '中国澳门–澳门', '中国' . config('INLAND') . '–'], $arr[0]);
            $arr = explode('–', $str);

            return is_numeric($num) ? $arr[$num] : $arr;
        } catch (\Exception|\Throwable $e) {
            $dbSearcher->close();
            trace("geo: $ip error:" . $e->getMessage(), 'error');
            return is_numeric($num) ? '' : [];
        }
    }
}


if ( ! function_exists('sysinfo')) {
    /**
     * 获取系统设置的动态配置
     * @document http://www.easyswoole.com/Components/Spl/splArray.html
     * @param string|true|null $key true-直接返回SplArray对象，非true取值与 SplArray->get 相同
     * @param string|null $default 默认值
     * @return array|SplArray|mixed|null
     */
    function sysinfo($key = null, $default = null)
    {
        /** @var \App\Model\Admin\Sysinfo $model */
        $model = model_admin('sysinfo');
        return $model->cacheSpl($key, $default);
    }
}


if ( ! function_exists('array_merge_decode')) {
    /**
     * array_merge_decode
     * @param array $array
     * @param array $merge
     * @return array
     */
    function array_merge_decode($array, $merge = [])
    {
        foreach (['array', 'merge'] as $var) {
            if (is_string($$var) && ($decode = json_decode($$var, true))) {
                $$var = $decode;
            }
        }
        return array_merge_multi($merge, $array);
    }
}


if ( ! function_exists('get_mode')) {
    /**
     * 获取当前运行环境
     * @param string $type all:返回整个模式值; mode:返回模块值; env:返回环境值
     * @return string 环境|模块.环境
     */
    function get_mode($type = 'env')
    {
        // dev|test|produce|user.test|sdk.dev|...
        $runMode = \EasySwoole\EasySwoole\Core::getInstance()->runMode();
        if ($type === 'all') {
            return $runMode;
        }
        $runMode = explode('.', $runMode);
        return $type === 'mode' ? $runMode[0] : ($runMode[1] ?? $runMode[0]);
    }
}


if ( ! function_exists('is_env')) {
    /**
     * 判断当前运行环境
     * @param string|array $env dev|test|produce|user.test|sdk.dev|...
     * @return bool
     */
    function is_env($env = 'dev')
    {
        $_env = get_mode();
        return is_array($env) ? in_array($_env, $env) : $_env === $env;
    }
}


if ( ! function_exists('is_module')) {
    /**
     * @param string|array $name log|sdk|pay|user|admin|account|....
     * @return bool
     */
    function is_module($name = 'log')
    {
        $_mode = get_mode('mode');
        return is_array($name) ? in_array($_mode, $name) : $_mode === $name;
    }
}


if ( ! function_exists('memory_convert')) {
    /**
     * 转换内存单位
     * @param numeric $bytes
     * @return string
     */
    function memory_convert($bytes)
    {
        $s = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $e = floor(log($bytes) / log(1024));

        return sprintf('%.2f ' . $s[$e], ($bytes / pow(1024, floor($e))));
    }
}


if ( ! function_exists('json_decode_ext')) {
    /**
     * json_decode的加强版，自动将extension字段处理为数组类型
     * @param string $data
     * @return array|mixed|string
     */
    function json_decode_ext($data = '')
    {
        is_string($data) && ($json = json_decode($data, true)) && $data = $json;

        is_array($data) && isset($data['extension']) && ! is_array($data['extension']) && ($data['extension'] = json_decode($data['extension'], true));
        return $data;
    }
}


if ( ! function_exists('get_google_service_account')) {

    /**
     * Google服务账号文件路径
     * @param string $pkgbnd
     * @return string
     */
    function get_google_service_account($pkgbnd)
    {
        return EASYSWOOLE_ROOT . "/../utility/google-service-account_$pkgbnd.json";
    }
}


if ( ! function_exists('http_tracker')) {
    /**
     * 子链路记录，返回一个结束回调，必须保证结束回调被调用
     * @param string $pointName 标识名
     * @param array $data 除自定义参数外，这些key尽量传递完整：ip,method,path,url,GET,POST,JSON,server_name,header
     * @param string|null $parentId 父级的pointId(在go函数内才需要传)
     * @return Closure
     */
    function http_tracker(string $pointName, array $data = [], $parentId = null)
    {
        // TODO 代码可能有冗余，待优化
        // 兼容go函数内的场景
        if ( ! is_null($parentId)) {
            $point = HttpTracker::getInstance()->createStart($pointName);
            $parentId && $point->setParentId($parentId);
            $point && $point->setStartArg($data + ['server_name' => config('SERVNAME')]);

            return function ($data = [], int $httpCode = 200) use ($point) {
                if ($point) {
                    $point->setEndArg(['httpStatusCode' => $httpCode, 'data' => $data])->end();
                }
            };
        }

        $point = HttpTracker::getInstance()->startPoint();
        $childPoint = false;
        if ($point) {
            $childPoint = $point->appendChild($pointName)->setStartArg($data + ['server_name' => config('SERVNAME')]);
        }

        return function ($data = [], int $httpCode = 200) use ($point, $childPoint) {
            if ($point && $childPoint) {
                $childPoint->setEndArg(['httpStatusCode' => $httpCode, 'data' => $data])->end();
            }
        };
    }
}


if ( ! function_exists('format_keyval')) {
    function format_keyval($kv = [])
    {
        $data = [];
        foreach ($kv as $arr) {
            if (empty($arr['Key']) || empty($arr['Value'])) {
                continue;
            }
            $data[$arr['Key']] = $arr['Value'];
        }
        return $data;
    }
}


if ( ! function_exists('unformat_keyval')) {
    function unformat_keyval($kv = [])
    {
        $result = [];
        foreach ($kv as $key => $value) {
            $result[] = [
                'Key' => $key,
                'Value' => $value
            ];
        }
        return $result;
    }
}


if ( ! function_exists('sign')) {
    /**
     * 简单的签名与验签
     * @param string|array $data 要参与签名的数据
     * @param string|null $sign 有传值则表示要验签
     * @param string $key 指定密钥，空则取 config('ENCRYPT.apikey')
     * @return string|bool  返回签名或验签结果
     */
    function sign($data, $sign = null, $key = '')
    {
        $key = $key ?: config('ENCRYPT.apikey');
        if ( ! $key) {
            throw new \Exception('Missing configuration: ENCRYPT.apikey');
        }

        if (is_array($data)) {
            ksort($data);
            $data = json_encode($data);
        }
        $hash = md5($data . $key);
        return is_null($sign) ? $hash : $hash === $sign;
    }
}


if ( ! function_exists('report_redis_key')) {
    /**
     * 返回上报队列里的redis-key
     * @param string $key 具体动作 或 归类.具体动作
     * @param string $type 归类
     * @param string $sys 系统
     * @return string
     */
    function report_redis_key($key = '', $type = 'origin', $sys = 'log')
    {
        $k = strpos($key, '.') ? explode('.', $key) : [$type, $key];
        return config("QUEUE.$sys.$k[0].$k[1]")
            // 定义啥就是啥
            ?// 例如： Report:Origin-Active
            : (config("QUEUE.$sys.prefix") . ucfirst($k[0]) . '-' . ucfirst($k[1]));
    }
}


if ( ! function_exists('redis_list_push')) {
    /**
     * 入redis队列
     * @param Redis $redis
     * @param string $key
     * @param mixed $data
     * @param bool $left 是否lpush，默认为rpush
     * @return false|int|Redis
     */
    function redis_list_push(Redis $redis, string $key, $data, bool $left = false)
    {
        if ( ! is_scalar($data)) {
            $data = json_encode($data);
        }
        $clusterNumber = config('QUEUE.clusterNumber');
        $clusterNumberWrite = config('QUEUE.clusterNumberWrite');
        // 写一定要比读小！！！不然redis会爆！！！
        $cn = min($clusterNumber ?: 0, $clusterNumberWrite ?: 0);
        if ($cn > 0) {
            mt_srand();
            $index = mt_rand(-1, $cn);
            $index > 0 && $key .= ".$index";
        }

        return $left ? $redis->lPush($key, $data) : $redis->rPush($key, $data);
    }
}


if ( ! function_exists('repeat_array_keys')) {
    /**
     * 数组中指定的几个key脱敏处理
     * @param array $data
     * @param array $keys
     * @param int $len
     * @return array
     */
    function repeat_array_keys($data, array $keys = [], int $len = 10): array
    {
        if ( ! is_array($data) || empty($keys)) {
            return $data;
        }

        foreach ($data as $key => &$val) {
            if (in_array($key, $keys)) {

                $prefix = substr($val, 0, $len);
                $suffix = substr($val, 0 - $len);

                $val = "$prefix***********$suffix";
            }
        }

        return $data;
    }
}

/******************** 一些请求内网api的封装 *********************/

if ( ! function_exists('request_admin_api')) {
    /**
     * 请求后台API
     * @param string $uri 地址
     * @param array $data 参数
     * @param string $method 请求方式
     * @param string $encry 加密方式
     * @return array|bool
     */
    function request_admin_api($uri, $data = [], $method = 'GET', $encry = 'rsa')
    {
        // 如果有其它逻辑处理，可在此单独写。甚至可在APP级别重写本函数
        // .....

        return request_lan_api('admin', $uri, $data, $method, $encry);
    }
}

if ( ! function_exists('request_sdk_api')) {
    /**
     * 请求SDK API
     * @param string $uri 地址
     * @param array $data 参数
     * @param string $method 请求方式
     * @param string $encry 加密方式
     * @return array|bool
     */
    function request_sdk_api($uri, $data = [], $method = 'GET', $encry = 'md5')
    {
        // 如果有其它逻辑处理，可在此单独写。甚至可在APP级别重写本函数
        // .....

        return request_lan_api('sdk', $uri, $data, $method, $encry);
    }
}

if ( ! function_exists('request_log_api')) {
    /**
     * 请求LOG API
     * @param string $uri 地址
     * @param array $data 参数
     * @param string $method 请求方式
     * @param string $encry 加密方式
     * @return array|bool
     */
    function request_log_api($uri, $data = [], $method = 'GET', $encry = 'md5')
    {
        // 如果有其它逻辑处理，可在此单独写。甚至可在APP级别重写本函数
        // .....

        return request_lan_api('log', $uri, $data, $method, $encry);
    }
}

if ( ! function_exists('request_pay_api')) {
    /**
     * 请求PAY API
     * @param string $uri 地址
     * @param array $data 参数
     * @param string $method 请求方式
     * @param string $encry 加密方式
     * @return array|bool
     */
    function request_pay_api($uri, $data = [], $method = 'GET', $encry = 'md5')
    {
        // 如果有其它逻辑处理，可在此单独写。甚至可在APP级别重写本函数
        // .....

        return request_lan_api('pay', $uri, $data, $method, $encry);
    }
}


if ( ! function_exists('request_lan_api')) {
    /**
     * 请求内网api
     * @param string $lan_key admin|sdk|pay|log
     * @param string $uri 地址
     * @param array $data 参数
     * @param string $method 请求方式
     * @param string $encry 加密方式
     * @param array $headers 头信息
     * @param string $notice 通知的主体（飞书|钉钉|……）标识
     * @return array|bool
     */
    function request_lan_api($lan_key, $uri, $data = [], $method = 'GET', $encry = 'md5', $headers = [], $notice = 'default')
    {
        $method = strtoupper($method);
        $lan = sysinfo($lan_key . '_lan');
        $lan = $lan[get_mode()] ?? config(strtoupper($lan_key . '_lan'));
        if ( ! $lan) {
            notice("{$lan_key} API请求失败，config或sysinfo未配置{$lan_key}_lan");
            return false;
        }

        // 参数加密
        switch (strtolower($encry)) {
            case 'rsa':
                // es-utility里默认有验证rsa的（仅验签，没做阻拦）
                $openssl = LamOpenssl::getInstance();
                $params = [
                    'encry' => 'rsa',
                    config('RSA.key') => $openssl->encrypt(json_encode($data))
                ];
                break;

            // 注意这种方式为了安全，记得在服务提供方的代码里写验签
            case 'md5':
                $params = $data + [
                        'encry' => 'md5',
                        'time' => time(),
                    ];
                $params['sign'] = md5($params['encry'] . $params['time'] . config('ENCRYPT.apikey'));
                break;

            default:
                notice("{$lan_key} API请求失败，未知的加密协议$encry");
                return false;
        }

        $url = 'http://' . $lan['ip'][array_rand($lan['ip'])] . $uri;
        try {
            $res = hcurl($url, $params, $method, $headers += ['Host' => $lan['domain']], [
                'keyword' => $lan_key,
                'retryCallback' => function ($code, $res, $org) {
                    return ($res['code'] ?? 0) == 200;
                }]);
            return $res['result'];
        } catch (\Exception $e) {
            notice($e->getMessage(), null, $notice);
            return false;
        }
    }
}


/******************** 云组件助手函数的封装 *********************/

if ( ! function_exists('get_drivers')) {
    /**
     * 主函数，获取实例
     * @param string $clsname 组件类名
     * @param string $cfgname 配置的名称
     * @param $config
     * @return mixed
     * @throws Exception
     */
    function get_drivers($clsname = '', $cfgname = '', $config = [])
    {
        $clsname = ucfirst($clsname);
        $cfg = config($cfgname);
        $driver = ucfirst(is_array($cfg['driver']) ? $config['driver'] : $cfg['driver']);

        $cfg = $cfg[$driver] ?? $cfg['config'];
        $className = "\\WonderGame\\EsUtility\\Common\\CloudLib\\$clsname\\$driver";
        if ( ! class_exists($className)) {
            throw new \Exception("$clsname Driver Not Found");
        }
        return new $className(array_merge($cfg, $config));
    }
}


if ( ! function_exists('cdn')) {
    /**
     * 助手函数，返回cdn操作对象
     * @param array $config
     * @return CdnInterface
     * @throws Exception
     */
    function cdn($config = []): CdnInterface
    {
        return get_drivers(__FUNCTION__, 'CDN_CLOUD', $config);
    }
}


if ( ! function_exists('storage')) {
    /**
     * 助手函数，返回storage操作对象
     * @param array $config
     * @return StorageInterface
     * @throws Exception
     */
    function storage($config = []): StorageInterface
    {
        return get_drivers(__FUNCTION__, strtoupper(__FUNCTION__), $config);
    }
}

if ( ! function_exists('captcha')) {
    /**
     * 助手函数，返回captcha操作对象
     * @param array $config
     * @return CaptchaInterface
     * @throws Exception
     */
    function captcha($config = []): CaptchaInterface
    {
        return get_drivers(__FUNCTION__, strtoupper(__FUNCTION__), $config);
    }
}


if ( ! function_exists('dns')) {
    /**
     * 助手函数，返回dns操作对象
     * @param array $config
     * @return DnsInterface
     * @throws Exception
     */
    function dns($config = []): DnsInterface
    {
        return get_drivers(__FUNCTION__, strtoupper(__FUNCTION__), $config);
    }
}


if ( ! function_exists('email')) {
    /**
     * 助手函数，返回email操作对象
     * @param array $config
     * @return EmailInterface
     * @throws Exception
     */
    function email($config = []): EmailInterface
    {
        return get_drivers(__FUNCTION__, strtoupper(__FUNCTION__), $config);
    }
}


if ( ! function_exists('sms')) {
    /**
     * 助手函数，返回sms操作对象
     * @param array $config
     * @return SmsInterface
     * @throws Exception
     */
    function sms($config = []): SmsInterface
    {
        return get_drivers(__FUNCTION__, strtoupper(__FUNCTION__), $config);
    }
}

/******************** 媒体或渠道常用函数的封装 *********************/

if ( ! function_exists('get_media_byauth')) {
    /**
     * 根据权限值获取媒体ids
     * @param int $auth 权限值
     * @return array
     */
    function get_media_byauth(int $auth = 0)
    {
        $medias = array_filter(config('MEDIA'), function ($arr) use ($auth) {
            return in_array($auth, $arr['auth'] ?? []);
        });
        return array_column($medias ?: [], 'id');
    }
}


if ( ! function_exists('get_media_byid')) {
    /**
     * 根据媒体id获取媒体信息
     * @param int $id 媒体id
     * @param string $field 仅返回指定字段
     * @return array|string
     */
    function get_media_byid(int $id = 0, string $field = '')
    {
        $media = array_column(config('MEDIA'), null, 'id');
        if (empty($media[$id]['extend'])) {
            return ! empty($field) ? ($media[$id][$field] ?? '') : ($media[$id] ?? '');
        } else {
            return ! empty($field) ? ($media[$media[$id]['extend']][$field] ?? '') : ($media[$media[$id]['extend']] ?? '');
        }
    }
}


if ( ! function_exists('get_media_class')) {
    /**
     * 根据标识实例化媒体类
     * @param string|number $media 媒体代号或ID
     * @param bool $throw 是否抛异常
     * @return \App\Common\Market\Base|null
     */
    function get_media_class($media, bool $throw = true)
    {
        if (is_numeric($media)) {
            $media = get_media_byid($media, 'code');
        }
        $class = '\\App\\Common\\Market\\' . ucfirst($media);
        if ( ! class_exists($class)) {
            if ($throw) {
                throw new \Exception("未知的媒体应用类型：$media");
            } else {
                trace("未知的媒体应用类型：$media", 'error');
            }
            return null;
        }
        return new $class();
    }
}


if ( ! function_exists('get_channel_byauth')) {
    /**
     * 根据权限值获取渠道ids
     * @param int $auth 权限值
     * @return array
     */
    function get_channel_byauth(int $auth = 0)
    {
        $channels = array_filter(config('CHANNEL'), function ($arr) use ($auth) {
            return in_array($auth, $arr['auth'] ?? []);
        });
        return array_column($channels ?: [], 'id');
    }
}


if ( ! function_exists('get_channel_byid')) {
    /**
     * 根据渠道id获取渠道信息
     * @param int $id 渠道id
     * @param string $field 仅返回指定字段
     * @return array|string
     */
    function get_channel_byid(int $id = 0, string $field = '')
    {
        foreach (config('CHANNEL') as $k => $v) {
            if ($v['id'] == $id) {
                return $field ? ($v[$field] ?? '') : $v;
            }
        }
        return '';
    }
}


if ( ! function_exists('get_channel_class')) {
    /**
     * 根据标识实例化渠道类
     * @param string|number $channel 渠道代号或ID
     * @param array $construct 实例化构造参数
     * @param bool $throw 是否抛异常
     */
    function get_channel_class($channel, array $construct = [], bool $throw = true)
    {
        if (is_numeric($channel)) {
            $channel = get_channel_byid($channel, 'code');
        }
        $class = '\\App\\Common\\Channel\\' . ucfirst($channel) . '\\' . ucfirst($channel);
        if ( ! class_exists($class)) {
            if ($throw) {
                throw new \Exception("未知的渠道类型：$channel");
            } else {
                trace("未知的渠道类型：$channel", 'error');
            }
            return null;
        }
        /** @var \App\Common\Channel\Base $class */
        return new $class($construct);
    }
}


if ( ! function_exists('is_tester')) {
    /**
     * 是否为测试员(uid,devid,ip……)
     * @param array|string $input 数据源
     * @param string $type 类型。如没有指定则默认会取uid,devid,ip这三个成员
     * @return bool
     */
    function is_tester(Request $request, $input = [], $type = '')
    {
        // 防止无限转发
        if (stripos($request->getUri()->getHost(), 'test-') !== false) {
            return false;
        }

        if ( ! $type) {
            $type = ['uid', 'devid', 'ip'];
            foreach ($type as $v) {
                if (call_user_func(__FUNCTION__, $request, $input[$v], $v)) {
                    return true;
                }
            }
            return false;
        }

        return RedisPool::invoke(function (Redis $redis) use ($input, $type) {
            $key = "Tester:$type:$input";
            return $redis->get($key);
        }, strtolower(APP_MODULE));
    }
}

if ( ! function_exists('forward_testserv')) {
    /**
     * 将请求转到test服
     * @return void
     */
    function forward_testserv(Request $request, $config = [])
    {
        $uri = $request->getUri();
        $swoole = $request->getSwooleRequest();
        $query = $uri->getQuery();
        $method = $request->getMethod();
        $host = 'test-' . $uri->getHost();

        $_body = $request->getBody()->__toString() ?: $swoole->rawContent();
        $params = array_merge($swoole->post ?: [], json_decode($_body, true) ?: [], json_decode(json_encode(simplexml_load_string($_body, 'SimpleXMLElement', LIBXML_NOCDATA)), true) ?: []);

        $url = $uri->getScheme() . '://' . (config('TESTER_BOX') ?: $host) . $uri->getPath() . ($query ? "?$query" : '');

        $result = hcurl(
            $url,
            $params,
            json_decode($_body, true) ? 'JSON' : $method,
            ['host' => $host] + $swoole->header,
            array_merge(['retryCallback' => false], $config)
        );
        if (empty($result) && (empty($config['resultType']) || $config['resultType'] === 'json')) {
            $result = [
                'code' => 555,
                'msg' => 'Test request error',
                'result' => []
            ];
        }
        return $result;
    }
}


if ( ! function_exists('hcurl')) {
    /**
     * 基于HttpClient封装的公共函数
     * @param string|array $url string时为要请求的完整网址和路径；数组时为便捷传参方式
     * @param array|string $data 请求参数，xml提交为string
     * @param string $method 提交方式：get|post|xml|json|put|delete|head|options|trace|patch
     * @param array $header 请求头
     * @param array $cfg 配置  resultType,retryCallback,retryTimes
     * @param array $option HttpClient的其它属性
     * @throws Exception|Error
     */
    function hcurl($url = '', $data = [], $method = 'post', $header = [], $cfg = [], $option = [])
    {
        $HttpRequest = new HttpRequest();
        return $HttpRequest->request('hCurl', $url, $data, $method, $header, $cfg, $option);
    }
}

if ( ! function_exists('curl')) {
    /**
     * 基于curl封装的公共函数
     * @param string|array $url string时为要请求的完整网址和路径；数组时为便捷传参方式
     * @param array|string $data 请求参数，xml提交为string
     * @param string $method 提交方式：get|post|xml|json|put|delete|head|options|trace|patch
     * @param array $header 请求头
     * @param array $cfg 配置  resultType,retryCallback,retryTimes
     * @param array $option curl的其它属性
     * @throws Exception|Error
     */
    function curl($url = '', $data = [], $method = 'post', $header = [], $cfg = [], $option = [])
    {
        $HttpRequest = new HttpRequest();
        return $HttpRequest->request('curl', $url, $data, $method, $header, $cfg, $option);
    }
}

