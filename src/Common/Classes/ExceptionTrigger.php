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
	
	protected function doError($trigger, $eMsg = [])
	{
		trace($eMsg, 'error', $trigger);
		
		$wg = new WaitGroup();
		$wg->add();
		
		go(function () use ($eMsg, $wg) {
			if (is_string($eMsg)) {
				$eMsg = [$eMsg];
			}
			// 错误类型
			$eMsg['type'] = 'program';
			// 报错服务器
			$eMsg['servername'] = config('SERVNAME');
			// trace通过常规文件记录
			unset($eMsg['trace']);
			
			try {
				if ($config = config('EXCEPTION_REPORT')) {
					if (isset($config['type']) && $config['type'] === 'http' && $config['url']) {
						$encrypt = LamOpenssl::getInstance()->publicEncrypt(json_encode($eMsg));
						$client = new HttpClient($config['url']);
						$response = $client->post(['envkeydata' => $encrypt]);
						$httpCode = $response->getStatusCode();
						$httpBody = $response->getBody();
					} else {
						$redis = defer_redis($config['poolname'], $config['db']);
						$redis->lPush($config['queue'], json_encode($eMsg));
					}
				}
			} catch (InvalidUrl | RedisException | \Exception | \Throwable $e) {
				trace($eMsg, 'info', 'lowlevel');
			}
			$wg->done();
		});
		
		// 协程等待
		$wg->wait();
		// 关闭
		$wg->close();
	}
}
