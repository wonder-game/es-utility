<?php


namespace WonderGame\EsUtility\Model\Admin;

use WonderGame\EsUtility\Common\OrmCache\Hash;

trait CrontabTrait
{
    use Hash;

    protected function setBaseTraitProptected()
    {
        $this->autoTimeStamp = true;
        $this->hashWhere = ['status' => [[0, 2], 'IN']];
    }

    protected function setServerAttr($data)
    {
        return is_array($data) ? implode(',', $data) : $data;
    }

    protected function setSysAttr($data)
    {
        return is_array($data) ? implode(',', $data) : $data;
    }

    protected function getServerAttr($value, $alldata)
    {
        return $this->getIntArray($value);
    }

    protected function getSysAttr($value, $alldata)
    {
        return $this->getIntArray($value);
    }

    protected function getIntArray($value)
    {
        if (is_string($value)) {
            $array = explode(',', $value);
            return array_map('intval', $array);
        } else {
            return $value;
        }
    }

    protected function setArgsAttr($data)
    {
        $result = [];
        if ( ! empty($data) && is_array($data)) {
            foreach ($data as $value) {
                // 不要空值
                if (empty($value['key']) || empty($value['value'])) {
                    continue;
                }
                // 需要将双引号替换成单引号，否则eval解析失败
                $value['value'] = str_replace('"', '\'', $value['value']);
                $result[$value['key']] = $value['value'];
            }
        }
        return json_encode($result);
    }

    protected function getArgsAttr($data)
    {
        $json = json_decode($data, true);
        return $json ? $json : [];
    }

    public function getCrontab($where = [], $columns = ['sys', 'zone', 'server'])
    {
        $result = [];
        $data = $this->cacheHGetAll();
        foreach ($data as &$value)
        {
            $continue = false;
            foreach ($columns as $col)
            {
                if (isset($value[$col]) && is_string($value[$col])) {
                    $value[$col] = explode(',', $value[$col]);
                }
                $is = (isset($where[$col]) && ( ! is_array($value[$col]) || ! in_array($where[$col], $value[$col]) ));
                if ($is) {
                    $continue = true;
                    break;
                }
            }
            if ($continue) {
                continue;
            }
            $result[] = $value;
        }
        return $result;
    }
}
