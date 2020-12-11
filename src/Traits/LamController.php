<?php
/**
 * 通用控制器组件
 *
 * @author 林坤源
 * @version 1.0.0 最后修改时间 2020年08月14日
 */
namespace Linkunyuan\EsUtility\Traits;

trait LamController
{
	protected function _isRsa($input = [], $header = [], $category = 'pay')
	{
		if(empty($input['envkeydata']) )
		{
			$data = verify_token($input, $header, 'operid');
			if( ! empty($data['INVERTOKEN']))
			{
				trace('密文有误:' . var_export($input, true), 'error', $category);
				return false;
			}
		}
		return true;
	}
}
