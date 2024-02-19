<?php

namespace WonderGame\EsUtility\Feishu\Message;

class Textarea extends Base
{
    protected $content = '';

    // https://open.feishu.cn/document/client-docs/bot-v3/add-custom-bot#%E6%94%AF%E6%8C%81%E5%8F%91%E9%80%81%E7%9A%84%E6%B6%88%E6%81%AF%E7%B1%BB%E5%9E%8B%E8%AF%B4%E6%98%8E
    public function fullData()
    {
        $data = [
            'msg_type' => 'post',
            'content' => [
                'post' => [
                    'zh_cn' => [
                        'tittle' => '程序异常',
                        'content' => [
                            [
                                [
                                    'tag' => 'text',
                                    'text' => $this->getServerText($this->content),
                                ],
                                [
                                    'tag' => 'at',
                                    'user_id' => 'all',
                                    'user_name' => '所有人',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        // $at = $this->getAtArray();
        // $data['content']['post']['zh_cn']['content'][0]  = array_merge($at);
        return $data;
    }
}
