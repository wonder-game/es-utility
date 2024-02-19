<?php

namespace WonderGame\EsUtility\Notify\DingTalk\Message;

class Markdown extends Base
{
    protected $title = '';

    protected $text = '';

    public function fullData()
    {
        return [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $this->title,
                'text' => $this->getAtText($this->text)
            ],
            'at' => [
                'atMobiles' => $this->atMobiles,
                'atUserIds' => $this->atUserIds,
                'isAtAll' => $this->isAtAll
            ]
        ];
    }
}
