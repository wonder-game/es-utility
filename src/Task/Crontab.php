<?php


namespace WonderGame\EsUtility\Task;

use EasySwoole\Task\AbstractInterface\TaskInterface;

/**
 * Crontab通用模板类
 */
class Crontab implements TaskInterface
{
    protected $eclass = '';
    protected $method = '';

    protected $args = [];

    public function __construct($attr, $args = [])
    {
        // 保存投递过来的数据
        list($this->eclass, $this->method) = $attr;
        $this->args = $args;
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        // 异常处理
        \EasySwoole\EasySwoole\Trigger::getInstance()->throwable($throwable);
        return '执行失败： ' . __CLASS__;
    }

    public function run(int $taskId, int $workerIndex)
    {
        $data = is_array($this->args) ? $this->args : [];

        foreach ($data as $k => $v) {
            // 解析指定函数
            if (preg_match('/date\(|time\(|strtotime\(/i', $v)) {
                eval('$data["' . $k .'"] = ' . $v . ';');
            }
        }

        $className = "\\App\\Crontab\\" . ucfirst($this->eclass);

        if (!class_exists($className)) {
            trace("$className Not Found!", 'error');
            return;
        }

        if ( ! method_exists($className, $this->method)) {
            trace("{$className}->{$this->method} Not Found!", 'error');
            return;
        }

        $start = round(microtime(true), 4) * 10000;
        (new $className())->{$this->method}($data);
        $end = round(microtime(true), 4) * 10000;

        $diff = $end - $start;
        if ($diff >= 10000) {
            return '耗时: [' . ($diff / 10000) . 's]';
        } else {
            return '耗时: [' . ($diff / 10) . 'ms]';
        }
    }
}
