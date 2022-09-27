<?php

namespace WonderGame\EsUtility\HttpController\Api;

trait CrontabTrait
{
    protected function onRequest(?string $action): ?bool
    {
        return parent::onRequest($action) && ! empty($this->rsa);
    }

    public function list()
    {
        /** @var \App\Model\Admin\Crontab $Crontab */
        $Crontab = model_admin('Crontab');

        $array = $Crontab->getCrontab($this->rsa);

        $this->success($array);
    }

    public function update()
    {
        /** @var \App\Model\Admin\Crontab $Crontab */
        $Crontab = model_admin('Crontab');
        $row = $Crontab->where('id', $this->rsa['id'])->get();
        if ($row) {
            $status = $this->rsa['status'] ?? 1;
            $row->update(['status' => $status]);
        }
        $this->success();
    }
}
