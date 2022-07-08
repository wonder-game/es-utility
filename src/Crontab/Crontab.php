<?php


namespace WonderGame\EsUtility\Crontab;

use Cron\CronExpression;
use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use EasySwoole\Utility\File;
use WonderGame\EsUtility\Common\Classes\Mysqli;
use WonderGame\EsUtility\Task\Crontab as CrontabTemplate;

class Crontab extends AbstractCronTask
{
    protected $tableName = 'crontab';

    public static function getRule(): string
    {
        return '* * * * *';
    }

    public static function getTaskName(): string
    {
        $arr = explode('\\', static::class);
        return end($arr);
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        \EasySwoole\EasySwoole\Trigger::getInstance()->throwable($throwable);
        return '执行失败： ' . __CLASS__;
    }

    public function run(int $taskId, int $workerIndex)
    {
        $cron = $this->getTaskList();

        if (empty($cron) || ! is_array($cron)) {
            return;
        }

        $task = TaskManager::getInstance();
        foreach ($cron as $value) {
            if ( ! CronExpression::isValidExpression($value['rule'])) {
                $msg = "id={$value['id']} 运行规则设置错误 {$value['rule']}";
                trace($msg, 'error');
                dingtalk_text($msg);
                continue;
            }

            if ( ! (CronExpression::factory($value['rule'])->isDue())) {
                // 时间未到
                continue;
            }

            $args = $value['args'] ?: [];
            if (is_string($args)) {
                $args = json_decode($args, true);
            }
            if ( ! is_array($args)) {
                $msg = "定时任务参数解析失败: id={$value['id']},name={$value['name']},args=" . var_export($value['args'], true);
                trace($msg, 'error');
                dingtalk_text($msg);
                continue;
            }

            $className = $this->getTemplateClass($value['rclass'] ?? 'Crontab');
            $class = new $className([$value['eclass'], $value['method']], $args);
            // 投递给异步任务
            $finish = $task->async($class, function ($reply, $taskId, $workerIndex) use ($value) {
                trace("[CRONTAB] id={$value['id']} finish! {$value['name']}, reply={$reply}, workerIndex={$workerIndex}, taskid={$taskId}");
            });
            // 只运行一次的任务
            if ($finish > 0 && $value['status'] == 2) {
                $this->updStatus($value['id']);
            }

            if ($finish <= 0) {
                trace("投递失败: 返回值={$finish}, id={$value['id']}, name={$value['name']}", 'error');
            }
        }
    }

    protected function getMysqlClient()
    {
        // db配置，连接池名 或 相关配置的数组 （最优先）
        $dbConfig = config('CRONTAB.db');

        if (is_string($dbConfig)) {
            $dbConfig = config('MYSQL.' . $dbConfig);
            if (empty($dbConfig)) {
                throw new \Exception('CRONTAB.db配置错误');
            }
        }
        return new Mysqli('default', is_array($dbConfig) ? $dbConfig : []);
    }

    protected function getCrontab()
    {
        // 查询条件，回调函数 或 字段条件的数组
        $where = config('CRONTAB.where');

        $Builder = new QueryBuilder();
        $Builder->where('status', [0, 2], 'IN');

        if (is_callable($where)) {
            $where($Builder);
        } elseif (is_array($where)) {
            foreach ($where as $whereField => $whereValue)
            {
                $Builder->where($whereField, ...$whereValue);
            }
        }

        $Builder->get($this->tableName);

        return $this->getMysqlClient()->query($Builder)->getResult();
    }

    protected function updStatus($id, $status = 1)
    {
        $Builder = new QueryBuilder();
        $Builder->where('id', $id)->update($this->tableName, ['status' => $status]);
        return $this->getMysqlClient()->query($Builder);
    }

    // 获取任务列表
    protected function getTaskList()
    {
        $file = config('CRONTAB_BACKUP_FILE') ?: (config('LOG.dir') . '/crontab.object.data');
        try {
            $cron = $this->getCrontab();

            // 成功记录到文件
            File::createFile($file, json_encode($cron, JSON_UNESCAPED_UNICODE));
        } catch (\Exception | \Throwable $e) {
            // 失败降级从文件读取
            if ( ! file_exists($file) || ! ($str = file_get_contents($file))) {
                // 连文件都没有，说明从未正常运行过
                throw $e;
            }

            $cron = json_decode($str, true);
        }
        return $cron;
    }

    // 获取模板类名
    protected function getTemplateClass($className)
    {
        if (empty($className)) {
            $className = 'Crontab';
        }
        // 异步任务模板类
        if ($className && strpos($className, '\\') === false) {
            $className = '\\WonderGame\\EsUtility\\Task\\' . ucfirst($className);
        }

        if ( ! class_exists($className) || ( ! $className instanceof TaskInterface)) {
//                trace("{$className} 不存在", 'error');
//                continue;
            // 2022-04-06 为兼容旧版本
            $className = CrontabTemplate::class;
        }
        return $className;
    }
}
