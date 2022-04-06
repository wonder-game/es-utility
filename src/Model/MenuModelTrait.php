<?php

namespace WonderGame\EsUtility\Model;

use EasySwoole\ORM\AbstractModel;
use WonderGame\EsUtility\Common\Classes\Tree;

/**
 * @extends AbstractModel
 */
trait MenuModelTrait
{
    protected function setBaseTraitProptected()
    {
        $this->sort = ['sort' => 'asc', 'id' => 'desc'];
    }

    protected function setRedirectAttr($data, $alldata)
    {
        return $data ? '/' . ltrim($data, '/') : '';
    }

    /*protected function setComponentAttr($data, $alldata)
    {
        return ltrim($data, '/');
    }*/

    protected function setNameAttr($data, $alldata)
    {
        return ucfirst(ltrim($data, '/'));
    }

    public function getRouter($userMenus = [])
    {
        $tree = new Tree($userMenus);
        $where = [
            'type' => [[0, 1], 'in'],
            'status' => 1
        ];
        $router = $tree->originData($where)->getTree(0, true);
        return $router;
    }

    public function menuList($where = [])
    {
        $Tree = new Tree();
        $where['status'] = 1;
        $where['type'] = [[0, 1], 'in'];
        return  $Tree->originData($where)->getTree();
    }

    public function menuAll($where = [])
    {
        $Tree = new Tree();
        return $Tree->originData($where)->getAll();
    }

    /**
     * 角色组权限码
     * @param int $rid
     * @return array
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function permCode($rid):array
    {
        $where = ['permission' => ['', '<>']];

        if (!isSuper($rid))
        {
            /** @var Role $Role */
            $Role = model('Role');
            $menuIds = $Role->where('id', $rid)->val('menu');
            if (empty($menuIds))
            {
                return [];
            }

            $where['id'] = [explode(',', $menuIds), 'in'];
        }
        $permission = $this->where($where)->column('permission');
        return is_array($permission) ? $permission : [];
    }
}
