## 简介

    可方便快捷的实现通知服务，目前已接入的有钉钉和微信公众号（含测试号）



## 使用场景

- 程序异常通知
- 数据上报到钉钉群
- 敏感操作通知、操作状态通知

## 目录结构

    src
     ├── DingTalk 钉钉实现
     ├── Interface 接口定义
     ├── Wechat 微信实现
     └── Feishu 飞书实现

## 开始

> composer require wonder-game/es-notify

#### 配置

钉钉

```php
<?php
   $DingTalkConfig = new \WonderGame\EsNotify\DingTalk\Config([
            // 钉钉WebHook url
            'url' => 'your dingtalk url',
            // 密钥
            'signKey' => 'your dingtalk sign_key',
            
            //  ... 也可以配置一些自定义属性, 获取方式 Config->getProperty('xx')
        ], true);
```

微信

```php
<?php
    $WeChatConfig = new \WonderGame\EsNotify\WeChat\Config([
            // 微信公众平台后台的 appid
            'appId' => '',
            // 微信公众平台后台配置的 AppSecret
            'appSecret' => '',
            // 微信公众平台后台配置的 Token
            'token' => '',
            // 点击后跳转地址
            'url' => 'https://github.com/Joyboo',
            // 发送给谁, openid[]
            'toOpenid' => [],
            // 注册WeChat实例时追加的配置( 可选参数 )
            'append' => []

            //  ... 也可以配置一些自定义属性, 获取方式 Config->getProperty('xx')
        ], true);
```

飞书

```php
<?php
    $FeishuConfig = new \WonderGame\EsNotify\Feishu\Config([
            // 钉钉WebHook url
            'url' => 'your feishu url',
            // 密钥
            'signKey' => 'your feishu sign_key',

            //  ... 也可以配置一些自定义属性, 获取方式 Config->getProperty('xx')
        ], true);
```

### 注册

可注册多个，不限数量，key需要保证唯一，调用通知时需声明此key

```php
<?php
// Config为配置类
\WonderGame\EsNotify\EsNotify::getInstance()->register('dingtalk', $Config);
```



### 调用通知

1. 调用doesOne时第一个参数为注册时的key，第二个参数为消息类
2. 钉钉支持的消息类型是固定的，都在DingTalk\Message目录
3. 微信公众号模板消息的每个模板，理论上格式都不同，请自行继承src/WeChat/Message/Base.php 实现struct抽象方法传递模板结构，可参考Warning.php和Notice.php

```php
<?php

// 这是一个钉钉Markdown消息示例
$message = new \WonderGame\EsNotify\DingTalk\Message\Markdown([
        //消息标题
        'title' => 'Joyboo', 
        // 内容
        'text' => '真帅',
        // @的手机号(可选)
        'atMobiles' => [],
        // @的userid（可选）
        'atUserIds' => [],
        // 是否@所有人（可选， 默认false）
        'isAtAll' => true
    ]);
// 开始发送钉钉消息，key是注册时传入的key
\WonderGame\EsNotify\EsNotify::getInstance()->doesOne('dingtalk', $message);



// 这是一个程序异常的消息示例
$message = new \WonderGame\EsNotify\WeChat\Message\Warning([
            'templateId' => '微信模板消息id',
            'file' => '发生异常的文件',
            'line' => '第几行',
            'servername' => '服务器名',
            'message' => 'mesage',
            // 微信文本颜色，默认红色
            //'color' => ''
]);
// 开始发送微信消息
\WonderGame\EsNotify\EsNotify::getInstance()->doesOne('wechat', $message);

```

### 常见问题

1. 我想在没注册的情况下，按指定配置实例化然后调用通知可以吗？

- 当然可以，Show Code

```php
<?php
// 第一步： 构造配置类
$DingTalkConfig = new \WonderGame\EsNotify\DingTalk\Config([
                // 动态传入你的配置
                'url' => 'your dingtalk WebHook url',
                'signKey' => 'your dingtalk sign key'
            ], true);
         
// 第二步： 构造消息类
$DingTalkMessage = new \WonderGame\EsNotify\DingTalk\Message\Markdown([
            'title' => '魔镜魔镜，谁是世界上最帅的人?',
            'text' => 'Joyboo无疑',
        ]);
        
// 然后就可以愉快的调用了
$DingTalkConfig->getNotifyClass()->does($DingTalkMessage);

```

- 微信同理

## TODO

- [ ] 异常处理
- [ ] 目前钉钉和微信Config和Message不能混用，所以无法实现doesAll方法，需解决

## 相关文档

- [钉钉-自定义机器人接入](https://open.dingtalk.com/document/group/custom-robot-access)
- [EasySwoole-微信SDK-模板消息](http://www.easyswoole.com/Components/WeChat2.x/officialAccount/templateMessage.html)
