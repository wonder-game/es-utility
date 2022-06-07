<?php


namespace WonderGame\EsUtility\HttpController\Admin;

use WonderGame\EsUtility\Common\Classes\CtxRequest;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;

/**
 * @property \App\Model\Admin\Admin $Model
 */
trait PubTrait
{
    protected function instanceModel()
    {
        $this->Model = model_admin('Admin');
        return true;
    }


    public function index()
	{
		return $this->_login();
	}

    public function _login($return = false)
	{
		$array = $this->post;
		if ( ! isset($array['username'])) {
			throw new HttpParamException(lang(Dictionary::ADMIN_PUBTRAIT_1));
		}

		// 查询记录
		$data = $this->Model->where('username', $array['username'])->get();

		if (empty($data) || ! password_verify($array['password'], $data['password'])) {
			throw new HttpParamException(lang(Dictionary::ADMIN_PUBTRAIT_4));
		}

		$data = $data->toArray();

		// 被锁定
		if (empty($data['status']) && ( ! is_super($data['rid']))) {
			throw new HttpParamException(lang(Dictionary::ADMIN_PUBTRAIT_2));
		}

		$request = CtxRequest::getInstance()->request;
		$this->Model->signInLog([
			'uid' => $data['id'],
			'name' => $data['realname'] ?: $data['username'],
			'ip' => ip($request),
		]);

		$token = get_login_token($data['id']);
        $result = ['token' => $token];
		return $return ? $result : $this->success($result, Dictionary::ADMIN_PUBTRAIT_3);
	}

	public function logout()
	{
		$this->success('success');
	}
}
