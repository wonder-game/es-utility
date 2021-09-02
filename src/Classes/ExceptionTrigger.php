<?php


namespace Linkunyuan\EsUtility\Classes;

use EasySwoole\Component\WaitGroup;
use EasySwoole\Trigger\Location;
use EasySwoole\Trigger\TriggerInterface;

class ExceptionTrigger implements TriggerInterface
{
    public function error($msg, int $errorCode = E_USER_ERROR, Location $location = null)
    {
        if($location == null){
            $location = new Location();
            $debugTrace = debug_backtrace();
            $caller = array_shift($debugTrace);
            $location->setLine($caller['line']);
            $location->setFile($caller['file']);
        }
        $eMsg = [
            'message' => $msg,
            'file' => $location->getFile(),
            'line' => $location->getLine()
        ];
        $this->doError(__FUNCTION__, $eMsg);
    }

    public function throwable(\Throwable $throwable)
    {
        $eMsg = [
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTrace()
        ];
        $this->doError(__FUNCTION__, $eMsg);

        throw $throwable;
    }

    protected function doError($trigger, $eMsg = [])
    {
        trace($eMsg, 'error', $trigger);

        $wg = new WaitGroup();
        $wg->add();

        go(function() use ($eMsg, $wg) {
            if (is_string($eMsg)) {
                $eMsg = [$eMsg];
            }
            // 错误类型
            $eMsg['type'] = 'program';
            // 报错服务器
            $eMsg['servername'] = config('SERVNAME');
            // trace通过常规文件记录
            unset($eMsg['trace']);

            try {
                $config = config('EXCEPTION_REPORT');
                $redis = defer_redis($config['poolname'], $config['db']);
                $redis->lPush($config['queue'], json_encode($eMsg));
            }
            catch (\EasySwoole\Redis\Exception\RedisException | \Exception | \Throwable $e )
            {
                // 如果连报警的redis都挂了，降级记录到文件
                trace($eMsg, 'info', 'lowlevel');
            }
            $wg->done();
        });

        // 协程等待
        $wg->wait();
        // 关闭
        $wg->close();
    }
}
