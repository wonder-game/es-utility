<?php

namespace WonderGame\EsUtility\Common\OrmCache;

/**
 * Hash缓存，适用于全表缓存
 */
trait Hash
{
    use Events;

    /**
     * 立即写入全部行
     * @var bool
     */
    protected $immediate = false;

    /**
     * hash key 字段
     * @var string
     */
    protected $hashKey = 'id';

    public function cacheGet()
    {

    }

    public function cacheSet()
    {

    }

    public function cacheDel()
    {

    }
}
