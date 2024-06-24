<?php

namespace WonderGame\EsUtility\Notify\Feishu\Message;

/**
 * Class CardError
 * @document https://open.feishu.cn/cardkit/editor?cardId=AAqHDSfEv3SKm&cardLocale=zh_cn
 * @package WonderGame\EsUtility\Notify\Feishu\Message
 */
class CardError extends Base
{
    /**
     * 标题栏颜色
     * @var string
     */
    protected $titleColor = 'red';

    /**
     * 主标题
     * @var string
     */
    protected $title = '程序异常';

    /**
     * 副标题，可不设置
     * @var string
     */
    protected $subTitle = '';

    /**
     * 运行的服务器
     * @var string
     */
    protected $servername = '';

    /**
     * 运行的项目
     * @var string
     */
    protected $project = '';

    /**
     * 发生的时间
     * @var string
     */
    protected $datetime = '';

    /**
     * 触发的方式  trigger|error
     * @var string
     */
    protected $trigger = '';

    /**
     * 文件
     * @var string
     */
    protected $filename = '';

    /**
     * 内容
     * @var string
     */
    protected $content = '';

    public function fullData()
    {
        $atAllText = [
            'tag' => 'div',
            'text' => [
                'tag' => 'lark_md',
                'content' => $this->getAtText(),
            ],
        ];
        $data = [
            "i18n_elements" => [
                "zh_cn" => [
                    [
                        "tag" => "column_set",
                        "flex_mode" => "none",
                        "background_style" => "default",
                        "columns" => [
                            [
                                "tag" => "column",
                                "width" => "weighted",
                                "vertical_align" => "top",
                                "elements" => [
                                    [
                                        "tag" => "markdown",
                                        "content" => "** 服务器：**\n{$this->servername}",
                                        "text_align" => "left",
                                        "text_size" => "normal"
                                    ]
                                ],
                                "weight" => 1
                            ],
                            [
                                "tag" => "column",
                                "width" => "weighted",
                                "vertical_align" => "top",
                                "elements" => [
                                    [
                                        "tag" => "markdown",
                                        "content" => "** 项目：**\n{$this->project}",
                                        "text_align" => "left",
                                        "text_size" => "normal"
                                    ]
                                ],
                                "weight" => 1
                            ]
                        ],
                        "margin" => "16px 0px 0px 0px"
                    ],
                    [
                        "tag" => "column_set",
                        "flex_mode" => "none",
                        "background_style" => "default",
                        "columns" => [
                            [
                                "tag" => "column",
                                "width" => "weighted",
                                "vertical_align" => "top",
                                "elements" => [
                                    [
                                        "tag" => "markdown",
                                        "content" => "**时间：**\n{$this->datetime}",
                                        "text_align" => "left",
                                        "text_size" => "normal"
                                    ]
                                ],
                                "weight" => 1
                            ],
                            [
                                "tag" => "column",
                                "width" => "weighted",
                                "vertical_align" => "top",
                                "elements" => [
                                    [
                                        "tag" => "markdown",
                                        "content" => "** 触发方式：**\n{$this->trigger}",
                                        "text_align" => "left",
                                        "text_size" => "normal"
                                    ]
                                ],
                                "weight" => 1
                            ]
                        ],
                        "margin" => "16px 0px 0px 0px"
                    ],
                    [
                        "tag" => "hr"
                    ],
                    [
                        "tag" => "column_set",
                        "flex_mode" => "none",
                        "background_style" => "default",
                        "columns" => [
                            [
                                "tag" => "column",
                                "width" => "weighted",
                                "vertical_align" => "top",
                                "elements" => [
                                    [
                                        "tag" => "column_set",
                                        "flex_mode" => "none",
                                        "horizontal_spacing" => "default",
                                        "background_style" => "default",
                                        "columns" => [
                                            [
                                                "tag" => "column",
                                                "elements" => [
                                                    [
                                                        "tag" => "div",
                                                        "text" => [
                                                            "tag" => "plain_text",
                                                            "content" => "文件: {$this->filename}",
                                                            "text_size" => "normal",
                                                            "text_align" => "left",
                                                            "text_color" => "default"
                                                        ],
                                                        "icon" => [
                                                            "tag" => "standard_icon",
                                                            "token" => "file-link-word_outlined",
                                                            "color" => "grey"
                                                        ]
                                                    ]
                                                ],
                                                "width" => "weighted",
                                                "weight" => 1
                                            ]
                                        ]
                                    ]
                                ],
                                "weight" => 1
                            ]
                        ],
                        "margin" => "16px 0px 0px 0px"
                    ],
                    [
                        "tag" => "column_set",
                        "flex_mode" => "none",
                        "horizontal_spacing" => "default",
                        "background_style" => "default",
                        "columns" => [
                            [
                                "tag" => "column",
                                "elements" => [
                                    [
                                        "tag" => "div",
                                        "text" => [
                                            "tag" => "plain_text",
                                            "content" => "详情: {$this->content}",
                                            "text_size" => "normal",
                                            "text_align" => "left",
                                            "text_color" => "default"
                                        ],
                                        "icon" => [
                                            "tag" => "standard_icon",
                                            "token" => "tab-more_outlined",
                                            "color" => "grey"
                                        ]
                                    ]
                                ],
                                "width" => "weighted",
                                "weight" => 1
                            ]
                        ]
                    ],
                    ...($this->isAtAll ? [$atAllText] : [])
                ]
            ],
            "i18n_header" => [
                "zh_cn" => [
                    "title" => [
                        "tag" => "plain_text",
                        "content" => $this->title
                    ],
                    "subtitle" => [
                        "tag" => "plain_text",
                        "content" => $this->subTitle
                    ],
                    "template" => $this->titleColor
                ]
            ]
        ];

        return [
            'msg_type' => 'interactive',
            'card' => $data,
        ];
    }
}
