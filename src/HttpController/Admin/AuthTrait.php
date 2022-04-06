<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use App\HttpController\BaseController;
use EasySwoole\Component\Timer;
use EasySwoole\Http\Exception\FileException;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\Db\MysqliClient;
use EasySwoole\Policy\Policy;
use EasySwoole\Policy\PolicyNode;
use EasySwoole\Utility\MimeType;
use WonderGame\EsUtility\Common\Classes\DateUtils;
use WonderGame\EsUtility\Common\Classes\LamJwt;
use WonderGame\EsUtility\Common\Classes\XlsWriter;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Exception\SyncException;

/**
 * @extends BaseController
 */
trait AuthTrait
{
    protected $uploadKey = 'file';

    /////////////////////////////////////////////////////////////////////////
    /// 权限认证相关属性                                                    ///
    ///     1. 子类无需担心重写覆盖，校验时会反射获取父类属性值，并做合并操作     ///
    ///     2. 对于特殊场景也可直接重写 setPolicy 方法操作Policy              ///
    ///     3. 大小写不敏感                                                 ///
    /////////////////////////////////////////////////////////////////////////

    // 别名认证
    protected array $_authAlias = ['change' => 'edit', 'export' => 'index'];

    // 无需认证
    protected array $_authOmit = [];

    protected $isExport = false;

    protected $ignoreCheckClientVersion = true;

    protected function onRequest(?string $action): bool
    {
        $this->setAuthTraitProptected();

        $return = parent::onRequest($action);
        if (!$return)
        {
            return false;
        }

        $this->isExport = $action === 'export';
        return $this->checkAuthorization();
    }

    protected function setAuthTraitProptected()
    {
    }

    protected function checkAuthorization()
    {
        $authorization = $this->getAuthorization();
        if (!$authorization)
        {
            $this->error(Code::CODE_UNAUTHORIZED, Dictionary::ADMIN_AUTHTRAIT_1);
            return false;
        }

        // jwt验证
        $jwt = LamJwt::verifyToken($authorization, config('auth.jwtkey'));
        $id = $jwt['data']['id'] ?? '';
        if ($jwt['status'] != 1 || empty($id))
        {
            $this->error(Code::CODE_UNAUTHORIZED, Dictionary::ADMIN_AUTHTRAIT_2);
            return false;
        }

        // 客户端版本
        if (!$this->checkClientVersion($jwt))
        {
            return false;
        }

        // uid验证
        /** @var AbstractModel $Admin */
        $Admin = model('Admin');
        // 当前用户信息
        $data = $Admin->where('id', $id)->get();
        if (empty($data))
        {
            $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_3);
            return false;
        }

