<?php

namespace WonderGame\EsUtility\Notify\Feishu\Message;

class Textarea extends Base
{
    protected $content = '';

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
