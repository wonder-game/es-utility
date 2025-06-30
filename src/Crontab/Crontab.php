<?php


namespace WonderGame\EsUtility\Crontab;

use Cron\CronExpression;
use EasySwoole\Component\AtomicManager;
use EasySwoole\EasySwoole\Crontab\AbstractCronTask;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\EasySwoole\Trigger;
use EasySwoole\Task\AbstractInterface\TaskInterface;
use EasySwoole\Utility\File;
use WonderGame\EsUtility\Crontab\Driver\Interfaces;
use WonderGame\EsUtility\Crontab\Driver\Mysql;
use WonderGame\EsUtility\Task\Crontab as CrontabTemplate;

class Crontab extends AbstractCronTask
{
    const KEY_CRONTAB_DELIVERY_TIMEOUT = 'crontab_delivery_timeout';
    protected $tableName = 'crontab';

    public static function getRule(): string
    {
        return '* * * * *';
    }

    public static function getTaskName(): string
    {
        $arr = explode('\\', static::class);
        return end($arr);
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        Trigger::getInstance()->throwable($throwable);
        return '执行失败： ' . __CLASS__;
    }

    public function throwable($row, string $message)
    {
        trace($message, 'error');
        $text = implode(" \n\n ", [
            '### **Crontab异常**',
            '- 服务器: ' . config('SERVNAME'),
            '- 项 目：' . config('SERVER_NAME'),
            "- id: {$row['id']}",
            "- name: {$row['name']}",
            "- 详 情：$message",
        ]);
        notice($text);
    }

    public function run(int $taskId, int $workerIndex)
    {
        $config = config('CRONTAB');
        $Drive = $this->driver($config['driver']);
        $backupFile = $config['backup'] ?: (config('LOG.dir') . '/crontab.data');

        try {
            $list = $Drive->list();
            // 成功记录到文件, todo 运行一次的任务此时还未修改
            $this->backupFile($backupFile, $list);
        } catch (\Exception|\Throwable $e) {
            // 失败降级从文件读取
            if ( ! file_exists($backupFile) || ! ($str = file_get_contents($backupFile))) {
                // 连文件都没有，说明从未正常运行过
                throw $e;
            }
            $list = json_decode($str, true);
        }

        if (empty($list) || ! is_array($list)) {
            return;
        }

        $namespace = $config['namespace'] ?: '\\App\\Crontab';
        $namespace = rtrim($namespace, '\\') . '\\';

        $task = TaskManager::getInstance();
        foreach ($list as $value) {
            if ( ! CronExpression::isValidExpression($value['rule'])) {
                $this->throwable($value, "运行规则设置错误 {$value['rule']}");
                continue;
            }

            if ( ! (CronExpression::factory($value['rule'])->isDue())) {
                // 时间未到
                continue;
            }

            $args = $value['args'] ?: [];
            if (is_string($args)) {
                $args = json_decode($args, true);
            }
            if ( ! is_array($args)) {
                $this->throwable($value, "定时任务参数解析失败: args=" . var_export($value['args'], true));
                continue;
            }

            $runClass = ucfirst($value['eclass'] ?? '');
            if ($runClass && strpos($runClass, '\\') === false) {
                $runClass = $namespace . $runClass;
            }
            if (empty($runClass) || empty($value['method']) || ! class_exists($runClass) || ! method_exists($runClass, $value['method'])) {
                $this->throwable($value, "参数异常: run class: $runClass, run method: {$value['method']}");
                continue;
            }
            $value['eclass'] = $runClass;

            $instance = $this->tplInstance($value, $args);
            // 投递给异步任务
            $finish = $task->async($instance, function ($reply, $taskId, $workerIndex) use ($value) {
                trace("[CRONTAB] SUCCESS id={$value['id']}, name={$value['name']}, reply={$reply}, workerIndex={$workerIndex}, taskid={$taskId}");
            });
            // 运行一次
            $once = $config['status_once'] ?? 2;
            // 禁用状态
            $disabled = $config['status_disabled'] ?? 1;
            if ($finish > 0 && $value['status'] == $once) {
                $Drive->update($value['id'], $disabled);
            }

            if ($finish <= 0) {
                // 返回值 -7 需要处理
                $finish === -7 && $this->handleDeliveryTimeout();
                $this->throwable($value, "投递失败: 返回值={$finish}, id={$value['id']}, name={$value['name']}");
            }
        }
    }

    protected function driver($name = 'Mysql'): Interfaces
    {
        if (empty($name)) {
            $name = Mysql::class;
        }
        // 允许自定义
        if (strpos($name, '\\') !== false) {
            $Ref = new \ReflectionClass($name);
            if ($Ref->implementsInterface(Interfaces::class)) {
                return $Ref->newInstance();
            } else {
                throw new \Exception($name . ' Please implements Interfaces');
            }
        } else {
            $Ref = new \ReflectionClass(static::class);
            $nameSpace = $Ref->getNamespaceName();
            $name = $nameSpace . '\\Driver\\' . ucfirst($name);
            if (class_exists($name)) {
                return new $name();
            } else {
                throw new \Exception("Class Not found: $name");
            }
        }
    }

    protected function backupFile($filename, $data = [])
    {
        $dir = dirname($filename);
        if (File::createDirectory($dir)) {
            $fp = fopen($filename, 'w+');
            fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
            fclose($fp);
        }
    }

    /**
     * 模板实例
     * @param array $row 单个crontab行
     * @param array $params 模板类实例化参数
     * @return TaskInterface
     */
    protected function tplInstance($row = [], $params = []): TaskInterface
    {
        $dftTpl = CrontabTemplate::class;
        $className = $row['rclass'] ?: $dftTpl;
        // 默认命名空间，跟随CrontabTemplate
        if (strpos($className, '\\') === false) {
            $Ref = new \ReflectionClass($dftTpl);
            $nameSpace = $Ref->getNamespaceName();
            $className = $nameSpace . '\\' . ucfirst($className);
        }

        $RefFull = new \ReflectionClass($className);
        if ($RefFull->implementsInterface(TaskInterface::class)) {
            return $RefFull->newInstance($row, $params);
        } else {
            trace("$className 异步任务模板未实现TaskInterface接口, 已使用默认模板", 'error');
            return new $dftTpl($row, $params);
        }
    }

    /**
     * 处理任务投递超时(投递异步任务返回-7)
     * 累积到N个-7，就生成文件，并清零计数器，靠linux的crontab检测到这个文件就把admin重启，并删除文件
     */
    protected function handleDeliveryTimeout()
    {
        // 采用 ES 自带的原子计数器计数

        // 请在registerCrontab注册一下该key的计数器，不注册则不处理
        $ato = AtomicManager::getInstance()->get(self::KEY_CRONTAB_DELIVERY_TIMEOUT);
        if (!$ato) {
            return;
        }

        $ato->add(1);

        // 如果超过指定次数，则重置计数器并生成 lock 文件
        // linux的crontab检测到这个文件就把admin重启，并删除文件
        $times = $ato->get();
        if ($times > config('crontab_delivery')) {
            $lockfile = config('LOG.dir') . '/crontab.lock';
            file_put_contents($lockfile, 'locked' . $times);
            
            $ato->set(0);
        }
    }
}
