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
	// 普通日志内容数组
	private $conArr = [];
	// 独立level日志
	private $levelArr = [];
	// 独立category日志
	private $catArr = [];

	public function __construct(string $logDir = null)
	{
		$this->logDir = $logDir ? : '';
	}

	public function log($msg, int $level = self::LOG_LEVEL_INFO, string $category = 'debug'):string
	{
		$cid = Coroutine::getCid();

		if($msg == 'AFTERREQUEST')
		{
			return (string) $this->save();
		}

		$str = $this->_preAct($msg, $level, $category, 'log');

		return $str;
	}

	public function console($msg, int $level = self::LOG_LEVEL_INFO, string $category = 'debug')
	{
		$str = $this->_preAct($msg, $level, $category, 'console');
		fwrite(STDOUT, Coroutine::getCid() . "\t$str\n");
	}

	// 保存日志
	public function save()
	{
		$cid = Coroutine::getCid();
		empty($this->logDir) && $this->logDir = config('LOG_DIR');
		$dir = $this->logDir . '/' . date('ym');
		is_dir($dir) or @ mkdir($dir);

		// 保存普通日志
		! empty($this->conArr[$cid]) && file_put_contents("$dir/" . date('d') . '.log', "\n" . implode("\n", $this->conArr[$cid]) . "\n",FILE_APPEND|LOCK_EX);

		// 保存独立文件的level日志
		if( ! empty($this->levelArr[$cid]))
		{
			foreach($this->levelArr[$cid] as $l => $a)
			{
				file_put_contents("$dir/" . date('d') . "-$l.log", "\n" . implode("\n", $a) . "\n", FILE_APPEND|LOCK_EX);
			}
		}

		// 保存独立文件的category日志
		if( ! empty($this->catArr[$cid]))
		{
			foreach($this->catArr[$cid] as $l => $a)
			{
				file_put_contents("$dir/" . date('d') . "-$l.log", "\n" . implode("\n", $a) . "\n", FILE_APPEND|LOCK_EX);
			}
		}


		// 务必注销，防止内存积爆
		unset( $this->conArr[$cid] );

		return true;
	}

	private function _preAct($msg, int & $level = self::LOG_LEVEL_INFO, string $category = 'console', string $func = 'log')
	{
		$cid = Coroutine::getCid();
		
		if( ! is_scalar($msg))
		{
			$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
		}
		$msg = str_replace(["\n","\r"], '', $msg);
		$date = date('Y-m-d H:i:s');
		$level = $this->levelMap($level);

		$str = "[$date][$category][$level]$msg";

		if($func == 'log')
		{
			// 独立文件的level日志
			if(in_array($level, config('LOG.apart_level')))
			{
				$this->levelArr[$cid][$level][] = "[$date][$category]$msg";
			}
			// 独立文件的category日志
			elseif(in_array($category, config('LOG.apart_category')))
			{
				$this->catArr[$cid][$category][] = "[$date][$level]$msg";
			}
			// 普通日志
			else
			{
				$this->conArr[$cid][] = $str;
			}
		}

		return $str;
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
