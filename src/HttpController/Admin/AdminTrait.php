<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use EasySwoole\ORM\AbstractModel;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Languages\Dictionary;

/**
 * @property \App\Model\Admin\Admin $Model
 */
trait AdminTrait
{
    protected function __search()
    {
        $where = [];

        empty($this->get['rid']) or $where['rid'] = $this->get['rid'];
        isset($this->get['status']) && is_numeric($this->get['status']) && $where['status'] = $this->get['status'];
        empty($this->get['name']) or $where['concat(username," ",realname)'] = ["%{$this->get['name']}%", 'like'];

        return $this->_search($where);
    }

    protected function __after_index($items, $total)
    {
        foreach ($items as &$value) {
            unset($value['password']);
            $value->relation;
        }
        return parent::__after_index($items, $total);
    }

    /**
     * @param false $return 是否返回数据，而不是输出
     * @param bool $gp game & package 是否查询游戏与包的数据
     * @return array
     */
    public function _getUserInfo($return = false, $gp = true)
    {
        $config = [
            // 充值枚举
            'pay' => config('pay')
        ];

        $config['sysinfo'] = sysinfo();

        // 客户端进入页,应存id
        if ( ! empty($this->operinfo['extension']['homePath'])) {
            /** @var AbstractModel $Menu */
            $Menu = model_admin('Menu');
            $homePage = $Menu->getHomePage($this->operinfo['extension']['homePath']);
        }
        $avatar = $this->operinfo['avatar'] ?? '';

        $result = [
            'id' => $this->operinfo['id'],
            'username' => $this->operinfo['username'],
            'realname' => $this->operinfo['realname'],
            'avatar' => $avatar,
            'desc' => $this->operinfo['desc'] ?? '',
            'homePath' => $homePage ?? '',
            'roles' => [
                [
                    'roleName' => $this->operinfo['role']['name'] ?? '',
                    'value' => $this->operinfo['role']['value'] ?? ''
                ]
            ]
        ];

        $result['config'] = $config;

        return $return ? $result : $this->success($result);
    }

    /**
     * 用户权限码
     */
    public function _getPermCode($return = false)
    {
        /** @var \App\Model\Admin\Menu $model */
        $model = model_admin('Menu');
        $code = $model->permCode($this->operinfo['rid']);
        return $return ? $code : $this->success($code);
    }

    public function edit()
    {
        if (empty($this->post['password'])) {
            unset($this->post['password']);
        }
        return $this->_edit();
    }

    public function _modify($return = false)
    {
        $userInfo = $this->operinfo;

        if ($this->isHttpGet()) {
            // role的关联数据也可以不用理会，ORM会处理
            unset($userInfo['password'], $userInfo['role']);
            // 默认首页treeSelect, 仅看有权限的菜单
            /** @var \App\Model\Admin\Menu $Menu */
            $Menu = model_admin('Menu');

            $menuList = $Menu->getTree(
                ['type' => [[0, 1], 'in'], 'status' => 1],
                ['filterIds' => $this->getUserMenus()]
            );
            $data = ['menuList' => $menuList, 'result' => $userInfo];
            return $return ? $data : $this->success($data);
        } elseif ($this->isHttpPost()) {
            $id = $this->post['id'];
            if (empty($id) || $userInfo['id'] != $id) {
                // 仅允许管理员编辑自己的信息
                throw new HttpParamException(lang(Dictionary::ADMIN_ADMINTRAIT_6));
            }

            if ($this->post['__password'] && ! password_verify($this->post['__password'], $userInfo['password'])) {
                throw new HttpParamException(lang(Dictionary::ADMIN_ADMINTRAIT_7));
            }

            if (empty($this->post['__password']) || empty($this->post['password'])) {
                unset($this->post['password']);
            }

            return $this->_edit($return);
        }
    }

    public function _getToken($return = false)
    {
        // 此接口比较重要，只允许超级管理员调用
        if ( ! $this->isSuper()) {
            throw new HttpParamException(lang(Dictionary::PERMISSION_DENIED));
        }
        if ( ! isset($this->get['id'])) {
            throw new HttpParamException(lang(Dictionary::ADMIN_ADMINTRAIT_8));
        }
        $id = $this->get['id'];
        $isExtsis = $this->Model->where(['id' => $id, 'status' => 1])->count();
        if ( ! $isExtsis) {
            throw new HttpParamException(lang(Dictionary::ADMIN_ADMINTRAIT_9));
        }
        $token = get_login_token($id, 3600);
        return $return ? $token : $this->success($token);
    }
}
