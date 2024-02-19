<?php

namespace WonderGame\EsUtility\Notify\Feishu\Message;

class Text extends Base
{
    protected $content = '';

    public function fullData()
    {
        return [
            'msg_type' => 'text',
            'content' => [
                'text' => $this->getAtText($this->getServerText($this->content)),
            ],
        ];
    }
}
