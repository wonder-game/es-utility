<?php

namespace WonderGame\EsUtility\Notify\Feishu\Message;

/**
 * 表分区不足提醒卡片
 */
class TablePartWarn extends Base
{
    protected $subTitle = '';

    /**
     * 哪些数据表分区不足
     * @var array
     */
    protected $dbTableList = [];

    protected $isAtAll = true;

    public function fullData()
    {
        $atAllText = [
            'tag' => 'div',
            'text' => [
                'tag' => 'lark_md',
                'content' => $this->getAtText(),
            ],
        ];
        $content = '';
        foreach ($this->dbTableList as $tableName) {
            $content .= "- $tableName \n";
        }
        $data = [
            'config' => [
                'update_multi' => true,
            ],
            'i18n_elements' => [
                'zh_cn' => [
                    [
                        'tag' => 'markdown',
                        'content' => $content,
                        'text_align' => 'left',
                        'text_size' => 'normal'
                    ],
                    ...($this->isAtAll ? [$atAllText] : [])
                ],
            ],
            'i18n_header' => [
                'zh_cn' => [
                    'title' => [
                        'tag' => 'plain_text',
                        'content' => $this->title
                    ],
                    'subtitle' => [
                        'tag' => 'plain_text',
                        'content' => $this->subTitle,
                    ],
                    'template' => 'red',
                ],
            ],
        ];

        return [
            'msg_type' => 'interactive',
            'card' => $data,
        ];
    }
}
