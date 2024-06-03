<?php

namespace WonderGame\EsUtility\Notify\Feishu\Message;

class Images extends Base
{
    protected $content = '';

    public function fullData()
    {
        $data = [
            'msg_type' => 'image',
            'content' => [
                'image_key' => $this->getImageKey($this->content),
            ],
        ];
        return $data;
    }
}
