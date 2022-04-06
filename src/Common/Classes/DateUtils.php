<?php


namespace WonderGame\EsUtility\Common\Classes;


class DateUtils
{
    const _ymd = 'ymd';
    const FULL = 'Y-m-d H:i:s';
    const YmdHis = 'YmdHis';
    const FMT_1 = 'Y-m-d';

    public static function format($time, $fmt = '')
    {
        if (!is_numeric($time)) {
            $time = strtotime($time);
        }
        if (empty($fmt)) {
            $fmt = self::FULL;
        }
        return date($fmt, $time);
    }

    /**
     * 当前系统时区与指定时区之间的差值,单位秒
     * @param string $tzs Asia/Shanghai
     */
    public static function timeZoneOffsetSec(string $tzs)
    {
        $date = date(self::FULL);
        // 当前系统运行的时区
        $currentRunTimeZone = date_default_timezone_get();
        $currTimeZone = new \DateTimeZone($currentRunTimeZone);
        $currentOffset = $currTimeZone->getOffset(new \DateTime($date));

        $toTimeZone = new \DateTimeZone($tzs);
        $toOffset = $toTimeZone->getOffset(new \DateTime($date));

        return $currentOffset - $toOffset;
    }

    public static function getTimeZoneStamp(int $time, $tzs): int
    {
        return $time + self::timeZoneOffsetSec($tzs);
    }

    /**
     * 转换, -5 -> America/Bogota
     * @param $tzn
     * @return int|mixed|string
     */
    public static function  getZoneTz($tzn)
    {
        // 兼容非tz
        if (is_numeric($tzn))
        {
            // 传递的是-5或8这种int值
            foreach (sysinfo('region_domain.region') as $value)
            {
                if ($value['tzn'] == $tzn)
                {
                    $tzn = $value['tzs'];
                    break;
                }
            }
        }
        if (empty($tzn))
        {
            $tzn = date_default_timezone_get();
        }
        return $tzn;
    }

    /**
     * 将时间戳格式化为对应时区的时间
     * @param $time
     * @param $tz
     * @return \DateTime
     */
    public static function formatTimeByTz($time, $tz, $format)
    {
        $tz = self::getZoneTz($tz);
        $DateTime = new \DateTime();
        $DateTime->setTimestamp($time)->setTimezone(new \DateTimeZone($tz));
        return $DateTime->format($format);
    }

    /**
     * 将指定时区的一个日期格式转换为另一个时区的时间戳（或格式化后的值）
     * @param $timeStamp
     * @param $tz
     * @param string $format
     * @return int|string
     */
    public static function timeChangeZoneByTimeStamp($timeStamp, $ttz, $tz, $format = '')
    {
        $tz = self::getZoneTz($tz);
        $ttz = self::getZoneTz($ttz);
        $fmt = self::formatTimeByTz($timeStamp, $ttz, self::FULL);
        return self::timeChangeZoneByDate($fmt, $tz, $format);
    }

    /**
     * todo 是否也需要指定时区??
     * 将一个日期格式转换为另一个时区的时间戳（或格式化后的值）
     * @param $date
     * @param $tz
     * @param string $format
     * @return int|string
     */
    public static function timeChangeZoneByDate($date, $tz, $format = '')
    {
        $tz = self::getZoneTz($tz);
        $DateTime = new \DateTime($date, new \DateTimeZone($tz));
        return empty($format) ? $DateTime->getTimestamp() : $DateTime->format($format);
    }

    /**
     * 将ymd 转换为客户端展示的Y-m-d格式，如果在客户端转换，会误杀合计行
     */
    public static function ymdToClientFormat(string $ymd): string
    {
        if (!is_numeric($ymd))
        {
            return $ymd;
        }
        $len = strlen($ymd);
        $array = str_split($ymd, 2);
        $join = implode('-', $array);
        return $len === 6 ? ('20' . $join) : $join;
    }
}