        if (empty($data['status']) && (!isSuper($data['rid'])))
        {
            $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_4);
            return false;
        }

        // 关联的分组信息
        $relation = $data->relation ? $data->relation->toArray() : [];
        $this->operinfo = $data->toArray();
        $this->operinfo['role'] = $relation;

        // 将管理员信息挂载到Request
        $this->request()->withAttribute('operinfo', $this->operinfo);
        return $this->checkAuth();
    }

    /**
     * 检查客户端版本
     * @param array $jwt
     * @return bool
     */
    protected function checkClientVersion(array $jwt)
    {
        if ($this->ignoreCheckClientVersion)
        {
            return true;
        }
        $sysinfo = sysinfo();
        $versionCode = [
            'version_later' => Code::VERSION_LATER,
            'version_force' => Code::VERSION_FORCE,
        ];

        foreach ($versionCode as $vKey => $code)
        {
            if (isset($jwt['data'][$vKey]) && isset($sysinfo[$vKey]) && $jwt['data'][$vKey] != $sysinfo[$vKey])
            {
                $this->error($code);
                return false;
            }
        }
        return true;
    }

    /**
     * 权限
     * @return bool
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    protected function checkAuth()
    {
        if ($this->isSuper())
        {
            return true;
        }

        $publicMethods = array_map('strtolower', array_keys($this->getAllowMethodReflections()));
        $currentAction = strtolower($this->getActionName());
        if (!in_array($currentAction, $publicMethods))
        {
            $this->error(Code::CODE_FORBIDDEN);
            return false;
        }
        $currentClassName = strtolower($this->getStaticClassName());
        $fullPath = "/$currentClassName/$currentAction";

        // 设置用户权限
        $userMenu = $this->getUserMenus();
        if (empty($userMenu))
        {
            $this->error(Code::CODE_FORBIDDEN);
            return false;
        }

        /** @var \App\Model\Menu $Menu */
        $Menu = model('Menu');
        $priv = $Menu->where('id', $userMenu, 'IN')->where('permission', '', '<>')->where('status', 1)->column('permission');
        if (empty($priv))
        {
            return true;
        }

        $policy = new Policy();
        foreach ($priv as $path)
        {
            $policy->addPath('/' . trim(strtolower($path), '/'));
        }

        $selfRef = new \ReflectionClass(self::class);
        $selfDefaultProtected = $selfRef->getDefaultProperties();
        $selfOmitAction = $selfDefaultProtected['_authOmit'] ?? [];
        $selfAliasAction = $selfDefaultProtected['_authAlias'] ?? [];

        // 无需认证操作
        if ($omitAction = array_map('strtolower', array_merge($selfOmitAction, $this->_authOmit)))
        {
            foreach ($omitAction as $omit)
            {
                in_array($omit, $publicMethods) && $policy->addPath("/$currentClassName/" . $omit);
            }
        }

        // 别名认证操作
        $aliasAction = array_change_key_case(array_map('strtolower', array_merge($selfAliasAction, $this->_authAlias)));
        if ($aliasAction && isset($aliasAction[$currentAction]))
        {
            $alias = trim($aliasAction[$currentAction], '/');
            if (strpos($alias, '/') === false)
            {
                if (in_array($alias, $publicMethods))
                {
                    $fullPath = "/$currentClassName/$alias";
                }
            } else {
                // 支持引用跨菜单的已有权限
                $fullPath = '/' . $alias;
            }
        }

        // 自定义认证操作
        $this->setPolicy($policy);

        $ok = $policy->check($fullPath) === PolicyNode::EFFECT_ALLOW;
        if (!$ok) {
            $this->error(Code::CODE_FORBIDDEN);
        }
        return $ok;
    }

    // 对于复杂场景允许自定义认证，优先级最高
    protected function setPolicy(Policy $policy)
    {

    }

    protected function setDbTimeZone(MysqliClient $client, $tzn)
    {
        $tznsql = ($tzn > 0 ? "+$tzn" : $tzn) . ':00';
        $sql = "set time_zone = '$tznsql';";
        trace($sql, 'info', 'sql');
        $client->rawQuery($sql);
    }

    protected function getDbTimeZone(MysqliClient $client, $debug = true)
    {
        $timeZone = $client->rawQuery("SHOW VARIABLES LIKE '%time_zone%'");
        if ($debug) {
            var_dump($timeZone);
        }
        return $timeZone;
    }

    protected function isSuper($rid = null)
    {
        if (is_null($rid)) {
            $rid = $this->operinfo['rid'];
        }
        return isSuper($rid);
    }

    protected function getUserMenus()
    {
        if ($this->isSuper())
        {
            return null;
        }
        $userMenu = explode(',', $this->operinfo['role']['menu'] ?? '');
        return is_array($userMenu) ? $userMenu : [];
    }

    public function add()
    {
        $this->info(__FUNCTION__);
    }

    public function edit()
    {
        $this->info(__FUNCTION__);
    }

    protected function info($name)
    {
        $rqm = $this->request()->getMethod();
        $method = $name . ucfirst(strtolower($rqm));

        try {
            // 为何用反射，因为父类中没有主动定义全部的方法，各子类也只有在用到的场景才会定义
            $ref = new \ReflectionClass(static::class);
            $methosClass = $ref->getMethod($method);
            // 反射的是自己，this调用
            $this->{ $methosClass->name }();
        }
        catch (\ReflectionException $e)
        {
            $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_5);
        }
        catch (HttpParamException | SyncException $e)
        {
            $this->error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 一般用来获取添加页需要的数据
     */
    protected function addGet()
    {
        return $this->success();
    }

    /**
     * 一般用来提交添加数据
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    protected function addPost()
    {
        $this->_writeBefore();
        $result = $this->Model->data($this->post)->save();
        $result ? $this->success() : $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_6);
    }

    protected function editPost()
    {
        $this->_writeBefore();
        $post = $this->post;
        $pk = $this->Model->getPk();
        if (!isset($post[$pk]))
        {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_7);
        }

        $model = $this->Model->where($pk, $post[$pk])->get();

        if (empty($model))
        {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_8);
        }

        $where = null;
        // 单独处理id为0值的情况，因为update传where后，data不会取差集，会每次update所有字段, 而不传$where时会走进preSetWhereFromExistModel用empty判断主键，0值会报错
        if (intval($post[$pk]) === 0)
        {
            $where = [$pk => $post[$pk]];
        }

        /*
         * update返回的是执行语句是否成功,只有mysql语句出错时才会返回false,否则都为true
         * 所以需要getAffectedRows来判断是否更新成功
         */
        $upd = $model->update($post, $where);
        if ($upd === false)
        {
            $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_9);
            trace('edit update失败: ' . $model->lastQueryResult()->getLastError());
        } else {
            $this->success();
        }
    }

    protected function editGet()
    {
        // todo 处理联合主键场景
        $pk = $this->Model->getPk();
        // 不排除id为0的情况
        if (!isset($this->get[$pk]) || $this->get[$pk] === '')
        {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_10);
        }
        $model = $this->Model->where($pk, $this->get[$pk])->get();
        if (empty($model))
        {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_11);
        }
        $data = $this->_afterEditGet($model->toArray());
        $this->success($data);
    }

    public function del()
    {
        $get = $this->get;
        $pk = $this->Model->getPk();
        if (!isset($get[$pk]))
        {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_12);
        }

        $model = $this->Model->where($pk, $get[$pk])->get();
        if (empty($model))
        {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_13);
        }

        $result = $model->destroy();
        $result ? $this->success() : $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_14);
    }

    public function change()
    {
        $post = $this->post;
        foreach (['id', 'column'] as $col)
        {
            if (!isset($post[$col]) || !isset($post[$post['column']]))
            {
                return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_15);
            }
        }

        $column = $post['column'];

        $pk = $this->Model->getPk();
        if (!isset($post[$pk]))
        {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_16);
        }

        $model = $this->Model->where($pk, $post[$pk])->get();
        if (empty($model))
        {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_17);
        }

        $where = null;
        // 单独处理id为0值的情况，因为update传where后，data不会取差集，会每次update所有字段, 而不传$where时会走进preSetWhereFromExistModel用empty判断主键，0值会报错
        if (intval($post[$pk]) === 0)
        {
            $where = [$pk => $post[$pk]];
        }

        $upd = $model->update([$column => $post[$column]], $where);
