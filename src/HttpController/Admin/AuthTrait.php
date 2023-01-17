<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use App\HttpController\BaseController;
use EasySwoole\Component\Timer;
use EasySwoole\Http\Exception\FileException;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\Policy\Policy;
use EasySwoole\Policy\PolicyNode;
use EasySwoole\Utility\MimeType;
use WonderGame\EsUtility\Common\Classes\CtxRequest;
use WonderGame\EsUtility\Common\Classes\DateUtils;
use WonderGame\EsUtility\Common\Classes\LamJwt;
use WonderGame\EsUtility\Common\Classes\XlsWriter;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;

/**
 * @extends BaseController
 */
trait AuthTrait
{
    protected $operinfo = [];

    protected $uploadKey = 'file';

    /**********************************************************************
    * 权限认证相关属性                                                      *
    *     1. 子类无需担心重写覆盖，校验时会反射获取父类属性值，并做合并操作       *
    *     2. 对于特殊场景也可直接重写 setPolicy 方法操作Policy                *
    *     3. 大小写不敏感                                                   *
    ***********************************************************************/

    // 别名认证
    protected array $_authAlias = ['change' => 'edit', 'export' => 'index'];

    // 无需认证
    protected array $_authOmit = ['upload', 'options'];

    protected $isExport = false;

    protected function onRequest(?string $action): ?bool
    {
        $this->setAuthTraitProptected();

        $return = parent::onRequest($action);
        if ( ! $return) {
            return false;
        }

        $this->isExport = $action === 'export';
        return $this->checkAuthorization();
    }

    // 返回主体数据
    protected function _getEntityData($id = 0)
    {
        /** @var AbstractModel $Admin */
        $Admin = model_admin('Admin');
        // 当前用户信息
        return $Admin->where('id', $id)->get();
    }

    protected function _verifyToken($authorization = '')
    {
        return LamJwt::verifyToken($authorization, config('auth.jwtkey'));
    }

    protected function setAuthTraitProptected()
    {
    }

    protected function checkAuthorization()
    {
        $authorization = $this->getAuthorization();
        if ( ! $authorization) {
            $this->error(Code::CODE_UNAUTHORIZED, Dictionary::ADMIN_AUTHTRAIT_1);
            return false;
        }

        // jwt验证
        $jwt = $this->_verifyToken($authorization);

        $id = $jwt['data']['id'] ?? '';
        if ($jwt['status'] != 1 || empty($id)) {
            $this->error(Code::CODE_UNAUTHORIZED, Dictionary::ADMIN_AUTHTRAIT_2);
            return false;
        }

        // 当前用户信息
        $data = $this->_getEntityData($id);
        if (empty($data)) {
            $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_3);
            return false;
        }

