<?php
class HttpTestController{

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
        echo "====== HTTP Module Tests ======\n\n";
        $this->testGetRequest();
        $this->testPostForm();
        $this->testPostJson();
        $this->testWithQuery();
        $this->testWithHeaders();
        $this->testMultipleHeaders();
        $this->testCallback();
        $this->testReturnValues();
        $this->testTimeout();
        $this->testPutRequest();
        $this->testDeleteRequest();
        $this->testChaining();
        $this->testHttpStatus();
        $this->result();
    }

    /** 页面展示入口 */
    function view(){
        $tests = array();

        $ret = http::get("https://httpbin.org/get")->timeout(10)->submit();
        $tests[] = array('name' => 'GET Request', 'badge' => $ret['success'] ? 'badge-pass' : 'badge-fail', 'detail' => $ret['success'] ? 'status:'.$ret['status'] : 'err:'.$ret['err']);

        $ret2 = http::post("https://httpbin.org/post")->withForm(array('username'=>'testuser','password'=>'123456'))->timeout(10)->submit();
        $tests[] = array('name' => 'POST Form', 'badge' => $ret2['success'] ? 'badge-pass' : 'badge-fail', 'detail' => $ret2['success'] ? 'status:'.$ret2['status'] : 'err:'.$ret2['err']);

        $ret3 = http::post("https://httpbin.org/post")->withJson(array('name'=>'hello','value'=>42))->timeout(10)->submit();
        $tests[] = array('name' => 'POST JSON', 'badge' => $ret3['success'] ? 'badge-pass' : 'badge-fail', 'detail' => $ret3['success'] ? 'status:'.$ret3['status'] : 'err:'.$ret3['err']);

        $ret4 = http::get("https://httpbin.org/get")->withQuery(array('foo'=>'bar','key'=>'value'))->timeout(10)->submit();
        $tests[] = array('name' => 'GET withQuery', 'badge' => $ret4['success'] ? 'badge-pass' : 'badge-fail', 'detail' => $ret4['success'] ? 'status:'.$ret4['status'] : 'err:'.$ret4['err']);

        $ret5 = http::put("https://httpbin.org/put")->withJson(array('key'=>'value'))->timeout(10)->submit();
        $tests[] = array('name' => 'PUT Request', 'badge' => $ret5['success'] ? 'badge-pass' : 'badge-fail', 'detail' => $ret5['success'] ? 'status:'.$ret5['status'] : 'err:'.$ret5['err']);

        $ret6 = http::delete("https://httpbin.org/delete")->timeout(10)->submit();
        $tests[] = array('name' => 'DELETE Request', 'badge' => $ret6['success'] ? 'badge-pass' : 'badge-fail', 'detail' => $ret6['success'] ? 'status:'.$ret6['status'] : 'err:'.$ret6['err']);

        $pass = 0; $fail = 0;
        foreach($tests as $t){ if($t['badge'] === 'badge-pass') $pass++; else $fail++; }

        ww_setVar("title", "HTTP Tests");
        ww_view("/test/http", array(
            'tests' => $tests,
            'total' => count($tests),
            'pass' => $pass,
            'fail' => $fail
        ));
    }

    function testGetRequest(){
        echo "\n--- testGetRequest ---\n";
        $ret = http::get("https://httpbin.org/get")->timeout(10)->submit();
        $this->assert("GET request success", $ret['success'] === true, "err: " . ($ret['err'] ?? ''));
        if($ret['success']){
            $data = json_decode($ret['body'], true);
            $this->assert("GET response has body", !empty($ret['body']));
            $this->assert("GET json has url", isset($data['url']));
        }
    }

    function testPostForm(){
        echo "\n--- testPostForm ---\n";
        $ret = http::post("https://httpbin.org/post")
            ->withForm(array('username' => 'testuser', 'password' => '123456'))
            ->timeout(10)->submit();
        $this->assert("POST form success", $ret['success'] === true, "err: " . ($ret['err'] ?? ''));
        if($ret['success']){
            $data = json_decode($ret['body'], true);
            $this->assert("POST form has form data", isset($data['form']));
            $this->assert("POST form username matches", $data['form']['username'] === 'testuser');
        }
    }

    function testPostJson(){
        echo "\n--- testPostJson ---\n";
        $ret = http::post("https://httpbin.org/post")
            ->withJson(array('name' => 'hello', 'value' => 42))
            ->timeout(10)->submit();
        $this->assert("POST json success", $ret['success'] === true, "err: " . ($ret['err'] ?? ''));
        if($ret['success']){
            $data = json_decode($ret['body'], true);
            $this->assert("POST json has data", isset($data['json']));
            $this->assert("POST json name matches", $data['json']['name'] === 'hello');
        }
    }

    function testWithQuery(){
        echo "\n--- testWithQuery ---\n";
        $ret = http::get("https://httpbin.org/get")
            ->withQuery(array('foo' => 'bar', 'key' => 'value'))
            ->timeout(10)->submit();
        $this->assert("GET withQuery success", $ret['success'] === true);
        if($ret['success']){
            $data = json_decode($ret['body'], true);
            $this->assert("query param foo exists", isset($data['args']['foo']));
            $this->assert("query param foo=bar", $data['args']['foo'] === 'bar');
        }
    }

    function testWithHeaders(){
        echo "\n--- testWithHeaders ---\n";
        $ret = http::get("https://httpbin.org/headers")
            ->withHeader("X-Custom-Header", "MyValue")
            ->timeout(10)->submit();
        $this->assert("GET withHeader success", $ret['success'] === true);
        if($ret['success']){
            $data = json_decode($ret['body'], true);
            $found = false;
            if(isset($data['headers'])){
                foreach($data['headers'] as $k => $v){
                    if(strtolower($k) === 'x-custom-header' && $v === 'MyValue') $found = true;
                }
            }
            $this->assert("custom header sent", $found);
        }
    }

    function testMultipleHeaders(){
        echo "\n--- testMultipleHeaders ---\n";
        $ret = http::get("https://httpbin.org/headers")
            ->withHeaders(array('X-Header-A' => 'AAA', 'X-Header-B' => 'BBB'))
            ->timeout(10)->submit();
        $this->assert("GET withHeaders success", $ret['success'] === true);
        if($ret['success']){
            $data = json_decode($ret['body'], true);
            $foundA = false; $foundB = false;
            if(isset($data['headers'])){
                foreach($data['headers'] as $k => $v){
                    if(strtolower($k) === 'x-header-a' && $v === 'AAA') $foundA = true;
                    if(strtolower($k) === 'x-header-b' && $v === 'BBB') $foundB = true;
                }
            }
            $this->assert("header A found", $foundA);
            $this->assert("header B found", $foundB);
        }
    }

    function testCallback(){
        echo "\n--- testCallback ---\n";
        $callbackResult = null;
        $ret = http::get("https://httpbin.org/get")
            ->timeout(10)
            ->onReady(function($response) use (&$callbackResult){
                $callbackResult = $response;
            })
            ->submit();
        $this->assert("callback was invoked", $callbackResult !== null);
        $this->assert("callback has success flag", isset($callbackResult['success']));
        $this->assert("submit also returns result", $ret !== null && isset($ret['success']));
    }

    function testReturnValues(){
        echo "\n--- testReturnValues ---\n";
        $ret = http::get("https://httpbin.org/get")->timeout(10)->submit();
        $this->assert("submit returns array", is_array($ret));
        $this->assert("return has success key", array_key_exists('success', $ret));
        $this->assert("return has header key", array_key_exists('header', $ret));
        $this->assert("return has body key", array_key_exists('body', $ret));
        $this->assert("return has status key", array_key_exists('status', $ret));
        $this->assert("status is 200", $ret['status'] == 200);
    }

    function testTimeout(){
        echo "\n--- testTimeout ---\n";
        $ret = http::get("https://httpbin.org/delay/1")->timeout(5)->submit();
        $this->assert("short timeout on fast URL succeeds", $ret['success'] === true);
    }

    function testPutRequest(){
        echo "\n--- testPutRequest ---\n";
        $ret = http::put("https://httpbin.org/put")
            ->withJson(array('key' => 'value'))
            ->timeout(10)->submit();
        $this->assert("PUT request success", $ret['success'] === true);
        if($ret['success']){
            $data = json_decode($ret['body'], true);
            $this->assert("PUT has json data", isset($data['json']));
        }
    }

    function testDeleteRequest(){
        echo "\n--- testDeleteRequest ---\n";
        $ret = http::delete("https://httpbin.org/delete")->timeout(10)->submit();
        $this->assert("DELETE request success", $ret['success'] === true);
    }

    function testChaining(){
        echo "\n--- testChaining ---\n";
        $ret = http::get("https://httpbin.org/get")
            ->withQuery(array('a' => '1'))
            ->withHeader("X-Test", "chain")
            ->timeout(10)
            ->followRedirects(true)
            ->submit();
        $this->assert("chained call success", $ret['success'] === true);
    }

    function testHttpStatus(){
        echo "\n--- testHttpStatus ---\n";
        $ret404 = http::get("https://httpbin.org/status/404")->timeout(10)->submit();
        $this->assert("404 status returns success (curl succeeded)", $ret404['success'] === true);
        $this->assert("404 status code is 404", $ret404['status'] == 404);
        $ret200 = http::get("https://httpbin.org/status/200")->timeout(10)->submit();
        $this->assert("200 status code is 200", $ret200['status'] == 200);
    }
}