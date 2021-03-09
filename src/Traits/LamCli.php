<?php
/**
 * 通用cli组件
 *
 * @author 林坤源
 * @version 1.0.4 最后修改时间 2020年11月26日
 */
namespace Linkunyuan\EsUtility\Traits;

use EasySwoole\EasySwoole\Logger;

trait LamCli
{
	public function __destruct()
	{
		// 保存日志
		Logger::getInstance()->log('AFTERREQUEST');

		// echo '+++++++++++++++++ __destruct ++++++++++++++++++';
	}

	// TODO crontab中调用model()后会重置set time_zone成最初值，原因未知
	// $tzn 一定要写  +8:00 或者 -5:00 的格式！！！
	public function setTimeZone($builder, $Dm, $tzn)
	{
		$builder->raw("set time_zone = '$tzn';");
		$Dm->query($builder);
	}
}
