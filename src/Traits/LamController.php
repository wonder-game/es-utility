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
		// 则要求JWT要符合规则
		$data = verify_token($input, $header, 'operid');

		// 如果不是rsa加密数据并且非本地开发环境
		if(empty($input['envkeydata']) &&  ! empty($data['INVERTOKEN'])  &&  get_cfg_var('env.app_dev') != 2)
		{
			trace('密文有误:' . var_export($input, true), 'error', $category);
			return false;
		}
		
		unset($data['token']);

		return $data;
	}
}
