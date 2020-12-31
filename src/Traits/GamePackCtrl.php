<?php
/**
 * 游戏与包控制器组件
 *
 * @author 林坤源
 * @version 1.0.10 最后修改时间 2020年12月16日
 */
namespace Linkunyuan\EsUtility\Traits;

use EasySwoole\Mysqli\QueryBuilder;

trait GamePackCtrl
{
	public function __construct()
	{
		parent::__construct();
		if(empty($_POST['envkeydata']))
		{
			return $this->writeJson(422, [], 'errCode:422');
		}
	}

	public function index()
	{
		$this->response()->write('Sync Games');
	}

	public function create()
	{
		return $this->update('create');
	}

	public function update($action = 'update')
	{
		if(empty($_POST['envkeydata']))
		{
			return $this->writeJson(422, [], 'errCode:422');
		}

		$action .= 'BySync';

		$cls = explode('\\', __CLASS__);
		$cls = substr(strtolower(array_pop($cls)), 0, -1); // 去掉最后面的s
		
		$Model = model($cls);
		$Model->$action($_POST->toArray());
		if($err = $Model->getError())
		{
			trace($err['msg'], 'error', 'sync');
		}
		return $this->writeJson( $err ? $err['code'] : 200 , $err['msg'] ?? '' , $err ? '请求失败' : 'SUCCESS');
	}

	protected function actionNotFound(?string $action)
	{
		$this->response()->withStatus(404);
		$file = EASYSWOOLE_ROOT.'/vendor/easyswoole/easyswoole/src/Resource/Http/404.html';
		if(!is_file($file)){
			$file = EASYSWOOLE_ROOT.'/src/Resource/Http/404.html';
		}
		$this->response()->write(file_get_contents($file));
	}
}
