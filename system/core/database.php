<?php
/**
 * Database 数据库操作类 / Database Operation Class
 *
 * 基于 PDO 的数据库封装，支持 MySQL 和 SQL Server / PDO-based database wrapper supporting MySQL and SQL Server
 * 提供常用 CRUD 操作和事务支持 / Provides common CRUD operations and transaction support
 */
class database {
    /** @var PDO PDO 连接实例 / PDO connection instance */
    private $pdo;

    /** @var string 数据库驱动类型 (mysql/sqlsrv) / Database driver type (mysql/sqlsrv) */
    private $driver;

    /**
     * 构造函数 - 创建数据库连接 / Constructor - Create database connection
     *
     * @param string $dsn      数据源名称 / Data Source Name
     *                          mysql:  mysql:host=localhost;dbname=test
     *                          sqlsrv: sqlsrv:Server=server_name;Database=database_name
     * @param string $username  用户名 / Username
     * @param string $passwd    密码 / Password
     * @param array  $option    PDO 连接选项 / PDO connection options
     */
    public function __construct($dsn,$username,$passwd,$option=null){
        // 默认启用持久连接 / Enable persistent connections by default
        if(!$option) $option=array(PDO::ATTR_PERSISTENT=>true);
        $this->pdo = new PDO($dsn, $username, $passwd, $option);
        // 关闭模拟预处理，使用原生预处理语句 / Disable emulated prepares, use native prepared statements
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        // 保持数字和日期的原始类型，不转为字符串 / Keep native types for numbers and dates
        $this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        // 错误模式设为异常 / Set error mode to exception
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // 从 DSN 前缀自动检测驱动类型 / Auto-detect driver type from DSN prefix
        $this->driver=strtolower(substr($dsn,0,strpos($dsn,":")));
        // MySQL 连接自动设置 UTF-8 编码 / Auto-set UTF-8 encoding for MySQL connections
        if($this->driver=='mysql'){
            $this->query("SET NAMES UTF8;");
        }
    }

    /**
     * 获取数据库驱动类型 / Get database driver type
     *
     * @return string 驱动名称 (mysql/sqlsrv) / Driver name (mysql/sqlsrv)
     */
    public function getType(){
        return $this->driver;
    }

    /**
     * 执行 SQL 语句 / Execute SQL statement
     *
     * 无参数时使用 exec() 返回影响行数 / Uses exec() returning affected rows when no params
     * 有参数时使用 prepare()+execute() 返回 PDOStatement / Uses prepare()+execute() returning PDOStatement when params given
     *
     * @param string $sql     SQL 语句 / SQL statement
     * @param array  $params  预处理参数 / Prepared statement parameters
     * @return bool|int|PDOStatement  影响行数或 PDOStatement / Affected rows or PDOStatement
     */
    function query($sql, $params = array()) {
        if (empty($params)) {
            return $this->pdo->exec($sql);
        }else {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            return $statement;
        }
    }

    /**
     * 开始事务 / Begin transaction
     */
    function beginTransaction(){
        $this->pdo->beginTransaction();
    }

    /**
     * 准备预处理语句 / Prepare a statement for execution
     *
     * 用于手动控制事务中的多条 SQL / For manual control of multiple SQL in transactions
     *
     * @param string $sql  SQL 语句 / SQL statement
     * @return PDOStatement  预处理语句对象 / Prepared statement object
     */
    function prepare($sql){
        return $this->pdo->prepare($sql);
    }

    /**
     * 执行预处理语句 / Execute a prepared statement
     *
     * 配合 prepare() 使用，用于事务中多次执行同一 SQL / Used with prepare() for executing same SQL multiple times in a transaction
     *
     * @param PDOStatement $statement  预处理语句 / Prepared statement
     * @param array        $params     参数列表 / Parameter list
     * @return mixed  执行结果 / Execution result
     */
    function execute($statement,$params=array()){
        return $statement->execute($params);
    }

