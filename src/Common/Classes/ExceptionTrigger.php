<?php


namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\Component\WaitGroup;
use EasySwoole\HttpClient\HttpClient;
use EasySwoole\HttpClient\Exception\InvalidUrl;
use EasySwoole\Redis\Exception\RedisException;
use EasySwoole\Trigger\Location;
use EasySwoole\Trigger\TriggerInterface;

class ExceptionTrigger implements TriggerInterface
{
    public function error($msg, int $errorCode = E_USER_ERROR, Location $location = null)
    {
        // 暂不处理notice级别的异常
        if (in_array($errorCode, [E_NOTICE]))
        {
            return;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        if($location == null){
            $location = new Location();
            $caller = array_shift($trace);
            $location->setLine($caller['line']);
            $location->setFile($caller['file']);
        }
        $eMsg = [
            'message' => $msg,
            'file' => $location->getFile(),
            'line' => $location->getLine(),
            'trace' => $trace
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
                if (isset($config['type']) && $config['type'] === 'http' && $config['url'])
                {
                    $encrypt = LamOpenssl::getInstance()->publicEncrypt(json_encode($eMsg));
                    $client = new HttpClient($config['url']);
                    $response = $client->post(['envkeydata' => $encrypt]);
                    $httpCode = $response->getStatusCode();
                    $httpBody = $response->getBody();
                } else {
                    $redis = defer_redis($config['poolname'], $config['db']);
                    $redis->lPush($config['queue'], json_encode($eMsg));
                }
            }
            catch (InvalidUrl| RedisException | \Exception | \Throwable $e )
            {
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
