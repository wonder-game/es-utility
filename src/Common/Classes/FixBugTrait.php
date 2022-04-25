<?php

namespace WonderGame\EsUtility\Common\Classes;

use App\Model\Package;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\HttpClient\Bean\Response;
use EasySwoole\HttpClient\HttpClient;

/**
 * @extends Controller
 */
trait FixBugTrait
{
	protected function fixProxyUrl()
	{
		$servername = config('SERVNAME');;
		$pkgbnd = 'com.wonderland.bombtankad';
		$gameid = 5;
		$input = $this->request()->getRequestParam();
//        trace("参数debug1： " . json_encode($input, JSON_UNESCAPED_UNICODE));
//        trace("参数debug2： " . $servername . ', ' . $pkgbnd . ', ' . $gameid . ', ' . explode('-', $servername)[0]);
		if (explode('-', $servername)[0] !== 'xjp'
			&& (isset($input['versioncode']) && intval($input['versioncode']) <= 6)
			&& (isset($input['pkgbnd']) && $input['pkgbnd'] === $pkgbnd)
			&& (isset($input['gameid']) && $input['gameid'] == $gameid)
		) {
			return $this->proxyServer($pkgbnd);
		}
		return true;
	}
	
	protected function proxyServer($pkgbnd)
	{
		$aname = explode('_', APP_NAME)[1];
		/** @var Package $Package */
		$Package = model('Package');
		if ($domain = $Package->cacheInfo(['pkgbnd' => $pkgbnd])['domain'][$aname]) {
			$Uri = $this->request()->getUri();
			$url = rtrim($domain, '/') . $Uri->getPath();
			if ($Uri->getQuery()) {
				$url .= '?' . $Uri->getQuery();
			}
			
			$headers = $this->request()->getHeaders();
			$headerArray = [];
			foreach ($headers as $hk => $hd) {
				if (is_array($hd)) {
					$hd = current($hd);
				}
				$headerArray[$hk] = $hd;
			}
			unset($headerArray['host']);
//            trace(json_encode($headerArray));
//            trace("开始转发： " . $url);
			$HttpClient = new HttpClient($url);
			if (stripos($url, 'https://') === 0) {
				$HttpClient->setEnableSSL();
			}
			$HttpClient->setHeaders($headerArray, false, false);
			$method = strtolower($this->request()->getMethod());
			
			/** @var Response $response */
			$response = null;
			// 不需要body的方法
			if (in_array($method, ['get', 'head', 'trace', 'delete'])) {
				$response = $HttpClient->$method();
			} elseif (in_array($method, ['post', 'patch', 'put', 'download'])) {
				$envKey = 'envkeydata';
				$input = $this->request()->getRequestParam();
				unset($input[$envKey]);
				$rsa = config('RSA');
				$openssl = LamOpenssl::getInstance($rsa['private'], $rsa['public']);
				$post = [$envKey => $openssl->encrypt(json_encode($input))];
				$response = $HttpClient->$method($post);
			}

//            trace('转发结束: ' . $response->getStatusCode() . ', body=' . $response->getBody());
			$array = json_decode($response->getBody(), true);
//            trace("转发完成： " . json_encode($array,  JSON_UNESCAPED_UNICODE));
			$this->writeJson($array['code'], $array['result'] ?? [], $array['message'] ?? ($array['msg'] ?? ''));
		}
		return false;
	}
}
