<?php
/**
 * 分表分区类
 *
 * 对表进行分区需要先建立好对应的分区表
 * 主要按照时间和range 类型进行分表和分区
 * @author lamson
 *
 */
namespace Linkunyuan\EsUtility\Classes;


class ShardTable
{
 	public $Db = null;

 	/**
 	 * 设置数据库对象
 	 * @param object $db 数据库对象
 	 * @return object 本对象
 	 */
 	public function setDb($db = null)
 	{
 	 	$this->Db = is_object($db) ? $db : db();
 	 	return $this;
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
		is_string($table) && strpos($table, ',')!==false && $table = explode(',', $table);
 		if(is_array($table))
		{
			$args = func_get_args();
			foreach($table as $v)
			{
				$args[0] = $v;
				$res = call_user_func_array(__METHOD__, $args);
			}
			return $res;
		}

 	 	try {
 	 	 	if ( ! $table)
 	 	 	{
 	 	 	 	return $this->_reMsg('参数table不能为空!', 1);
 	 	 	}

 	 	 	if ( ! is_object($this->Db))
 	 	 	{
 	 	 	 	return $this->_reMsg('请先设置db对象', 1);
 	 	 	}

 	 	 	// 对于用月分区，月的截止时间戳应该采用下个月1号的第一秒。所以这里的sdate应该采用下月的1号0秒
			//$sdate = $sdate ? : date('Ymd');
			$sdate = $sdate ? : date('Ymd',  $type == 2 ? mktime(0,0,0,date('n')+1,1) : time());
			$edate = $edate ? : date('Ymd', strtotime('+'  . ($type<3 ? 90 : 370) . ' days'));
			if ($sdate >= $edate)
			{
				return $this->_reMsg('开始日期必须小于结束日期', 1);
			}

			$arr = listdate($sdate, $edate, $type);

 	 	 	// 查看是否存在此表
			$tables = [0=>1];
			$showtab && $tables = $this->Db->query(" show create table $table");
 	 	 	if( ! empty($tables[0]))
 	 	 	{
 	 	 	 	// 获取此表当前的分区情况
 	 	 	 	$oldpt = $newpt = [];
 	 	 	 	$partitions = $this->Db->query("
					select 
						partition_description descr
					from
						INFORMATION_SCHEMA.partitions 
					where 
						TABLE_SCHEMA=schema() and TABLE_NAME='$table';
				");
				// halt($partitions);

				// 还没有分区
				if( ! isset($partitions[0]['descr']))
				{
					foreach($arr as $k => $v)
					{
						$psql[] = "PARTITION p$k VALUES LESS THAN (" . strtotime($v) . ')';
					}
					$sql = "ALTER TABLE $table  PARTITION  BY RANGE ($field)(" . implode(',', $psql)  .")";
					// halt($sql);
					$this->Db->execute($sql);
				}else
				{
					$partitions = array_column($partitions, 'descr');
					$psql = [];
					foreach($arr as $k => $v)
					{
						if( ! in_array(strtotime($v), $partitions))
						{
							$psql[] = "PARTITION p$k VALUES LESS THAN (" . strtotime($v) . ')';
						}
					}

					$psql && ($sql = "ALTER TABLE $table  ADD PARTITION (" . implode(',', $psql)  .")") && $this->Db->execute($sql);
					// halt($sql);
				}
 	 	 	 	return $this->_reMsg("表{$table}添加分区完成");
 	 	 	} else {
 	 	 		return $this->_reMsg("表{$table}不存在", 1);
 	 	 	}
 	 	} catch (\Exception $e) {
 	 	 	return $this->_reMsg($e->getMessage(), 2);
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
		//发警报
		/*$code && wx_tplmsg([
			'first' => '来自【' . get_cfg_var('env.servname') . "】的消息：扩展分区执行错误",
			'keyword1' => "错误内容:$msg",
			'keyword2' =>"错误代号:$code",
			'keyword3' => date('Y年m月d日 H:i:s'),
			'remark' => '查看详情'
		]);*/

		trace($msg, 'error', 'crontab');
		return ['err'=>$code, 'msg'=>$msg];
	}
}
