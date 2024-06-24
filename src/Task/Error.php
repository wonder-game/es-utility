<?php

namespace WonderGame\EsUtility\Task;

use EasySwoole\Task\AbstractInterface\TaskInterface;
use EasySwoole\Utility\File;
use WonderGame\EsUtility\Common\Classes\DateUtils;
use WonderGame\EsUtility\Notify\DingTalk\Message\Markdown;
use WonderGame\EsUtility\Notify\EsNotify;
use WonderGame\EsUtility\Notify\Feishu\Message\CardError;

/**
 * 程序异常
 */
class Error implements TaskInterface
{
    protected $warp = " \n\n ";

    protected $data = [];

    protected $merge = [];

    public function __construct($data = [], $merge = [])
    {
        $this->data = $data;
        $this->merge = $merge;
    }

    public function run(int $taskId, int $workerIndex)
    {
        if ($this->checkTime()) {
            $title = '程序异常';
            $servname = config('SERVNAME');
            $servername = config('SERVER_NAME');

            $driver = config('ES_NOTIFY.driver') ?: 'dingTalk';

            switch ($driver) {
                case 'feishu': // 飞书 card

                    $message = $this->data['message'] ?? '';
                    foreach ($this->merge as $key => $value) {
                        $message = "\n" . (is_int($key) ? $value : "$key: $value");
                    }

                    $Message = new CardError([
                        'servername' => $servname,
                        'project' => $servername,
                        'datetime' => date(DateUtils::FULL),
                        'trigger' => $this->data['trigger'] ?? '',
                        'filename' => "{$this->data['file']} 第 {$this->data['line']} 行",
                        'content' => $message,
                        'isAtAll' => true
                    ]);

                    break;
                default: // 钉钉 markdown
                    $data = [
                        '### **' . $title . '**',
                        '- 服务器: ' . $servname,
                        '- 项 目：' . $servername,
                        "- 文 件：{$this->data['file']} 第 {$this->data['line']} 行",
                        "- 详 情：" . $this->data['message'] ?? '',
                        '- 触发方式： ' . $this->data['trigger'] ?? '',
                    ];

                    foreach ($this->merge as $key => $value) {
                        $data[] = "- " . (is_int($key) ? $value : "$key: $value");
                    }

                    $message = implode($this->warp, $data);
                    $Message = new Markdown([
                        'title' => $title,
                        'text' => $message,
                        'isAtAll' => true
                    ]);
                    break;
            }

            EsNotify::getInstance()->doesOne($driver, $Message);

            $message = "$title: {$this->data['message']}, 文件 {$this->data['file']} 第 {$this->data['line']} 行";
            wechat_warning($message);
        }
    }

    public function onException(\Throwable $throwable, int $taskId, int $workerIndex)
    {
        trace($throwable->__toString(), 'error');
    }

    /**
     * 同一个文件出错，N分钟内不重复发送
     * @param string $file
     * @return bool
     */
    protected function checkTime()
    {
        $file = $this->data['file'];
        if ( ! $file) {
            return false;
        }
        $time = time();
        $strId = md5($file);
        $chkFile = config('LOG.dir') . '/checktime.data';
        File::touchFile($chkFile, false);
        $content = file_get_contents($chkFile);
        if ($arr = json_decode($content, true)) {
            $last = $arr[$strId] ?? '';
            $limit = (config('err_limit_time') ?: 5) * 60;
            if ($last && $limit && $last > $time - $limit) {
                // 时间未到
                return false;
            }
        }
        $arr[$strId] = $time;
        file_put_contents($chkFile, json_encode($arr));
        return true;
    }
}
