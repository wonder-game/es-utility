<?php


namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Mysqli\Config;
use EasySwoole\Mysqli\Client;
use Swoole\Coroutine;

class Mysqli
{
    protected $config = [];

    /** @var Client|null $MysqliClient */
    protected $MysqliClient = null;

    public function __construct($name = 'default', $config = [])
    {
        if (Coroutine::getCid() < 0)
        {
            throw new \Exception('请在协程环境运行Mysqli');
        }
        $this->config = config('MYSQL.' . $name);
        $this->config = array_merge($this->config, $config);

        $this->initClient();
    }

    protected function initClient()
    {
        if (is_null($this->MysqliClient))
        {
            $this->MysqliClient = new Client(new Config($this->config));
        }
        return $this;
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
        if (is_string($order))
        {
            list($sortField, $sortValue) = explode(' ', $order);
            $builder->orderBy($sortField, $sortValue);
        }
        // ['sort' => 'desc'] || ['sort' => 'desc', 'id' => 'asc']
        else if (is_array($order))
        {
            // 保证传值的最高优先级
            foreach ($order as $k => $v)
            {
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
