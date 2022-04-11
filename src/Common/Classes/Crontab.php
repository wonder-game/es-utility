<?php


namespace WonderGame\EsUtility\Common\Classes;

use Cron\CronExpression;
use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Utility\File;
use WonderGame\EsUtility\Task\Crontab as CrontabTemplate;
use EasySwoole\Task\AbstractInterface\TaskInterface;

class Crontab extends AbstractCronTask
{
    public static function getRule(): string
    {
        return '* * * * *';
    }

    public static function getTaskName():string
    {
        $arr = explode('\\', static::class);
        return end($arr);
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        throw $throwable;
    }

    public function run(int $taskId, int $workerIndex)
    {
        $file = config('LOG.dir') . '/crontab.object.data';
        try {
            /** @var \App\Model\Crontab $model */
            $model = model('Crontab');
            // 获取执行Crontab列表
            $cron = $model->getCrontab(explode('-', config('SERVNAME'))[3]);

            // 成功记录到文件
            File::createFile($file, json_encode($cron, JSON_UNESCAPED_UNICODE));
        }
        catch (\Exception | \Throwable $e)
        {
            // 失败降级从文件读取
            $str = file_get_contents($file);

            if (!$str)
            {
                return;
            }

            $cron = json_decode($str, true);
        }

        if (empty($cron) || !is_array($cron))
        {
            return;
        }

        $task = TaskManager::getInstance();
        foreach ($cron as $value) {
            if (!CronExpression::isValidExpression($value['rule']))
            {
                trace("id={$value['id']} 运行规则设置错误 {$value['rule']}", 'error');
                continue;
            }

            $className = $value['rclass'] ?? 'Crontab';
            // 异步任务模板类
            if ($className && strpos($className, '\\') === false) {
                $className = '\\WonderGame\\EsUtility\\Task\\' . ucfirst($className);
            }

            if (!class_exists($className) || (! $className instanceof TaskInterface)) {
//                trace("{$className} 不存在", 'error');
//                continue;
                // 2022-04-06 为兼容旧版本
                $className = CrontabTemplate::class;
            }

            if ( ! (CronExpression::factory($value['rule'])->isDue())) {
                // 时间未到
                continue;
            }

            $args = is_array($value['args']) ? $value['args'] : json_decode($value['args'], true);

            $class = new $className([$value['eclass'], $value['method']], $args);
            // 投递给异步任务
            $finish = $task->async($class, function ($reply, $taskId, $workerIndex) use ($value) {
                trace("id={$value['id']} finish! {$value['name']}, reply={$reply}, workerIndex={$workerIndex}, taskid={$taskId}");
            });
            // 只运行一次的任务
            if ($finish && $value['status'] == 2)
            {
                $model->update(['status' => 1], ['id' => $value['id']]);
            }
        }
    }
}
