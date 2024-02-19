<?php

namespace WonderGame\EsUtility\Notify\WeChat\Message;

class Warning extends Base
{
    protected $content = '';

    public function struct()
    {
        return [
            'keyword1' => ['value' => $this->content],
            'keyword2' => ['value' => APP_MODULE],
            'keyword3' => ['value' => config('SERVNAME')],
            'keyword4' => ['value' => date('Y年m月d日 H:i:s')],
        ];
    }
}
