<?php


namespace Linkunyuan\EsUtility\Classes;

use EasySwoole\Component\Singleton;
use EasySwoole\WeChat\WeChat;
use RuntimeException;
use EasySwoole\WeChat\Bean\OfficialAccount\TemplateMsg;

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
     * 注册WeChat实例
     * @param string $name  实例名称
     * @param WeChat $weChat WeChat实例对象
     */
    public function register(string $name, WeChat $weChat): void
    {
        if (isset($this->weChatList[$name])) {
            throw new RuntimeException('重复注册weChat.');
        }
        $this->weChatList[$name] = $weChat;
    }

    /**
     * 获取WeChat实例
     * @param string $name 实例名称-传入该参数获取对应实例
     *
     * @return WeChat 返回WeChat实例对象
     */
    public function weChat(string $name = 'default'): WeChat
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
            $openid = [$openid];
        }

        $templateMsg = new TemplateMsg();
        $templateMsg->setUrl(config('wechat.url'));
        $templateMsg->setTemplateId($tmpId);
        $templateMsg->setData($data);

        $wechat = $this->weChat();
        if (! $wechat->officialAccount()->accessToken()->getToken()) {
            $wechat->officialAccount()->accessToken()->refresh();
        }

        foreach ($openid as $id)
        {
            try {
                $templateMsg->setTouser($id);
                $wechat->officialAccount()->templateMsg()->send($templateMsg);
            }
            // 未关注、取消关注 或 其他
            catch (\EasySwoole\WeChat\Exception\OfficialAccountError | \Exception $e)
            {
                continue;
            }
        }
    }
}
