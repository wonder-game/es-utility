<?php

namespace WonderGame\EsUtility\HttpController\Admin;

use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\AbstractModel;
use WonderGame\EsUtility\Common\Exception\HttpParamException;
use WonderGame\EsUtility\Common\Http\Code;

trait HttpTrackerTrait
{
	protected function __search()
	{
		if (empty($this->get['where'])) {
			// 默认最近14天
			$tomorrow = strtotime('tomorrow');
			$begintime = $tomorrow - (14 * 86400);
			$endtime = $tomorrow - 1;
			$this->Model->where('instime', [$begintime, $endtime], 'BETWEEN');
		} else {
			$this->Model->where($this->get['where']);
		}
		return null;

		// 已废弃
		return function (QueryBuilder $builder) {
			$filter = $this->filter();
			$builder->where('instime', [$filter['begintime'], $filter['endtime']], 'between');
			if (isset($filter['repeated']) && $filter['repeated'] !== '') {
				$builder->where('repeated', $filter['repeated']);
			}

			// envkey: {"one":"point_name","two":"point_id"}
			// envvalue: {"one":"123","two":"4556"}
			foreach (['envkey', 'envvalue'] as $col) {
				if ( ! empty($filter[$col])) {
					$filter[$col] = json_decode($filter[$col], true);
				}
			}

			if ( ! empty($filter['envkey'])) {
				foreach ($filter['envkey'] as $key => $value) {
					if ($like = $filter['envvalue'][$key]) {
						$calc = true;
						// 支持逻辑运算转换为like
						$symbol = ['&&' => ' AND ', '||' => ' OR '];
						foreach ($symbol as $sym => $join) {
							if (strpos($like, $sym) !== false) {
								$tmp = [];
								$arr = explode($sym, $like);
								foreach ($arr as $item) {
									$item && $tmp[] = "$value LIKE '%{$item}%'";
								}
								if ($tmp) {
									$tmp = implode($join, $tmp);
									$builder->where("($tmp)");
									$calc = false;
								}
							}
						}
						if ($calc) {
							$builder->where($value, "%{$like}%", 'LIKE');
						}
					}
				}
			}

			$runtime = $filter['runtime'] ?? 0;
			if ($runtime > 0) {
				$builder->where('runtime', $runtime, '>=');
			}
			/*
			 * 生成的SQL分析示例
			 * explain partitions SELECT SQL_CALC_FOUND_ROWS * FROM `http_tracker` WHERE  `instime` between 1646197200 AND 1647493199  AND `point_name` LIKE '%123%'  AND (point_id LIKE '%4556%' AND point_id LIKE '%789%') ORDER BY instime DESC  LIMIT 0, 100\G
			 * */
		};
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
				$model = model('HttpTracker');
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
