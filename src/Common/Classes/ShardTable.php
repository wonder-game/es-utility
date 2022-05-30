<?php
/**
 * 分表分区类
 *
 * 对表进行分区需要先建立好对应的分区表
 * 主要按照时间和range 类型进行分表和分区
 * @author lamson
 *
 */

namespace WonderGame\EsUtility\Common\Classes;


use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;
use EasySwoole\Spl\SplBean;

class ShardTable extends SplBean
{
    /**
     * 连接池name
     * @var string
     */
    protected $connectionName = 'default';

    /**
     * 是否原生模式（原生模式直接执行SQL，不绑定参数）
     * @var bool
     */
    protected $raw = true;

    // 执行原生SQL并获取Result
	protected function query($sql = '')
	{
		return $this->execute($sql)->getResult();
	}

	protected function execute($sql = '')
	{
        $Builder = new QueryBuilder();
        $Builder->raw($sql);
		return DbManager::getInstance()->query($Builder, $this->raw, $this->connectionName);
	}

	/**
	 * 按日,月,年时间分表
	 * @param string|array $table 表名
	 * @param int $sdate 开始时间,格式20180101
	 * @param int $edate 结束时间,格式20180203
	 * @param string $field 分区字段
	 * @param int $type 分区类型。1：日； 2：月； 3：季； 4：年；
	 * @param bool $showtab 是否需求执行show table以确认表是否存在
	 * @return void|array
	 */
	public function rangePartition($table = '', $sdate = 0, $edate = 0, $field = 'instime', $type = 2, $showtab = false)
	{
		is_string($table) && strpos($table, ',') !== false && $table = explode(',', $table);
		if (is_array($table)) {
			$args = func_get_args();
			foreach ($table as $v) {
				$args[0] = $v;
				$res = call_user_func_array(__METHOD__, $args);
			}
			return $res;
		}

		try {
			if ( ! $table) {
				return $this->_reMsg('参数table不能为空!', 1);
			}

			// 对于用月分区，月的截止时间戳应该采用下个月1号的第一秒。所以这里的sdate应该采用下月的1号0秒
			//$sdate = $sdate ? : date('Ymd');
			$sdate = $sdate ?: date('Ymd', $type == 2 ? mktime(0, 0, 0, date('n') + 1, 1) : time());
			$edate = $edate ?: date('Ymd', strtotime('+' . ($type < 3 ? 90 : 370) . ' days'));
			if ($sdate >= $edate) {
				return $this->_reMsg('开始日期必须小于结束日期', 1);
			}

			// 异步执行分区
			TaskManager::getInstance()->async(function () use ($sdate, $edate, $type, $showtab, $table, $field) {
//			go(function () use ($sdate, $edate, $type, $showtab, $table, $field){
				$arr = listdate($sdate, $edate, $type);

				// 获取此表当前的分区情况
				$oldpt = $newpt = [];
				$partitions = $this->query("
					select 
						partition_description descr
					from
						INFORMATION_SCHEMA.partitions 
					where 
						TABLE_SCHEMA=schema() and TABLE_NAME='$table';
				");
				// halt($partitions);

				// 还没有分区
				if ( ! isset($partitions[0]['descr'])) {
					foreach ($arr as $k => $v) {
						$psql[] = "PARTITION p$k VALUES LESS THAN (" . strtotime($v) . ')';
					}
					$sql = "ALTER TABLE $table  PARTITION  BY RANGE ($field)(" . implode(',', $psql) . ")";
					// halt($sql);
					$this->execute($sql);
				} else {
					$partitions = array_column($partitions, 'descr');
					$psql = [];
					foreach ($arr as $k => $v) {
						if ( ! in_array(strtotime($v), $partitions)) {
							$psql[] = "PARTITION p$k VALUES LESS THAN (" . strtotime($v) . ')';
						}
					}

					$psql && ($sql = "ALTER TABLE $table  ADD PARTITION (" . implode(',', $psql) . ")") && $this->execute($sql);
					// halt($sql);
				}
				$res = $this->_reMsg("表{$table}添加分区完成");

				return $res;
			});
		} catch (\Exception $e) {
			return $this->_reMsg($e->getMessage(), 2);
		}
	}

	public function shard($month_ereg = '', $quarter_ereg = '', $year_ereg = '', $field = 'instime')
	{
		$month_table = $quarter_table = $year_table = [];

		$tables = $this->query('SHOW TABLES');
		foreach ($tables as $v) {
			$v = current($v);
			foreach (['month', 'quarter', 'year'] as $t) {
				$var = "{$t}_ereg";
				$var = $$var;
				if ($var && preg_match($var, $v)) {
					$var = "{$t}_table";
					array_push($$var, $v);
					break;
				}
			}
		}

		$month_table && $this->rangePartition($month_table, 0, 0, $field);
		$quarter_table && $this->rangePartition($quarter_table, 0, 0, $field, 3);
		$year_table && $this->rangePartition($year_table, 0, 0, $field, 4);
		return true;
	}

	public function checkPartition($day)
	{
		$alltable = $this->query('SHOW TABLES');

		$cutOff = strtotime("+{$day} days");

		$warning = [];
		foreach ($alltable as $item) {
			$tname = current($item);
			// RANGE分区
			$sql = "select partition_description descr from INFORMATION_SCHEMA.partitions where TABLE_SCHEMA=schema() and TABLE_NAME='{$tname}' and PARTITION_METHOD='RANGE'";
			$partition = $this->execute($sql)->getResultColumn('descr');
			if (empty($partition) || empty($partition[0])) {
				continue;
			}
			$max = max($partition);

			if ($max <= $cutOff) {
				$warning[] = $tname;
			}
		}
		if ($warning) {
			$title = '数据表分区不足！！';
			$msg = "检测到以下表分区不足{$day}天：" . implode('、', $warning);
			trace($title . $msg, 'info', 'worker');
			dingtalk_text($title . $msg);
			wechat_notice($title, $msg);
		}
	}


	/**
	 * 返回信息
	 * $msg 返回信息
	 * $code 状态码,0-成功,非0-失败
	 * return array
	 */
	private function _reMsg($msg = '', $code = 0)
	{
        if ($code > 0) {
            $title = '执行分区错误: ';
            dingtalk_text("$title  $msg", true);
            wechat_notice($title, $msg);
        }
		trace($msg, $code ? 'error' : 'info', 'crontab');
		return ['err' => $code, 'msg' => $msg];
	}
}
