<?php

namespace WonderGame\EsUtility\Common\CloudLib\Email;

/**
 * 发送邮件
 */
interface EmailInterface
{
    /**
     * @param string|array $to 要发送的邮箱
     * @param array $params 模板参数
     * @param bool $ingo 是否在go函数中执行
     * @return mixed
     */
    function send($to = [], array $params = [], bool $ingo = false);
}
