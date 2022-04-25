<?php

namespace WonderGame\EsUtility\Common\Classes;

/**
 * 此类已废弃！！请使用swoole协程客户端
 * 同步Pdo操作类
 * @author lamson
 * @version 2.0 2017-08-14
 * @since 3.0 2019-01-01 优化版第二个版本
 */
class LamPdo extends \PDO
{
	private $_linkId = null; // 实例化pdo成功后的返回对象
	
	public $config = [];
	public $continue = false; // 异常后是否仍继续执行后续的程序（包括SQL）
	public $throwExp = true; // 默认抛异常,不能exit终止程序
	
	private $lastSql = ''; // 最后一次执行的sql语句
	private $fetchType = \PDO::FETCH_ASSOC; // 查询语句返回的数据集类型
	private $sqlStmt = ''; // 组装的sql语句
	private $queryType = ''; // 当前正在执行语句类型
	private $errorInfo = []; // 错误信息
	
	
	public function __construct($config = [])
	{
		$this->config = $config;
		
		try {
			$dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
			$this->_linkId = parent::__construct($dsn, $config['user'], $config['password'], $config['options'] ?? null);
		} catch (\PDOException $e) {
			throw $e;
		}
	}
	
	/**
	 * 执行一条SQL查询（查）预处理语句，并返回PDOStatement对象
	 * @param string $sql
	 * @param array $data
	 * @return object PDOStatement对象
	 */
	public function querySql($sql, array $data = [])
	{
		return $this->executeSql($sql, $data, false);
	}
	
	/**
	 * 执行一条SQL操作（增删改）预处理语句，并返回受影响的行数
	 * @param string $sql
	 * @param array $data
	 * @param bool $fetch 是否只记录SQL语句而不执行
	 * @return int|\PDOStatement 受影响的行数 或 stmt对象
	 */
	public function execSql($sql, array $data = [], $fetch = false)
	{
		$stmt = $this->executeSql($sql, $data, false, $fetch);
		return $stmt ? $stmt->rowCount() : $stmt;
	}
	
	/**
	 * 执行一条预处理语句
	 * @param string $sql 要执行的SQL
	 * @param array $data
	 * @param bool $rs true返回执行结果  false返回结果对象
	 * @param bool $fetch 是否只记录SQL语句而不执行
	 * @return bool|object
	 */
	public function executeSql($sql, array $data = [], $rs = true, $fetch = false)
	{
		$this->errorInfo = []; // 执行新SQL语句之前先清除现有错误
		$this->lastSql = $sql;
		
		$stmt = $this->prepare($sql);
		if ($fetch) {
			return $stmt;
		}
		$res = $stmt->execute($data);
		$res or ($stmt->errorInfo() && $this->errorInfo = array_combine(['err_code', 'err_no', 'err_info'], $stmt->errorInfo()));
		
		// 如果因为链接超时而执行失败，则自动重新执行 add by lamson
		if ($this->getErrorInfo($data) == 'reConnected') {
			return call_user_func(__METHOD__, $sql, $data, $rs, $fetch);
		}
		return $rs ? $res : $stmt;
	}
	
	/**
	 * 返回单条查询结果
	 * @param string $sql
	 * @param array $data
	 * @param string $type 数组类型（关联数组或是索引数组）
	 * @return array 一维数组
	 */
	public function queryOne($sql, array $data = [], $type = '')
	{
		// 智能添加limit 1
		stripos($sql, ' LIMIT ') or ($sql .= ' LIMIT 1');
		$res = $this->queryAll($sql, $data, $type);
		return empty($res) ? [] : $res[0];
	}
	
	/**
	 * 返回所有查询结果
	 * @param string $sql
	 * @param array $data
	 * @param string $type 数组类型（关联数组或是索引数组）
	 * @return array 二维数组
	 */
	public function queryAll($sql, array $data = [], $type = '')
	{
		$type = $type ?: $this->fetchType;
		if (($stmt = $this->querySql($sql, $data))) {
			$res = $stmt->fetchAll($type);
		}
		return empty($res) ? [] : $res;
	}
	
