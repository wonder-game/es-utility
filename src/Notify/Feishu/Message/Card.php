<?php

namespace WonderGame\EsUtility\Notify\Feishu\Message;

class Card extends Base
{
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
                            'content' => $this->inner ? $this->getServerText($this->content) : $this->content,
                        ],
                    ],
                    [
                        'tag' => 'action',
                        'actions' => [
                            [
                                'tag' => 'button',
                                'text' => [
                                    'tag' => 'lark_md',
                                    'content' => $this->inner ? $this->getServerText($this->content) : $this->content,
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
                        'content' => $this->title,
                    ],
                ],
            ],
        ];
        return $data;
    }
}
