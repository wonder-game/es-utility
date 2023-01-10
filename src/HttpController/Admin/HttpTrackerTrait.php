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
			$tomorrow = strtotime('tomorrow');
			$begintime = $tomorrow - (2 * 86400);
			$endtime = $tomorrow - 1;
			$this->Model->where('instime', [$begintime, $endtime], 'BETWEEN');
		} else {
			$this->Model->where($this->get['where']);
		}
		return null;
	}

    /**
     * 生成父子结构。为啥不是无限树形结构? 树形结构需要多查询一次，慢，并且只有上了RPC才用得上
     * @param $items
     * @param $total
     * @return mixed
     */
    protected function __after_index($items, $total)
    {
        $parentId = [];
        /** @var AbstractModel $item */
        foreach ($items as $item) {
            $parentId[] = $item->point_id;
        }

        if ($parentId) {
            $childMap = $childKey = [];
            $childs = $this->Model->where('parent_id', $parentId, 'IN')->all();
            /** @var AbstractModel $item */
            foreach ($childs as $item) {
                $childKey[] = $item['point_id'];
                $childMap[$item['parent_id']][] = $item->toArray();
            }

            $result = [];
            /** @var AbstractModel $item */
            foreach ($items as $item) {

                // 有爸爸
                if (in_array($item['point_id'], $childKey)) {
                    continue;
                }

                // 有儿子
                if ($childMap[$item['point_id']]) {
                    $item['children'] = $childMap[$item['point_id']];
                }

                $result[] = $item;
            }
        }

        return parent::__after_index($result, $total);
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
