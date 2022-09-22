<?php

namespace WonderGame\EsUtility\Crontab\Drive;

interface Interfaces
{
    // 获取列表
    public function list(): array;

    // 修改状态
    public function update(int $id, int $status);
}
