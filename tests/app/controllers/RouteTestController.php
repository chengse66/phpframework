<?php
class RouteTestController{

    private $pass = 0;
    private $fail = 0;
    private $errors = array();
    private $results = array();

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

    function home(){
        echo "====== Route / Bootstrap Tests ======\n\n";
        $this->testControllerAutoSuffix();
        $this->testControllerNotFound();
        $this->testControllerMethodNotFound();
        $this->testControllerWithSuffix();
        $this->testRouteSuccess();
        $this->testRouteMethodNotFound();
        $this->testConfig();
        $this->testConfigCache();
        $this->testSetGetVar();
        $this->testAppPath();
        $this->testCleanPath();
        $this->testWebroot();
        $this->testModelNotExist();
        $this->testInputSanitization();
        $this->testDao();
        $this->result();
    }

    /** 页面展示入口 */
    function view(){
        $this->testControllerAutoSuffix();
        $this->testControllerNotFound();
        $this->testControllerMethodNotFound();
        $this->testControllerWithSuffix();
        $this->testRouteSuccess();
        $this->testRouteMethodNotFound();
        $this->testConfig();
        $this->testConfigCache();
        $this->testSetGetVar();
        $this->testAppPath();
        $this->testCleanPath();
        $this->testWebroot();
        $this->testDao();

        $checks = array();
        foreach($this->results as $r){
            $checks[] = array(
                'label' => $r['name'],
                'badge' => $r['pass'] ? 'badge-pass' : 'badge-fail',
                'detail' => $r['pass'] ? 'PASS' : 'FAIL' . ($r['detail'] ? ' - '.$r['detail'] : '')
            );
        }

        ww_setVar("title", "Route Tests");
        ww_view("/test/route", array(
            'checks' => $checks,
            'total' => count($checks),
            'pass' => $this->pass,
            'fail' => $this->fail
        ));
    }

    function testControllerAutoSuffix(){
        echo "\n--- testControllerAutoSuffix ---\n";
        $result = bootstrap::controller("test", "home");
        $this->assert("controller 'test' resolves to TestController", $result !== false);
    }

    function testControllerNotFound(){
        echo "\n--- testControllerNotFound ---\n";
        $result = bootstrap::controller("nonexistent_controller_xyz", "index");
        $this->assert("nonexistent controller returns false", $result === false);
    }

    function testControllerMethodNotFound(){
        echo "\n--- testControllerMethodNotFound ---\n";
        $result = bootstrap::controller("test", "nonexistent_method_xyz");
        $this->assert("existent controller with nonexistent method returns false", $result === false);
    }

    function testControllerWithSuffix(){
        echo "\n--- testControllerWithSuffix ---\n";
        $result = bootstrap::controller("TestController", "home");
        $this->assert("controller with 'Controller' suffix still resolves", $result !== false);
    }

    function testRouteSuccess(){
        echo "\n--- testRouteSuccess ---\n";
        ob_start();
        $result = bootstrap::route("test", "home");
        $output = ob_get_clean();
        $this->assert("route returns true", $result === true);
        $this->assert("route output contains 'test home'", strpos($output, "test home") !== false, "output: " . $output);
    }

    function testRouteMethodNotFound(){
        echo "\n--- testRouteMethodNotFound ---\n";
        $result = bootstrap::route("test", "nonexistent_method_xyz");
        $this->assert("route with nonexistent method returns false", $result === false);
    }

    function testConfig(){
        echo "\n--- testConfig ---\n";
        $config = bootstrap::config("config");
        $this->assert("config returns array", is_array($config));
        $this->assert("config has 'default' key", isset($config['default']));
        if(isset($config['default'])){
            $this->assert("config default has dsn", isset($config['default']['dsn']));
            $this->assert("config default has user", isset($config['default']['user']));
            $this->assert("config default has passwd", isset($config['default']['passwd']));
        }
    }

    function testConfigCache(){
        echo "\n--- testConfigCache ---\n";
        $c1 = bootstrap::config("config");
        $c2 = bootstrap::config("config");
        $this->assert("config cache: same result on second call", $c1 === $c2);
    }

    function testSetGetVar(){
        echo "\n--- testSetGetVar ---\n";
        bootstrap::setVar("test_var", "hello_world");
        $val = bootstrap::getVar("test_var");
        $this->assert("setVar/getVar roundtrip", $val === "hello_world");

        bootstrap::setVar("test_array", array(1, 2, 3));
        $arr = bootstrap::getVar("test_array");
        $this->assert("setVar/getVar array type", is_array($arr));
        $this->assert("setVar/getVar array count", count($arr) === 3);

        bootstrap::setVar("test_number", 42);
        $num = bootstrap::getVar("test_number");
        $this->assert("setVar/getVar integer", $num === 42);
    }

    function testAppPath(){
        echo "\n--- testAppPath ---\n";
        $path = bootstrap::app_path("/controllers/TestController.php");
        $this->assert("app_path contains app", strpos($path, "/app/") !== false, "got: $path");
        $this->assert("app_path ends with controller file", strpos($path, "controllers/TestController.php") !== false);
    }

    function testCleanPath(){
        echo "\n--- testCleanPath ---\n";
        $path = bootstrap::app_path("/views/test.html");
        $this->assert("app_path for views works", strpos($path, "/app/views/") !== false);

        $path2 = bootstrap::app_path("models/User.php");
        $this->assert("app_path without leading slash works", strpos($path2, "/app/models/User.php") !== false);
    }

    function testWebroot(){
        echo "\n--- testWebroot ---\n";
        $root = bootstrap::webroot();
        $this->assert("webroot is non-empty string", is_string($root) && strlen($root) > 0);
        $this->assert("webroot contains no backslashes", strpos($root, "\\") === false);
    }

    function testModelNotExist(){
        echo "\n--- testModelNotExist ---\n";
        $result = bootstrap::model("NonExistentModel");
        $this->assert("nonexistent model returns null/empty", empty($result) || $result === null);
    }

    function testInputSanitization(){
        echo "\n--- testInputSanitization ---\n";
        $dirty = "../../../etc/passwd";
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $dirty);
        $this->assert("path traversal stripped (only alphanumeric remain)", preg_match('/[^a-zA-Z0-9_]/', $clean) === 0);

        $dirty2 = "home<script>alert(1)</script>";
        $clean2 = preg_replace('/[^a-zA-Z0-9_]/', '', $dirty2);
        $this->assert("xss stripped", $clean2 === "homescriptalert1script");

        $dirty3 = "valid_method123";
        $clean3 = preg_replace('/[^a-zA-Z0-9_]/', '', $dirty3);
        $this->assert("valid method name preserved", $clean3 === "valid_method123");

        $dirty4 = "home' OR '1'='1";
        $clean4 = preg_replace('/[^a-zA-Z0-9_]/', '', $dirty4);
        $this->assert("sql injection stripped", preg_match('/[^a-zA-Z0-9_]/', $clean4) === 0);
    }

    function testDao(){
        echo "\n--- testDao ---\n";
        $dao = bootstrap::dao("default", "config");
        $this->assert("dao returns database object", $dao instanceof database);
        $dao2 = bootstrap::dao("default", "config");
        $this->assert("dao caches connection", $dao === $dao2);
    }
}