<?php
class DatabaseTestController{

    private $db;
    private $pass = 0;
    private $fail = 0;
    private $errors = array();
    private $results = array();

    function __construct(){
        $this->db = ww_dao("default", "test_db");
    }

    private function assert($name, $condition, $msg = ''){
        $this->results[] = array('name' => $name, 'pass' => $condition, 'detail' => $msg);
        if($condition){
            $this->pass++;
            echo "[PASS] $name\n";
        }else{
            $this->fail++;
            $this->errors[] = $name . ($msg ? " -> $msg" : "");
            echo "[FAIL] $name" . ($msg ? " -> $msg" : "") . "\n";
        }
    }

    private function result(){
        $total = $this->pass + $this->fail;
        echo "\n========== Result ==========\n";
        echo "Total: $total | Pass: $this->pass | Fail: $this->fail\n";
        if($this->fail > 0){
            echo "Failed tests:\n";
            foreach($this->errors as $e) echo "  - $e\n";
        }
        echo "============================\n";
    }

    private function setup(){
        $this->db->query("DROP TABLE IF EXISTS `ww_test_users`");
        $this->db->query("DROP TABLE IF EXISTS `ww_test_logs`");
        $this->db->query(
            "CREATE TABLE `ww_test_users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(200),
                `age` INT DEFAULT 0,
                `status` TINYINT DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
        $this->db->query(
            "CREATE TABLE `ww_test_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT,
                `action` VARCHAR(200),
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
        );
        echo "[SETUP] Test tables created\n";
    }

    private function cleanup(){
        $this->db->query("DROP TABLE IF EXISTS `ww_test_users`");
        $this->db->query("DROP TABLE IF EXISTS `ww_test_logs`");
        echo "[CLEANUP] Test tables dropped\n";
    }

    /** CLI入口 */
    function home(){
        echo "====== Database Module Tests ======\n\n";
        $this->setup();
        $this->testGetType();
        $this->testInsert();
        $this->testFetch();
        $this->testFetchAll();
        $this->testColumn();
        $this->testLastInsertId();
        $this->testQueryWithParams();
        $this->testUpdate();
        $this->testDelete();
        $this->testDeleteEmptyConditions();
        $this->testUpdateWithoutConditions();
        $this->testTransactionCommit();
        $this->testTransactionRollback();
        $this->testFieldExists();
        $this->testIndexExists();
        $this->testNullValueInsert();
        $this->cleanup();
        $this->result();
    }

    /** 页面展示入口 */
    function view(){
        $this->setup();
        $this->testGetType();
        $this->testInsert();
        $this->testFetch();
        $this->testFetchAll();
        $this->testColumn();
        $this->testLastInsertId();
        $this->testUpdate();
        $this->testDelete();
        $this->testDeleteEmptyConditions();
        $this->testTransactionCommit();
        $this->testTransactionRollback();
        $this->testNullValueInsert();

        $rows = $this->db->fetchAll("SELECT * FROM ww_test_users ORDER BY id");
        $this->cleanup();

        $tests = array();
        foreach($this->results as $r){
            $tests[] = array('name' => $r['name'], 'badge' => $r['pass'] ? 'badge-pass' : 'badge-fail', 'detail' => $r['detail']);
        }
        ww_setVar("title", "Database Tests");
        ww_view("/test/db", array(
            'table_name' => 'ww_test_users',
            'operations' => $tests,
            'has_data' => !empty($rows),
            'rows' => $rows
        ));
    }

    function testGetType(){
        echo "\n--- testGetType ---\n";
        $type = $this->db->getType();
        $this->assert("getType returns mysql", $type === 'mysql', "got: $type");
    }

    function testInsert(){
        echo "\n--- testInsert ---\n";
        $result = $this->db->insert('ww_test_users', array(
            'name' => 'Alice', 'email' => 'alice@test.com', 'age' => 25
        ));
        $this->assert("insert returns truthy", $result !== false, "got: " . var_export($result, true));
        $result2 = $this->db->insert('ww_test_users', array(
            'name' => 'Bob', 'email' => 'bob@test.com', 'age' => 30
        ));
        $this->assert("insert second row", $result2 !== false);
    }

    function testFetch(){
        echo "\n--- testFetch ---\n";
        $row = $this->db->fetch("SELECT * FROM ww_test_users WHERE name = :name", array(':name' => 'Alice'));
        $this->assert("fetch returns array", is_array($row), "got: " . gettype($row));
        $this->assert("fetch name is Alice", $row['name'] === 'Alice', "got: " . $row['name']);
        $this->assert("fetch age is 25", $row['age'] == 25, "got: " . $row['age']);
        $empty = $this->db->fetch("SELECT * FROM ww_test_users WHERE name = :name", array(':name' => 'Nobody'));
        $this->assert("fetch non-existing returns false", $empty === false);
    }

    function testFetchAll(){
        echo "\n--- testFetchAll ---\n";
        $rows = $this->db->fetchAll("SELECT * FROM ww_test_users ORDER BY id");
        $this->assert("fetchAll returns array", is_array($rows));
        $this->assert("fetchAll count is 2", count($rows) === 2, "got: " . count($rows));
        $rows2 = $this->db->fetchAll("SELECT * FROM ww_test_users WHERE age > :age", array(':age' => 28));
        $this->assert("fetchAll with params returns 1 row", count($rows2) === 1);
    }

    function testColumn(){
        echo "\n--- testColumn ---\n";
        $count = $this->db->column("SELECT COUNT(*) FROM ww_test_users");
        $this->assert("column returns count", is_numeric($count) && $count >= 2, "got: " . var_export($count, true));
        $name = $this->db->column("SELECT name FROM ww_test_users WHERE id = :id", array(':id' => 1), 0);
        $this->assert("column with params returns value", !empty($name), "got: " . var_export($name, true));
    }

    function testLastInsertId(){
        echo "\n--- testLastInsertId ---\n";
        $this->db->insert('ww_test_users', array('name' => 'Charlie', 'email' => 'charlie@test.com', 'age' => 35));
        $id = $this->db->lastInsertId();
        $this->assert("lastInsertId returns numeric", is_numeric($id));
        $this->assert("lastInsertId greater than 0", $id > 0);
    }

    function testQueryWithParams(){
        echo "\n--- testQueryWithParams ---\n";
        $stmt = $this->db->query("SELECT * FROM ww_test_users WHERE age > :age", array(':age' => 28));
        $this->assert("query with params returns PDOStatement", $stmt instanceof PDOStatement);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assert("query with params fetches rows", count($rows) >= 1);
    }

    function testUpdate(){
        echo "\n--- testUpdate ---\n";
        $result = $this->db->update('ww_test_users',
            array('age' => 26, 'email' => 'alice_new@test.com'),
            array('name' => 'Alice')
        );
        $this->assert("update returns truthy", $result !== false);
        $row = $this->db->fetch("SELECT * FROM ww_test_users WHERE name = :name", array(':name' => 'Alice'));
        $this->assert("updated age is 26", $row['age'] == 26, "got: " . $row['age']);
        $this->assert("updated email", $row['email'] === 'alice_new@test.com');
    }

    function testDelete(){
        echo "\n--- testDelete ---\n";
        $this->db->insert('ww_test_users', array('name' => 'ToDelete', 'email' => 'del@test.com', 'age' => 99));
        $result = $this->db->delete('ww_test_users', array('name' => 'ToDelete'));
        $this->assert("delete returns truthy", $result !== false);
    }

    function testDeleteEmptyConditions(){
        echo "\n--- testDeleteEmptyConditions ---\n";
        $result = $this->db->delete('ww_test_users', array());
        $this->assert("delete with empty conditions returns false", $result === false);
    }

    function testUpdateWithoutConditions(){
        echo "\n--- testUpdateWithoutConditions ---\n";
        $count = $this->db->column("SELECT COUNT(*) FROM ww_test_users");
        $this->db->update('ww_test_users', array('status' => 1));
        $count2 = $this->db->column("SELECT COUNT(*) FROM ww_test_users");
        $this->assert("update without conditions changes all rows", $count === $count2, "before: $count, after: $count2");
    }

    function testTransactionCommit(){
        echo "\n--- testTransactionCommit ---\n";
        $this->db->beginTransaction();
        $this->db->insert('ww_test_users', array('name' => 'TxUser1', 'email' => 'tx1@test.com', 'age' => 40));
        $this->db->commit();
        $row = $this->db->fetch("SELECT * FROM ww_test_users WHERE name = :name", array(':name' => 'TxUser1'));
        $this->assert("transaction commit: data persisted", $row !== false && $row['name'] === 'TxUser1');
    }

    function testTransactionRollback(){
        echo "\n--- testTransactionRollback ---\n";
        $this->db->beginTransaction();
        $this->db->insert('ww_test_users', array('name' => 'TxUser2', 'email' => 'tx2@test.com', 'age' => 41));
        $this->db->rollback();
        $row = $this->db->fetch("SELECT * FROM ww_test_users WHERE name = :name", array(':name' => 'TxUser2'));
        $this->assert("transaction rollback: data not persisted", $row === false);
    }

    function testFieldExists(){
        echo "\n--- testFieldExists ---\n";
        $this->assert("field name exists", $this->db->field_exists('ww_test_users', 'name') === true);
        $this->assert("field nonexistent does not exist", $this->db->field_exists('ww_test_users', 'nonexistent') === false);
    }

    function testIndexExists(){
        echo "\n--- testIndexExists ---\n";
        $this->assert("primary key index exists", $this->db->index_exists('ww_test_users', 'PRIMARY') === true);
        $this->assert("nonexistent index returns false", $this->db->index_exists('ww_test_users', 'nonexistent_idx') === false);
    }

    function testNullValueInsert(){
        echo "\n--- testNullValueInsert ---\n";
        $this->db->insert('ww_test_users', array('name' => 'NullTester', 'email' => null, 'age' => 50));
        $row = $this->db->fetch("SELECT * FROM ww_test_users WHERE name = :name", array(':name' => 'NullTester'));
        $this->assert("null value insert: row exists", $row !== false);
        $this->assert("null value insert: email is NULL", $row['email'] === null);
    }
}