//        $rowCount = $model->lastQueryResult()->getAffectedRows();
        $upd !== false ? $this->success() : $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_18);
    }

    public function index()
    {
        $page = $this->get[config('fetchSetting.pageField')] ?? 1;          // 当前页码
        $limit = $this->get[config('fetchSetting.sizeField')] ?? 20;    // 每页多少条数据

        $where = $this->_search();

        // 处理排序
        $this->_order();

        $this->Model->scopeIndex();

        $this->Model->limit($limit * ($page - 1), $limit)->withTotalCount();
        $items = $this->Model->all($where);

        $result = $this->Model->lastQueryResult();
        $total = $result->getTotalCount();

        // 后置操作
        $data = $this->_afterIndex($items, $total);
        $this->success($data);
    }

    protected function _order()
    {
        $sortField = $this->get['_sortField'] ?? ''; // 排序字段
        $sortValue = $this->get['_sortValue'] ?? ''; // 'ascend' | 'descend'

        $order = [];
        if ($sortField && $sortValue) {
            // 去掉前端的end后缀
//            $sortValue = substr($sortValue, 0, -3);
            $sortValue = str_replace('end', '', $sortValue);
            $order[$sortField] = $sortValue;
        }

        $this->Model->setOrder($order);
        return $order;
    }

    /**
     * 因为有超级深级的JSON存在，如果需要导出全部，那么数据必须在后端处理，字段与前端一一对应
     * 不允许客户端如extension.user.sid这样取值 或者 customRender 或者 插槽渲染, 否则导出全部时无法处理
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function export()
    {
        // 处理表头，客户端应统一处理表头
        $th = [];
        if ($thStr = $this->get[config('fetchSetting.exportThField')])
        {
            // _th=ymd=日期|reg=注册|login=登录

            $thArray = explode('|', urldecode($thStr));
            foreach ($thArray as $value)
            {
                list ($thKey, $thValue) = explode('=', $value);
                // 以表头key表准
                if ($thKey) {
                    $th[$thKey] = $thValue ?? '';
                }
            }
        }

        $where = $this->_search();

        // 处理排序
        $this->_order();

        // todo 希望优化为fetch模式
        $items = $this->Model->all($where);
        $data = $this->_afterIndex($items, 0)[config('fetchSetting.listField')];

        // 是否需要合并合计行，如需合并，data为索引数组，为空字段需要占位

        // xlsWriter固定内存模式导出
        $excel = new XlsWriter();

        // 客户端response响应头获取不到Content-Disposition，用参数传文件名
        $fileName = $this->get[config('fetchSetting.exprotFilename')] ?? '';
        if (!empty($fileName))
        {
            $fileName = sprintf('export-%d-%s.xlsx', date(DateUtils::YmdHis), substr(uniqid(), -5));
        }

        $excel->ouputFileByCursor($fileName, $th, $data);
        $fullFilePath = $excel->getConfig('path') . $fileName;

        $this->response()->sendFile($fullFilePath);
//        $this->response()->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->response()->withHeader('Content-Type', MimeType::getMimeTypeByExt('xlsx'));
//        $this->response()->withHeader('Content-Type', 'application/octet-stream');
        // 客户端获取不到这个header,待调试
//        $this->response()->withHeader('Content-Disposition', 'attachment; filename=' . $fileName);
        $this->response()->withHeader('Cache-Control', 'max-age=0');
        $this->response()->end();

        // 下载完成就没有用了，延时删除掉
        Timer::getInstance()->after(1000, function () use ($fullFilePath) {
            @unlink($fullFilePath);
        });
    }

    public function upload()
    {
        try {
            /** @var \EasySwoole\Http\Message\UploadFile $file */
            $file = $this->request()->getUploadedFile($this->uploadKey);

            // todo 文件校验
            $fileType = $file->getClientMediaType();

            $clientFileName = $file->getClientFilename();
            $arr = explode('.', $clientFileName);
            $suffix = end($arr);

            $ymd = DateUtils::timeChangeZoneByTimeStamp(time(), '', '', DateUtils::_ymd);
            $join = "/{$ymd}/";

            $dir = rtrim(config('UPLOAD.dir'), '/') . $join;
            // 当前控制器名做前缀
            $arr = explode('\\', static::class);
            $prefix = end($arr);
            $fileName = uniqid($prefix . '_', true) . '.' . $suffix;

            $fullPath = $dir . $fileName;
            $file->moveTo($fullPath);
