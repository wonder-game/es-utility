<?php

namespace WonderGame\EsUtility\Task;

use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use WonderGame\EsUtility\Common\Classes\CtxRequest;
use WonderGame\EsUtility\Model\BaseModelTrait;

/**
 * 异步更新从库数据
 */
class SyncData implements TaskInterface
{
    /**
     * [
     *    'action' => 'create|update|delete'
     *    'class' => 同步到哪些模型
     *    'data' => 同步的数据
     *    'operinfo' => 异步任务和request不在一个协程，为了记录操作日志中的管理员信息，run时需主动设置 （__construct与run也不在一个协程中）
     * ]
     * @var array|mixed
     */
    protected $data = [];

    /**
     * 备份删除的日志标识
     * @var string
     */
    protected $delBakupKey = 'delete_backup';

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // 异常处理
        trace($throwable->__toString(), 'error');
    }

    public function run(int $taskId, int $workerIndex)
    {
        if ( ! empty($this->data['operinfo'])) {
            CtxRequest::getInstance()->withOperinfo($this->data['operinfo']);
        }

        $classArray = $this->data['class'] ?? [];
        if (is_array($classArray)) {
            foreach ($classArray as $class) {
                $this->doSync($class);
            }
        }
    }

    protected function doSync($className)
    {
        if ( ! class_exists($className)) {
            return false;
        }

        // 单独处理删除操作
        if (isset($this->data['action']) && $this->data['action'] === 'delete') {
            return $this->_delete($className);
        }

        $orgs = $this->data['data'] ?? [];
        if (empty($orgs)) {
            return false;
        }

        /** @var AbstractModel | \App\Model\Sdk\Game $model */
        $model = new $className();
        if ( ! $model instanceof AbstractModel) {
            return false;
        }

        $schemaInfo = $model->schemaInfo();
        $pk = $schemaInfo->getPkFiledName();
        // 只保留数据表字段
        $columns = array_keys($schemaInfo->getColumns());
        $data = array_intersect_key($orgs, array_flip($columns));

        if (isset($data['extension'])) {
            is_array($data['extension']) or $data['extension'] = json_decode($data['extension'], true);
            $data['extension'] or $data['extension'] = [];

            // 其它加入extension
            foreach ($orgs as $k => $v) {
                if ( ! in_array($k, $columns + ['ip', config('RSA.key'), 'instime', 'updtime'])) {
                    $data['extension'][$k] = $v;
                }
            }

            is_array($data['extension']) && $data['extension'] = json_encode($data['extension'], JSON_UNESCAPED_UNICODE);
        }

        try {

            // 先查一遍，有就编辑，没有就新增，避免数据不同步的时候要手动去同步
            $count = $model->where($pk, $data[$pk])->count($pk);
            if ($count > 0) {
                $model->update($data, [$pk => $data[$pk]]);
                $orgs[$pk] = $data[$pk];
            } else {
                $insertId = $model->_clone()->data($data)->duplicate(array_keys($data))->save();
                $orgs[$pk] = is_bool($insertId) ? $data[$pk] : $insertId;
            }
        } catch (\Exception|\Throwable $e) {
            $title = '数据同步失败';

            dingtalk_text($title, "$title: class: $className, 错误信息: {$e->getMessage()}");
            wechat_notice($title, "class: $className, 错误信息: {$e->getMessage()}");
            trace("数据同步失败, class: $className, data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . $e->__toString(), 'info', 'sync');
        }

        // 清除缓存， 由对应model事件执行
//        $model->cacheDel($orgs[$pk]);
    }

    protected function _delete($className)
    {
        /** @var AbstractModel | BaseModelTrait | \App\Model\Log\Game $model */
        $model = new $className();

        $pk = $model->getPk();

        $orgs = $this->data['data'] ?? [];
        if (empty($orgs)) {
            return;
        }

        // 兼容0值
        if (isset($orgs[$pk]) && $orgs[$pk] !== '') {
            if ($row = $model->where($pk, $orgs[$pk])->get()) {
                // 备份删除数据
                $this->_delete_backup($row);
                // 执行删除
                $rowCount = $row->destroy();
                // 不论成功失败，上报
                $this->_delete_report($className, $rowCount, $row->lastQuery()->getLastQuery());
            }

            // 删缓存, 由对应model事件执行
//            $model->cacheDel($orgs[$pk], null);
        }
    }

    // 记录，用于误删恢复
    protected function _delete_backup(AbstractModel $model)
    {
        // 记录原始data
        $rawArray = $model->toRawArray();
        trace("[{$this->delBakupKey}_data]" . json_encode($rawArray, JSON_UNESCAPED_UNICODE));

        // 构造恢复SQL
        $builder = new QueryBuilder();
        $builder->insert($model->getTableName(), $rawArray);
        trace("[{$this->delBakupKey}_sql]{$builder->getLastQuery()}");
    }

    // 数据删除通知
    protected function _delete_report($className = '', $rowCount = 0, $sql = '')
    {
        $newline = " \n\n ";
        $title = '数据删除通知';

        $ding = "## $title $newline";
        $ding .= "- Class: {$className}{$newline}";
        $ding .= "- 删除行数: {$rowCount}{$newline}";
        $ding .= "- 执行SQL：{$sql}{$newline}";
        $ding .= "- 如需误删恢复，请查日志关键字：{$this->delBakupKey}";
        dingtalk_markdown('数据删除通知', $ding, false);
        wechat_notice($title, $sql);
    }
}
