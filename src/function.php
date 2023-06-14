<?php

use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\I18N\I18N;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\Db\MysqliClient;
use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;
use EasySwoole\Spl\SplArray;
use WonderGame\EsNotify\DingTalk\Message\Markdown;
use WonderGame\EsNotify\DingTalk\Message\Text;
use WonderGame\EsNotify\EsNotify;
use WonderGame\EsNotify\WeChat\Message\Notice;
use WonderGame\EsNotify\WeChat\Message\Warning;
use WonderGame\EsUtility\Common\Classes\CtxRequest;
use WonderGame\EsUtility\Common\Classes\LamJwt;
use WonderGame\EsUtility\Common\Classes\Mysqli;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\HttpTracker\Index as HttpTracker;


if ( ! function_exists('is_super')) {
    /**
     * 是否超级管理员
     * @param $rid
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
     * @param $name
     * @param $thorw
     * @return string|null
     * @throws Exception
     */
    function find_model($name, $thorw = true)
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

        if ($thorw) {
            throw new \Exception('Class Not Found: ' . $name);
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
     * @return AbstractModel
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

        $gameid = '';
        // 实例化XXX_gid模型
        if (strpos($name, ':')) {
            list($name, $gameid) = explode(':', $name);
        }
        $tableName = $gameid != '' ? parse_name($name, 0, false) . "_$gameid" : '';

        $className = find_model($space . $name);

        /** @var AbstractModel $model */
        $model = new $className($data, $tableName, $gameid);

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
    /**
     * @param string $name
     * @param array $data
     * @param bool|numeric $inject
     * @return \EasySwoole\ORM\AbstractModel
     */
    function model_admin(string $name = '', array $data = [], $inject = false)
    {
        return model('Admin\\' . ucfirst($name), $data, $inject);
    }
}

if ( ! function_exists('model_log')) {
    /**
     * @param string $name
     * @param array $data
     * @param bool|numeric $inject
     * @return \EasySwoole\ORM\AbstractModel
     */
    function model_log(string $name = '', array $data = [], $inject = false)
    {
        return model('Log\\' . ucfirst($name), $data, $inject);
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
     * @return \EasySwoole\Redis\Redis
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
     * @param integer $type 转换类型  0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
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
    function array_sort_multi($data = [], $field = '', $direction = SORT_DESC)
    {
        if ( ! $data) return [];
        $arrsort = [];
        foreach ($data as $uniqid => $row) {
            foreach ($row as $key => $value) {
                $arrsort[$key][$uniqid] = $value;
            }
        }
        if ($direction) {
            array_multisort($arrsort[$field], $direction, $data);
        }
        return $data;
    }
}

if ( ! function_exists('listdate')) {
    /**
     * 返回两个日期之间的具体日期或月份
     *
     * @param string|int $beginday 开始日期，格式为Ymd或者Y-m-d
     * @param string|int $endday 结束日期，格式为Ymd或者Y-m-d
     * @param int $type 类型 1：日； 2：月； 3：季； 4：年
     * @return array
     */
    function listdate($beginday, $endday, $type = 2)
    {
        $dif = difdate($beginday, $endday, $type != 2);

        // 季
        if ($type == 3) {
            // 开始的年份, 结束的年份
            $arry = [date('Y', strtotime($beginday)), date('Y', strtotime($endday))];
            // 开始的月份, 结束的月份
            $arrm = [date('m', strtotime($beginday)), date('m', strtotime($endday))];
            $arrym = [];

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
        elseif ($type == 4) {
            $begintime = substr($beginday, 0, 4);
            for ($i = 0; $i <= $dif; ++$i) {
                $arr[$begintime - 1] = $begintime . '0101'; // p2018=>strtotime(20190101)
                ++$begintime;
            }
        } else {
            // 日期 p180302=>strtotime(20180304)
            if ($type === true || $type == 1) {
                $format = 'Y-m-d';
                $unit = 'day';
                $d = '';
            } // 月份 p1803=>strtotime(20180401)
            elseif ($type === false || $type == 2) {
                $format = 'Y-m';
                $unit = 'month';
                $d = '01';
            }

            $begintime = strtotime(date($format, strtotime($beginday)));
            for ($i = 0; $i <= $dif; ++$i) {
                $key = strtotime("+$i $unit", $begintime);
                $format = str_replace('-', '', $format);
                $arr[date(strtolower($format), $key - 3600 * 24)] = date(ucfirst($format), $key) . $d;
            }
        }
        return $arr;
    }
}


if ( ! function_exists('difdate')) {
    /**
     * 计算两个日期相差多少天或多少月
     */
    function difdate($beginday, $endday, $d = false)
    {
        $beginstamp = strtotime($beginday);
        $endstamp = strtotime($endday);

        // 相差多少个月
        if ( ! $d) {
            list($date_1['y'], $date_1['m']) = explode('-', date('Y-m', $beginstamp));
            list($date_2['y'], $date_2['m']) = explode('-', date('Y-m', $endstamp));
            return ($date_2['y'] - $date_1['y']) * 12 + $date_2['m'] - $date_1['m'];
        }

        // 相差多少天
        return ceil(($endstamp - $beginstamp) / (3600 * 24));
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
        $jwt = LamJwt::verifyToken($token);
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

        // 其中“;”为getHeaderLine拼接, “, ”为代理拼接
        foreach ([';', ','] as $delimiter) {
            if (strpos($ip, $delimiter) !== false) {
                $list = explode($delimiter, $ip);
                foreach ($list as $item) {
                    $item = trim($item);
                    if ( ! empty($item) && ! in_array($item, ['unknown'])) {
                        return $item;
                    }
                }
            }
        }

        return $ip;
    }
}


if ( ! function_exists('lang')) {
    function lang($const = '')
    {
        return I18N::getInstance()->translate($const);
    }
}


if ( ! function_exists('wechat_notice')) {
    function wechat_notice($title = '', $content = '', $color = '#32CD32')
    {
        EsNotify::getInstance()->doesOne('wechat', new Notice([
            'templateId' => config('WX_TPLID.notice'),
            'title' => $title,
            'content' => $content,
            'color' => $color
        ]));
    }
}


if ( ! function_exists('wechat_warning')) {
    function wechat_warning($file, $line, $servername, $message, $color = '#FF0000')
    {
        EsNotify::getInstance()->doesOne('wechat', new Warning([
            'templateId' => config('WX_TPLID.warning'),
            'file' => $file,
            'line' => $line,
            'servername' => $servername,
            'message' => $message,
            'color' => $color
        ]));
    }
}


if ( ! function_exists('dingtalk_text')) {
    function dingtalk_text($content = '', $at = true)
    {
        EsNotify::getInstance()->doesOne('dingtalk', new Text([
            'content' => $content,
            'isAtAll' => $at
        ]));
    }
}


if ( ! function_exists('dingtalk_markdown')) {
    function dingtalk_markdown($title = '', $text = '', $at = true)
    {
        if (is_array($text)) {
            $arr = ['### **' . $title . '**'];
            foreach ($text as $key => $value) {
                $exp = strpos(strtolower($key), 'exp') === 0;
                $arr[] = $exp ? $value : "- $key: $value";
            }
            $text = implode(" \n\n ", $arr);
        }
        EsNotify::getInstance()->doesOne('dingtalk', new Markdown([
            'title' => $title,
            'text' => $text,
            'isAtAll' => $at
        ]));
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
     * @param $array
     * @param $merge
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
     * @param string $env dev|test|produce|user.test|sdk.dev|...
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
     * @param string $name log|sdk|pay|user|admin|account|....
     * @return bool
     */
    function is_module($name = 'log')
    {
        return get_mode('mode') === $name;
    }
}

if ( ! function_exists('memory_convert')) {
    /**
     * 转换内存单位
     * @param $bytes
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
     * @param $pkgbnd
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
     * @return Closure
     */
    function http_tracker(string $pointName, array $data = [])
    {
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
