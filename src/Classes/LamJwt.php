<?php
/**
 * jwt生成与解析
 *
 * @author 林坤源
 * @version 1.0.2 最后修改时间 2020年10月21日
 */

namespace Linkunyuan\EsUtility\Classes;

use EasySwoole\Jwt\Jwt;

class LamJwt
{
	/**
	 * 生成jwt
	 * @param array $data
	 * @param string $key
	 * @param int $expire
	 * @param array $extend
	 * @return string jwt值
	 */
	public static function getToken($data = [], $key = '', $expire = 86400, $extend = [])
	{
		$time = time();
		$uniqid = uniqid();

		is_array($extend) && extract($extend);

		$jwt = Jwt::getInstance()
			->setSecretKey($key) // 秘钥
			->publish();

		$jwt->setAlg('HMACSHA256'); // 加密方式
		$jwt->setAud($aud??''); // 用户(接收jwt的一方)
		$jwt->setExp($time + $expire); // 过期时间
		$jwt->setIat($time); // 发布时间
		$jwt->setIss($iss??''); // 发行人(jwt签发者)
		$jwt->setJti($uniqid); // jwt id 用于标识该jwt
		//$jwt->setNbf(time()+60*5); // 在此之前不可用
		$jwt->setSub($sub ?? ''); // 主题

		// 自定义数据
		$jwt->setData($data);

		// 最终生成的token
		$token = $jwt->__toString();
		return base64_encode($token);
	}

	/**
	 * 验证和解析jwt
	 * @param string $token
	 * @param string $key
	 * @param bool $only_data 是否只返回data字段的内容
	 * @return array status为1才代表成功
	 */
	public static function verifyToken($token = '', $key = '', $only_data = true)
	{
		$token = base64_decode($token);
		try {
			$jwt = Jwt::getInstance()->setSecretKey(config('ENCRYPT.key'))->decode($token);
			$status = $jwt->getStatus();

			switch ($status)
			{
				case  1:
					$data = [
						'aud' => $jwt->getAud(),
						'data' => $jwt->getData(),
						'exp' => $jwt->getExp(),
						'iat' => $jwt->getIat(),
						'iss' => $jwt->getIss(),
						'jti' => $jwt->getJti(),
						'sub' => $jwt->getSub()
					];
					$only_data && $data = $data['data'];
					break;
				case  -1:
					$data = '无效';
					break;
				case  -2:
					$data = 'token过期';
					break;
			}
		} catch (\EasySwoole\Jwt\Exception $e) {

		}

		return ['status'=> $status, 'data' => $data];
	}
}
