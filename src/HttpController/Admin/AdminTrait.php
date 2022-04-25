<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use WonderGame\EsUtility\Common\Classes\Tree;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;

trait AdminTrait
{
	protected function _search()
	{
		$where = [];
		if ( ! empty($this->get['rid'])) {
			$where['rid'] = $this->get['rid'];
		}
		foreach (['username', 'realname'] as $val) {
			if ( ! empty($this->get[$val])) {
				$where[$val] = ["%{$this->get[$val]}%", 'like'];
			}
		}
		return $where;
	}
	
	protected function _afterIndex($items, $total)
	{
		$Role = model('Role');
		$roleList = $Role->getRoleListAll();
		foreach ($items as &$value) {
			unset($value['password']);
			$value->relation;
		}
		return parent::_afterIndex(['items' => $items, 'roleList' => $roleList], $total);
	}
	
	public function getUserInfo()
	{
		$upload = config('UPLOAD');
		
		$config = [
			// 图片上传路径
			'imageDomain' => $upload['domain'],
			// 充值枚举
			'pay' => config('pay')
		];
		
		$config['sysinfo'] = sysinfo();
		
		// 客户端进入页,应存id
		if ( ! empty($this->operinfo['extension']['homePath'])) {
			$Tree = new Tree();
			$homePage = $Tree->originData(['type' => [[0, 1], 'in']])->getHomePath($this->operinfo['extension']['homePath']);
		}
		$avatar = $this->operinfo['avatar'] ?? '';
		if ($avatar) {
			$avatar = $config['imageDomain'] . $avatar;
		}
		
		$super = $this->isSuper();
		
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
		
		$gameids = $this->operinfo['extension']['gameids'] ?? [];
		is_string($gameids) && $gameids = explode(',', $gameids);
		
		// 默认选择游戏，管理员级别 > 系统级别
		if (isset($config['sysinfo']['default_select_gameid']) && $config['sysinfo']['default_select_gameid'] !== '') {
			// 权限
			if ($super || in_array($config['sysinfo']['default_select_gameid'], $gameids)) {
				$result['sleGid'] = $config['sysinfo']['default_select_gameid'];
			}
			// todo 设置多个，返回有权限的部分，前端如果是单选，要改为选中第一个
		}
		if (isset($this->operinfo['extension']['gid']) && $this->operinfo['extension']['gid'] !== '') {
			$result['sleGid'] = $this->operinfo['extension']['gid'];
		}
		
		// 游戏和包
		$Game = model('Game');
		$Package = model('Package');
		if ( ! $super) {
			$Game->where(['id' => [$gameids, 'in']]);
			
			$pkgbnd = $this->operinfo['extension']['pkgbnd'] ?? [];
			is_string($pkgbnd) && $pkgbnd = explode(',', $pkgbnd);
			$Package->where(['pkgbnd' => [$pkgbnd, 'in']]);
		}
		
		$result['gameList'] = $Game->where('status', 1)->setOrder()->field(['id', 'name'])->all();
		$result['pkgList'] = $Package->field(['gameid', 'pkgbnd', 'name', 'id'])->setOrder()->all();
		
		$result['config'] = $config;
		
		$this->success($result);
	}
	
	/**
	 * 用户权限码
	 */
	public function getPermCode()
	{
		$model = model('Menu');
		$code = $model->permCode($this->operinfo['rid']);
		$this->success($code);
	}
	
	protected function addGet()
	{
		$result = $this->_views();
		$this->success($result);
	}
	
	protected function _afterEditGet($items)
	{
		$result = $this->_views();
		
		unset($items['password'], $items['itime']);
		$result['result'] = $items;
		
		return $result;
	}
	
	protected function _views()
	{
	}
	
	protected function _writeBefore()
	{
		// 留空，不修改密码
		if (empty($this->post['password'])) {
			unset($this->post['password']);
		}
	}
	
	protected function editPost()
	{
		if ( ! $this->isSuper()) {
			$id = $this->post['id'];
			if (empty($id)) {
				$this->error(Code::ERROR_OTHER, Dictionary::ADMIN_ADMINTRAIT_3);
			}
			$origin = $this->Model->where('id', $id)->val('extension');
			
			/**
			 * 和数据库对比，如果原来已分配的包，当前操作用户没有这个包的权限，要追加进post
			 * @param $current 当前操作用户的gameids或pkgbnd
			 * @param $org 数据库原值，gameid或pkgbnd
			 * @param $post $this->post[xxx]
			 */
			$diffAuth = function ($current, $org, $post) {
				is_string($current) && $current = explode(',', $current);
				is_string($org) && $org = explode(',', $org);
				is_string($post) && $post = explode(',', $post);
				
				$result = [];
				foreach ($org as $value) {
					if ( ! in_array($value, $current)) {
						$result[] = $value;
					}
				}
				
				return array_unique(array_merge($post, $result));
			};
			
			// 包权限
			$this->post['extension']['pkgbnd'] = $diffAuth(
				$this->operinfo['extension']['pkgbnd'],
				$origin['pkgbnd'],
				$this->post['extension']['pkgbnd']
			);
			// 游戏权限
			$this->post['extension']['gameids'] = $diffAuth(
				$this->operinfo['extension']['gameids'],
				$origin['gameids'],
				$this->post['extension']['gameids']
			);
		}
		
		parent::editPost();
	}
	
	public function modify()
	{
		$userInfo = $this->operinfo;
		
		if ($this->isMethod('get')) {
			// role的关联数据也可以不用理会，ORM会处理
			unset($userInfo['password'], $userInfo['role']);
			// 默认首页treeSelect
			$Menu = model('Menu');
			$menuList = $Menu->menuList();
			$this->success(['menuList' => $menuList, 'result' => $userInfo]);
		} elseif ($this->isMethod('post')) {
			$id = $this->post['id'];
			if (empty($id) || $userInfo['id'] != $id) {
				// 仅允许管理员编辑自己的信息
				return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_ADMINTRAIT_6);
			}
			
			if ($this->post['__password'] && ! password_verify($this->post['__password'], $userInfo['password'])) {
				return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_ADMINTRAIT_7);
			}
			
			parent::editPost();
		}
	}
	
	public function getToken()
	{
		// 此接口比较重要，只允许超级管理员调用
		if ( ! $this->isSuper()) {
			return $this->error(Code::CODE_FORBIDDEN);
		}
		if ( ! isset($this->get['id'])) {
			return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_ADMINTRAIT_8);
		}
		$id = $this->get['id'];
		$isExtsis = $this->Model->where(['id' => $id, 'status' => 1])->count();
		if ( ! $isExtsis) {
			return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_ADMINTRAIT_9);
		}
		$token = get_login_token($id, 3600);
		$this->success($token);
	}
}