	/**
	 * 总记录数
	 * @param string $table
	 * @param string $where
	 * @param array $data
	 * @return int
	 */
	public function count($table, $where = 1, $data = [])
	{
		$sql = "SELECT count(1) AS total FROM $table WHERE " . ($where ?: 1);
		$r = $this->queryOne($sql, $data);
		return (int)$r['total'] ?? 0;
	}
	
	/**
	 * 插入数据
	 * @param string $table 表名
	 * @param array $idata 数据键值对数组，如['name'=>'lamson','age'=>18]其中键为表字段，值为数值。支持批量插入 [ ['name'=>'xiaoxiao','age'=>8], ['name'=>'lamson','age'=>18]]
	 * @param bool $retid 是否返回插入数据后获得的主键值
	 * @param bool $fetch 是否只记录SQL语句而不执行
	 * @return int|boot 受影响的行数或者主键值，出错则返回false
	 */
	public function insert($table, array $idata, $retid = false, $fetch = false)
	{
		$this->sqlStmt = "INSERT INTO $table %SET%";
		
		// 根据字段过滤数据
		$idata = $this->create($table, $idata);
		$set = '';
		
		// INSERT INTO user (age,name) values (?, ?), (?, ?), (?, ?);
		if (is_array($key = current($idata))) {
			$s = '';
			foreach ($key as $k => $v) {
				$set .= "$k,";
				$s .= "?,";
			}
			
			$set = '(' . trim($set, ',') . ') values ';
			$val = []; // [6, 'xiaoxiao', 33, 'lamson']
			foreach ($idata as $v) {
				$set .= '(' . trim($s, ',') . '),';
				
				foreach ($v as $value) {
					$val[] = $value;
				}
			}
			$idata = $val;
		} // INSERT INTO user set age=?, name=?;
		else {
			$key = array_keys($idata);
			foreach ($key as $v) {
				$set .= "$v=?,";
			}
			$idata = array_values($idata);
		}
		
		unset($key, $val);
		
		$set = trim($set, ',');
		
		if ($res = $this->setData($set, isset($k))->execSql($this->sqlStmt, $idata, $fetch)) {
			if ($retid) {
				return $this->lastInsertId();
			}
		}
		return $res;
	}
	
	/**
	 * 删除数据
	 * @param string $table
	 * @param array $idata 条件键值对数组，如['name'=>'lamson','age'=>18]其中键为表字段，值为数值，条件之间的关系为and
	 * @param bool $fetch 是否只记录SQL语句而不执行
	 * @return int|boot 受影响的行数，出错则返回false
	 */
	public function delete($table, array $idata, $fetch = false)
	{
		// 根据字段过滤数据
		$idata = $this->create($table, $idata);
		
		$this->sqlStmt = "DELETE FROM $table %WHERE%";
		$array = $this->buildWhere($idata);
		return $this->where($array['where'])->execSql($this->sqlStmt, $array['value'], $fetch);
	}
	
	/**
	 * 更新数据
	 * @param string $table 表名
	 * @param array $set 要更新的数据
	 * @param array $where 条件键值对数组，如['name'=>'lamson','age'=>18]其中键为表字段，值为数值，条件之间的关系为and
	 * @param int $limit 要更新的行数
	 * @param bool $fetch 是否只记录SQL语句而不执行
	 * @return int|boot 受影响的行数，出错则返回false
	 */
	public function update($table, array $set, array $where, $limit = '', $fetch = false)
	{
		$this->sqlStmt = "UPDATE $table  %SET% %WHERE%";
		
		// 如果有指定只更新N条
		$limit > 0 && $this->sqlStmt .= " LIMIT $limit";
		
		// 根据字段过滤数据
		$set = $this->create($table, $set);
		
		$_set = '';
		$_setkey = array_keys($set);
		$value = array_values($set);
		
		foreach ($value as $k => $v) {
			// 支持原样语法  [key=>['exp', value]]
			if (is_array($v) && $v[0] == 'exp') {
				$_set .= $_setkey[$k] . "=$v[1],";
				unset($value[$k], $_setkey[$k]);
			}
		}
		
		foreach ($_setkey as $k => $v) {
			$_set .= $v . '=?,';
		}
		$_set = trim($_set, ',');
		
		$array = $this->buildWhere($where);
		$value = array_merge($value, $array['value']);
		
		return $this->setData($_set)->where($array['where'])->execSql($this->sqlStmt, $value, $fetch);
	}
	
