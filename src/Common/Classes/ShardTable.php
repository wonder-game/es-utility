<?php
/**
 * 分表分区类
 *
 * 对表进行分区需要先建立好对应的分区表
 * 主要按照时间和range 类型进行分表和分区
 * @author lamson
 *
 */

namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Spl\SplBean;

class ShardTable extends SplBean
{
    protected $mysqlPool = 'default';
    protected $mysqlConfig = [];

    protected $tzs = '+8:00';

    protected function getClient()
    {
        $MysqlClient = new Mysqli($this->mysqlPool, ['timeout' => -1] + $this->mysqlConfig);
        if ( ! empty($this->tzs)) {
            $MysqlClient->setTimeZone($this->tzs);
        }
        return $MysqlClient;
    }

    /**
     * 检查表分区
     * @param $day
     * @return void
     */
    public function checkPartition($day)
    {
        $Mysqli = $this->getClient();
        $alltable = $this->queryRaw($Mysqli, 'SHOW TABLES', false);

        $cutOff = strtotime("+{$day} days");

        $warning = [];
        foreach ($alltable as $item) {
            $tname = current($item);
            // RANGE分区
            $sql = "SELECT partition_description descr FROM INFORMATION_SCHEMA.partitions WHERE TABLE_SCHEMA=schema() AND TABLE_NAME='{$tname}' AND PARTITION_METHOD='RANGE'";
            $partition = $this->queryRaw($Mysqli, $sql)->getResultColumn('descr');
            if (empty($partition) || empty($partition[0])) {
                continue;
            }
            $max = max($partition);

            if ($max <= $cutOff) {
                $warning[] = $tname;
            }
        }
        $Mysqli->close();
        if ($warning) {
            $title = '数据表分区不足！！';
            $msg = "检测到以下表分区不足{$day}天：" . implode('、', $warning);
            trace($title . $msg, 'info', 'worker');
            dingtalk_text($title . $msg);
            wechat_notice($title, $msg);
        }
    }

    /**
     * 按日,月,年时间分表
     * @param string|array $table 表名
     * @param int $sdate 开始时间,格式20180101
     * @param int $edate 结束时间,格式20180203
     * @param string $field 分区字段
     * @param int $type 分区类型: 1-日； 2-月； 3-季； 4-年
     * @param bool $showtab 是否需求执行show table以确认表是否存在
     * @return void|array
     */
    public function rangePartition($table = '', $sdate = 0, $edate = 0, $field = 'instime', $type = 2, $showtab = false)
    {
        is_string($table) && strpos($table, ',') !== false && $table = explode(',', $table);
        if (is_array($table)) {
            $args = func_get_args();
            foreach ($table as $v) {
                $args[0] = $v;
                $res = call_user_func_array(__METHOD__, $args);
            }
            return $res;
        }


        if ( ! $table) {
            return $this->_reMsg('参数table不能为空!', 1);
        }

        // 对于用月分区，月的截止时间戳应该采用下个月1号的第一秒。所以这里的sdate应该采用下月的1号0秒
        //$sdate = $sdate ? : date('Ymd');
        $sdate = $sdate ?: date('Ymd', $type == 2 ? mktime(0, 0, 0, date('n') + 1, 1) : time());
        $edate = $edate ?: date('Ymd', strtotime('+' . ($type < 3 ? 90 : 370) . ' days'));
        if ($sdate >= $edate) {
            return $this->_reMsg('开始日期必须小于结束日期', 1);
        }
        $arr = listdate($sdate, $edate, $type, $type == 2 ? 'Ym01' : 'Ymd', $type == 2 ? 'ym' : 'ymd');
        if (empty($arr)) {
            return;
        }

        go(function () use ($arr, $type, $showtab, $table, $field) {

            try {
                $Mysqli = $this->getClient();

                // 获取此表当前的分区情况
                $parSql = "SELECT PARTITION_DESCRIPTION descr FROM INFORMATION_SCHEMA.partitions WHERE TABLE_SCHEMA=schema() AND TABLE_NAME='$table'";
                $partitions = $this->queryRaw($Mysqli, $parSql, false);

                // 还没有分区
                if (empty($partitions[0]['descr'])) {
                    $addSql = [];
                    foreach ($arr as $k => $v) {
                        $addSql[] = "PARTITION p$k VALUES LESS THAN (UNIX_TIMESTAMP($v))";
                    }
                    $sql = "ALTER TABLE $table PARTITION  BY RANGE ($field)(" . implode(',', $addSql) . ")";
                    $this->queryRaw($Mysqli, $sql);
                } else {
                    $psql = [];
                    $partitions = array_column($partitions, 'descr');
                    $fmt = $this->fmtByMysql($Mysqli, $arr);
                    foreach ($fmt as $k => $v) {
                        if ( ! in_array($v, $partitions)) {
                            $psql[] = "PARTITION $k VALUES LESS THAN ($v)";
                        }
                    }
                    if ($psql) {
                        $sql = "ALTER TABLE $table ADD PARTITION (" . implode(',', $psql) . ")";
                        $this->queryRaw($Mysqli, $sql);
                    }
                }
                $res = $this->_reMsg("表{$table}执行分区完成");

                $Mysqli->close();
                return $res;
            } catch (\Exception $e) {
                if ($Mysqli && $Mysqli instanceof Mysqli) {
                    $Mysqli->close();
                }
                return $this->_reMsg($e->getMessage(), 2);
            }
        });
    }

    public function shard($month_ereg = '', $quarter_ereg = '', $year_ereg = '', $field = 'instime')
    {
        $Mysqli = $this->getClient();
        $month_table = $quarter_table = $year_table = [];

        $tables = $this->queryRaw($Mysqli, 'SHOW TABLES', false);
        foreach ($tables as $v) {
            $v = current($v);
            foreach (['month', 'quarter', 'year'] as $t) {
                $var = "{$t}_ereg";
                $var = $$var;
                if ($var && preg_match($var, $v)) {
                    $var = "{$t}_table";
                    array_push($$var, $v);
                    break;
                }
            }
        }
        $Mysqli->close();

        $month_table && $this->rangePartition($month_table, 0, 0, $field, 2);
        $quarter_table && $this->rangePartition($quarter_table, 0, 0, $field, 3);
        $year_table && $this->rangePartition($year_table, 0, 0, $field, 4);
        return true;
    }

    protected function queryRaw(Mysqli $Mysqli, $sql = '', $ret = true)
    {
        $Builder = new QueryBuilder();
        $Builder->raw($sql);
        $Result = $Mysqli->query($Builder);
        return $ret ? $Result : $Result->getResult();
    }

    protected function fmtByMysql(Mysqli $Mysqli, $arr = [])
    {
        $sql = [];
        foreach ($arr as $k => $v) {
            $sql[] = "UNIX_TIMESTAMP($v) AS p$k";
        }
        return $this->queryRaw($Mysqli, 'SELECT ' . implode(',', $sql))->getResultOne();
    }


    /**
     * 返回信息
     * $msg 返回信息
     * $code 状态码,0-成功,非0-失败
     * return array
     */
    private function _reMsg($msg = '', $code = 0)
    {
        if ($code > 0) {
            $title = '执行分区错误: ';
            dingtalk_text("$title  $msg", true);
            wechat_notice($title, $msg);
        }
        trace($msg, $code ? 'error' : 'info', 'crontab');
        return ['err' => $code, 'msg' => $msg];
    }
}
