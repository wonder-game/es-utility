<?php


namespace Linkunyuan\EsUtility\Classes;

use Cron\CronExpression;
use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use EasySwoole\EasySwoole\Task\TaskManager;
use Linkunyuan\EsUtility\Traits\LamCli;

class Crontab extends AbstractCronTask
{
    use LamCli;

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
        // 获取执行Crontab列表
        $model = model('Crontab');
        $cron = $model->getCrontab(explode('-', config('SERVNAME'))[3]);
        if (empty($cron)) {
            return;
        }
        $task = TaskManager::getInstance();
        foreach ($cron as $value) {
            if (!CronExpression::isValidExpression($value['rule'])) {
                trace("运行规则设置错误 " . json_encode($value->toArray(), JSON_UNESCAPED_UNICODE), 'error');
                continue;
            }
            $className = $value['rclass'];
            // 异步任务模板类，默认在\App\Task命名空间
            if (strpos($className, '\\') === false) {
                $className = '\\App\\Task\\' . ucfirst($className);
            }
            if (!class_exists($className)) {
                trace("{$className} 不存在", 'error');
                continue;
            }

            if (!(CronExpression::factory($value['rule'])->isDue())) {
                // 时间未到
                continue;
            }

            $args = json_decode($value['args'], true);

            $class = new $className([$value['eclass'], $value['method']], is_array($args) ? $args : $value['args']);
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