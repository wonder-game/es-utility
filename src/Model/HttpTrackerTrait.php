<?php

namespace WonderGame\EsUtility\Model;

use EasySwoole\HttpClient\Bean\Response;
use EasySwoole\HttpClient\HttpClient;
use WonderGame\EsUtility\Common\Classes\LamOpenssl;

trait HttpTrackerTrait
{
	protected function setBaseTraitProptected()
	{
		$this->connectionName = 'log';
		$this->sort = ['instime' => 'desc'];
	}
	
	// starttime和endtime是小数点后有四位的时间戳字符串,转为int毫秒时间戳
	protected function _trackerTime($timeStamp)
	{
		return $timeStamp ? intval($timeStamp * 1000) : $timeStamp;
	}
	
	protected function getStartTimeAttr($val)
	{
		return $this->_trackerTime($val);
	}
	
	protected function getEndTimeAttr($val)
	{
		return $this->_trackerTime($val);
	}
	
	protected function getUrlAttr($val)
	{
		return urldecode($val);
	}
	
	protected function getRequestAttr($val)
	{
		$arr = [];
		if ($json = json_decode($val, true)) {
			$arr = $json;
		}
		return arrayToStd($arr);
	}
	
	protected function getResponseAttr($val)
	{
		$json = json_decode($val, true);
		$arr = is_array($json) ? $json : [];
		return arrayToStd($arr);
	}
	
	/**
	 * @return Response|null
	 * @throws \EasySwoole\HttpClient\Exception\InvalidUrl
	 */
	public function repeatOne(): ?Response
	{
		$data = $this->toRawArray();
//        var_dump($data['point_id'] . '++++++++++++++ repeatOne ');
		$url = $data['url'];
		$request = json_decode($data['request'], true);
		
		$HttpClient = new HttpClient($url);
		if (stripos($url, 'https://') === 0) {
			$HttpClient->setEnableSSL();
		}
		// 禁止重定向
		$HttpClient->setFollowLocation(0);
		
		// 没有header或者method说明是以前的结构，暂不处理
		if ( ! isset($request['header']) || ! isset($request['method'])) {
			return null;
		}
		$headers = [];
		foreach ($request['header'] as $hk => $hd) {
			if (is_array($hd)) {
				$hd = current($hd);
			}
			$headers[$hk] = $hd;
		}
		
		// UserAgent区分复发请求
		$headers['user-agent'] = ($headers['user-agent'] ?? '') . ';HttpTracker';
		
		$HttpClient->setHeaders($headers, true, false);
		
		$method = strtolower($request['method']);
		
		/** @var Response $response */
		$response = null;
		// 不需要body的方法
		if (in_array($method, ['get', 'head', 'trace', 'delete'])) {
			$response = $HttpClient->$method();
		} elseif (in_array($method, ['post', 'patch', 'put', 'download'])) {
			$rsa = config('RSA');
			$openssl = LamOpenssl::getInstance($rsa['private'], $rsa['public']);
			$post = [$rsa['key'] => $openssl->encrypt(json_encode($request['POST']))]; // 可能不一定为POST
			$response = $HttpClient->$method($post);
		}
		
		if ($response) {
			$body = $response->getBody();
			$json = json_decode($body, true);
			if ($response->getStatusCode() !== 200 || $json['code'] !== 200) {
				trace("复发请求失败，返回BODY: {$body}，参数为：" . json_encode($data, JSON_UNESCAPED_UNICODE));
			}
		}
		
		return $response;
	}
}
