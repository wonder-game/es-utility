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

            $className = $this->getTemplateClass($value['rclass']);
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
        $Mysqli = new Mysqli('default', is_array($dbConfig) ? $dbConfig : []);
        \Swoole\Coroutine::defer(function () use ($Mysqli) {
            $Mysqli->close();
        });
        return $Mysqli;
    }

    protected function getCrontab()
    {
        // 查询条件，回调函数 或 字段条件的数组
        $where = config('CRONTAB.where');

        $Builder = new QueryBuilder();
        $Builder->where('status', [0, 2], 'IN');

        if (is_callable($where)) {
            $where($Builder);
        } elseif (is_string($where)) {
            $Builder->where($where);
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
        $file = config('CRONTAB.backup') ?: (config('LOG.dir') . '/crontab.object.data');
        try {
            $cron = $this->getCrontab();
            // 成功记录到文件
            $this->backupFile($file, $cron);
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

    protected function backupFile($filename, $data = [])
    {
        $dir = dirname($filename);
        if (File::createDirectory($dir)) {
            $fp = fopen($filename, 'w+');
            fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
            fclose($fp);
        }
    }

    /**
     * 获取模板类名
     * @param string $className
     * @return string
     */
    protected function getTemplateClass($className = '')
    {
        $dftTemp = CrontabTemplate::class;
        $className = empty($className) ? $dftTemp : $className;
        try {
            // 默认命名空间，跟随CrontabTemplate
            if (strpos($className, '\\') === false) {
                $Ref = new \ReflectionClass($dftTemp);
                $nameSpace = $Ref->getNamespaceName();
                $className = $nameSpace . '\\'. ucfirst($className);
            }

            // 必须实现TaskInterface接口
            $RefFull = new \ReflectionClass($className);
            if ($RefFull->implementsInterface(TaskInterface::class)) {
                return $className;
            }
        } catch (\ReflectionException $e) {
            trace_immediate($e->__toString(), 'error');
        }
        // todo 为兼容旧版本，否则为抛异常, 上方捕获逻辑也不需要

        return $dftTemp;
    }
}