//            chmod($fullPath, 0777);

            $url = $join . $fileName;
            $this->writeUpload($url);
        }
        catch (FileException $e)
        {
            $this->writeUpload('', Code::ERROR_OTHER, $e->getMessage());
        }
    }

    public function unlink()
    {
//        $suffix = pathinfo($this->post['url'], PATHINFO_EXTENSION);
//        $info = pathinfo($this->post['url']);
//        $filename = $info['basename'];
//        // todo 文件校验, 比如子类为哪个控制器，只允许删除此前缀的
//        $suffix = $info['extension'];
//
//        // 指定目录
//        $dir = rtrim(config('UPLOAD.dir'), '/') . '/images/';
//
//        $file = $dir . $filename;
//        if (is_file($file))
//        {
//            @unlink($file);
//        }
        $this->success();
    }

    /**
     * 构造查询数据
     * @return array
     */
    protected function _search()
    {
        return null;
    }

    /**
     * 公共参数,配合where使用
     * @return array
     */
    protected function filter()
    {
        $filter = [];

        if (isset($this->get['tzn']))
        {
            $tzn = $this->get['tzn'];
            foreach (sysinfo('region_domain.region') as $k => $v)
            {
                if ($v['tzn'] == $tzn)
                {
                    $filter['tzs'] = $v['tzs'];
                    break;
                }
            }
            $filter['tzn'] = $tzn;
        }

        // begintime, beginday
        $begintime = $this->get['begintime'] ?? '';
        if ($begintime)
        {
            $begintime = strtotime($begintime . (strpos($begintime, ':') !== false ? '' : ' 00:00:00'));
            $filter['begintime'] = DateUtils::timeChangeZoneByTimeStamp($begintime, '', $filter['tzs']);
            $filter['beginday'] = DateUtils::timeChangeZoneByTimeStamp($begintime, '', $filter['tzs'], DateUtils::_ymd);
        }

        // endtime, endday
        if(isset($this->get['endtime']))
        {
            $endtime = $this->get['endtime'];
            $endtime = strtotime($endtime . (strpos($endtime, ':') !== false ? '' : ' 23:59:59'));
            $filter['endtime'] = DateUtils::timeChangeZoneByTimeStamp($endtime, '', $filter['tzs']);
            $filter['endday'] = DateUtils::timeChangeZoneByTimeStamp($endtime, '', $filter['tzs'], DateUtils::_ymd);
        }

        // gameid, pkgbnd
        // 对应extension字段
        $extColName = ['gameid' => 'gameids', 'pkgbnd' => 'pkgbnd'];
        foreach (['gameid', 'pkgbnd'] as $col)
        {
            if (isset($this->get[$col]))
            {
                $value = $this->get[$col];
                $value = explode(',', $value);
                $filter[$col] = $value;
            }
            // 非超级管理员只允许有权限的
            elseif (!$this->isSuper())
            {
                $filter[$col] = $this->operinfo['extension'][$extColName[$col]] ?? [];
            }
        }

        return $filter + $this->get;
    }

    /**
     * 列表后置操作
     * @param $items
     * @return mixed
     */
    protected function _afterIndex($items, $total)
    {
        return [config('fetchSetting.listField') => $items, config('fetchSetting.totalField') => $total];
    }

    protected function _afterEditGet($data)
    {
        return $data;
    }

    protected function _writeBefore()
    {

    }
}
