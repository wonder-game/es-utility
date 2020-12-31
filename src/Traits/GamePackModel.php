<?php
/**
 * 游戏与包模型组件
 *
 * @author 林坤源
 * @version 1.0.10 最后修改时间 2020年12月16日
 */
namespace Linkunyuan\EsUtility\Traits;

use EasySwoole\Mysqli\QueryBuilder;

trait GamePackModel
{
	// 是否有game表和package表
	public $hasGamePackTab = true;

	public function createBySync($orgs = [])
	{
		$data = $this->updateBySync($orgs);
		return $data;
	}

	public function updateBySync($orgs = [])
	{
		if($this->hasGamePackTab)
		{
			// 只保留数据表字段
			$data = array_intersect_key($orgs, $columns = $this->schemaInfo()->getColumns());

			is_array($data['extension']) or $data['extension'] = json_decode($data['extension'], true);
			$data['extension'] or $data['extension'] = [];

			// 其它加入extension
			foreach ($orgs as $k => $v)
			{
				if ( ! isset($columns[$k]) && ! in_array($k, ['ip', 'envkeydata', 'instime', 'updtime']))
				{
					$data['extension'][$k] = $v;
				}
			}

			try{
				$this->data($data)->replace(['id'=>$data['id']]);
			}catch (\Exception $e)
			{
				$this->_error = ['code'=>503, 'msg'=>$e->getMessage()];
				return [];
			}
		}

		// 只有game操作时才需要创建分表
		$cls = explode('\\', __CLASS__);
		$cls = strtolower(array_pop($cls));
		if($cls == 'game')
		{
			// 创建xxxx_GAMEID表(为了解决insert时添加数据表失败的情况，这里update时也尝试着建表)
			foreach (config('SYNC.crtab') as $tab)
			{
				$this->func(function (QueryBuilder $builder) use ($orgs, $tab) {
					$builder->raw("CREATE TABLE  IF NOT EXISTS `{$tab}_$orgs[id]` LIKE `{$tab}_0`;");
					return true;
				});
			}
		}
		return $data??true;
	}
}
