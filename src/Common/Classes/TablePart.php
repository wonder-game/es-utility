<?php

namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Spl\SplBean;
use WonderGame\EsUtility\Notify\DingTalk\Message\Markdown;
use WonderGame\EsUtility\Notify\EsNotify;
use WonderGame\EsUtility\Notify\Feishu\Message\TablePartWarn;

/**
 * MySQL表分区类
 */
class TablePart extends SplBean
{
    /**
     * MySQL连接名称
     * @var string
     */
    protected $mysqlPool = 'default';

    /**
     * MySQL其他配置项
     * @var array
     */
    protected $mysqlConfig = [];

    /**
     * MySQL使用的时区，默认为东8区
     * @var string
     */
    protected $tzs = '+8:00';

    /** @var null | Mysqli */
    private $MysqlClient = null;

    protected $dbName = '';

    protected function initialize(): void
    {
        $MysqlClient = new Mysqli($this->mysqlPool, ['timeout' => -1] + $this->mysqlConfig);
        if ( ! empty($this->tzs)) {
            $MysqlClient->setTimeZone($this->tzs);
        }
        $this->MysqlClient = $MysqlClient;

        if (empty($this->dbName)) {
            $this->dbName = $this->MysqlClient->rawQuery('select database() as db')[0]['db'] ?? '';
        }
    }

    public function __destruct()
    {
        $this->MysqlClient->close();
    }

    /**
     * 执行原生SQL
     * @param string $sql
     * @return \EasySwoole\ORM\Db\Result
     */
    protected function queryRaw($sql)
    {
        $Builder = new QueryBuilder();
        $Builder->raw($sql);
        return $this->MysqlClient->query($Builder);
    }

    /**
     * 本库所有数据表
     * @return array
     */
    protected function showTables()
    {
        $sql = 'SELECT table_name FROM information_schema.tables WHERE table_schema = schema()';
        return $this->queryRaw($sql)->getResultColumn('table_name') ?: [];
    }

    /**
     * 检查表分区是否存在
     * @param string $tableName
     * @return bool
     */
    protected function checkExist($tableName)
    {
        $sql = "SELECT COUNT(*) AS part_count FROM information_schema.partitions WHERE table_schema = schema() AND table_name = '$tableName' AND partition_name IS NOT NULL";
        $partCount = $this->queryRaw($sql)->getResultOne()['part_count'] ?? 0;
        return $partCount > 0;
    }

    /**
     * 初始化表分区
     * @param string $tableName
     * @param string $fieldName
     * @param array $listdate
     * @return \EasySwoole\ORM\Db\Result
     */
    protected function initPart($tableName, $fieldName, $listdate)
    {
        $sql = [];
        foreach ($listdate as $ymd => $Ymd) {
            $sql[] = "PARTITION p$ymd VALUES LESS THAN (UNIX_TIMESTAMP($Ymd))";
        }
        $sql = "ALTER TABLE $tableName PARTITION  BY RANGE ($fieldName)(" . implode(',', $sql) . ")";
        trace("{$this->dbName}.{$tableName} 表分区初始化完成, SQL=$sql");
        return $this->queryRaw($sql);
    }

    /**
     * 新增表分区
     * @param string $tableName
     * @param array $listdate
     * @return void
     */
    protected function addPart($tableName, $listdate)
    {
        // 获取此表当前的分区情况
        $parSql = "SELECT PARTITION_NAME name FROM INFORMATION_SCHEMA.partitions WHERE TABLE_SCHEMA=schema() AND TABLE_NAME='$tableName'";
        $partNames = $this->queryRaw($parSql)->getResultColumn('name');
        $psql = [];

        foreach ($listdate as $ymd => $Ymd) {
            $partName = "p{$ymd}";
            if ( ! in_array($partName, $partNames)) {
                $psql[] = "PARTITION $partName VALUES LESS THAN (UNIX_TIMESTAMP($Ymd))";
            }
        }
        if ($psql) {
            $sql = "ALTER TABLE $tableName ADD PARTITION (" . implode(',', $psql) . ")";
            $this->queryRaw($sql);
            trace("{$this->dbName}.{$tableName} 表分区新增完成, SQL=$sql");
        }
    }

