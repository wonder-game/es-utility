<?php

namespace WonderGame\EsUtility\Notify\Interfaces;

interface NotifyInterface
{
    public function does(MessageInterface $message);

    public function sendUser(MessageInterface $message, $union_id);
}
