<?php

namespace WonderGame\EsUtility\WebSocket\Controller;

use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\Socket\Client\WebSocket;
use WonderGame\EsUtility\Common\Classes\CtxRequest;

/**
 * @extends Controller
 */
trait BaseControllerTrait
{
    protected function onRequest(?string $actionName): bool
    {
        CtxRequest::getInstance()->caller = $this->caller();
        return parent::onRequest($actionName);
    }

    protected function onException(\Throwable $throwable): void
    {
        \EasySwoole\EasySwoole\Trigger::getInstance()->throwable($throwable);
    }

    protected function fmtMessage($event, $data = [], $msg = '')
    {
        return json_encode(['event' => $event, 'data' => $data, 'msg' => $msg]);
    }

    protected function responseMessage($event, $data = [], $msg = '')
    {
        return $this->response()->setMessage($this->fmtMessage($event, $data, $msg));
    }

    protected function halfResponseMessage($event, $data = [], $msg = '')
    {
        $messageContent = $this->fmtMessage($event, $data, $msg);
        $Server = ServerManager::getInstance()->getSwooleServer();

        /** @var WebSocket $client */
        $client = $this->caller()->getClient();
        $fd = $client->getFd();
        if ($Server->isEstablished($fd)) {
            $Server->push($fd, $messageContent);
        } else {
            trace(" halfResponseMessage isEstablished为false， fd={$fd}, data={$messageContent}", 'error');
        }
    }
}
