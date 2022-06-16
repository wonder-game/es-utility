<?php


namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Mysqli\Client;
use EasySwoole\Mysqli\Config;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\Db\MysqliClient;

class Mysqli extends MysqliClient
{
    protected $_config = [];

    /**
     * 存储数据表字段列表 ['hourly' => ['gameid', 'login', 'reg', ...]]
     * @var array
     */
    protected $tableStruct = [];

    /**
     * @param string $name 连接池名
     * @param array $config 需要合并的配置项
     */
    public function __construct(string $name = 'default', array $config = [])
    {
        $this->_config = config('MYSQL.' . $name);
        $this->_config = array_merge($this->_config, $config);

        parent::__construct(new Config($this->_config));

        if ( ! isset($this->_config['save_log']) || $this->_config['save_log'] !== false) {
            $this->onQuery(function ($res, Client $client, $start) {
                trace($client->lastQueryBuilder()->getLastQuery(), 'info', 'sql');
            });
        }
    }

    /**
     * 获取非虚拟列字段
     * @param $tableName
     * @return array
     * @throws \EasySwoole\Mysqli\Exception\Exception
     */
    public function fullColumns($tableName): array
    {
        if ( ! isset($this->tableStruct[$tableName])) {
            $data = $this->rawQuery("show full columns from {$tableName} where Extra<>'VIRTUAL GENERATED'");
            $this->tableStruct[$tableName] = array_column($data, 'Field');
        }
        return $this->tableStruct[$tableName];
    }

    public function replace($tableName, $data = [], $duplicate = [])
    {
        $columns = array_flip($this->fullColumns($tableName));
        // 过滤非字段key
        $data = array_intersect_key($data, $columns);
        $duplicate = empty($duplicate) ? $data : array_intersect_key($duplicate, $columns);

        // 构建SQL
        $Builder = new QueryBuilder();
        $Builder->onDuplicate($duplicate)->insert($tableName, $data);

        return $this->query($Builder, true);
    }

    public function insert($tableName, $data = [])
    {
        $columns = array_flip($this->fullColumns($tableName));
        // 构建SQL
        $Builder = new QueryBuilder();
        $data = array_intersect_key($data, $columns);
        $Builder->insert($tableName, $data);

        return $this->query($Builder, true);
    }

    public function insertAll($tableName, $data = [])
    {
        $columns = array_flip($this->fullColumns($tableName));
        // 构建SQL
        $Builder = new QueryBuilder();
        $Builder->insertAll($tableName, $data, ['field' => $columns]);

        return $this->query($Builder, true);
    }

    /**
     * 设置连接时区
     * @param $tzn 格式为: -5 或 -5:00 或 8 或 +8:00 ...
     * @throws \EasySwoole\Mysqli\Exception\Exception
     */
    public function setTimeZone($tzn)
    {
        if (strpos($tzn, ':') === false) {
            $tznInt = intval($tzn);
            $tzn = ($tznInt > 0 ? "+$tznInt" : $tznInt) . ':00';
        }

        $this->rawQuery("set time_zone = '{$tzn}'");
    }

    public function getTimeZone()
    {
        $dbTimeZone = $this->rawQuery("SHOW VARIABLES LIKE 'time_zone'");
        $PhpTimeZone = date_default_timezone_get();

        return [$dbTimeZone, $PhpTimeZone];
    }
}
