<?php


namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Component\WaitGroup;
use EasySwoole\HttpClient\Exception\InvalidUrl;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\Redis\Exception\RedisException;
use EasySwoole\Trigger\Location;
use EasySwoole\Trigger\TriggerInterface;

class ExceptionTrigger implements TriggerInterface
{
	public function error($msg, int $errorCode = E_USER_ERROR, Location $location = null)
	{
		// 暂不处理notice级别的异常
		if (in_array($errorCode, [E_NOTICE])) {
			return;
		}

		$trace = debug_backtrace();
		if ($location == null) {
			$location = new Location();
			$caller = array_shift($trace);
			$location->setLine($caller['line']);
			$location->setFile($caller['file']);
		}
		$eMsg = [
			'message' => $msg,
			'file' => $location->getFile(),
			'line' => $location->getLine(),
			'trace' => $trace
		];
		$this->doTrigger(__FUNCTION__, $eMsg);
	}

	public function throwable(\Throwable $throwable)
	{
		$eMsg = [
			'message' => $throwable->getMessage(),
			'file' => $throwable->getFile(),
			'line' => $throwable->getLine(),
			'trace' => $throwable->getTrace(),
		];
		$this->doTrigger(__FUNCTION__, $eMsg);
	}

	protected function doTrigger($trigger, $eMsg = [])
	{
		trace($eMsg, 'error', $trigger);
		unset($eMsg['trace']);
		$eMsg['trigger'] = $trigger;
		if (\Swoole\Coroutine::getCid() >= 0) {
			$task = \EasySwoole\EasySwoole\Task\TaskManager::getInstance();
			$task->async(new \WonderGame\EsUtility\Task\Error($eMsg));
		}
	}
}
