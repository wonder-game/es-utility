<?php

namespace WonderGame\EsUtility\Notify\WeChat\Message;

use EasySwoole\Spl\SplBean;
use WonderGame\EsUtility\Notify\Interfaces\MessageInterface;

/**
 * 每个微信模板结构都不同，此Message目录仅定义通用的几个，如果各项目需要增加模板，请继承此类
 */
abstract class Base extends SplBean implements MessageInterface
{
    protected $templateId = '';

    public function setTemplateId($templateId)
    {
        $this->templateId = $templateId;
    }

    public function getTemplateId()
    {
        return $this->templateId;
    }

    abstract public function struct();

    public function fullData()
    {
        return [$this->getTemplateId(), $this->struct()];
    }
}