    /**
     * 提交事务 / Commit transaction
     */
    function commit(){
        $this->pdo->commit();
    }

    /**
     * 回滚事务 / Rollback transaction
     */
    function rollback(){
        $this->pdo->rollBack();
    }

    /**
     * 查询单个列的值 / Query a single column value
     *
     * @param string $sql     SQL 语句 / SQL statement
     * @param array  $params  预处理参数 / Prepared statement parameters
     * @param int    $column  列序号（从 0 开始）/ Column index (0-based)
     * @return bool|string  列值，失败返回 false / Column value, false on failure
     */
    function column($sql, $params = array(), $column = 0) {
        $statement = $this->pdo->prepare($sql);
        $result=false;
        if($statement->execute($params)){
            $result=$statement->fetchColumn($column);
        }
        unset($statement);
        return $result;
    }

    /**
     * 获取最后插入的自增 ID / Get the last inserted auto-increment ID
     *
     * @return int|string  最后插入行的 ID / ID of the last inserted row
     */
    function lastInsertId(){
        return $this->pdo->lastInsertId();
    }

    /**
     * 查询单行数据 / Fetch a single row
     *
     * @param string $sql     SELECT 语句 / SELECT statement
     * @param array  $params  预处理参数 / Prepared statement parameters
     * @return array|bool  关联数组，无匹配返回 false / Associative array, false if no match
     */
    function fetch($sql, $params = array()) {
        $result=false;
        $statement = $this->pdo->prepare($sql);
        if($statement->execute($params)){
          $result= $statement->fetch(PDO::FETCH_ASSOC);
        }
        unset($statement);
        return $result;
    }

