<?php


namespace WonderGame\EsUtility\HttpController\Admin;

use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;

/**
 * Class Menu
 * @property \App\Model\Admin\Menu $Model
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

        $result = $this->Model->getTree($where);
        $this->success($result);
    }

    public function _add($return = false)
    {
        // 如果name不为空，检查唯一性
        $name = $this->post['name'] ?? '';
        if ( ! empty($name)) {
            $model = $this->Model->_clone();
            if ($model->where('name', $name)->count()) {
                return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_MENUTRAIT_1);
            }
        }
        return parent::_add($return);
    }

    /**
     * Client vue-router
     */
    public function _getMenuList($return = false)
    {
        $userMenus = $this->getUserMenus();
        if ( ! is_null($userMenus) && empty($userMenus)) {
            throw new HttpParamException(lang(Dictionary::PERMISSION_DENIED));
        }

        $where = ['type' => [[0, 1], 'in'], 'status' => 1];
        $options = ['isRouter' => true, 'filterIds' => $userMenus];
        $menu = $this->Model->getTree($where, $options);
        return $return ? $menu : $this->success($menu);
    }

    /**
     * 所有菜单树形结构
     * @return void
     */
    public function _treeList($return = false)
    {
        $treeData = $this->Model->getTree();
        return $return ? $treeData : $this->success($treeData);
    }
}
