<?php
class database {
	private $pdo;
    private $driver;
    /**
     * @param string $dsn   mysql:host=localhost;dbname=test<br>
     *                      sqlsrv:Server=server_name;Database=database_name;Uid=username;PWD=password
     * @param string $username  用户名
     * @param string $passwd    密码
     * @param null $option  执行参数
     */
	public function __construct($dsn,$username,$passwd,$option=null){
	    if($option) $option=array(PDO::ATTR_PERSISTENT=>true);
	    $this->pdo = new PDO($dsn, $username, $passwd, $option);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	    $this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->driver=strtolower(substr($dsn,0,strpos($dsn,":")));
        if($this->driver=='mysql'){
            $this->query("SET NAMES UTF8;");
        }
	}

    /**
     * 获取数据库类型
     * @param string type
     */
    public function getType(){
        return $this->driver;
    }

    /**
     * 执行查询不返回结果
     * @param $sql  SQL
     * @param array $params 参数列表
     * @return bool|int 是否执行
     */
	function query($sql, $params = array()) {
		if (empty ( $params )) {
			$result = $this->pdo->exec($sql);
		}else {
            $statement = $this->pdo->prepare($sql);
            $result = $statement->execute($params);
            unset($statement);
        }
        return $result;
	}

    /**
     * 开始事务处理
     */
	function beginTransaction(){
        $this->pdo->beginTransaction();
    }

    /**
     * 事务处理准备SQL
     * @param $sql SQL
     * @return PDOStatement 执行对象
     */
    function prepare($sql){
        return $this->pdo->prepare($sql);
    }

    /**
     * 执行事务处理参数
     * @param PDOStatement $statement    执行对象
     * @param array $params 参数清单
     * @return mixed
     */
    function execute($statement,$params=array()){
        return $statement->execute($params);
    }

    /**
     * 提交事务处理
     */
    function commit(){
        $this->pdo->commit();
    }

    /**
     * 回滚
     */
    function rollback(){
        $this->pdo->rollBack();
    }

    /**
     * 获取列
     * @param string $sql SQL
     * @param array $params 查询参数
     * @param int $column   列号
     * @return bool|string
     */
	function column($sql, $params = array(), $column = 0) {
		$statement = $this->pdo->prepare ( $sql );
		$result=false;
		if($statement->execute ( $params )){
		    $result=$statement->fetchColumn ($column);
		}
		unset($statement);
		return $result;
	}

    /**
     * 最后一次插入的ID
     * @return int ID
     */
	function lastInsertId(){
		return $this->pdo->lastInsertId ();
	}

    /**
     * 查询单个对象
     * @param string $sql   SQL查询语句
     * @param array $params 参数列表
     * @return bool|mixed   获取的单个对象
     */
	function fetch($sql, $params = array()) {
	    $result=false;
		$statement = $this->pdo->prepare ( $sql );
		if($statement->execute ( $params )){
		  $result= $statement->fetch ( PDO::FETCH_ASSOC );
		}
		unset($statement);
		return $result;
	}

    /**
     * 查询所有数据
     * @param string $sql  SQL查询语句
     * @param array $params 参数列表
     * @return array|bool   数据列表
     */
	function fetchAll($sql, $params = array()) {
	    $result=false;
		$statement = $this->pdo->prepare($sql );
        if($statement->execute ( $params )){
            $result= $statement->fetchAll ( PDO::FETCH_ASSOC );
		}
		unset($statement);
		return $result;
	}

    /**
     * 更新数据库
     * @param string $table    数据库表名
     * @param array $fileds 列数据
     * @param array $conditions  条件数组
     * @param string $glue 与条件或条件
     * @return bool|int  是否执行成功
     */
	function update($table, $fields = array(), $conditions  = array(), $glue = 'AND') {
		$fields = $this->implode ( $fields, ',' );
		$params = $this->implode ( $conditions, $glue );
		$p = array_merge ( $fields ['params'], $params ['params'] );
		$sql = "UPDATE " . $this->tablename ( $table ) . " SET {$fields['fields']}";
		$sql .= $params ['fields'] ? ' WHERE ' . $params ['fields'] : '';
		return $this->query ( $sql, $p );
	}

    /**
     * 插入到数据库
     * @param string $table    表名字
     * @param array $fields 数据数组
     * @param bool $replace 是否替换
     * @return bool|int 是否执行成功
     */
	function insert($table, $fields = array(), $replace = FALSE) {
		$cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
		$condition = $this->implode ( $fields, ',' );
		return $this->query ( "$cmd " . $this->tablename ( $table ) . " SET {$condition['fields']}", $condition ['params'] );
	}

    /**
     * 删除表数据
     * @param string $table    表名
     * @param array $conditions 删除条件
     * @param string $glue  与条件或条件
     * @return bool|int 是否成功
     */
	function delete($table, $conditions = array(), $glue = 'AND') {
		$condition = $this->implode ( $conditions, $glue );
		$sql = "DELETE FROM " . $this->tablename ( $table );
		$sql .= $condition ['fields'] ? ' WHERE ' . $condition ['fields'] : '';
		return $this->query ( $sql, $condition ['params'] );
	}

    /**
     * 字段名是否存在
     * @param string $tablename 表名
     * @param string $fieldname 字段名
     * @return bool 是否存在
     */
	function field_exists($tablename, $fieldname) {
		$isexists = $this->fetch ( "DESCRIBE " . $this->tablename ( $tablename ) . " `{$fieldname}`" );
		return ! empty ( $isexists );
	}

    /**
     * 索引数组是否存在
     * @param string $tablename 表名
     * @param array $indexs 索引数组
     * @return bool
     */
	function index_exists($tablename, $indexs) {
		if (! empty ( $indexs )) {
			$indexs = $this->fetchall ( "SHOW INDEX FROM " . $this->tablename ( $tablename ) );
			if (! empty ( $indexs ) && is_array ( $indexs )) {
				foreach ( $indexs as $row ) {
					if ($row ['Key_name'] == $indexs) {
						return true;
					}
				}
			}
		}
		return false;
	}

    /**
     * 返回表名
     * @param string $table 表名
     * @return string 表名
     */
	private function tablename($table) {
		return "`$table`";
	}

	/**
	 * 销毁当前连接对象
	 */
	function __destruct(){
		unset($this->pdo);
	    $this->pdo=null;
	}

    /**
     * @param $params   参数列表
     * @param string $glue  连接参数
     * @return array   连接数组
     */
    private function implode($params, $glue = ',') {
        $glue =strtoupper($glue);
        $result = array (
            'fields' => ' 1 ',
            'params' => array ()
        );
        $split=$suffix= '';
        if ($glue=="AND" || $glue=="OR") $suffix = '__';
        if (! is_array ( $params )) {
            $result ['fields'] = $params;
            return $result;
        }else {
            $result ['fields'] = '';
            foreach ($params as $fields => $value) {
                $result ['fields'] .= $split . "`$fields` =  :{$suffix}$fields";
                $split = ' ' . $glue . ' ';
                $result ['params'] [":{$suffix}$fields"] = is_null($value) ? '' : $value;
            }
        }
        return $result;
    }
}
