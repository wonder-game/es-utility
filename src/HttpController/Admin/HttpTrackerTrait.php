<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use EasySwoole\Mysqli\QueryBuilder;
use WonderGame\EsUtility\Common\Classes\Mysqli;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Http\Code;

/**
 * @property \App\Model\Log\HttpTracker $Model
 */
trait HttpTrackerTrait
{
    protected function instanceModel()
    {
        $this->Model = model_log($this->getStaticClassName());
        return true;
    }

    /**
     * 仅构造了where的builder，后续如何操作各自实现
     * @return QueryBuilder
     */
    protected function _builder()
    {
        $filter = $this->filter();

        if (empty($filter['begintime']) || empty($filter['endtime'])) {
            // 近1个小时
            $time = time();
            $filter['begintime'] = $time - 3600;
            $filter['endtime'] = $time;
        }

        $builder = new QueryBuilder();
        $builder->where('instime', [$filter['begintime'], $filter['endtime']], 'BETWEEN');

        foreach (['repeated', 'server_name', 'url', 'ip', 'depth'] as $col) {
            if (isset($filter[$col]) && $filter[$col] !== '') {
                $sym = strpos($filter[$col], '%') !== false ? 'LIKE' : '=';
                $builder->where($col, $filter[$col], $sym);
            }
        }

        // 注意，1. JSON值是严格类型的 2. -> 与 ->> 的区别

        // request->key
        foreach (['path'] as $col) {
            if (isset($filter[$col]) && $filter[$col] !== '') {
                $sym = strpos($filter[$col], '%') !== false ? 'LIKE' : '=';
                $builder->where("(request->'$.$col' $sym '$filter[$col]')");
            }
        }

        // 请求参数查询, GET,POST,JSON,XML
        if ( ! empty($filter['rq_key']) && isset($filter['rq_value']) && $filter['rq_value'] !== '') {

            $sym = strpos($filter['rq_value'], '%') !== false ? 'LIKE' : '=';

            $arr = [];
            foreach (['GET', 'POST', 'JSON', 'XML'] as $k) {
                $arr[] = "request->'$.$k.$filter[rq_key]' $sym '$filter[rq_value]'";
            }

            $str = implode(' OR ', $arr);
            $builder->where("($str)");
        }

        // 响应参数查询 data.result
        if ( ! empty($filter['rp_key']) && isset($filter['rp_value']) && $filter['rp_value'] !== '') {

            $sym = strpos($filter['rp_value'], '%') !== false ? 'LIKE' : '=';

            $str = "response->'$.data.result.$filter[rp_key]' $sym '$filter[rp_value]'";
            $builder->where("($str)");
        }

        // 自定义部分::
        if ($my = trim($filter['sql'])) {
            $builder->where("($my)");
        }

        return $builder;
    }

    // 预览SQL
    public function getSql($return = false, $withCount = false)
    {
        $builder = $this->_builder();

        $builder->orderBy('instime');

        $page = $this->get[config('fetchSetting.pageField')] ?? 1;
        $limit = $this->get[config('fetchSetting.sizeField')] ?? 20;
        $builder->limit($limit * ($page - 1), $limit);

        if ($withCount) {
            $builder->withTotalCount();
        }

        $builder->get($this->Model->tableName());

        if ($return) {
            return $builder;
        }

        $sql = $builder->getLastQuery();
        $this->success($sql);
    }

    // 模拟模型获取器
    protected function getAttr($data = [])
    {
        foreach ($data as $col => &$val) {
            $getter = 'get' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $col))) . 'Attr';
            if (method_exists($this->Model, $getter)) {
                $val = call_user_func([$this->Model, $getter], $val, $data);
            }
        }
        return $data;
    }

    public function index()
    {
        $builder = $this->getSql(true, true);
        $sql = $builder->getLastQuery();

        $Mysqli = new Mysqli($this->Model->getConnectionName());

        try {
            $data = $Mysqli->query($builder)->getResult();
            if (empty($data)) {
                $data = parent::__after_index() + ['sql' => $sql];
                $this->success($data);
                return;
            }

            $total = intval($Mysqli->rawQuery('SELECT FOUND_ROWS() as count')[0]['count'] ?? 0);

            $instimes = $ids = [];

            foreach ($data as &$item) {
                $ids[] = $item['point_id'];
                $instimes[] = intval($item['instime']);
                $item = $this->getAttr($item);
            }

            $min = min($instimes);
            $max = max($instimes);

            $builder = new QueryBuilder();
            $builder->where('instime', [$min, $max + 100], 'BETWEEN')
                ->where('parent_id', $ids, 'IN')
                ->get($this->Model->tableName());
            $childs = $Mysqli->query($builder)->getResult();

            $Mysqli->close();
            if ($childs) {
                // 如果point_id是子元素，从第一级删掉
                $childIds = [];
                // 判断是谁的儿子
                $childMap = [];
                foreach ($childs as &$item) {
                    $childIds[] = $item['point_id'];
                    $childMap[$item['parent_id']][] = $this->getAttr($item);
                }

                foreach ($data as $key => &$val) {
                    if (in_array($val['point_id'], $childIds)) {
                        unset($data[$key]);
                        continue;
                    }
                    if ( ! empty($childMap[$val['point_id']])) {
                        $val['children'] = $childMap[$val['point_id']];
                    }
                }
            }

            $result = parent::__after_index($this->toArray($data), $total) + ['sql' => $sql];
            $this->success($result);

        } catch (\Exception|\Throwable $e) {
            $Mysqli && $Mysqli->close();
            $this->error(Code::ERROR_OTHER, $e->getMessage());
        }
    }

    // 单条复发
    public function _repeat($return = false)
    {
        if (empty($this->post['point_id']) || empty($this->post['instime'])) {
            throw new HttpParamException('point_id 或 instime 不能为空');
        }
        $row = $this->Model->where($this->post)->get();
        if ( ! $row) {
            throw new HttpParamException("找不到原始记录 $this->post[point_id]");
        }

        $response = $row->repeatOne();
        if ( ! $response) {
            throw new HttpParamException('Http Error! ');
        }

        $data = [
            'httpStatusCode' => $response->getStatusCode(),
            'data' => json_decode($response->getBody(), true)
        ];
        return $return ? $data : $this->success($data);
    }

    // 试运行，查询count
    public function _count($return = false)
    {
        $builder = $this->_builder()->getOne($this->Model->tableName(), "count(`point_id`) as count");
        $sql = $builder->getLastQuery();

        $Mysqli = new Mysqli($this->Model->getConnectionName());
        try {

            $count = $Mysqli->query($builder)->getResultOne()['count'] ?? 0;
            $Mysqli->close();

            $data = ['count' => intval($count), 'sql' => $sql];
            return $return ? $data : $this->success($data);
        } catch (\Exception|\Throwable $e) {
            $Mysqli && $Mysqli->close();
            if ($return) {
                throw $e;
            } else {
                $this->error(Code::ERROR_OTHER, $e->getMessage(), ['sql' => $sql]);
            }
        }
    }
}
