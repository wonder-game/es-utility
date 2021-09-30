<?php


namespace Linkunyuan\EsUtility\Classes;

use EasySwoole\Component\Singleton;
use RuntimeException;
use EasySwoole\WeChat\OfficialAccount\Application as OfficialAccount;
use EasySwoole\WeChat\Factory;

/**
 * Class WeChatManager
 * @author Joyboo
 * @date 2021-03-24
 */
class WeChatManager
{
    use Singleton;

    /**
     * @var array 存储全部WeChat对象
     */
    protected $weChatList = [];

    /**
     * 创建WeChat实例
     * @param string $name  实例名称
     */
    public function register(string $name = 'default', array $config = []): void
    {
        if (isset($this->weChatList[$name])) {
            throw new RuntimeException('重复注册weChat.');
        }
        $config = array_merge_multi(config('wechat'), $config);
        $officialAccount = Factory::officialAccount($config['config']);

        $this->weChatList[$name] = $officialAccount;
    }

    /**
     * 获取WeChat实例
     * @param string $name 实例名称-传入该参数获取对应实例
     *
     * @return WeChat 返回WeChat实例对象
     */
    public function weChat(string $name = 'default'): OfficialAccount
    {
        if (isset($this->weChatList[$name])) {
            return $this->weChatList[$name];
        }

        throw new RuntimeException('not found weChat name');
    }

    /**
     * 推送模板消息
     * @param array $data
     * @param string $openid
     * @param string $tmpId
     */
    public function sendTemplateMessage($data = [], $openid = '', $tmpId = '')
    {
        if (empty($openid)) {
            $openid = config('wechat.touser');
        }
        if (empty($tmpId)) {
            $tmpId = config('wechat.templateId');
        }
        if (is_string($openid))
        {
            $openid = explode(',', $openid);
        }

        $OfficialAccount = $this->weChat();

        foreach ($openid as $id)
        {
            try {
                $OfficialAccount->templateMessage->send([
                    'touser' => $id,
                    'template_id' => $tmpId,
                    'url' => config('wechat.url'),
                    'data' => $data,
                ]);
            }
            // 未关注、取消关注 或 其他
            catch (\Throwable | \Exception $e)
            {
                continue;
            }
        }
    }
}
