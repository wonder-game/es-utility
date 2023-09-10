<?php

namespace WonderGame\EsUtility\Crontab\Driver;

use EasySwoole\Mysqli\QueryBuilder;
use WonderGame\EsUtility\Common\Classes\Mysqli;

/**
 * 依赖配置
 * CRONTAB.db
 * CRONTAB.where
 * MYSQL.xx
 */
class Mysql implements Interfaces
{
    /**
     * @var null | Mysqli
     */
    protected $Client = null;

    protected $tableName = '';

    public function list(): array
    {
        $where = config('CRONTAB.where');

        $Builder = new QueryBuilder();
        $Builder->where('status', [0, 2], 'IN');

        if (is_callable($where)) {
            $where($Builder);
        } elseif (is_string($where)) {
            $Builder->where($where);
        } elseif (is_array($where)) {
            foreach ($where as $whereField => $whereValue) {
                if (is_array($whereValue)) {
                    $Builder->where($whereField, ...$whereValue);
                } else {
                    $Builder->where($whereField, $whereValue);
                }
            }
        }

        $Builder->get($this->getTableName());

        return $this->getClient()->query($Builder)->getResult();
    }

    public function update(int $id, int $status)
    {
        $Builder = new QueryBuilder();
        $Builder->where('id', $id)->update($this->getTableName(), ['status' => $status]);
        return $this->getClient()->query($Builder)->getAffectedRows();
    }

    protected function getTableName()
    {
        if (empty($this->tableName)) {
            $this->tableName = config('CRONTAB.table') ?: 'crontab';
        }
        return $this->tableName;
    }

    protected function getClient()
    {
        if ($this->Client instanceof Mysqli) {
            return $this->Client;
        }

        $dbConfig = config('CRONTAB.db');

        if (is_string($dbConfig)) {
            $dbConfig = config('MYSQL.' . $dbConfig);
            if (empty($dbConfig)) {
                throw new \Exception('CRONTAB.db配置错误');
            }
        }
        $this->Client = new Mysqli('default', is_array($dbConfig) ? $dbConfig : []);
        \Swoole\Coroutine::defer(function () {
            $this->Client->close();
        });
        return $this->Client;
    }
}
