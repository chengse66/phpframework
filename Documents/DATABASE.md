# Database 数据库操作类

基于 PDO 的数据库封装类，支持 MySQL 和 SQL Server，提供常用 CRUD 操作和事务支持。

---

## 获取实例

通过 `bootstrap::dao()` 或 `ww_dao()` 获取：

```php
$db = ww_dao("default", "config");
// 等价于 bootstrap::dao("default", "config")
```

配置文件（`app/config/config.php`）：

```php
return array(
    "default" => array(
        "dsn"    => "mysql:host=localhost;dbname=mydb",
        "user"   => "root",
        "passwd" => "root"
    )
);
```

SQL Server 配置：

```php
return array(
    "mssql" => array(
        "dsn"    => "sqlsrv:Server=hostname;Database=mydb",
        "user"   => "sa",
        "passwd" => "password"
    )
);
```

> 实例按 `$section_$configFile` 键名单例缓存，同一配置节点不会重复创建连接。

---

## 连接特性

- 默认启用持久连接 (`PDO::ATTR_PERSISTENT => true`)
- MySQL 连接自动执行 `SET NAMES UTF8`
- 驱动类型从 DSN 前缀自动检测
- 关闭 `EMULATE_PREPARES`，使用原生预处理语句（防 SQL 注入）
- 错误模式设为 `PDO::ERRMODE_EXCEPTION`

---

## 方法列表

### getType()

获取当前数据库驱动类型。

```php
$type = $db->getType();   // "mysql" 或 "sqlsrv"
```

| 返回 | 类型 | 说明 |
|------|------|------|
| driver | string | 小写的驱动名 |

---

### query($sql, $params)

执行 SQL 语句。

```php
// 无参数 → 返回影响行数（int）或 false
$affected = $db->query("DELETE FROM logs WHERE created_at < '2024-01-01'");

// 有参数 → 返回 PDOStatement
$stmt = $db->query("SELECT * FROM users WHERE age > :age", array(':age' => 18));
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$sql` | string | SQL 语句 |
| `$params` | array | 预处理参数，默认 `[]` |
| **返回** | int\|bool\|PDOStatement | 无参数时返回 exec 结果；有参数时返回 PDOStatement |

---

### fetch($sql, $params)

查询单行数据，返回关联数组。

```php
$user = $db->fetch("SELECT * FROM users WHERE id = :id", array(':id' => 1));
// array('id' => 1, 'name' => 'Alice', 'email' => 'alice@test.com')

// 无匹配时返回 false
$empty = $db->fetch("SELECT * FROM users WHERE id = :id", array(':id' => 999));
// false
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$sql` | string | SELECT 语句 |
| `$params` | array | 预处理参数 |
| **返回** | array\|false | 关联数组或 false |

---

### fetchAll($sql, $params)

查询所有匹配行。

```php
$users = $db->fetchAll("SELECT * FROM users WHERE status = :status", array(':status' => 1));
// array(array('id'=>1, 'name'=>'Alice'), array('id'=>2, 'name'=>'Bob'))
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$sql` | string | SELECT 语句 |
| `$params` | array | 预处理参数 |
| **返回** | array\|false | 关联数组列表或 false |

---

### column($sql, $params, $column)

查询单个列的值。

```php
// 查询总记录数
$count = $db->column("SELECT COUNT(*) FROM users");
// int 42

// 查询指定列
$name = $db->column("SELECT name FROM users WHERE id = :id", array(':id' => 1), 0);
// string "Alice"
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$sql` | string | SQL 语句 |
| `$params` | array | 预处理参数 |
| `$column` | int | 列序号（从 0 开始），默认 `0` |
| **返回** | mixed\|false | 列值或 false |

---

### insert($table, $fields, $replace)

插入数据，使用 `SET` 语法。

