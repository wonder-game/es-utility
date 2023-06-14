<?php


namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Mysqli\Client;
use EasySwoole\Mysqli\Config;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\Db\Cursor;
use EasySwoole\ORM\Db\MysqliClient;
use EasySwoole\ORM\Db\Result;

class Mysqli extends MysqliClient
{
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
        if ($name && is_array($cfg = config('MYSQL.' . $name))) {
            $configArray = array_merge($cfg, $config);
            $this->connectionName($name);
        } else {
            $configArray = $config;
            $this->connectionName(md5(json_encode($configArray)));
        }

        parent::__construct(new Config($configArray + ['timeout' => -1]));

        if ( ! isset($configArray['save_log']) || $configArray['save_log'] !== false) {
            $this->onQuery(function ($res, Client $client, $start) {
                trace($client->lastQueryBuilder()->getLastQuery(), 'info', 'sql');
            });
        }
    }

    public function query(QueryBuilder $builder, bool $rawQuery = true): Result
    {
        return parent::query($builder, $rawQuery);
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

    /**
     * 获取数据表主键
     * @param $tableName
     * @return array
     * @throws \EasySwoole\Mysqli\Exception\Exception
     */
    public function getPrimaryKey($tableName): array
    {
        $data = $this->rawQuery("show full columns from {$tableName}");

        $pk = [];
        // 有可能是复合主键
        foreach ($data as $value) {
            if ($value['Key'] === 'PRI') {
                $pk[] = $value['Field'];
            }
        }

        return $pk;
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
        $data = array_intersect_key($data, $columns);
        // 构建SQL
        $Builder = new QueryBuilder();
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
     * @param QueryBuilder $Builder
     * @param string $modelName AbstractModel子类，否则为数组
     * @return \Generator
     * @throws \Throwable
     */
    public function fetch(QueryBuilder $Builder, string $modelName = '')
    {
        // 如果之前非fetch模式，使用新配置重新创建链接
        if ( ! $this->config->isFetchMode()) {
            $this->config->setFetchMode(true);
            $this->close();
        }

        $this->connect();
        /** @var Cursor $Cursor */
        $Cursor = $this->query($Builder, false)->getResult();

        if ($modelName && class_exists($modelName) && is_subclass_of($modelName, AbstractModel::class)) {
            $Cursor->setModelName($modelName);
            $Cursor->setReturnAsArray(false);
        } else {
            $Cursor->setReturnAsArray(true);
        }

        while ($ret = $Cursor->fetch()) {
            yield $ret;
        }
    }

    /**
     * 设置连接时区
     * @param string $tzn 格式为: -5 或 -5:00 或 8 或 +8:00 ...
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

    public function startTransaction()
    {
        $Builder = new QueryBuilder();
        $Builder->startTransaction();
        $this->query($Builder);
    }

    public function commit()
    {
        $Builder = new QueryBuilder();
        $Builder->commit();
        $this->query($Builder);
    }

    public function rollback()
    {
        $Builder = new QueryBuilder();
        $Builder->rollback();
        $this->query($Builder);
    }
}