        if (empty($data['status']) && ( ! is_super($data['rid']))) {
            $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_4);
            return false;
        }

        // 关联的分组信息
        $relation = $data->relation ? $data->relation->toArray() : [];
        $this->operinfo = $data->toArray();
        $this->operinfo['role'] = $relation;

        // 将管理员信息挂载到Request
        CtxRequest::getInstance()->withOperinfo($this->operinfo);
        return $this->checkAuth();
    }

    /**
     * 权限
     * @return bool
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    protected function checkAuth()
    {
        if ($this->isSuper()) {
            return true;
        }

        $publicMethods = $this->getAllowMethods('strtolower');

        $currentAction = strtolower($this->getActionName());
        if ( ! in_array($currentAction, $publicMethods)) {
            $this->error(Code::CODE_FORBIDDEN);
            return false;
        }
        $currentClassName = strtolower($this->getStaticClassName());
        $fullPath = "/$currentClassName/$currentAction";

        // 设置用户权限
        $userMenu = $this->getUserMenus();
        if (empty($userMenu)) {
            $this->error(Code::CODE_FORBIDDEN);
            return false;
        }

        /** @var AbstractModel $Menu */
        $Menu = model_admin('Menu');
        $priv = $Menu->where('id', $userMenu, 'IN')->where('permission', '', '<>')->where('status', 1)->column('permission');
        if (empty($priv)) {
            return true;
        }

        $policy = new Policy();
        foreach ($priv as $path) {
            $policy->addPath('/' . trim(strtolower($path), '/'));
        }

        $selfRef = new \ReflectionClass(self::class);
        $selfDefaultProtected = $selfRef->getDefaultProperties();
        $selfOmitAction = $selfDefaultProtected['_authOmit'] ?? [];
        $selfAliasAction = $selfDefaultProtected['_authAlias'] ?? [];

        // 无需认证操作
        if ($omitAction = array_map('strtolower', array_merge($selfOmitAction, $this->_authOmit))) {
            foreach ($omitAction as $omit) {
                in_array($omit, $publicMethods) && $policy->addPath("/$currentClassName/" . $omit);
            }
        }

        // 别名认证操作
        $aliasAction = array_change_key_case(array_map('strtolower', array_merge($selfAliasAction, $this->_authAlias)));
        if ($aliasAction && isset($aliasAction[$currentAction])) {
            $alias = trim($aliasAction[$currentAction], '/');
            if (strpos($alias, '/') === false) {
                if (in_array($alias, $publicMethods)) {
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
        if ( ! $ok) {
            $this->error(Code::CODE_FORBIDDEN);
        }
        return $ok;
    }

    // 对于复杂场景允许自定义认证，优先级最高
    protected function setPolicy(Policy $policy)
    {

    }

    protected function isSuper($rid = null)
    {
        return is_super(is_null($rid) ? $this->operinfo['rid'] : $rid);
    }

    protected function getUserMenus()
    {
        if ($this->isSuper()) {
            return null;
        }
        return $this->operinfo['role']['menu'] ?? [];
    }

    protected function ifRunBeforeAction()
    {
        foreach (['__before__common', '__before_' . $this->getActionName()] as $beforeAction) {
            if (method_exists(static::class, $beforeAction)) {
                $this->$beforeAction();
            }
        }
    }

    protected function __getModel(): AbstractModel
    {
        $request = array_merge($this->get, $this->post);

        if ( ! $this->Model instanceof AbstractModel) {
            throw new HttpParamException('Model Not instanceof AbstractModel !');
        }

        $pk = $this->Model->getPk();
        // 不排除id为0的情况
        if ( ! isset($request[$pk]) || $request[$pk] === '') {
            throw new HttpParamException(lang(Dictionary::ADMIN_AUTHTRAIT_10));
        }
        $model = $this->Model->where($pk, $request[$pk])->get();
        if (empty($model)) {
            throw new HttpParamException(lang(Dictionary::ADMIN_AUTHTRAIT_11));
        }

        return $model;
    }

    public function _add($return = false)
    {
        if ($this->isHttpPost()) {
            $result = $this->Model->data($this->post)->save();
            if ($return) {
                return $result;
            } else {
                $result ? $this->success() : $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_6);
            }
        }
    }

    public function _edit($return = false)
    {
        $pk = $this->Model->getPk();
        $model = $this->__getModel();
        $request = array_merge($this->get, $this->post);

        if ($this->isHttpPost()) {

            $where = null;
            // 单独处理id为0值的情况，因为update传where后，data不会取差集，会每次update所有字段, 而不传$where时会走进preSetWhereFromExistModel用empty判断主键，0值会报错
            if (intval($request[$pk]) === 0) {
                $where = [$pk => $request[$pk]];
            }

            /*
             * update返回的是执行语句是否成功,只有mysql语句出错时才会返回false,否则都为true
             * 所以需要getAffectedRows来判断是否更新成功
             * 只要SQL没错误就认为成功
             */
            $upd = $model->update($request, $where);
            if ($upd === false) {
                trace('edit update失败: ' . $model->lastQueryResult()->getLastError());
                throw new HttpParamException(lang(Dictionary::ADMIN_AUTHTRAIT_9));
            }
        }

        return $return ? $model->toArray() : $this->success($model->toArray());
    }

    public function _del($return = false)
    {
        $model = $this->__getModel();
        $result = $model->destroy();
        if ($return) {
            return $model->toArray();
        } else {
            $result ? $this->success() : $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_14);
        }
    }

    public function _change($return = false)
    {
        $post = $this->post;
        $pk = $this->Model->getPk();
        if ( ! isset($post[$pk]) || ( ! isset($post[$post['column']]) && ! isset($post['value']))) {
            return $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_15);
        }

        $column = $post['column'];

        $model = $this->__getModel();

        $where = null;
        // 单独处理id为0值的情况，因为update传where后，data不会取差集，会每次update所有字段, 而不传$where时会走进preSetWhereFromExistModel用empty判断主键，0值会报错
        if (intval($post[$pk]) === 0) {
            $where = [$pk => $post[$pk]];
        }

        $value = $post[$column] ?? $post['value'];
        if (strpos($column, '.') === false) {
            // 普通字段
            $upd = $model->update([$column => $value], $where);
        } else {
            // json字段
            list($one, $two) = explode('.', $column);
            $upd = $model->update([$one => QueryBuilder::func(sprintf("json_set($one, '$.%s','%s')", $two, $value))], $where);
        }

