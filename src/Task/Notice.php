<?php

namespace WonderGame\EsUtility\Task;

use EasySwoole\Task\AbstractInterface\TaskInterface;

/**
 * 异步发送告警，见 function.php 的 notice_async()
 */
class Notice implements TaskInterface
{
    protected $data = [];

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function run(int $taskId, int $workerIndex)
    {
        notice($this->data['content'] ?? '', $this->data['title'] ?? null, $this->data['name'] ?? 'default');
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        trace("告警发送失败：{$throwable->getMessage()}，原告警：" . ($this->data['content'] ?? ''), 'error');
    }
}
