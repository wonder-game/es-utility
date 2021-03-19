<?php
/**
 * 通用cli组件
 *
 * @author 林坤源
 * @version 1.0.4 最后修改时间 2020年11月26日
 */
namespace Linkunyuan\EsUtility\Traits;

use EasySwoole\Component\Di;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;

trait LamCli
{
    /** @var DbManager */
    protected $DbManager;
    /** @var QueryBuilder */
    protected $QueryBuilder;

    protected $mytz = '';
    protected $tzn = '';

    public function __construct()
    {
        $this->DbManager = DbManager::getInstance();
        $this->QueryBuilder = Di::getInstance()->get('QueryBuilder');

        $this->mytz = get_cfg_var('date.timezone');
        date_default_timezone_set($this->mytz);

        $tzn = substr((int) date('O'), 0, -2); // 格式：8 或者 -5
        $this->tzn = ($tzn > 0 ? "+$tzn" : $tzn) . ':00';
    }

    public function __destruct()
    {
        // 保存日志
        Logger::getInstance()->log('AFTERREQUEST');

        // echo '+++++++++++++++++ __destruct ++++++++++++++++++';
    }

    /**
     * TODO crontab中调用model()后会重置set time_zone成最初值，原因未知
     * 重设php和mysql时区
     * @param $mytz PHP标准的时区
     * @param $tzn 一定要写  +8:00 或者 -5:00 的格式！！！
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function setTimeZone($tzn, $mytz = '')
    {
        $mytz && date_default_timezone_set($mytz);

        $this->QueryBuilder->raw("set time_zone = '$tzn';");
        $this->DbManager->query($this->QueryBuilder);
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
            'begin_stamp' => $beginStamp,
            'end' => $end,
            'end_stamp' => $endStamp
        ];
    }
}
