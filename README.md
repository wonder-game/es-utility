
### 简介

    基于Easyswoole封装的一些Trait和Class，放到Composer仓库以实现多项目共用一套代码

### 开始

> composer require wonder-game/es-utility

### 需要掌握的基础知识：

- [EasySwoole](http://www.easyswoole.com)
- [Swoole](https://wiki.swoole.com)
- [Composer](https://getcomposer.org)

### 目录结构及常用介绍

    src 理解为EasySwoole的App目录
     ├── Common  主要放一些非EasySwoole的东
     |      ├── Classes 自定义类
     │      │     ├── Crontab 实现定时任务的类（后面会移动至Crontab目录）
     │      │     ├── CtxRequest 协程单例类，解决一些痛点，如Model内无法获取Http Request、WebSocket Caller实例等，作用与EasySwoole\Component\Context\ContextManager类似
     │      │     ├── DateUtils 时间日期时区等处理
     │      │     ├── ExceptionTrigger 自定义异常处理器，将异常上报至redis或http
     │      │     ├── FdManager  WebSocket连接符管理，共享内存(Swoole\Table)实现
     │      │     ├── LamJwt jwt
     │      │     ├── LamLog 自定义日志处理器
     │      │     ├── LamOpenssl RSA数据加密和解密
     │      │     ├── LamUnit 辅助工具类
     │      │     ├── Mysqli 对MysqlClient的二次封装
     │      │     ├── ShardTable 定时建分区、续分区
     │      │     ├── Tree 数行结构处理
     │      │     └── XlsWriter 数据导入和导出
     │      ├── Exception 各种自定义异常
     │      ├── Http Http相关的配置
     │      │     └── Code Http响应状态码，项目的Code请`继承`它
     │      ├── Language I18N国际化目录
     │      │     ├── Dictionary 国际化字典，项目请`继承`它
     │      │     └── Languages I18n助手类，主要用来注册、设置
     │      │
     │      └── OrmCache 模型缓存组件，已实现 String、Hash、Set、SplArray
     │
     ├── HttpController
     │        ├── Admin
     │        │     ├── BaseTrait 继承BaseController
     │        │     ├── AuthTrait 继承BaseTrait引用类，是其他控制器的父类，主要实现一些CURD等基础操作，子类可写最少代码实现相关功能
     │        │     └── ... 其他业务控制器
     │        ├── Api
     │        └── BaseController 所有控制器的基类
     ├── HttpTracker 链路追踪
     │        ├── Index 继承自PointContext，目的是为了默认开启autoSave及设置saveHandler，实例化时用它替代PointContext
     │        └── SaveHandler 实现SaveHandlerInterface接口
     ├── Model
     │     ├── BaseModelTrait 所有Model的基类
     │     └── ... 其他业务模型
     ├── Task 异步任务
     │     ├── Crontab 通用的异步任务模板
     │     └── ... 异步任务类
     ├── WebSocket 同 HttpController
     ├── ... 其他业务
     ├── EventInitialize 对EasySwooleEvent::initialize事件的一些封装
     ├── EventMainServerCreate  对EasySwooleEvent::mainServerCreate事件的一些封装
     └── function.php 常用函数，项目可预定义对应函数以实现不同逻辑

Controller
```php
<?php
use WonderGame\EsUtility\HttpController\Admin\AdminTrait;

class MyAdminController
{
	use AdminTrait;
    
	// here are some methods from AdminTrait ....
}

```
Model
```php
<?php
use WonderGame\EsUtility\Model\AdminModelTrait;

class MyAdminModel
{
	use AdminModelTrait;
    
	// here are some methods from AdminModelTrait ....
}

```

### 答疑解惑

 function.php 为何不写在此项目的composer.json

    function.php应该由项目的composer.json去定义引入的顺序
    位置一定得是在项目的函数引入之后，否则无法预定义函数，而放在此项目的composer.json会被优先加载

为何多数文件选择trait而不使用继承

    trait和继承各有优劣，选择trait目的是为了EasySwoole推荐的继承关系不被破坏

trait有哪些坑

    1. 不允许重写属性，所以基本都定义了一个setTraitProtected方法去修改trait属性
    2. 不允许重载方法，当某些项目可能比方法多一个小逻辑时，需要及时调整代码的封装，否则需要整个复制多一份，日积月累，反而可能更难维护
    3. 由于 2 的限制，现将普通控制器方法的public方法名默认添加一个固定前缀，通过基础控制器 /src/HttpController/BaseControllerTrait.php 的 actionNotFound 方法来实现更加灵活的调用方式

### TODO

- [x] 创建定时任务Crontab和消费任务Consumer，src/Common/Classes/Crontab移动至src/Crontab目录
- [ ] 自定义Log处理器改为onLog + Event方式
- [ ] 重写Tree、ShardTable类
- [x] WebSocket相关类，事件、解析、Caller、连接符管理等
- [x] Crontab支持database、file、http等方式获取
- [x] es-orm-cache 组件封装，替换原有的cacheinfo系列方法
- [ ] WebSocket实现导出全部，永不超时，进度实时可见，随时取消

### 其他

- [trait冲突解决](https://www.php.net/manual/zh/language.oop5.traits.php)
- [XlsWriter](https://xlswriter-docs.viest.me/zh-cn)

