<?php

namespace WonderGame\EsUtility\Notify\Feishu\Message;

class Card extends Base
{
    protected $content = '';

    public function fullData()
    {
        $data = [
            'msg_type' => 'interactive',
            'card' => [
                'elements' => [
                    [
                        'tag' => 'div',
                        'text' => [
                            'tag' => 'lark_md',
                            'content' => $this->getServerText($this->content),
                        ],
                    ],
                    [
                        'tag' => 'action',
                        'actions' => [
                            [
                                'tag' => 'button',
                                'text' => [
                                    'tag' => 'lark_md',
                                    'content' => $this->getServerText($this->content),
                                ],
                                'url' => 'https://www.baidu.com',
                                'type' => 'default',
                                'value' => [],
                            ],
                        ],
                    ],
                    [
                        'tag' => 'div',
                        'text' => [
                            'tag' => 'lark_md',
                            'content' => $this->getAtText(),
                        ],
                    ],
                ],
                'header' => [
                    'title' => [
                        'tag' => 'plain_text',
                        'content' => '程序异常',
                    ],
                ],
            ],
        ];
        return $data;
    }
}
