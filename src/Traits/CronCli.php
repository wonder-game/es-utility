<?php


namespace Linkunyuan\EsUtility\Traits;

use Linkunyuan\EsUtility\Classes\LamPdo;

trait CronCli
{
    protected $mytz = '';
    protected $tzn = '';

    public function __construct()
    {
        $this->mytz = get_cfg_var('date.timezone');
        date_default_timezone_set($this->mytz);

        $tzn = substr((int) date('O'), 0, -2); // 格式：8 或者 -5
        $this->tzn = ($tzn > 0 ? "+$tzn" : $tzn) . ':00';
    }

    public function getDb($name = 'default', $options = [])
    {
        $config = config("MYSQL.{$name}");
        if (is_array($options)) {
            $config['options'] = array_merge($config['options'], $options);
        }
        return new LamPdo($config);
    }

    /**
     * 重设php和mysql时区
     * @param $mytz PHP标准的时区
     * @param $tzn 一定要写  +8:00 或者 -5:00 的格式！！！
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function setCronTimeZone(LamPdo $pdo, $tzn, $mytz = '')
    {
        $mytz && date_default_timezone_set($mytz);

        $pdo->executeSql("set time_zone = '$tzn';");
    }

    /**
     * 修复模式的时间范围
     * @param string $repair
     * @return array
     */
    protected function parseRepair($repair = '')
    {
        if (empty($repair)) {
            return [];
        }

        list($begin, $end) = explode('~', $repair);
        if (!is_numeric($begin)) {
            $beginStamp = strtotime($begin);
        }
        if (!is_numeric($end)) {
            $endStamp = strtotime($end);
        }
        return [
            'begin' => $begin,
            'begin_stamp' => $beginStamp ?? $begin,
            'end' => $end,
            'end_stamp' => $endStamp ?? $end
        ];
    }
}