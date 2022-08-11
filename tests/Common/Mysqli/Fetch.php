<?php

namespace Tests\Common\Mysqli;

use EasySwoole\Mysqli\QueryBuilder;
use PHPUnit\Framework\TestCase;
use WonderGame\EsUtility\Common\Classes\Mysqli;

/**
 * php easyswoole phpunit tests/Common/Mysqli/Fetch.php
 */
class Fetch extends TestCase
{
    protected $config = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'root',
        'password' => '0987abc123',
        'database' => 'vben_admin',
        'timeout' => 10,
        'charset' => 'utf8mb4',

        // 自定义Mysqli参数
        'save_log' => false,
    ];

    protected $tableName = 'log_sql';

    public function testFetchArray()
    {
        $Builder = new QueryBuilder();
        $Mysqli = new Mysqli('', $this->config);

        $Builder->get($this->tableName);
        $Generator = $Mysqli->fetch($Builder);

        foreach ($Generator as $key => $row)
        {
            var_dump($row, \Swoole\Coroutine::getCid(), '================ row, cid');
        }
        $Mysqli->close();

        $this->assertIsArray($row);
    }

    // public function testFetchModel() {}
}
