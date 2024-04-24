<?php

namespace WonderGame\EsUtility\Common\CloudLib\Email;

/**
 * 发送邮件
 */
interface EmailInterface
{
    /**
     * @param array $to 要发送的邮箱
     * @param array $params 模板参数
     * @return mixed
     */
    function send($to = [], array $params = []);
}
