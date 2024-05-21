<?php

namespace WonderGame\EsUtility\Common\Classes;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\Spl\SplBean;

class Tree extends SplBean
{
    /**
     * 初始数据，执行完initialize之后就无了
     * @var array
     */
    protected $data = [];

    /**
     * 根元素id
     * todo 支持所有节点，和一些相关方法
     * @var int
     */
    protected $rootId = 0;

    /**
     * 可用id的列表, null-不限制
     * @var null | array | int
     */
    protected $filterIds = null;

    /**
     * 客户端路由格式
     * @var bool
     */
    protected $isRouter = false;

    protected $idKey = 'id';
    protected $pidKey = 'pid';
    protected $childKey = 'children';

    /**
     * 树形数据
     * @var array
     */
    private $tree = [];

    private $origin = [];
    private $parent = [];
    private $children = [];

    protected function initialize(): void
    {
        foreach ($this->data as $value) {
            if ($value instanceof AbstractModel) {
                $value = $value->toArray();
            }
            $this->setNode($value[$this->idKey], $value[$this->pidKey], $value);
        }
        unset($this->data);

        $this->filters();
        $this->toRouter();
    }

    protected function setNode($id, $pid = 0, $data = [])
    {
        $this->origin[$id] = $data;
        $this->children[$pid][$id] = $id;
        $this->parent[$id] = $pid;
    }

    /**
     * 所有祖先元素的id
     * @param array $ids
     * @return array 一维
     */
    protected function getParents(array $ids = [])
    {
        $idArray = $ids;
        foreach ($ids as $id) {
            if (isset($this->parent[$id])) {
                $pids = $this->getParents([$this->parent[$id]]);
                if (is_array($pids)) {
                    $idArray = array_merge($idArray, $pids);
                }
            }
        }
        return array_unique($idArray);
    }

    /**
     * 根据filterIds过滤某些数据
     * @return void
     */
    protected function filters()
    {
        if ( ! is_null($this->filterIds)) {

            if (is_numeric($this->filterIds)) {
                $this->filterIds = [$this->filterIds];
            }

            $allow = $this->getParents($this->filterIds);
            foreach ($this->origin as $key => $value) {
                if ( ! in_array($key, $allow)) {
                    unset($this->origin[$key]);
                }
            }
        }
    }

    /**
     * 生成完整树形结构
     * @return array
     */
    public function treeData()
    {
        $tree = $this->origin;
        foreach ($this->origin as $id => $value) {
            if ($value[$this->pidKey] == $this->rootId) {
                continue;
            }

            // children也是索引数组
            $len = count($tree[$value[$this->pidKey]][$this->childKey] ?? []);
            $tree[$value[$this->pidKey]][$this->childKey][$len] = $tree[$id];
            $tree[$id] = &$tree[$value[$this->pidKey]][$this->childKey][$len];
        }

        foreach ($tree as $item) {
            if (isset($item[$this->pidKey]) && $item[$this->pidKey] == $this->rootId) {
                $this->tree[] = $item;
            }
        }

        return $this->tree;
    }

    /****************** 以下为vue-router相关方法 *******************/

    /**
     * 将源数据转换为vue-router格式
     * @return void
     * @throws \EasySwoole\Validate\Exception\Runtime
     */
    protected function toRouter()
    {
        if ( ! $this->isRouter) {
            return;
        }
        foreach ($this->origin as &$value) {
            // 构造树形结构必须的几个key
            $row = [
                $this->idKey => $value[$this->idKey],
                $this->pidKey => $value[$this->pidKey],
            ];
            foreach (['path', 'component', 'name', 'redirect',] as $col) {
                $row[$col] = $value[$col] ?? '';
            }

            // meta,强类型,对应types/vue-router.d.ts
            $meta = [
                'orderNo' => intval($value['sort']),
                'title' => $value['title'],
                'ignoreKeepAlive' => $value['keepalive'] != 1,
                'affix' => $value['affix'] == 1,
                'icon' => $value['icon'],
                'hideMenu' => $value['isshow'] != 1,
                'hideBreadcrumb' => $value['breadcrumb'] != 1
            ];
            // 外部链接, isext=1为外链，=0为frameSrc
            $validate = new \EasySwoole\Validate\Validate();
            $validate->addColumn('path')->url();
            $validate->addColumn('isext')->differentWithColumn(1);
            $isFrame = $validate->validate($value);
            if ($isFrame) {
                $meta['frameSrc'] = $value['path'];
                // 当为内嵌时，path已经不需要了，但优先级比frameSrc高，需要覆盖掉path为非url
                $row['path'] = $value['name'] ?? '';
            }
            $row['meta'] = $meta;
            $value = $row;
        }
    }

    /**
     * 获取某一个菜单的完整path，对应vben的homePath字段
     * @param array|int|null $id 菜单id
     * @param string $column
     * @return string
     */
    public function getHomePage($id = null, $column = 'path')
    {
        // 不传则使用filterIds
        if (is_null($id)) {
            $id = $this->filterIds;
        }
        $id = is_array($id) ? $id[0] : $id;
        $path = $this->getFullPath($id, $column);
        return implode('/', array_reverse($path));
    }

    /**
     * 指定id的完整path路径，有序
     * @param int $id
     * @param int $i
     * @return array
     */
    protected function getFullPath($id, $column = '', $i = 0)
    {
        $path = [];
        if (isset($this->origin[$id][$column])) {
            $path[$i] = $this->origin[$id][$column];
            if (isset($this->parent[$id])) {
                $array = $this->getFullPath($this->parent[$id], $column, ++$i);
                $path = array_merge($path, $array);
            }
        }
        return $path;
    }
}
