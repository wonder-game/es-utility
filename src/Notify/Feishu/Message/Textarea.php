<?php

namespace WonderGame\EsUtility\Notify\Feishu\Message;

class Textarea extends Base
{
    public function fullData()
    {
        $data = [
            'msg_type' => 'post',
            'content' => [
                'post' => [
                    'zh_cn' => [
                        'title' => $this->title,
                        'content' => [
                            [
                                [
                                    'tag' => 'text',
                                    'text' => $this->inner ? $this->getServerText($this->content) : $this->content,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $at = $this->getAtArray();
        $data['content']['post']['zh_cn']['content'][0]  = array_merge($data['content']['post']['zh_cn']['content'][0], $at);
        return $data;
    }
}
