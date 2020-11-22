<?php
/**
 * 日志处理
 * 
 * @author 林坤源
 * @version 1.0.4 最后修改时间 2020年11月22日
 */

namespace Linkunyuan\EsUtility\Classes;


use EasySwoole\Log\LoggerInterface;
use Swoole\Coroutine;

class LamLog implements LoggerInterface
{

	private $logDir;
	public $conArr = [];

	public function __construct(string $logDir = null)
	{
		$this->logDir = $logDir ? : '';
	}

	public function log($msg, int $level = self::LOG_LEVEL_INFO, string $category = 'debug'):string
	{
		if($msg == 'AFTERREQUEST')
		{
			return (string) $this->save();
		}

		$str = $this->_preAct($msg, $level, $category, 'log');
		return $this->conArr[Coroutine::getCid()][] = $str;
	}

	public function console($msg, int $level = self::LOG_LEVEL_INFO, string $category = 'debug')
	{
		$str = $this->_preAct($msg, $level, $category, 'console');
		fwrite(STDOUT, Coroutine::getCid() . "\t$str\n");
	}

	// 保存日志
	public function save()
	{
		empty($this->logDir) && $this->logDir = config('LOG_DIR');
		$dir = $this->logDir . '/' . date('ym');
		is_dir($dir) or @ mkdir($dir);

		$file = "$dir/" . date('d') . '.log';

		file_put_contents($file, "\n" . implode("\n", $this->conArr[Coroutine::getCid()]) . "\n",FILE_APPEND|LOCK_EX);

		// 务必注销，防止内存积爆
		unset( $this->conArr[Coroutine::getCid()] );

		return true;
	}

	private function _preAct($msg, int $level = self::LOG_LEVEL_INFO, string $category = 'console', string $func = 'log')
	{
		if( ! is_scalar($msg))
		{
			$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
		}
		$date = date('Y-m-d H:i:s');
		$level = $this->levelMap($level);

		return "[$date][$category][$level]$msg";
	}

	private function levelMap(int $level)
	{
		switch ($level)
		{
			case self::LOG_LEVEL_INFO:
				return 'info';
			case self::LOG_LEVEL_NOTICE:
				return 'notice';
			case self::LOG_LEVEL_WARNING:
				return 'warning';
			case self::LOG_LEVEL_ERROR:
				return 'error';
			default:
				return 'unknown';
		}
	}
}
