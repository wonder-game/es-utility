<?php


namespace WonderGame\EsUtility\HttpController\Admin;

use WonderGame\EsUtility\Common\Classes\CtxRequest;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Languages\Dictionary;

trait PubTrait
{
    protected function setBaseTraitProptected()
    {
        $this->modelName = null;
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
            throw new HttpParamException(Dictionary::ADMIN_PUBTRAIT_1);
        }

        $model = model('Admin');
        // 查询记录
        $data = $model->where('username', $array['username'])->get();

        if ($data && password_verify($array['password'], $data['password']))
        {
            $data = $data->toArray();

            // 被锁定
            if (empty($data['status']) && (!isSuper($data['rid'])))
            {
                throw new HttpParamException(Dictionary::ADMIN_PUBTRAIT_2);
            }

            $request = CtxRequest::getInstance()->request;

            // 记录登录日志
            /** @var AdminLog $AdminLog */
            $AdminLog = model('AdminLog');
            $AdminLog->data([
                'uid' => $data['id'],
                'name' => $data['realname'] ?: $data['username'],
                'ip' => ip($request),
            ])->save();

            $token = get_login_token($data['id']);
            $this->success(['token' => $token], Dictionary::ADMIN_PUBTRAIT_3);
        } else {
            $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_PUBTRAIT_4);
        }
    }

    public function logout()
    {
        $this->success('success');
    }
}