	/**
	 * 自增
	 * @param string $table 表名
	 * @param array $set 要更新的数据，如['view'=>3]
	 * @param array $where 条件键值对数组，如['name'=>'lamson','age'=>18]其中键为表字段，值为数值，条件之间的关系为and
	 * @param string $symbol 运算符
	 * @param bool $fetch 是否只记录SQL语句而不执行
	 * @return int|boot 受影响的行数，出错则返回false
	 */
	public function inc($table, array $set, array $where, $symbol = '+', $fetch = false)
	{
		foreach ($set as $k => & $v) {
			$v = ['exp', "$k $symbol $v"];
		}
		return $this->update($table, $set, $where, $fetch);
	}
	
	/**
	 * 自减
	 * @param string $table 表名
	 * @param string $table
	 * @param array $set 要更新的数据
	 * @param array $where 条件键值对数组，如['name'=>'lamson','age'=>18]其中键为表字段，值为数值，条件之间的关系为and
	 * @param bool $fetch 是否只记录SQL语句而不执行
	 * @return int|boot 受影响的行数，出错则返回false
	 */
	public function dec($table, array $set, array $where, $fetch = false)
	{
		return $this->inc($table, $set, $where, '-', $fetch);
	}
	
	/**
	 * 自动更新或插入
	 * @param string $table 表名
	 * @param array $data 要插入的数据
	 * @param array $set 当主键或唯一键冲突时要更新的数据
	 * @param bool $fetch 是否仅返回SQL而不执行
	 * @return int|boot 受影响的行数，出错则返回false
	 */
	public function replace($table, array $data = [], array $set = [], $fetch = false)
	{
		// 根据字段过滤数据
		$data = $this->create($table, $data);
		
		$set = $set ?: $data;
		
		$set = $this->create($table, $set);
		
		$ins = $ups = '';
		
		foreach ($data as $k => $v) {
			// 支持原样语法  [key=>['exp', value]]
			if (is_array($v) && $v[0] == 'exp') {
				$ins .= "`$k`=$v[1],";
			} else {
				$ins .= "`$k`='$v',";
			}
		}
		$ins = trim($ins, ',');
		
		foreach ($set as $k => $v) {
			// 支持原样语法  [key=>['exp', value]]
			if (is_array($v) && $v[0] == 'exp') {
				$ups .= "`$k`=$v[1],";
			} else {
				$ups .= "`$k`='$v',";
			}
		}
		$ups = trim($ups, ',');
		
		return $this->execSql("INSERT INTO $table SET $ins ON DUPLICATE KEY UPDATE $ups;", [], $fetch);
	}
	
