<?php

// 此文件非项目文件，仅展示出项目使用的一些配置项，使其可以用 config('xxx') 获取，有些是必选有些是可选，需自行阅读代码

return [
    'TEMP_DIR' => EASYSWOOLE_ROOT . '/Temp/' . get_mode('mode'),
    'LOG' => [
        // 日志存储的目录
        'dir' => EASYSWOOLE_ROOT . (is_env('dev') ? '' : '/..') . '/logs/' . get_mode('all'),
        // 记录日志的级别
        'level' => \EasySwoole\Log\LoggerInterface::LOG_LEVEL_DEBUG,
        // 自定义日志处理器
        'handler' => new \WonderGame\EsUtility\Common\Logs\Handler(),
        'logConsole' => ! is_env('produce'),
        'displayConsole' => true,
        'ignoreCategory' => [],
        // 单独记录的日志级别 level
        'apart_level' => ['error'],
        // 单独记录的日志类型 category
        'apart_category' => ['sql', 'pay', 'cksql', 'media', 'channel'],
    ],

    'SERVER_NAME' => (is_env('test') ? 'test-' : '') . 'ES-utility',
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'SERVER_TYPE' => EASYSWOOLE_WEB_SERVER,
        'SOCK_TYPE' => SWOOLE_TCP,
        'RUN_MODEL' => SWOOLE_PROCESS,
        'SETTING' => [
            'worker_num' => is_env('test') ? 2 : 8,
            'reload_async' => true,
            'max_wait_time' => 3,
            // 'max_request' => 10000, // 当发现内存泄漏问题应尽快定位，不要依赖此配置
        ],
        'TASK' => [
            'workerNum' => is_env('test') ? 2 : 5,
            'maxRunningNum' => 128,
            'timeout' => 15
        ]
    ],

    'MYSQL' => [
        // 后台
        'admin' => [
            'host' => '',
            'port' => 3306,
            'user' => 'root',
            'password' => '',
            'database' => '',
            'timeout' => 30,
            'charset' => 'utf8mb4',
        ],
        // ... 其他api项目
    ],

    'REDIS' => [
        // 后台
        'admin' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'auth' => '',
            'db' => 1,
            'timeout' => 10,
            'packageMaxLength' => 1024 * 1024 * 10,
        ],
        // ... 其他api项目
    ],

    // 文件上传
    'UPLOAD' => [
        'dir' => EASYSWOOLE_ROOT . '/uploads',
    ],

    // 文件导出
    'export_dir' => PUBLIC_DIR . '/excel/',

    // i18n国际化
    'LANGUAGES' => [
        'Cn' => [
            'class' => \App\Common\Languages\Chinese::class,
            'match' => '/.*/i',
            'default' => true,
        ],
    ],

    // 不写入log_sql的规则
    'NOT_WRITE_SQL' => [
        // 正则匹配规则
        'pattern' => is_env('dev') ? [] : ['/^SELECT/i'],
        'table' => ['http_tracker', 'process_info', 'log_sql', 'log_login', 'log_error']
    ],

    // api项目，接口RSA加密配置
    'RSA' => [
        'key' => 'envkeydata',
        'private' => EASYSWOOLE_ROOT . '/utility/private_key.pem',
        'public' => EASYSWOOLE_ROOT . '/utility/public_key.pem',
    ],

    // jwt配置（后台和api项目都可能用到）
    'ENCRYPT' => [
        // 传递Token的Header键,需要与客户端保持一致
        'jwtkey' => 'authorization',
        // jwt的密钥
        'key' => '',
        // token有效期
        'expire' => 86400 * 3,
        // 如果是后台项目，token有效期小于此值会通过websocket自动续期
        'refresh_time' => 86400 * 2,
        // 如果是后台项目，新的token生成后通过此Task通知客户端，程序内容是websocket服务器通知指定的管理员执行更新token的动作
        'refresh_task' => \App\Task\RefreshToken::class
    ],

    // 当前服务器标识
    'SERVNAME' => get_cfg_var('env.servname'),

    // 定时任务配置
    'CRONTAB' => [
        'driver' => 'Mysql',
        'db' => [
            'host' => '',
            'port' => 3306,
            'user' => 'root',
            'password' => '',
            'database' => '',
            'timeout' => 30,
            'charset' => 'utf8mb4',
        ],
        // 查询定时任务时的where条件
        'where' => function (\EasySwoole\Mysqli\QueryBuilder $builder) {
            // 需替换成指定的服务标识，表示具体运行在哪个服务中，例如 admin|sdk|pay|log
            $system = '';
            // 需替换为服务器id，表示运行在哪台服务器，一般从get_cfg_var('env.servname')解析出服务器id
            $server = '';
            $builder->where("(FIND_IN_SET('$system', sys)>0 AND FIND_IN_SET('$server', server)>0)");
        },
    ],

    // 通知组件配置（程序异常会自动通知，或主动调用通知的其他场景）
    'ES_NOTIFY' => is_env('dev') ? [] : [
        // 钉钉webhook机器人
        'dingTalk' => [
            'default' => [
                'url' => '',
                'signKey' => '',
            ],
        ],
        // 微信测试号
        'weChat' => [
            'default' => [
                'appId' => '',
                'appSecret' => '',
                'token' => 'token',
                'url' => '',
                'tplId' => [
                    // 模板内容：{{keyword1.DATA}} 系统：{{keyword2.DATA}} 服务器：{{keyword3.DATA}} 时间：{{keyword4.DATA}}
                    'warning' => '',
                    // 模板内容：{{keyword1.DATA}} 系统：{{keyword2.DATA}} 服务器：{{keyword3.DATA}} 时间：{{keyword4.DATA}}
                    'notice' => '',
                ],
                'toOpenid' => [
                    '', // Joyboo
                ],
            ],
        ],
        // 飞书webhook机器人
        'feishu' => [
            'default' => [
                'url' => '',
                'signKey' => '',
            ],
        ],
    ],

    // 队列配置，可选配置，规则和层级可自由设置
    'QUEUE' => [
        // 集群模式配置，一个队列过大，会导致所在分片的内存过高，而其他分片又很空闲。这里简单将一个队列拆分为N个队列分散存储。也可使用集群客户端命令判断slot所在分片来平均存储到多个分片
        // redis集群模式，读取消费队列的分片数目,0-不开启分片
        // 'clusterNumber' => 0,
        // redis集群模式，写入消费队列的分片数目,0-不开启分片
        // 'clusterNumberWrite' => 0,

        // ************** 其他自定义配置
        // 后台队列名称
        'admin' => [
            'xxx' => 'xxx-name'
        ],
        // 其他系统的队列
        'log' => [
            'origin' => [
                'xxx' => 'xxx-name',
            ]
        ],
    ],

    // 后台项目，与客户端交互的字段名
    'fetchSetting' => [
        // 当前第几页
        'pageField' => 'page',
        // 每页大小
        'sizeField' => 'pageSize',
        // 列表dataSource的Key
        'listField' => 'items',
        // 合计页的Key
        'footerField' => 'summer',
        // 总条数
        'totalField' => 'total',
        // 导出表头
        'exportThField' => '_th',
        // 导出全部时发送的文件名
        'exprotFilename' => '_fname',
    ],


    // 纯真 (CZ88.net)
    'CZ88' => [
        'db_file_ipv4' => '',
        'db_file_ipv6' => '',
        'key' => '',
        //'query_type' => 'MEMORY'
    ],

    // websocket相关配置
    'ws' => [
        // 心跳
        'heartbeat' => [
            'request_message' => 'ping',
            'response_message' => 'pong',
        ]
    ]
];
