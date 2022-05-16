<?php


namespace WonderGame\EsUtility\Crontab;

use Cron\CronExpression;
use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use EasySwoole\Utility\File;
use WonderGame\EsUtility\Task\Crontab as CrontabTemplate;

class Crontab extends AbstractCronTask
{
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
		$file = config('CRONTAB_BACKUP_FILE') ?: (config('LOG.dir') . '/crontab.object.data');
		try {
            if ( ! find_model('Crontab', false)) {
                trace('Crontab Model Class Not Found! ', 'error');
                return;
            }
			/** @var \App\Model\Crontab $model */
			$model = model('Crontab');
			// 获取执行Crontab列表
			$cron = $model->getCrontab(explode('-', config('SERVNAME'))[3]);

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

			$className = $value['rclass'] ?? 'Crontab';
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

			$class = new $className([$value['eclass'], $value['method']], $args);
			// 投递给异步任务
			$finish = $task->async($class, function ($reply, $taskId, $workerIndex) use ($value) {
				trace("[CRONTAB] id={$value['id']} finish! {$value['name']}, reply={$reply}, workerIndex={$workerIndex}, taskid={$taskId}");
			});
			// 只运行一次的任务
			if ($finish > 0 && $value['status'] == 2) {
				$model->update(['status' => 1], ['id' => $value['id']]);
			}

			if ($finish <= 0) {
				trace("投递失败: 返回值={$finish}, id={$value['id']}, name={$value['name']}", 'error');
			}
		}
	}
}