    /**
     * 执行创建分区
     * @param string $pattern 正则表达式
     * @param string $fieldName 分区字段名
     * @param array $listdate 分组key=>value,对应分区的descr和value
     * @return void
     */
    protected function doCreate($pattern, $fieldName, $listdate)
    {
        if (empty($listdate)) {
            throw new \Exception("listdate 参数错误.请检查传递的时间参数");
        }

        $tableList = $this->showTables();
        foreach ($tableList as $tableName) {
            if (!preg_match($pattern, $tableName)) {
                continue;
            }
            // 符合规则的数据表，对于已有分区则添加，无分区则执行初始化
            if ($this->checkExist($tableName)) {
                $this->addPart($tableName, $listdate);
            } else {
                $this->initPart($tableName, $fieldName, $listdate);
            }
        }
    }

    /**
     * 清除数据表的所有分区，此动作不会删除数据
     * @param string $tableName
     * @return \EasySwoole\ORM\Db\Result
     */
    public function clearPart($tableName)
    {
        $sql = "ALTER TABLE {$tableName} REMOVE PARTITIONING";
        return $this->queryRaw($sql);
    }

    /**
     * 按时间删除分区
     * @param string $tableName 数据库表名
     * @param int $days 删除多少天前的分区
     * @return false|void
     */
    public function delPart($tableName, $days)
    {
        // 是否需要限制最低保留天数？？并发单表日记录可能几个亿，底层暂不做限制
        // $days = $days < 5 ? 5 : $days;

        $sql = "SELECT PARTITION_NAME name FROM INFORMATION_SCHEMA.partitions WHERE TABLE_SCHEMA=schema() AND TABLE_NAME='$tableName' AND PARTITION_DESCRIPTION <= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL $days DAY))";

        $parts = $this->queryRaw($sql)->getResultColumn('name');

        if ($parts) {
            $parts = implode(',', $parts);
            $sql = "ALTER TABLE $tableName DROP PARTITION {$parts}";
            $this->queryRaw($sql);
            trace("$tableName 删除分区成功: $parts");
        }
    }

    // 拆分分区，将一个分区拆分为多个子分区，仅支持 RANGE/LIST 分区
    public function splitPart()
    {
        // todo
    }

    // 合并分区，将多个相邻分区合并为一个，仅支持 RANGE/LIST 分区
    public function mergePart()
    {
        // todo
    }

    /**
     * 优化分区，清除碎片，期间该分区会被锁定
     * @param string $tableName
     * @param string|array $partName 分区名称
     * @return void
     */
    public function optimizePart($tableName, $partName)
    {
        if (is_array($partName)) {
            $partName = implode(',', $partName);
        }
        $sql = "ALTER TABLE {$tableName} OPTIMIZE PARTITION {$partName}";
        return $this->queryRaw($sql);
    }

    /**
     * 按日创建分区
     * @param string $pattern 匹配数据表的正则表达式，示例值：/(active_\d+)|(login_\d+)|(reg_\d+)/i
     * @param string $fieldName 分区字段
     * @param string $startDate 开始时间,格式20251024
     * @param string $endDate 结束时间,格式20251024
     * @return void
     */
    public function createDay($pattern, $fieldName = 'instime', $startDate = '', $endDate = '')
    {
        $startDate = $startDate ?: date('Ymd');
        $endDate = $endDate ?: date('Ymd', strtotime('+90 days'));

        $listdate = listdate($startDate, $endDate, 1);

        $this->doCreate($pattern, $fieldName, $listdate);
    }

