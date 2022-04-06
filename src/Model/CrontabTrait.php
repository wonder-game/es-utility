<?php


namespace WonderGame\EsUtility\Model;


trait CrontabTrait
{
    protected function setBaseTraitProptected()
    {
        $this->autoTimeStamp = true;
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
        if (is_string($value))
        {
            $array = explode(',', $value);
            return array_map('intval', $array);
        } else {
            return $value;
        }
    }

    protected function setArgsAttr($data)
    {
        $result = [];
        if (!empty($data) && is_array($data)) {
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
        return $json ? $json : '';
    }

    public function getCrontab($svr = '')
    {
        // 0-启用,2-运行一次
        return $this->where(['status' => [[0, 2], 'in']])->all();
    }
}
