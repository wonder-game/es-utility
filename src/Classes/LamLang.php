<?php

namespace Linkunyuan\EsUtility\Classes;

use EasySwoole\I18N\AbstractDictionary;
use EasySwoole\I18N\I18N;

class LamLang {}


// 定义一个词典。const值请务必于const变量名一致，这样是避免用户手敲词条名称出错
class Dictionary extends AbstractDictionary
{
	const CANT_FIND_PKG = 'CANT_FIND_PKG';
	const MISS_KEY_PARA = 'MISS_KEY_PARA';
	const SUCCESS = 'SUCCESS';
	const FAIL = 'FAIL';
	const VENDOR_TYPE_ERR = 'VENDOR_TYPE_ERR';
	const VENDOR_NOT_BINDED = 'VENDOR_NOT_BINDED';
	const VENDOR_HAD_BINDED = 'VENDOR_HAD_BINDED';
	const LOSE_TOKEN = 'LOSE_TOKEN';
	const JWT_ERR = 'JWT_ERR';
	const CANT_FIND_USER = 'CANT_FIND_USER';
	const HUAWEI_API_FAIL = 'HUAWEI_API_FAIL';
	const HUAWEI_RES_FAIL = 'HUAWEI_RES_FAIL';
	const CIPHER_ERR = 'CIPHER_ERR';
	const PROTOCAL_ERR = 'PROTOCAL_ERR';
	const CANCEL_CLOSE = 'CANCEL_CLOSE';
	const PAYSUCCESS = 'PAYSUCCESS';
	const PREPARING = 'PREPARING';
}

// 简体中文包
class Chinese extends Dictionary{
	const CANT_FIND_PKG = '找不到包信息';
	const MISS_KEY_PARA = '缺少关键参数';
	const SUCCESS = '请求成功';
	const FAIL = '请求失败';
	const VENDOR_TYPE_ERR = '第三方类型有误';
	const VENDOR_NOT_BINDED = '还没有绑定账号，无法登录';
	const VENDOR_HAD_BINDED = '此账号已被绑定';
	const LOSE_TOKEN = '缺少token';
	const JWT_ERR = 'jwt有误';
	const CANT_FIND_USER = '找不到该用户';
	const HUAWEI_API_FAIL = '请求华为接口失败';
	const HUAWEI_RES_FAIL = '华为返回数据有误';
	const CIPHER_ERR = '密文有误';
	const PROTOCAL_ERR = '协议有误';
	const CANCEL_CLOSE = '您已取消支付，请关闭网页';
	const PAYSUCCESS = '支付成功';
	const PREPARING = '支付中';
}

// 繁体中文包
class Traditional extends Dictionary
{
	const CANT_FIND_PKG = '找不到包資訊';
	const MISS_KEY_PARA = '缺少關鍵參數';
	const SUCCESS = '請求成功';
	const FAIL = '請求失敗';
	const VENDOR_TYPE_ERR = '協力廠商類型有誤';
	const VENDOR_NOT_BINDED = '還沒有綁定帳號，無法登入';
	const VENDOR_HAD_BINDED = '此帳號已被綁定';
	const LOSE_TOKEN = '缺少token';
	const JWT_ERR = 'jwt有誤';
	const CANT_FIND_USER = '找不到該用戶';
	const HUAWEI_API_FAIL = '請求華為介面失敗';
	const HUAWEI_RES_FAIL = '華為返回數據有誤';
	const CIPHER_ERR = '密文有誤';
	const PROTOCAL_ERR = '協定有誤';
	const CANCEL_CLOSE = '您已取消支付，請關閉網頁';
	const PAYSUCCESS = '支付成功';
	const PREPARING = '支付中';
}

// 英文包
class English extends Dictionary
{
	const CANT_FIND_PKG = 'Can\'t find package';
	const MISS_KEY_PARA = 'Missing key parameter';
	const SUCCESS = 'Success';
	const FAIL = 'Fail';
	const VENDOR_TYPE_ERR = 'Vendor type error';
	const VENDOR_NOT_BINDED = 'Vendor not binded';
	const VENDOR_HAD_BINDED = 'Vendor had binded';
	const LOSE_TOKEN = 'Lose token';
	const JWT_ERR = 'Jwt error';
	const CANT_FIND_USER = 'Can\'t find user';
	const HUAWEI_API_FAIL = 'Huawei api fail';
	const HUAWEI_RES_FAIL = 'Huawei response fail';
	const CIPHER_ERR = 'Ciphertext error';
	const PROTOCAL_ERR = 'Protocal error';
	const CANCEL_CLOSE = 'You have cancelled the payment, please close the page';
	const PAYSUCCESS = 'Payment successful';
	const PREPARING = 'Payment preparing';
}


// 注册语言包
I18N::getInstance()->addLanguage(new Chinese(),'Cn');
I18N::getInstance()->addLanguage(new Traditional(),'Tw');
I18N::getInstance()->addLanguage(new English(),'En');

//设置默认语言包
I18N::getInstance()->setDefaultLanguage(get_cfg_var('env.language') ?: 'Cn');