```php
$db->insert('users', array(
    'name'  => 'Alice',
    'email' => 'alice@test.com',
    'age'   => 25
));
// INSERT INTO `users` SET `name` = :name, `email` = :email, `age` = :age

// 替换插入（REPLACE INTO）
$db->insert('users', array('id' => 1, 'name' => 'Alice'), true);
// REPLACE INTO `users` SET `id` = :id, `name` = :name
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$table` | string | 表名 |
| `$fields` | array | 字段键值对 |
| `$replace` | bool | 是否使用 REPLACE INTO，默认 `false` |
| **返回** | bool\|int | 执行结果 |

---

### update($table, $fields, $conditions, $glue)

更新数据。

```php
// 带条件更新
$db->update('users',
    array('age' => 26, 'email' => 'new@test.com'),
    array('id' => 1)
);
// UPDATE `users` SET `age` = :age, `email` = :email WHERE `id` = :__id

// 无条件更新（更新所有行）
$db->update('users', array('status' => 1));
// UPDATE `users` SET `status` = :status

// OR 条件
$db->update('users',
    array('status' => 0),
    array('role' => 'admin', 'active' => 0),
    'OR'
);
// UPDATE `users` SET `status` = :status WHERE `role` = :__role OR `active` = :__active
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$table` | string | 表名 |
| `$fields` | array | 要更新的字段键值对 |
| `$conditions` | array | WHERE 条件键值对，默认 `[]` |
| `$glue` | string | 条件连接词，`'AND'`（默认）或 `'OR'` |
| **返回** | bool\|int | 执行结果 |

---

### delete($table, $conditions, $glue)

删除数据。**空条件时返回 false（安全机制，防止误删全表）。**

```php
// 按条件删除
$db->delete('users', array('status' => 0));
// DELETE FROM `users` WHERE `status` = :__status

// OR 条件
$db->delete('logs', array('expired' => 1, 'archived' => 1), 'OR');

// 空条件 → 返回 false（不执行）
$db->delete('users', array());
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$table` | string | 表名 |
| `$conditions` | array | WHERE 条件键值对 |
| `$glue` | string | `'AND'`（默认）或 `'OR'` |
| **返回** | bool\|int | 执行结果，空条件返回 `false` |

---

### lastInsertId()

获取最后插入的自增 ID。

```php
$db->insert('users', array('name' => 'Alice'));
$id = $db->lastInsertId();   // "3"
```

| 返回 | 类型 | 说明 |
|------|------|------|
| id | string | 最后插入行的 ID（字符串形式） |

---

### beginTransaction() / commit() / rollback()

事务操作。

```php
$db->beginTransaction();
try {
    $db->insert('orders', array('user_id' => 1, 'amount' => 100));
    $db->insert('order_items', array('order_id' => $db->lastInsertId(), 'product' => 'A'));
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
}
```

---

### prepare($sql) / execute($statement, $params)

手动控制预处理语句，用于复杂事务场景。

```php
$db->beginTransaction();
$stmt = $db->prepare("INSERT INTO users (name) VALUES (:name)");
$db->execute($stmt, array(':name' => 'Alice'));
$db->execute($stmt, array(':name' => 'Bob'));
$db->commit();
```

| 方法 | 参数 | 返回 |
|------|------|------|
| `prepare($sql)` | SQL 语句 | PDOStatement |
| `execute($stmt, $params)` | PDOStatement + 参数数组 | 执行结果 |

---

### field_exists($table, $field)

检查字段是否存在（仅 MySQL）。

```php
if ($db->field_exists('users', 'email')) {
    // 字段存在
}
```

---

### index_exists($table, $indexName)

检查索引是否存在（仅 MySQL）。

```php
if ($db->index_exists('users', 'PRIMARY')) {
    // 主键索引存在
}
```

---

## 参数绑定说明

`insert`、`update`、`delete` 方法内部使用命名参数自动绑定：

- WHERE 条件参数自动加 `__` 前缀，与 SET 值参数区分
- 字段名用反引号包裹（`` `field` ``），防止保留字冲突
- 所有值通过 PDO 预处理绑定，防止 SQL 注入

```
UPDATE `users` SET `age` = :age WHERE `id` = :__id
                          ↑ SET参数      ↑ WHERE参数（带__前缀）
```