//        $rowCount = $model->lastQueryResult()->getAffectedRows();
        if ($upd === false) {
            throw new HttpParamException(lang(Dictionary::ADMIN_AUTHTRAIT_18));
        }
        return $return ? $model->toArray() : $this->success();
    }

    // index在父类已经预定义了，不能使用actionNotFound模式
    public function index()
    {
        return $this->_index();
    }

    public function _index($return = false)
    {
        if ( ! $this->Model instanceof AbstractModel) {
            throw new HttpParamException(lang(Dictionary::PARAMS_ERROR));
        }

        $page = $this->get[config('fetchSetting.pageField')] ?? 1;          // 当前页码
        $limit = $this->get[config('fetchSetting.sizeField')] ?? 20;    // 每页多少条数据

        $this->__with();
        $where = $this->__search();

        // 处理排序
        $this->__order();

        $this->Model->scopeIndex();

        $this->Model->limit($limit * ($page - 1), $limit)->withTotalCount();
        $items = $this->Model->all($where);

        $result = $this->Model->lastQueryResult();
        $total = $result->getTotalCount();

        $data = $this->__after_index($items, $total);
        return $return ? $data : $this->success($data);
    }

    protected function __after_index($items, $total)
    {
        return [config('fetchSetting.listField') => $items, config('fetchSetting.totalField') => $total];
    }

    protected function __with($column = 'relation')
    {
        $origin = $this->Model->getWith();
        $exist = is_array($origin) && in_array($column, $origin);
        if ( ! $exist && method_exists($this->Model, $column)) {
            $with = is_array($origin) ? array_merge($origin, [$column]) : [$column];
            $this->Model->with($with);
        }
        return $this;
    }

    protected function __order()
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
        if ($thStr = $this->get[config('fetchSetting.exportThField')]) {
            // _th=ymd=日期|reg=注册|login=登录

            $thArray = explode('|', urldecode($thStr));
            foreach ($thArray as $value) {
                list ($thKey, $thValue) = explode('=', $value);
                // 以表头key表准
                if ($thKey) {
                    $th[$thKey] = $thValue ?? '';
                }
            }
        }

        $where = $this->__with()->__search();

        // 处理排序
        $this->__order();

        // todo 希望优化为fetch模式
        $items = $this->Model->all($where);
        $data = $this->__after_index($items, 0)[config('fetchSetting.listField')];

        // 是否需要合并合计行，如需合并，data为索引数组，为空字段需要占位

        // xlsWriter固定内存模式导出
        $excel = new XlsWriter();

        // 客户端response响应头获取不到Content-Disposition，用参数传文件名
        $fileName = $this->get[config('fetchSetting.exprotFilename')] ?? '';
        if (empty($fileName)) {
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

            $ymd = date(DateUtils::YMD);
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
        } catch (FileException $e) {
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
     * 可在具体的控制器的【基本组件里(即：use xxxTrait 的 xxxTrait里)】重写此方法以实现如有个性化的搜索条件
     * @return array
     */
    protected function __search()
    {
        // 。。。。这里一般是基本组件的构造where数组的代码
        return $this->_search([]);
    }

    /**
     * 构造查询数据
     * 可在具体的控制器【内部】重写此方法以实现如有个性化的搜索条件
     * @return array
     */
    protected function _search($where = [])
    {
        // 。。。。这里一般是控制器的构造where数组的代码
        return $where;
    }

    /**
     * 公共参数,配合where使用
     * 考虑到有时会有大数据量的搜索条件，特意使用$this->input 而不是 $this->get
     * @return array
     */
    protected function filter()
    {
        $filter = $this->input;

        if (isset($filter['begintime'])) {
            if ((strpos($filter['begintime'], ':') === false)) {
                $filter['begintime'] .= ' 00:00:00';
            }

            $filter['begintime'] = strtotime($filter['begintime']);
            $filter['beginday'] = date(DateUtils::YMD, $filter['begintime']);
        }

        if (isset($filter['endtime'])) {
            if (strpos($filter['endtime'], ':') === false) {
                $filter['endtime'] .= ' 23:59:59';
            }

            $filter['endtime'] = strtotime($filter['endtime']);
            $filter['endday'] = date(DateUtils::YMD, $filter['endtime']);
        }


        $extColName = ['gameid', 'pkgbnd', 'adid'];

        // 特意让$filter拥有以下这几个key的成员，值至少为[]
        // 这样外围有需要可直接写 $filter['XXX'] && ....，而不需要写isset($filter['XXX']) && $filter['XXX'] && ....
        foreach ([... $extColName, 'status'] as $col) {
            $filter[$col] = (isset($filter[$col]) && $filter[$col] !== '') ? explode(',', ($filter[$col])) : [];
        }

        // 非超级管理员只允许有权限的
        if ( ! $this->isSuper()) {

            foreach ($extColName as $col) {
                // 是否需要校验相关权限
                $isChk = ! empty($this->operinfo['role']['chk_' . $col]);
                if ($isChk) {
                    $my = $this->operinfo['role'][$col] ?? [];
                    // 故意造一个不存在的值
                    $my = $my ?: [-1];
                    $filter[$col] = $filter[$col] ? array_intersect($my, $filter[$col]) : $my;
                }
            }
        }

        // 地区
        $filter['area'] = $filter['area'] ?? '';

        return $filter;
    }

    // 生成OptionsItem[]结构
    protected function __options($where = null, $label = 'name', $value = 'id', $return = false)
    {
        $options = $this->Model->field([$label, $value])->all($where);
        $result = [];
        foreach ($options as $option) {
            $result[] = [
                'label' => $option->getAttr($label),
                'value' => $option->getAttr($value),
            ];
        }
        return $return ? $result : $this->success($result);
    }
}
