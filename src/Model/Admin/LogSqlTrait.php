<?php


namespace WonderGame\EsUtility\Model\Admin;


use EasySwoole\Mysqli\QueryBuilder;
use WonderGame\EsUtility\Common\Classes\CtxRequest;

trait LogSqlTrait
{
	protected function setBaseTraitProptected()
	{
		$this->autoTimeStamp = true;
	}

	/**
	 * 关联
	 * @return array|mixed|null
	 * @throws \Throwable
	 */
	public function relation()
	{
		$callback = function (QueryBuilder $query) {
			$query->fields(['id', 'username', 'realname', 'avatar', 'status']);
			return $query;
		};
		return $this->hasOne(find_model('Admin\Admin'), $callback, 'admid', 'id');
	}

	public function sqlWriteLog($sql = '')
	{
		$Ctx = CtxRequest::getInstance();
		$operinfo = $Ctx->getOperinfo();

		$data = [
			'admid' => $operinfo['id'] ?? 0,
			'content' => $sql,
			'ip' => ip($Ctx->request)
		];

		$this->data($data)->save();
	}
}
