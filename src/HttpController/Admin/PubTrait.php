<?php


namespace WonderGame\EsUtility\HttpController\Admin;

use WonderGame\EsUtility\Common\Classes\CtxRequest;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;

trait PubTrait
{
    protected function setBaseTraitProptected()
    {
        $this->modelName = 'Admin';
    }

    public function index()
    {
        return $this->login();
    }

    public function login()
    {
        $array = $this->post;
        if (!isset($array['username']))
        {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_PUBTRAIT_1);
        }

        // 查询记录
        $data = $this->Model->where('username', $array['username'])->get();

        if (empty($data) || !password_verify($array['password'], $data['password']))
        {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_PUBTRAIT_4);
        }

        $data = $data->toArray();

        // 被锁定
        if (empty($data['status']) && (!isSuper($data['rid'])))
        {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_PUBTRAIT_2);
        }

        $request = CtxRequest::getInstance()->request;
        $this->Model->signInLog([
            'uid' => $data['id'],
            'name' => $data['realname'] ?: $data['username'],
            'ip' => ip($request),
        ]);

        $token = get_login_token($data['id']);
        $this->success(['token' => $token], Dictionary::ADMIN_PUBTRAIT_3);
    }

    public function logout()
    {
        $this->success('success');
    }
}
