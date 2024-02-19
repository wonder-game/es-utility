<?php

namespace WonderGame\EsUtility\Task;

use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\DbManager;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use WonderGame\EsUtility\Common\Classes\CtxRequest;

/**
 * 异步创建模型分表
 */
class CreateSubTable implements TaskInterface
{
    /**
     * [
     *      data => [model], // id为必须
     *      class => 需要创建分表的模型class
     *      operinfo => 异步任务和request不在一个协程，为了记录操作日志中的管理员信息，run时需主动设置 （__construct与run也不在一个协程中）
     * ]
     * @var array|mixed
     */
    protected $data = [];

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // 异常处理
        trace($throwable->__toString(), 'error');
    }

    /**
     * @param int $taskId
     * @param int $workerIndex
     */
    public function run(int $taskId, int $workerIndex)
    {
        if ( ! empty($this->data['operinfo'])) {
            CtxRequest::getInstance()->withOperinfo($this->data['operinfo']);
        }

        $classes = $this->data['class'];
        if (is_array($classes)) {
            foreach ($classes as $tableClass) {
                $this->doCreate($tableClass);
            }
        }
    }

    protected function doCreate($className = '')
    {
        if ( ! class_exists($className)) {
            return;
        }

        $data = $this->data['data'];

        $id = $data['id'];
        if ( ! is_numeric($id)) {
            return;
        }

        // id=0的表需要提前建好
        if (intval($id) === 0) {
            return;
        }

        // 是否需要让model支持完整名称 ??

        $arr = explode('\\', $className);
        $name = parse_name(end($arr));
        $fullName = "{$name}_{$id}";

        try {
            /** @var AbstractModel $model */
            $model = new $className([], $fullName, $id);
            if ( ! $model instanceof AbstractModel) {
                return;
            }
            $connectionName = $model->getConnectionName();

            $builder = new QueryBuilder();
            $builder->raw("CREATE TABLE  IF NOT EXISTS `{$fullName}` LIKE `{$name}_0`;");
            DbManager::getInstance()->query($builder, true, $connectionName);
        } catch (\EasySwoole\Mysqli\Exception\Exception|\Throwable $e) {
            $title = '创建分表失败';
            notice("$title [$connectionName . $fullName] : " . $e->getMessage());
            wechat_notice("$title {$e->getMessage()}");
            trace("$title [$connectionName . $fullName] : " . $e->getMessage(), 'error');
        }
    }
}
