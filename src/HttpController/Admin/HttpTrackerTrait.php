<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\AbstractModel;
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

    protected function __search()
	{
		if (empty($this->get['where'])) {
            $begintime = strtotime('today');
			$endtime = $begintime + 86399;
			$this->Model->where('instime', [$begintime, $endtime], 'BETWEEN');
		} else {
			$this->Model->where($this->get['where']);
		}
		return null;
	}

    protected function __with($column = 'relation')
    {
        $this->Model->with(['children']);
        return $this;
    }

    /**
     * @param $items
     * @param $total
     * @return mixed
     */
    protected function __after_index($items, $total)
    {
        $relationes = $childKey = $result = [];
        /** @var AbstractModel $item */
        foreach ($items as $item) {
            $array = $item->toArray(false, false);

            // 成为子元素后，从第一级删掉
            if (is_array($array['children'])) {
                $childKey = array_merge($childKey, array_column($array['children'], 'point_id'));
            }

            $relationes[] = $array;
        }

        unset($items);
        // 第一次foreach时，$childKey还不全
        foreach ($relationes as $relatione) {
            if ( ! in_array($relatione['point_id'], $childKey)) {
                $result[] = $relatione;
            }
        }
        return parent::__after_index($result, $total) + [
                'sql' => str_replace('SQL_CALC_FOUND_ROWS', '', $this->Model->lastQuery()->getLastQuery())
            ];
    }

	// 单条复发
    public function _repeat($return = false)
	{
		$pointId = $this->post['pointId'];
		if (empty($pointId)) {
			throw new HttpParamException('PointId id empty.');
		}
		$row = $this->Model->where('point_id', $pointId)->get();
		if ( ! $row) {
			throw new HttpParamException('PointId id Error: ' . $pointId);
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
		$where = $this->post['where'];
		if (empty($where)) {
			throw new HttpParamException('ERROR is Empty');
		}
		try {
			$count = $this->Model->where($where)->count('point_id');
            $data = ['count' => $count];
			return $return ? $data : $this->success($data);
		} catch (\Exception | \Throwable $e) {
            if ($return) {
                throw $e;
            } else {
                $this->error(Code::ERROR_OTHER, $e->getMessage());
            }
		}
	}

	// 确定运行
    public function _run($return = false)
	{
		$where = $this->post['where'];
		if (empty($where)) {
			throw new HttpParamException('run ERROR is Empty');
		}
		try {
			$count = $this->Model->where($where)->count('point_id');
			if ($count <= 0) {
				throw new HttpParamException('COUNT行数为0');
			}
			$task = \EasySwoole\EasySwoole\Task\TaskManager::getInstance();
//            $status = $task->async(new \App\Task\HttpTracker([
//                'count' => $count,
//                'where' => $where
//            ]));
			$status = $task->async(function () use ($where) {
				trace('HttpTracker 开始 ');

				/** @var AbstractModel $model */
				$model = model_log('HttpTracker');
				$model->where($where)->chunk(function ($item) {
					$item->repeatOne();
				}, 300);
				trace('HttpTracker 结束 ');
			});
			if ($status > 0) {
                $data = ['count' => $count, 'task' => $status];
				return $return ? $data : $this->success($data);
			} else {
				throw new HttpParamException("投递异步任务失败: $status");
			}
		} catch (HttpParamException | \Exception | \Throwable $e) {
			if ($return) {
                throw $e;
            } else {
                $this->error(Code::ERROR_OTHER, $e->getMessage());
            }
		}
	}
}
