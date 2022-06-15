<?php


namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Mysqli\Client;
use EasySwoole\Mysqli\Config;
use EasySwoole\Mysqli\QueryBuilder;
use Swoole\Coroutine;

class Mysqli
{
    protected $config = [];

    /** @var Client|null $MysqliClient */
    protected $MysqliClient = null;

    /**
     * 存储数据表字段列表 ['hourly' => ['gameid', 'login', 'reg', ...]]
     * @var array
     */
    protected $tableStruct = [];

    public function __construct($name = 'default', $config = [])
    {
        if (Coroutine::getCid() < 0) {
            throw new \Exception('请在协程环境运行Mysqli');
        }
        $this->config = config('MYSQL.' . $name);
        $this->config = array_merge($this->config, $config);

        $this->initClient();
    }

    protected function initClient()
    {
        if (is_null($this->MysqliClient)) {
            $this->MysqliClient = new Client(new Config($this->config));

            // 除非主动声明为false，否则默认是记录日志的
            if ( ! isset($this->config['save_log']) || $this->config['save_log'] !== false) {
                // 注意回调callback第二个参数与全局DBManager不同
                $this->MysqliClient->onQuery(function ($res, Client $client, $start) {
                    trace($client->lastQueryBuilder()->getLastQuery(), 'info', 'sql');
                });
            }
        }
        return $this;
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
            $data = $this->MysqliClient->rawQuery("show full columns from {$tableName} where Extra<>'VIRTUAL GENERATED'");
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
        $sql = $Builder->getLastQuery();

        return $this->MysqliClient->rawQuery($sql);
    }

    /**
     * @param $tableName
     * @param array $data 数据，批量时为二维数组
     * @param false $multiple true-批量, false-单条
     * @return array|bool|mixed
     * @throws \EasySwoole\Mysqli\Exception\Exception
     */
    public function insert($tableName, $data = [], $multiple = false)
    {
        $columns = array_flip($this->fullColumns($tableName));

        // 构建SQL
        $Builder = new QueryBuilder();

        if ($multiple) {
            // 批量
            $Builder->insertAll($tableName, $data, ['field' => $columns]);
        } else {
            // 单条
            $data = array_intersect_key($data, $columns);
            $Builder->insert($tableName, $data);
        }

        $sql = $Builder->getLastQuery();

        return $this->MysqliClient->rawQuery($sql);
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

        $this->MysqliClient->rawQuery("set time_zone = '{$tzn}'");
    }

    public function getTimeZone()
    {
        $dbTimeZone = $this->MysqliClient->rawQuery("SHOW VARIABLES LIKE '%time_zone%'");
        $PhpTimeZone = date_default_timezone_get();

        return [$dbTimeZone, $PhpTimeZone];
    }

    public function parseWhere($where = [])
    {
        $builder = $this->MysqliClient->queryBuilder();
        foreach ($where as $whereFiled => $whereProp) {
            if (is_array($whereProp)) {
                $builder->where($whereFiled, ...$whereProp);
            } else {
                $builder->where($whereFiled, $whereProp);
            }
        }
        return $this;
    }

    public function order($order)
    {
        $builder = $this->MysqliClient->queryBuilder();
        // 'id desc'
        if (is_string($order)) {
            list($sortField, $sortValue) = explode(' ', $order);
            $builder->orderBy($sortField, $sortValue);
        } // ['sort' => 'desc'] || ['sort' => 'desc', 'id' => 'asc']
        else if (is_array($order)) {
            // 保证传值的最高优先级
            foreach ($order as $k => $v) {
                $builder->orderBy($k, $v);
            }
        }
        return $this;
    }

    public function get($tableName)
    {
        $this->MysqliClient->queryBuilder()->get($tableName);
        return $this->exec();
    }

    public function exec()
    {
        return $this->MysqliClient->execBuilder();
    }

    public function getClient()
    {
        return $this->MysqliClient;
    }

    public function close()
    {
//        $this->MysqliClient->reset();
        $this->MysqliClient->close();
    }
}