    /**
     * 按月创建分区
     * @param string $pattern 匹配数据表的正则表达式，示例值：/(active_\d+)|(login_\d+)|(reg_\d+)/i
     * @param string $fieldName 分区字段
     * @param string $startDate 开始时间,格式20251024
     * @param string $endDate 结束时间,格式20251024
     * @return void
     */
    public function createMonth($pattern, $fieldName = 'instime', $startDate = '', $endDate = '')
    {
        $startDate = $startDate ?: date('Ymd', mktime(0, 0, 0, date('n') + 1, 1));
        $endDate = $endDate ?: date('Ymd', strtotime('+90 days'));

        $listdate = listdate($startDate, $endDate, 2, 'Ym01', 'ym');

        $this->doCreate($pattern, $fieldName, $listdate);
    }

    /**
     * 按季度创建分区
     * @param string $pattern 匹配数据表的正则表达式，示例值：/(active_\d+)|(login_\d+)|(reg_\d+)/i
     * @param string $fieldName 分区字段
     * @param string $startDate 开始时间,格式20251024
     * @param string $endDate 结束时间,格式20251024
     * @return void
     */
    public function createQuarter($pattern, $fieldName = 'instime', $startDate = '', $endDate = '')
    {
        $startDate = $startDate ?: date('Ymd');
        $endDate = $endDate ?: date('Ymd', strtotime('+370 days'));

        $listdate = listdate($startDate, $endDate, 3);

        $this->doCreate($pattern, $fieldName, $listdate);
    }

    /**
     * 按年创建分区
     * @param string $pattern 匹配数据表的正则表达式，示例值：/(active_\d+)|(login_\d+)|(reg_\d+)/i
     * @param string $fieldName 分区字段
     * @param string $startDate 开始时间,格式20251024
     * @param string $endDate 结束时间,格式20251024
     * @return void
     */
    public function createYear($pattern, $fieldName = 'instime', $startDate = '', $endDate = '')
    {
        $startDate = $startDate ?: date('Ymd');
        $endDate = $endDate ?: date('Ymd', strtotime('+370 days'));

        $listdate = listdate($startDate, $endDate, '%y');

        $this->doCreate($pattern, $fieldName, $listdate);
    }

    /**
     * 检查表分区是否小于 n 天
     * @param int $days
     * @param array $tableList
     * @param boolean $send 是否执行发送动作，可设置为false后自行发送消息
     * @return void
     */
    public function checkPart($days, $tableList = [], $send = true)
    {
        if (empty($tableList)) {
            $tableList = $this->showTables();
        }
        $cutOff = strtotime("+{$days} days");
        $warTables = [];
        foreach ($tableList as $tableName) {
            // RANGE分区
            $sql = "SELECT max(partition_description) descr FROM INFORMATION_SCHEMA.partitions WHERE TABLE_SCHEMA=schema() AND TABLE_NAME='{$tableName}' AND PARTITION_METHOD='RANGE'";
            $maxPart = $this->queryRaw($sql)->getResultOne()['descr'] ?? 0;

            if ($maxPart && $maxPart <= $cutOff) {
                $warTables[] = $tableName;
            }
        }

        // 通知
        if ($warTables && $send) {

            $servname = config('SERVNAME');
            $driver = config('ES_NOTIFY.driver') ?: 'dingTalk';

            $title = "MySQL数据表分区不足{$days}天";
            switch ($driver) {
                case 'feishu': // 飞书 card
                    $Message = new TablePartWarn([
                        'title' => $title,
                        'subTitle' => "服务器：{$servname}，数据库：{$this->dbName}",
                        'dbTableList' => $warTables,
                    ]);

                    break;
                default: // 钉钉 markdown
                    $data = [
                        '### **' . $title . '**',
                        '- 服务器: ' . $servname,
                        '- 数据库: ' . $this->dbName,
                    ];
                    foreach ($warTables as $idx => $tableName) {
                        $idx++;
                        $data[] = "{$idx}. 数据表：$tableName";
                    }
                    $Message = new Markdown([
                        'title' => $title,
                        'text' => implode(" \n\n ", $data),
                        'isAtAll' => true
                    ]);
                    break;
            }

            EsNotify::getInstance()->doesOne($driver, $Message);
            trace("$title, 服务器：$servname, 数据库: {$this->dbName}, 数据表：" . json_encode($warTables), 'error');
        }
        return $warTables;
    }
}
