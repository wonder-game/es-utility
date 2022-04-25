<?php

namespace WonderGame\EsUtility\Common\Http;

use EasySwoole\Http\Message\Status;

class Code extends Status
{
	// 基本错误类型
	const ERROR_OTHER = 1000;
	
	// 温柔刷新
	const VERSION_LATER = 1001;
	// 强制刷新
	const VERSION_FORCE = 1002;
}
