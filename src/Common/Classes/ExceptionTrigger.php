<?php


namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Trigger\Location;
use EasySwoole\Trigger\TriggerInterface;

/**
 * 错误与异常处理器
 *  - 遵守 error_reporting()：@ 抑制的错误、php.ini 未开启的级别（如 E_DEPRECATED）不处理；E_NOTICE 始终不处理
 *  - trace 不带参数、限制层数：参数里常有整包配置、整个请求参数，一条 warning 可能写出几十 KB
 *  - 忽略规则：命中配置 EXCEPTION_TRIGGER.ignore 的不写日志、不推送通知，见 isIgnored()
 *  - 限频：同一位置、同一消息在间隔内只记一次日志、投递一次告警，期间省略的条数附在下一条里，避免小问题放大成日志风暴
 *    间隔默认 60 秒，可通过配置 EXCEPTION_TRIGGER.interval 修改，0 表示不限频
 */
class ExceptionTrigger implements TriggerInterface
{
    /** 默认限频间隔（秒） */
    const INTERVAL = 60;

    /** 限频表的最大条目数，超过后清空，防止消息各不相同时无限增长 */
    const MAX_KEYS = 5000;

    /** trace 保留的最大层数 */
    const TRACE_LIMIT = 15;

    /** @var array 限频 key => 最近一次放行的时间 */
    protected static $last = [];

    /** @var array 限频 key => 间隔内被省略的条数 */
    protected static $suppressed = [];

    public function error($msg, int $errorCode = E_USER_ERROR, Location $location = null)
    {
        if ($errorCode === E_NOTICE || ! (error_reporting() & $errorCode)) {
            return;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, static::TRACE_LIMIT);
        if ($location == null) {
            $location = new Location();
            $caller = array_shift($trace);
            $location->setLine($caller['line'] ?? 0);
            $location->setFile($caller['file'] ?? '');
        }
        $eMsg = [
            'message' => $msg,
            'file' => $location->getFile(),
            'line' => $location->getLine(),
            'trace' => $trace
        ];
        $this->doTrigger(__FUNCTION__, $eMsg);
    }

    public function throwable(\Throwable $throwable)
    {
        $eMsg = [
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => array_map(function ($item) {
                unset($item['args']);
                return $item;
            }, array_slice($throwable->getTrace(), 0, static::TRACE_LIMIT)),
        ];
        $this->doTrigger(__FUNCTION__, $eMsg);
    }

    protected function doTrigger($trigger, $eMsg = [])
    {
        if ($this->isIgnored($eMsg['message'] ?? '', $eMsg['file'] ?? '')) {
            return;
        }
        if ($this->isLimited($trigger, $eMsg)) {
            return;
        }
        $this->report($trigger, $eMsg);
    }

    /**
     * 写日志并投递告警任务
     * @param string $trigger
     * @param array $eMsg
     * @return void
     */
    protected function report($trigger, $eMsg = [])
    {
        trace($eMsg, 'error', $trigger);
        unset($eMsg['trace']);
        $eMsg['trigger'] = $trigger;
        if (\Swoole\Coroutine::getCid() >= 0) {
            $task = \EasySwoole\EasySwoole\Task\TaskManager::getInstance();
            $task->async(new \WonderGame\EsUtility\Task\Error($eMsg));
        }
    }

    /**
     * 是否被限频；放行时会把间隔内省略的条数追加到 $eMsg['message']
     * @param string $trigger
     * @param array $eMsg
     * @return bool
     */
    protected function isLimited($trigger, array &$eMsg): bool
    {
        $interval = config('EXCEPTION_TRIGGER.interval');
        $interval = is_numeric($interval) ? intval($interval) : static::INTERVAL;
        if ($interval <= 0) {
            return false;
        }

        $message = is_scalar($eMsg['message'] ?? '') ? (string)($eMsg['message'] ?? '') : json_encode($eMsg['message'], JSON_UNESCAPED_UNICODE);
        $key = md5($trigger . ($eMsg['file'] ?? '') . ($eMsg['line'] ?? '') . mb_substr($message, 0, 200));
        $now = time();
        if (isset(static::$last[$key]) && $now - static::$last[$key] < $interval) {
            static::$suppressed[$key] = (static::$suppressed[$key] ?? 0) + 1;
            return true;
        }
        if (count(static::$last) > static::MAX_KEYS) {
            static::$last = static::$suppressed = [];
        }
        static::$last[$key] = $now;
        if ( ! empty(static::$suppressed[$key])) {
            if (is_string($eMsg['message'])) {
                $eMsg['message'] .= '（上次记录后同类错误另有 ' . static::$suppressed[$key] . ' 条已省略）';
            }
            static::$suppressed[$key] = 0;
        }
        return false;
    }

    /**
     * 是否命中忽略规则，规则由配置 EXCEPTION_TRIGGER.ignore 定义：
     * [
     *     [
     *         // 触发位置的文件路径需包含该片段，可省略
     *         'file' => '/easyswoole/pool/src/AbstractPool.php',
     *         // 文案必须同时包含全部关键字，可省略
     *         'msg' => ['Swoole\Table', 'unable to allocate memory'],
     *     ],
     * ]
     * file 与 msg 都未配置的规则视为无效，避免误忽略全部告警
     * @param mixed $msg
     * @param mixed $file
     * @return bool
     */
    protected function isIgnored($msg, $file): bool
    {
        $rules = config('EXCEPTION_TRIGGER.ignore');
        if ( ! is_array($rules) || ! $rules) {
            return false;
        }

        $msg = is_string($msg) ? $msg : '';
        // PHP 报告的是调用方法的用户代码位置，统一分隔符方便配置书写
        $file = str_replace('\\', '/', (string)$file);

        foreach ($rules as $rule) {
            if ( ! is_array($rule)) {
                continue;
            }
            $ruleFile = ! empty($rule['file']) ? str_replace('\\', '/', (string)$rule['file']) : '';
            $ruleMsg = ! empty($rule['msg']) ? (array)$rule['msg'] : [];
            if ($ruleFile === '' && ! $ruleMsg) {
                continue;
            }
            if ($ruleFile !== '' && ($file === '' || strpos($file, $ruleFile) === false)) {
                continue;
            }
            foreach ($ruleMsg as $marker) {
                if ($marker === '' || strpos($msg, (string)$marker) === false) {
                    continue 2;
                }
            }
            return true;
        }
        return false;
    }
}
