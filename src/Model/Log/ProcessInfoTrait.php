<?php

namespace WonderGame\EsUtility\Model\Log;

trait ProcessInfoTrait
{
    protected function _attrEncode($data)
    {
        return is_array($data) ? json_encode(array_to_std($data)) : $data;
    }

    protected function _attrDecode($data)
    {
        return is_string($data) ? json_decode($data, true) : $data;
    }

    protected function setProcessAttr($data)
    {
        return $this->_attrEncode($data);
    }

    protected function setCoroutineAttr($data)
    {
        return $this->_attrEncode($data);
    }

    protected function setCoroutineListAttr($data)
    {
        return $this->_attrEncode($data);
    }

    protected function setMysqlPoolAttr($data)
    {
        return $this->_attrEncode($data);
    }

    protected function setRedisPoolAttr($data)
    {
        return $this->_attrEncode($data);
    }

    protected function getProcessAttr($data)
    {
        return $this->_attrDecode($data);
    }

    protected function getCoroutineAttr($data)
    {
        return $this->_attrDecode($data);
    }

    protected function getCoroutineListAttr($data)
    {
        return $this->_attrDecode($data);
    }

    protected function getMysqlPoolAttr($data)
    {
        return $this->_attrDecode($data);
    }

    protected function getRedisPoolAttr($data)
    {
        return $this->_attrDecode($data);
    }
}
