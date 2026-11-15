<?php

namespace WonderGame\EsUtility\Common\CloudLib\IdAuth;

use WonderGame\EsUtility\Common\Exception\HttpParamException;

/**
 * 不依赖第三方平台，由SDK校验（无法校验真实性）
 */
class Nothing extends Base
{
    public function checkIdCard($uid, $card, $realname)
    {
        if ( ! preg_match('/^[\x{4e00}-\x{9fa5}·]{2,25}$/isu', $realname)) {
            throw new HttpParamException("姓名不符: $realname");
        }

        if ( ! preg_match("/^\d{6}(19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[1-2]\d|3[0-1])\d{3}[\dxX]$/", $card)) {
            throw new HttpParamException("证件号不符: $card");
        }

        // 1-99
        $age = $this->cardAge($card);
        if ($age < 1 || $age > 99) {
            throw new HttpParamException("无效的证件号：$card");
        }

        if ( ! $this->isIdCard($card)) {
            throw new HttpParamException("证件号校验有误：$card");
        }

        return true;
    }

    /** 合法的省级行政区划码（身份证前两位） */
    const PROVINCE_CODES = [
        11, 12, 13, 14, 15, // 华北
        21, 22, 23,          // 东北
        31, 32, 33, 34, 35, 36, 37, // 华东
        41, 42, 43, 44, 45, 46,     // 中南
        50, 51, 52, 53, 54,         // 西南
        61, 62, 63, 64, 65,         // 西北
        71, 81, 82, 91,             // 台港澳、国外
    ];

    /** 明显测试号的默认黑名单，可用 sysinfo 的 idcard_blacklist 追加 */
    const TEST_CARDS = [
        '11010519491231002X',
        '110101199003076776',
        '440524188001010014',
        '110101199003074477',
        '320311770706001',
    ];

    /**
     * 异步实名的基础校验：不查库、不调云商，纯本地规则
     * 覆盖长度、字符格式、出生日期真实性（含平年/闰年与当月天数）、行政区划码、
     * 校验位、全零/重复/顺序/测试号、姓名为空与非法字符
     * @param string $card 身份证号
     * @param string $realname 姓名
     * @return bool
     * @throws HttpParamException 任一规则不通过
     */
    public function checkBasic($card, $realname)
    {
        $card = is_string($card) ? trim($card) : '';
        $realname = is_string($realname) ? trim($realname) : '';

        // 姓名：2～25 个汉字（或少数民族姓名的间隔号），不能以间隔号开头/结尾，不能连续间隔号
        if ($realname === '') {
            throw new HttpParamException('姓名不能为空');
        }
        if ( ! preg_match('/^[\x{4e00}-\x{9fa5}·]{2,25}$/isu', $realname)) {
            throw new HttpParamException('姓名不符');
        }
        if (mb_substr($realname, 0, 1, 'UTF-8') === '·' || mb_substr($realname, -1, 1, 'UTF-8') === '·' || strpos($realname, '··') !== false) {
            throw new HttpParamException('姓名不符');
        }

        // 身份证：18 位，前 17 位数字，末位数字或 X
        if (strlen($card) !== 18 || ! preg_match('/^\d{17}[\dxX]$/', $card)) {
            throw new HttpParamException('证件号不符');
        }
        $card = strtoupper($card);

        // 出生日期必须真实存在：checkdate 已覆盖月份范围、当月天数与闰年 2 月
        $year = (int)substr($card, 6, 4);
        $month = (int)substr($card, 10, 2);
        $day = (int)substr($card, 12, 2);
        if ( ! checkdate($month, $day, $year) || $year < 1900 || $year > (int)date('Y')) {
            throw new HttpParamException('证件号出生日期有误');
        }

        // 行政区划码：只校验省级前两位
        if ( ! in_array((int)substr($card, 0, 2), self::PROVINCE_CODES, true)) {
            throw new HttpParamException('证件号行政区划码有误');
        }

        // 校验位
        if ( ! $this->isIdCard($card)) {
            throw new HttpParamException('证件号校验有误');
        }

        // 全零 / 重复 / 顺序 / 测试号
        if ($this->isFakeCard($card)) {
            throw new HttpParamException('无效的证件号');
        }

        // 年龄区间沿用现有规则（1～99 岁）
        $age = $this->cardAge($card);
        if ($age < 1 || $age > 99) {
            throw new HttpParamException('无效的证件号');
        }

        return true;
    }

    /**
     * 是否为明显伪造的证件号：全零、17 位全相同、连续顺序号、配置的测试号
     * @param string $card 已转大写的 18 位证件号
     * @return bool
     */
    public function isFakeCard($card)
    {
        $body = substr($card, 0, 17);

        // 17 位全相同（全零号也属于这一类）
        if (preg_match('/^(\d)\1{16}$/', $body)) {
            return true;
        }

        // 连续顺序号，如 12345678901234567、76543210987654321（含 0 结尾回绕）
        static $asc = '0123456789012345678901234567890123';
        static $desc = '9876543210987654321098765432109876';
        if (strpos($asc, $body) !== false || strpos($desc, $body) !== false) {
            return true;
        }

        $blacklist = [];
        try {
            $blacklist = (array)(sysinfo('idcard_blacklist') ?: []);
        } catch (\Throwable $e) {
            // 变量服务不可用时只校验内置名单，不影响认证
        }
        foreach (array_merge(self::TEST_CARDS, $blacklist) as $item) {
            if (is_string($item) && $item !== '' && strtoupper($item) === $card) {
                return true;
            }
        }

        return false;
    }

    /**
     * 计算身份证号中的年龄
     * @param string $card
     * @param bool $ra true则返回包含年、月、日、年龄的数组
     * @return array|int|string
     */
    public function cardAge($card, $ra = false)
    {
        $time = time();

        $by = substr($card, 6, 4);
        $bm = substr($card, 10, 2);
        $bd = substr($card, 12, 2);

        if ( ! is_numeric($by)) {
            throw new HttpParamException("身份证有误: {$card}");
        }

        $ty = date('Y', $time);

        $bmd = $bm . $bd;
        $tmd = date('md', $time);

        // 虚岁
        $age = $ty - $by;
        // 实岁
        if ($bmd > $tmd) {
            $age--;
        }
        $array = ['year' => $by, 'month' => $bm, 'day' => $bd, 'age' => $age];
        return $ra ? $array : $age;
    }

    /**
     * 判断身份证号是否符合算法
     * @param string $idcard 身份证号码
     */
    public function isIdCard($idcard = '')
    {
        if ( ! is_string($idcard)) return false;
        #  转化为大写，如出现x
        $idcard = strtoupper($idcard);
        #  加权因子
        $wi = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2); //前17位对应系数表
        $ai = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'); //第18位对应系数表
        #  按顺序循环处理前17位
        $sigma = 0;
        #  提取前17位的其中一位，并将变量类型转为实数
        for ($i = 0; $i < 17; $i++) {
            $b = (int)$idcard[$i];
            # 提取相应的加权因子
            $w = $wi[$i];
            # 把从身份证号码中提取的一位数字和加权因子相乘，并累加
            $sigma += $b * $w;
        }
        #  计算序号
        $sidcard = $sigma % 11;
        #  按照序号从校验码串中提取相应的字符。
        return $idcard[17] == $ai[$sidcard];
    }
}
