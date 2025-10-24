<?php

namespace Tests\Common;

use PHPUnit\Framework\TestCase;
use WonderGame\EsUtility\Notify\EsNotify;

class TablePart extends TestCase
{
    protected $mysqlConfig = [
        'host' => '',
        'password' => '',
        'database' => '',
        'port' => 3305,
        'user' => 'root',
        'timeout' => 30,
        'charset' => 'utf8mb4',
    ];

    protected $feishuWebhook = [
        'url' => '',
        'signKey' => '',
    ];

    protected $dingtalkWebhook = [
        'url' => '',
        'signKey' => '',
    ];

    protected function getClass()
    {
        // 测试用，实际可不传config
        return new \WonderGame\EsUtility\Common\Classes\TablePart([
            'mysqlPool' => '',
            'mysqlConfig' => $this->mysqlConfig
        ]);
    }

    /**
     * 检查数据表分区，并发送飞书异常通知
     * php easyswoole es-phpunit -mode=xx.admin.dev Tests/Common/TablePart.php --filter=testCheckPartFeishu
     * @return void
     * @throws \Exception
     */
    public function testCheckPartFeishu()
    {
        config('ES_NOTIFY.driver', 'feishu');
        $Feishu = new \WonderGame\EsUtility\Notify\Feishu\Config($this->feishuWebhook);
        // 注册一个机器人
        EsNotify::getInstance()->register($Feishu, 'feishu');

        $TablePart = $this->getClass();
        $this->assertIsObject($TablePart);

        $TablePart->checkPart(3);
    }

    /**
     * 检查数据表分区，并发送钉钉异常通知
     * php easyswoole es-phpunit -mode=xx.admin.dev Tests/Common/TablePart.php --filter=testCheckPartDingtalk
     * @return void
     * @throws \Exception
     */
    public function testCheckPartDingtalk()
    {
        config('ES_NOTIFY.driver', 'dingtalk');
        $DingTalk = new \WonderGame\EsUtility\Notify\DingTalk\Config($this->dingtalkWebhook);
        // 注册一个机器人
        EsNotify::getInstance()->register($DingTalk, 'dingtalk');

        $TablePart = $this->getClass();
        $this->assertIsObject($TablePart);

        $TablePart->checkPart(3);
    }

    /**
     * 按日分区
     * php easyswoole es-phpunit -mode=xx.admin.dev Tests/Common/TablePart.php --filter=testCreateDay
     * @return void
     */
    public function testCreateDay()
    {
        $TablePart = $this->getClass();
        $this->assertIsObject($TablePart);

        // 清空所有分区
        //$TablePart->clearPart('active_0');

        $pattern = '/active_0/i';

        // 初始化分区, 默认，90天
        $TablePart->createDay($pattern);

        // 新增分区，时间延长
        $TablePart->createDay($pattern, 'instime', date('Ymd'), date('Ymd', strtotime('+180 days')));
    }

    /**
     * 按月分区
     * php easyswoole es-phpunit -mode=xx.admin.dev Tests/Common/TablePart.php --filter=testCreateMonth
     * @return void
     */
    public function testCreateMonth()
    {
        $TablePart = $this->getClass();
        $this->assertIsObject($TablePart);

        // 清空所有分区
//        $TablePart->clearPart('active_0');

        $pattern = '/active_0/i';

        // 初始化分区, 默认，90天
        $TablePart->createMonth($pattern);

        // 新增分区，时间延长
        $TablePart->createMonth($pattern, 'instime', date('Ymd', mktime(0, 0, 0, date('n') + 1, 1)), date('Ymd', strtotime('+180 days')));
    }

    /**
     * 按季度分区
     * php easyswoole es-phpunit -mode=xx.admin.dev Tests/Common/TablePart.php --filter=testCreateQuarter
     * @return void
     */
    public function testCreateQuarter()
    {
        $TablePart = $this->getClass();
        $this->assertIsObject($TablePart);

        // 清空所有分区
//        $TablePart->clearPart('active_0');

        $pattern = '/active_0/i';

        // 初始化分区, 默认，370天
        $TablePart->createQuarter($pattern);

        $TablePart->createQuarter($pattern, 'instime', date('Ymd'), date('Ymd', strtotime('+450 days')));
    }

    /**
     * 按年分区
     * php easyswoole es-phpunit -mode=xx.admin.dev Tests/Common/TablePart.php --filter=testCreateYear
     * @return void
     */
    public function testCreateYear()
    {
        $TablePart = $this->getClass();
        $this->assertIsObject($TablePart);

        // 清空所有分区
//        $TablePart->clearPart('active_0');

        $pattern = '/active_0/i';

        // 初始化分区, 默认，370天
        $TablePart->createYear($pattern);

        $TablePart->createYear($pattern, 'instime', date('Ymd'), date('Ymd', strtotime('+1650 days')));
    }

    /**
     * 删除指定分区
     * php easyswoole es-phpunit -mode=xx.admin.dev Tests/Common/TablePart.php --filter=testDelPart
     * @return void
     */
    public function testDelPart()
    {
        $TablePart = $this->getClass();
        $this->assertIsObject($TablePart);

        // 创建一个分区，历史30天
        $pattern = '/active_0/i';
        $TablePart->createDay($pattern, 'instime', date('Ymd', strtotime("-30 days")), date('Ymd'));
        $TablePart->delPart('active_0', 20);

        $TablePart->clearPart('active_0');
    }
}