	/**
	 * 根据表字段自动过渡数据
	 * @param string $table 表名
	 * @param array $data 要插入或更新数据
	 * @return array 过滤后的数据
	 */
	public function create($table, array $data = [])
	{
		$columns = $this->queryAll("SHOW COLUMNS FROM $table");
		// 字段名为下标的数组
		$columns = array_fill_keys(array_column($columns, 'Field'), '');
		
		// 批量插入 array( array('name'=>'test1','age'=>18), array('name'=>'test2','age'=>20))
		if (is_array(current($data)) && isset($data[0])) {
			foreach ($data as $k => & $v) {
				$v = array_intersect_key($v, $columns);
				if ( ! $v) {
					unset($data[$k]);
				}
			}
			return $data;
		}
		return (array)array_intersect_key($data, $columns);
	}
	
	
	// 链式操作的一些方法
	// field(string), where(string), order(string), group(string), limit(int, [int]), setData(string)
	public function __call($name, $args)
	{
		switch (strtoupper($name)) {
			case 'SETDATA':
				// edit by lamson 支持批量插入
				$set = (empty($args[1]) ? ' SET ' : ' ') . $args[0];
				$this->sqlStmt = str_replace('%SET%', $set, $this->sqlStmt);
				break;
			
			case 'FIELD':
				$field = empty($args[0]) ? '*' : $args[0];
				$this->sqlStmt = str_replace('%FIELD%', $field, $this->sqlStmt);
				break;
			
			case 'WHERE':
				$where = empty($args[0]) ? '' : " WHERE $args[0]";
				$this->sqlStmt = str_replace('%WHERE%', $where, $this->sqlStmt);
				break;
			
			case 'ORDER':
				$other = empty($args[0]) ? '' : " ORDER BY $args[0] %OTHER% ";
				break;
			
			case 'GROUP':
				$other = empty($args[0]) ? '' : " GROUP BY $args[0] %OTHER% ";
				break;
			
			case 'LIMIT':
				$other = ! empty($args) ? ' %OTHER% LIMIT ' . implode(',', $args) : '';
				break;
		}
		
		! empty($other) && $this->sqlStmt = str_replace('%OTHER%', $other, $this->sqlStmt);
		
		return $this;
	}
	
	//获取正在执行的sql语句
	public function getLastSql()
	{
		return $this->lastSql;
	}
	
	//设置查询结果集类型
	public function setFetchType($type)
	{
		$this->fetchType = $type;
		return $this;
	}
	
	/**
	 * 构造where内容
	 * @param array $idata 条件数组
	 * @return ['where'=>$where字符串, 'value'=>值数组]
	 * @author lamson
	 */
	public function buildWhere($idata = [])
	{
		if (is_array($idata)) {
			$key = array_keys($idata);
			$value = array_values($idata);
			
			$idata = []; // 准备存储新的值
			
			$where = '';
			foreach ($value as $k => $v) {
				if (is_array($v)) // [操作符, 值]
				{
					$str = '';
					if (in_array($v[0], ['in', 'between'])) {
						// 构造 ?,?,?,
						$v[1] = explode(',', $v[1]);
						foreach ($v[1] as $v1) {
							$str .= "?,";
							$idata[] = $v1;
						}
						$str = '(' . trim($str, ',') . ')';
					} else {
						$str = '?';
						$idata[] = $v[1];
					}
					
					$where .= " `{$key[$k]}` $v[0] $str AND";
				} else {
					$where .= " `{$key[$k]}`=? AND";
					$idata[] = $v;
				}
			}
			
			$where = ! empty($where) ? trim($where, 'AND') : '1=2';
		}
		
		return ['where' => $where, 'value' => $idata];
	}
	
	// 获取错误信息
	public function getErrorInfo($data = [])
	{
		// 如果是连接超时，则尝试重联 add by lamson
		if ( ! empty($this->errorInfo['err_no']) && in_array($this->errorInfo['err_no'], ['2013', '2006'])) {
			$this->__construct($this->config);
			return 'reConnected';
		}
		
		trace($this->lastSql . ($data ? '  ,data :: ' . json_encode($data, JSON_UNESCAPED_UNICODE) : ''), 'info', 'sql');
		return empty($this->errorInfo['err_info']) ? '' : $this->log($data);
	}
	
	/**
	 * 记录日志
	 * @param array $data SQL操作中的数据
	 */
	private function log($data = [])
	{
		// edit by lamson
		$extend = array_merge($this->errorInfo, [
			'last_sql' => $this->lastSql,
			'data' => str_replace("\n", '', var_export($data, true))
		]);
		$e = new \Exception('执行sql错误');
		
		if ($this->throwExp) {
			throw $e;
		}
		
		if ($this->continue) {
			// 注意，此异常后面的程序仍会继续执行（包括SQL）
			return false;
		}
	}
}
