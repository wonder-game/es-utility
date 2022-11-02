<?php


namespace WonderGame\EsUtility\HttpController\Admin;

use EasySwoole\Component\Timer;
use EasySwoole\Utility\MimeType;
use WonderGame\EsUtility\Common\Classes\CtxRequest;
use WonderGame\EsUtility\Common\Classes\LamJwt;
use WonderGame\EsUtility\Common\Classes\XlsWriter;
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
        return $return ? $result + ['data'=>$data] : $this->success($result, Dictionary::ADMIN_PUBTRAIT_3);
	}

	public function logout()
	{
		$this->success('success');
	}

    // 动态创建Excel模板并返回文件流
    public function downExcelTpl()
    {
        $token = $this->getAuthorization();
        if (empty($token)) {
            $this->error(Code::ERROR_OTHER, '未登录');
            return;
        }
        $jwt = LamJwt::verifyToken($token, config('auth.jwtkey'));
        $id = $jwt['data']['id'] ?? '';
        if ($jwt['status'] != 1 || empty($id)) {
            $this->error(Code::ERROR_OTHER, 'token无效');
            return;
        }

        $colWidth = $this->get['col_width'] ?? 30;
        $rowHeight = $this->get['row_height'] ?? 15;
        $header = explode(',', $this->get[config('fetchSetting.exportThField')] ?? '');

        $XlsWriter = new XlsWriter();
        $fullFilePath = $XlsWriter->xlsxTemplate($header, $colWidth, $rowHeight);

        $this->response()->sendFile($fullFilePath);
        $this->response()->withHeader('Content-Type', MimeType::getMimeTypeByExt('xlsx'));
        $this->response()->withHeader('Cache-Control', 'max-age=0');
        $this->response()->end();

        Timer::getInstance()->after(1000, function () use ($fullFilePath) {
            @unlink($fullFilePath);
        });
    }
}
