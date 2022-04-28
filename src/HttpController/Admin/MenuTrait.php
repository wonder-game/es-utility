<?php


namespace WonderGame\EsUtility\HttpController\Admin;


use WonderGame\EsUtility\Common\Classes\Tree;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;

/**
 * Class Menu
 * @property \App\Model\Menu $Model
 * @package App\HttpController\Admin
 */
trait MenuTrait
{
	public function index()
	{
		$input = $this->get;

		$where = [];
		if ( ! empty($input['title'])) {
			$where['title'] = ["%{$input['title']}%", 'like'];
		}
		if (isset($input['status']) && $input['status'] !== '') {
			$where['status'] = $input['status'];
		}

		$result = $this->Model->menuAll($where);
		$this->success($result);
	}

	public function add()
	{
		// 如果name不为空，检查唯一性
		$name = $this->post['name'] ?? '';
		if ( ! empty($name)) {
			/** @var \App\Model\Menu $model */
			$model = model('Menu');
			if ($model->where('name', $name)->count()) {
				return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_MENUTRAIT_1);
			}
		}
		parent::add();
	}

	/**
	 * Client vue-router
	 */
	public function getMenuList()
	{
		$userMenus = $this->getUserMenus();
		if ( ! is_null($userMenus) && empty($userMenus)) {
			return $this->error(Code::CODE_FORBIDDEN);
		}
		$menu = $this->Model->getRouter($userMenus);
		$this->success($menu);
	}

    /**
     * 所有菜单树形结构
     * @return void
     */
    public function treeList()
    {
        $Tree = new Tree();
        $treeData = $Tree->originData()->getTree();
        $this->success($treeData);
    }
}
