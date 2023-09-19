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
     * @return AbstractModel
     */
    function model(string $name = '', array $data = [], $inject = false): AbstractModel
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
     * @return AbstractModel
     */
    function model_admin(string $name = '', array $data = [], $inject = false): AbstractModel
    {
        return model('Admin\\' . ucfirst($name), $data, $inject);
    }
}

if ( ! function_exists('model_log')) {
    /**
     * @param string $name
     * @param array $data
     * @param bool|numeric $inject
     * @return AbstractModel
     */
    function model_log(string $name = '', array $data = [], $inject = false): AbstractModel
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
        foreach ($data as $uniqid => &$row) {
            foreach ($row as $key => &$value) {
                $arrsort[$key][$uniqid] = $value = format_number($value, 2, true);
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
        $interval = date_diff(date_create($beginday), date_create($endday));
        return (int)$interval->format($format);
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
     * @return string
     */
    function report_redis_key($key = '', $type = 'origin')
    {
        $k = strpos($key, '.') ? explode('.', $key) : [$type, $key];
        return config("REPORT.$k[0].$k[1]")
            // 定义啥就是啥
            ?// 例如： Report:Origin-Active
            : (config('REPORT.PREFIX') . ucfirst($k[0]) . '-' . ucfirst($k[1]));
    }
}