    /**
     * 查询所有匹配行 / Fetch all matching rows
     *
     * @param string $sql     SELECT 语句 / SELECT statement
     * @param array  $params  预处理参数 / Prepared statement parameters
     * @return array|bool  关联数组列表，失败返回 false / List of associative arrays, false on failure
     */
    function fetchAll($sql, $params = array()) {
        $result=false;
        $statement = $this->pdo->prepare($sql);
        if($statement->execute($params)){
            $result= $statement->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($statement);
        return $result;
    }

    /**
     * 更新数据 / Update data
     *
     * @param string $table       表名 / Table name
     * @param array  $fields      要更新的字段键值对 / Field key-value pairs to update
     * @param array  $conditions  WHERE 条件键值对 / WHERE condition key-value pairs
     * @param string $glue        条件连接词 AND/OR / Condition connector AND/OR
     * @return bool|int  执行结果 / Execution result
     */
    function update($table, $fields = array(), $conditions  = array(), $glue = 'AND') {
        $fields = $this->implode($fields, ',');
        $params = $this->implode($conditions, $glue);
        $p = array_merge($fields['params'], $params['params']);
        $sql = "UPDATE " . $this->tablename($table) . " SET {$fields['fields']}";
        $sql .= $params['fields'] ? ' WHERE ' . $params['fields'] : '';
        return $this->query($sql, $p);
    }

    /**
     * 插入数据 / Insert data
     *
     * 使用 INSERT INTO ... SET 语法 / Uses INSERT INTO ... SET syntax
     *
     * @param string $table    表名 / Table name
     * @param array  $fields   字段键值对 / Field key-value pairs
     * @param bool   $replace  是否使用 REPLACE INTO / Whether to use REPLACE INTO
     * @return bool|int  执行结果 / Execution result
     */
    function insert($table, $fields = array(), $replace = FALSE) {
        $cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
        $condition = $this->implode($fields, ',');
        return $this->query("$cmd " . $this->tablename($table) . " SET {$condition['fields']}", $condition['params']);
    }

    /**
     * 删除数据 / Delete data
     *
     * 空条件时返回 false（安全机制，防止误删全表）/ Returns false when conditions are empty (safety mechanism to prevent accidental full-table deletion)
     *
     * @param string $table       表名 / Table name
     * @param array  $conditions  WHERE 条件键值对 / WHERE condition key-value pairs
     * @param string $glue        条件连接词 AND/OR / Condition connector AND/OR
     * @return bool|int  执行结果，空条件返回 false / Execution result, false for empty conditions
     */
    function delete($table, $conditions = array(), $glue = 'AND') {
        // 空条件拒绝执行，防止全表删除 / Refuse to execute with empty conditions to prevent full-table deletion
        if (empty($conditions)) {
            return false;
        }
        $condition = $this->implode($conditions, $glue);
        $sql = "DELETE FROM " . $this->tablename($table);
        $sql .= $condition['fields'] ? ' WHERE ' . $condition['fields'] : '';
        return $this->query($sql, $condition['params']);
    }

    /**
     * 检查字段是否存在 / Check if a field exists in a table
     *
     * 仅支持 MySQL / MySQL only
     *
     * @param string $tablename  表名 / Table name
     * @param string $fieldname  字段名 / Field name
     * @return bool  字段是否存在 / Whether the field exists
     */
    function field_exists($tablename, $fieldname) {
        $isexists = $this->fetch("DESCRIBE " . $this->tablename($tablename) . " `{$fieldname}`");
        return !empty($isexists);
    }

    /**
     * 检查索引是否存在 / Check if an index exists on a table
     *
     * 仅支持 MySQL / MySQL only
     *
     * @param string $tablename  表名 / Table name
     * @param string $indexname  索引名 / Index name
     * @return bool  索引是否存在 / Whether the index exists
     */
    function index_exists($tablename, $indexname) {
        $rows = $this->fetchAll("SHOW INDEX FROM " . $this->tablename($tablename));
        if (!empty($rows) && is_array($rows)) {
            foreach ($rows as $row) {
                if ($row['Key_name'] == $indexname) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 给表名加反引号，转义内部反引号防止注入 / Wrap table name in backticks, escape internal backticks
     *
     * @param string $table  表名 / Table name
     * @return string  反引号包裹的表名 / Backtick-wrapped table name
     */
    private function tablename($table) {
        return '`' . str_replace('`', '``', $table) . '`';
    }

    /**
     * 析构函数 - 销毁连接 / Destructor - Destroy connection
     */
    function __destruct(){
        unset($this->pdo);
        $this->pdo=null;
    }

    /**
     * 将键值对数组转换为 SQL 片段和参数数组 / Convert key-value array to SQL fragment and parameter array
     *
     * 用于 insert/update/delete 的 SET 和 WHERE 子句构建 / Used for building SET and WHERE clauses in insert/update/delete
     *
     * WHERE 条件的参数名自动加 __ 前缀，避免与 SET 参数冲突 / WHERE parameter names are auto-prefixed with __ to avoid collision with SET params
     *
     * @param array  $params  字段键值对 / Field key-value pairs
     * @param string $glue    连接词 (,AND,OR) / Connector (,AND,OR)
     * @return array  array('fields'=>string, 'params'=>array) / SQL fragment and bound parameters
     */
    private function implode($params, $glue = ',') {
        $glue =strtoupper($glue);
        $result = array (
            'fields' => '',
            'params' => array ()
        );
        $split=$suffix= '';
        // AND/OR 模式下参数名加 __ 后缀 / Add __ suffix to param names in AND/OR mode
        if ($glue=="AND" || $glue=="OR") $suffix = '__';
        if (! is_array($params)) {
            $result['fields'] = $params;
            return $result;
        }else {
            foreach ($params as $fields => $value) {
                $safe = str_replace('`', '``', $fields);
                $result['fields'] .= $split . "`{$safe}` =  :{$suffix}$fields";
                $split = ' ' . $glue . ' ';
                $result['params'][":{$suffix}$fields"] = $value;
            }
        }
        return $result;
    }
}
