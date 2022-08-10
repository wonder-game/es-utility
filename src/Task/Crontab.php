<?php


namespace WonderGame\EsUtility\Task;

use EasySwoole\Task\AbstractInterface\TaskInterface;

/**
 * Crontab通用模板类
 */
class Crontab implements TaskInterface
{
    /**
     * 此类参数
     * @var array
     */
    protected $data = [];

    /**
     * 业务类的参数
     * @var array
     */
	protected $args = [];

	public function __construct($data, $args = [])
	{
		// 参数校验已在外部处理
        $this->data = $data;
		$this->args = $args;
	}

	public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
	{
		// 异常处理
		\EasySwoole\EasySwoole\Trigger::getInstance()->throwable($throwable);
		return '执行失败： ' . __CLASS__;
	}

	public function run(int $taskId, int $workerIndex)
	{
		$data = is_array($this->args) ? $this->args : [];

		foreach ($data as $k => $v) {
			// 解析指定函数
			if (preg_match('/date\(|time\(|strtotime\(/i', $v)) {
				eval('$data["' . $k . '"] = ' . $v . ';');
			}
		}

		$className = $this->data['eclass'];
        $method = $this->data['method'];

		$start = round(microtime(true), 4) * 10000;
        call_user_func([new $className(), $method], $data);
		$end = round(microtime(true), 4) * 10000;

		$diff = $end - $start;
		if ($diff >= 10000) {
			return '耗时: [' . ($diff / 10000) . 's]';
		} else {
			return '耗时: [' . ($diff / 10) . 'ms]';
		}
	}
}
