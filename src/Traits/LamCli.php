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
}
