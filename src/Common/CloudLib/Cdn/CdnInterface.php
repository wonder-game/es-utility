<?php

namespace WonderGame\EsUtility\Common\CloudLib\Cdn;

interface CdnInterface
{
    // 刷新
    public function reload(array $path = [], string $type = '', bool $force = false);

    // 预热
    public function push($path = [], $area = '');
}
