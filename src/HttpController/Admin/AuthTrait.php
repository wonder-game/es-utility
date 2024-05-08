<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use App\HttpController\Base;
use EasySwoole\Component\Timer;
use EasySwoole\Http\Exception\FileException;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\Policy\Policy;
use EasySwoole\Policy\PolicyNode;
use EasySwoole\Utility\MimeType;
use WonderGame\EsUtility\Common\Classes\CtxRequest;
use WonderGame\EsUtility\Common\Classes\DateUtils;
use WonderGame\EsUtility\Common\Classes\Mysqli;
use WonderGame\EsUtility\Common\Classes\XlsWriter;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Http\Code;
use WonderGame\EsUtility\Common\Languages\Dictionary;

/**
 * @extends Base
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
        $this->setAuthTraitProtected();

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
        $Admin = model_admin('Admin');
        // 当前用户信息
        return $Admin->where('id', $id)->get();
    }

    protected function setAuthTraitProtected()
    {
    }

    protected function checkAuthorization()
    {
        // jwt验证
        try {
            $jwt = verify_token([], 'id');
        } catch (HttpParamException $e) {
            // jwt认证失败必须返回401，否则无法跳转登录页
            $this->error(Code::CODE_UNAUTHORIZED, $e->getMessage());
            return false;
        }

        // 当前用户信息
        $data = $this->_getEntityData($jwt['id']);
        if (empty($data)) {
            $this->error(Code::CODE_UNAUTHORIZED, Dictionary::ADMIN_AUTHTRAIT_3);
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

        $this->operinfoAfter();

        // 将管理员信息挂载到Request
        CtxRequest::getInstance()->withOperinfo($this->operinfo);
        return $this->checkAuth();
    }

    protected function operinfoAfter()
    {

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

        $Menu = model_admin('Menu');
        // 设置用户权限
        $userMenu = $this->getUserMenus();
        if ( ! is_null($userMenu)) {
            if (empty($userMenu)) {
                $this->error(Code::CODE_FORBIDDEN);
                return false;
            }
            $Menu->where('id', $userMenu, 'IN');
        }

        $priv = $Menu->where('permission', '', '<>')->where('status', 1)->column('permission');
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
        if (empty($this->operinfo['role']['chk_menu'])) {
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

    /**
     * @return bool|int|void
     */
    public function _add($return = false)
    {
        if ($this->isHttpPost()) {
            $result = $this->Model->data($this->post)->save();
            if ($return) {
                return $result;
            }
            return $result ? $this->success() : $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_6);
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
            $upd = $model->setExtSave($this->Model->getExtSave())->update($request, $where);
            if ($upd === false) {
                trace('edit update失败: ' . $model->lastQueryResult()->getLastError());
                throw new HttpParamException(lang(Dictionary::ADMIN_AUTHTRAIT_9));
            }
        }

        return $return ? $model->toArray() : $this->success($model->toArray());
    }

    /**
     * @param bool $return 是否需要返回目标数据
     */
    public function _del($return = false)
    {
        $model = $this->__getModel();
        $result = $model->destroy();
        if ($return) {
            return $model->toArray();
        }
        return $result ? $this->success() : $this->error(Code::ERROR_OTHER, Dictionary::ADMIN_AUTHTRAIT_14);
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

    protected function __after_index($items = [], $total = 0, $summer = [])
    {
        return [config('fetchSetting.listField') => $items, config('fetchSetting.totalField') => $total] + ($summer ? [config('fetchSetting.footerField') => $summer] : []);
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

    /**
     * 排序，支持叠加
     */
    protected function __order()
    {
        $sortField = $this->get['_sortField'] ?? ''; // 排序字段
        $sortValue = $this->get['_sortValue'] ?? ''; // 'ascend' | 'descend'

        $order = [];
        if ($sortField && $sortValue) {
            $sortField = explode(',', $sortField);
            // 去掉前端的end后缀
            $sortValue = explode(',', str_replace('end', '', $sortValue));
            foreach ($sortField as $k => $v) {
                // 如无对应的指定顺序则保持与第一个字段的相同
                $order[$v] = $sortValue[$k] ?? $sortValue[0];
            }
        }

        if ($this->isExport) {
            $sort = $this->Model->sort;
            // 'id desc'
            if (is_string($sort)) {
                list($sortField, $sortValue) = explode(' ', $sort);
                $order[$sortField] = $sortValue;
            } // ['sort' => 'desc'] || ['sort' => 'desc', 'id' => 'asc']
            else if (is_array($sort)) {
                // 保证传值的最高优先级
                foreach ($sort as $k => $v) {
                    if ( ! isset($order[$k])) {
                        $order[$k] = $v;
                    }
                }
            }
        } else {
            $this->Model->setOrder($order);
        }
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

        // fetch模式+固定内存导出
        $connectName = $this->Model->getConnectionName();
        $Mysql = new Mysqli($connectName);
        $Builder = new QueryBuilder();

        // 处理where,此处where仅支持array与funciton，方法内请勿直接调用$this->Model->where()
        $where = $this->__with()->__search();
        if (is_callable($where)) {
            call_user_func($where, $Builder);
        } elseif (is_array($where)) {
            foreach ($where as $wk => $wv) {
                if ( ! is_array($wv)) {
                    $Builder->where($wk, $wv);
                } else {
                    $Builder->where($wk, ...$wv);
                }
            }
        }

        // 处理排序
        $order = $this->__order();
        foreach ($order as $ok => $ov) {
            $Builder->orderBy($ok, $ov);
        }

        $Builder->get($this->Model->getTableName());

        $Gener = $Mysql->fetch($Builder, $this->Model);

        // xlsWriter固定内存模式导出
        $excel = new XlsWriter();

        // 客户端response响应头获取不到Content-Disposition，用参数传文件名
        $fileName = $this->get[config('fetchSetting.exprotFilename')] ?? '';
        if (empty($fileName)) {
            $fileName = sprintf('export-%d-%s.xlsx', date(DateUtils::YmdHis), substr(uniqid(), -5));
        }

        $excel->ouputFileByCursor($fileName, $th, $Gener, function ($alldata) {
            return $this->__after_index($alldata)[config('fetchSetting.listField')];
        });
        $fullFilePath = $excel->getConfig('path') . $fileName;

        $Mysql->close();

        $this->response()->sendFile($fullFilePath);
//        $this->response()->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->response()->withHeader('Content-Type', MimeType::getMimeTypeByExt('xlsx'));
//        $this->response()->withHeader('Content-Type', 'application/octet-stream');
        // 客户端获取不到这个header,待调试
//        $this->response()->withHeader('Content-Disposition', 'attachment; filename=' . $fileName);
        $this->response()->withHeader('Cache-Control', 'max-age=0');
        $this->response()->end();

        \Swoole\Coroutine::defer(function () use ($fullFilePath) {
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
            $ctlname = strtolower($this->getStaticClassName());
            $fileName = uniqid($ctlname . '_', true) . '.' . $suffix;

            $fullPath = $dir . $fileName;
            $file->moveTo($fullPath);
//            chmod($fullPath, 0777);

            $url = $join . $fileName;
            $this->writeUpload($url);
        } catch (FileException $e) {
            $this->writeUpload('', Code::ERROR_OTHER, $e->getMessage());
        }
    }

    // 分片上传的主目录
    protected function getUploadPartDir()
    {
        $ctlname = strtolower($this->getStaticClassName());
        $dir = rtrim(config('UPLOAD.dir'), '/') . "/$ctlname";
        is_dir($dir) or @ mkdir($dir, 0777, true);
        return $dir;
    }

    /**
     * 大文件分片上传，合并的操作耗时稍久，记得将客户端timeout设置大一些
     * 1. 创建分片id， type=start
     * 2. 上传每一个分片文件（客户端将File切割为Blob[]）
     * 3. 合并所有分片，type=end
     * @return void
     * @throws \HttpParamException
     */
    public function _uploadPart($return = false)
    {
        if ($this->input['type'] === 'start') {

            $ext = pathinfo($this->input['filename'], PATHINFO_EXTENSION);
            mt_srand();
            $uploadId = uniqid("$ext-") . '-' . mt_rand(10000, 99999);

            return $return ? $uploadId : $this->success($uploadId);
        }
        elseif ($this->input['type'] === 'end')
        {
            if (empty($this->input['upload_id'])) {
                throw new HttpParamException("参数错误：upload_id is empty");
            }

            $uploadId = $this->input['upload_id'];

            $dir = $this->getUploadPartDir();
            $relpath = "/$uploadId/";
            $dir .= $relpath;

            // 保持上传文件名不变
            $filename = $this->input['filename'];

            $parts = scandir($dir);
            // $parts = array_diff($parts, ['.', '..', '.upload']);
            // 过滤非分片标识符
            $parts = array_filter($parts, function ($val) { return is_numeric($val); });
            if (empty($parts)) {
                throw new HttpParamException('分片文件未上传');
            }

            sort($parts);

            $fp = fopen($dir . $filename, 'a');
            foreach ($parts as $part) {
                fwrite($fp, file_get_contents($dir . $part));
                @ unlink($dir . $part);
            }
            fclose($fp);

            // 相对路径
            $localPath = $relpath . $filename;
            return $return ? $localPath : $this->success($localPath);
        } else {
            /** @var \EasySwoole\Http\Message\UploadFile $file */
            $file = $this->request()->getUploadedFile($this->uploadKey);

            $uploadId = $this->input['upload_id'];
            if (empty($uploadId) || ! isset($this->input['part'])) {
                throw new HttpParamException('没有上传标识符');
            }
            $dir = $this->getUploadPartDir();
            $relpath = "/$uploadId/";
            $dir .= $relpath;
            is_dir($dir) or mkdir($dir, 0777, true);

            $file->moveTo($dir . $this->input['part']);
            // 相对路径
            $localPath = $relpath . $this->input['part'];
            return $return ? $localPath : $this->writeUpload($localPath);
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
            $filter['beginfmt'] = date(DateUtils::FMT_1, $filter['begintime']);
        }

        if (isset($filter['endtime'])) {
            if (strpos($filter['endtime'], ':') === false) {
                $filter['endtime'] .= ' 23:59:59';
            }

            $filter['endtime'] = strtotime($filter['endtime']);
            $filter['endday'] = date(DateUtils::YMD, $filter['endtime']);
            $filter['endfmt'] = date(DateUtils::FMT_1, $filter['endtime']);
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
