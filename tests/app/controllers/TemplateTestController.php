<?php
class TemplateTestController{

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

    /** CLI入口 */
    function home(){
        echo "====== Template Engine Tests ======\n\n";
        $this->testVariableOutput();
        $this->testVariableAssign();
        $this->testRawOutput();
        $this->testXssEscaping();
        $this->testDotToArrow();
        $this->testDotToArrowInQuotes();
        $this->testDotToArrowConcat();
        $this->testForeach();
        $this->testNestedForeach();
        $this->testIf();
        $this->testIfElse();
        $this->testIfElseif();
        $this->testNestedIf();
        $this->testFor();
        $this->testFunctionCall();
        $this->testFunctionBlacklist();
        $this->testGlobalVar();
        $this->testGlobalVarInjection();
        $this->testInclude();
        $this->testPhpBlock();
        $this->testBomRemoval();
        $this->testCompiledExecution();
        $this->testPathTraversal();
        $this->result();
    }

    /** 页面展示入口 */
    function view(){
        $this->testVariableOutput();
        $this->testVariableAssign();
        $this->testRawOutput();
        $this->testXssEscaping();
        $this->testDotToArrow();
        $this->testDotToArrowInQuotes();
        $this->testDotToArrowConcat();
        $this->testForeach();
        $this->testNestedForeach();
        $this->testIf();
        $this->testIfElse();
        $this->testIfElseif();
        $this->testNestedIf();
        $this->testFor();
        $this->testFunctionCall();
        $this->testFunctionBlacklist();
        $this->testGlobalVar();
        $this->testGlobalVarInjection();
        $this->testInclude();
        $this->testPhpBlock();
        $this->testBomRemoval();
        $this->testCompiledExecution();
        $this->testPathTraversal();

        $tests = array();
        foreach($this->results as $r){
            $tests[] = array(
                'name' => $r['name'],
                'badge' => $r['pass'] ? 'badge-pass' : 'badge-fail',
                'detail' => $r['pass'] ? 'PASS' : 'FAIL' . ($r['detail'] ? ' - '.$r['detail'] : '')
            );
        }

        ww_setVar("title", "Template Engine Tests");
        ww_view("/test/template", array(
            'tests' => $tests,
            'total' => count($tests),
            'pass' => $this->pass,
            'fail' => $this->fail
        ));
    }

    /** 编译辅助 / Compile helper */
    private function compile($tpl){
        $engine = new template();
        return $engine->Compiling($tpl);
    }

    /** 编译并执行 / Compile and execute */
    private function execTemplate($tpl, $vars = array()){
        $compiled = $this->compile($tpl);
        extract($vars);
        ob_start();
        eval('?>' . $compiled);
        return ob_get_clean();
    }

    /** 去除空白后比较（模板编译会在标签间产生空白）/ Compare ignoring whitespace */
    private function compact($s){
        return preg_replace('/\s+/', '', $s);
    }

    // ---- 变量输出（自动转义）----
    function testVariableOutput(){
        echo "\n--- testVariableOutput ---\n";
        $out = $this->compile('hello {$name}');
        $this->assert("variable output uses htmlspecialchars", strpos($out, 'htmlspecialchars') !== false && strpos($out, '$name') !== false, $out);
        $this->assert("variable output preserves text", strpos($out, 'hello ') !== false);
    }

    // ---- 变量赋值 ----
    function testVariableAssign(){
        echo "\n--- testVariableAssign ---\n";
        $out = $this->compile('{$x = 10}');
        $this->assert("variable assign compiled", strpos($out, '$x') !== false && strpos($out, 'echo') === false, $out);
    }

    // ---- 原始输出 {!$var} ----
    function testRawOutput(){
        echo "\n--- testRawOutput ---\n";
        $out = $this->compile('{!$name}');
        $this->assert("raw output: no htmlspecialchars", strpos($out, 'htmlspecialchars') === false, $out);
        $this->assert("raw output: has echo $name", strpos($out, 'echo $name') !== false, $out);

        $html = $this->execTemplate('{!$html}', array('html' => '<b>bold</b>'));
        $this->assert("raw output: outputs unescaped HTML", $html === '<b>bold</b>', "got: $html");
    }

    // ---- XSS 自动转义 ----
    function testXssEscaping(){
        echo "\n--- testXssEscaping ---\n";
        $html = $this->execTemplate('{$input}', array('input' => '<script>alert(1)</script>'));
        $this->assert("XSS: script tags escaped", strpos($html, '<script>') === false, "got: $html");
        $this->assert("XSS: contains &lt;script&gt;", strpos($html, '&lt;script&gt;') !== false, "got: $html");

        $html2 = $this->execTemplate('{"a\"onmouseover=\"alert(1)"}', array());
        // 直接测试变量值含引号
        $html3 = $this->execTemplate('{$val}', array('val' => '" onclick="alert(1)"'));
        $this->assert("XSS: quotes escaped", strpos($html3, '&quot;') !== false, "got: $html3");
    }

    // ---- 点号转箭头 ----
    function testDotToArrow(){
        echo "\n--- testDotToArrow ---\n";
        $out = $this->compile('{$user.name}');
        $this->assert("dot to arrow: user->name", strpos($out, '$user->name') !== false, $out);

        $out2 = $this->compile('{$a.b.c}');
        $this->assert("dot to arrow: chain a->b->c", strpos($out2, '$a->b->c') !== false, $out2);
    }

    // ---- 点号在引号内不转换 ----
    function testDotToArrowInQuotes(){
        echo "\n--- testDotToArrowInQuotes ---\n";
        $out = $this->compile('{$x = "hello.world"}');
        $this->assert("dot in double quotes preserved", strpos($out, '"hello.world"') !== false, $out);

        $out2 = $this->compile("{\$x = 'hello.world'}");
        $this->assert("dot in single quotes preserved", strpos($out2, "'hello.world'") !== false, $out2);
    }

    // ---- 字符串连接符不转换 ----
    function testDotToArrowConcat(){
        echo "\n--- testDotToArrowConcat ---\n";
        $out = $this->compile('{php} $a . $b {/php}');
        $this->assert("concat dot preserved", strpos($out, '$a . $b') !== false, $out);

        $out2 = $this->compile('{php} "a" . "b" {/php}');
        $this->assert("string concat preserved", strpos($out2, '"a" . "b"') !== false, $out2);
    }

    // ---- foreach ----
    function testForeach(){
        echo "\n--- testForeach ---\n";
        $out = $this->compile('{foreach $items as $item}<p>{$item}</p>{/foreach}');
        $this->assert("foreach compiled", strpos($out, 'foreach') !== false && strpos($out, '$items as $item') !== false, $out);
        $this->assert("foreach body preserved", strpos($out, '<p>') !== false, $out);

        $html = $this->execTemplate('{foreach $list as $v}[{$v}]{/foreach}', array('list' => array('a','b','c')));
        $this->assert("foreach executes correctly", $this->compact($html) === '[a][b][c]', "got: $html");
    }

    // ---- 嵌套 foreach ----
    function testNestedForeach(){
        echo "\n--- testNestedForeach ---\n";
        $tpl = '{foreach $rows as $row}{foreach $row.items as $item}[{$item}]{/foreach}{/foreach}';
        $out = $this->compile($tpl);
        $this->assert("nested foreach compiles", strpos($out, 'foreach') !== false, $out);

        $data = array(
            (object)array('items' => array('a','b')),
            (object)array('items' => array('c'))
        );
        $html = $this->execTemplate($tpl, array('rows' => $data));
        $this->assert("nested foreach executes", $this->compact($html) === '[a][b][c]', "got: $html");
    }

    // ---- if ----
    function testIf(){
        echo "\n--- testIf ---\n";
        $out = $this->compile('{if $show}visible{/if}');
        $this->assert("if compiled", strpos($out, 'if') !== false && strpos($out, '$show') !== false, $out);

        $html = $this->execTemplate('{if $flag}yes{/if}', array('flag' => true));
        $this->assert("if true shows content", trim($html) === 'yes', "got: $html");

        $html2 = $this->execTemplate('{if $flag}yes{/if}', array('flag' => false));
        $this->assert("if false hides content", trim($html2) === '', "got: $html2");
    }

    // ---- if/else ----
    function testIfElse(){
        echo "\n--- testIfElse ---\n";
        $tpl = '{if $ok}yes{else}no{/if}';
        $out = $this->compile($tpl);
        $this->assert("if/else compiled", strpos($out, 'else') !== false, $out);

        $html1 = $this->execTemplate($tpl, array('ok' => true));
        $this->assert("if true shows yes", trim($html1) === 'yes', "got: $html1");

        $html2 = $this->execTemplate($tpl, array('ok' => false));
        $this->assert("if false shows no", trim($html2) === 'no', "got: $html2");
    }

    // ---- if/elseif ----
    function testIfElseif(){
        echo "\n--- testIfElseif ---\n";
        $tpl = '{if $x == 1}one{elseif $x == 2}two{else}other{/if}';
        $out = $this->compile($tpl);
        $this->assert("elseif compiled", strpos($out, 'elseif') !== false, $out);

        $html1 = $this->execTemplate($tpl, array('x' => 1));
        $this->assert("elseif: x=1 shows one", trim($html1) === 'one', "got: $html1");

        $html2 = $this->execTemplate($tpl, array('x' => 2));
        $this->assert("elseif: x=2 shows two", trim($html2) === 'two', "got: $html2");

        $html3 = $this->execTemplate($tpl, array('x' => 9));
        $this->assert("elseif: x=9 shows other", trim($html3) === 'other', "got: $html3");
    }

    // ---- 嵌套 if ----
    function testNestedIf(){
        echo "\n--- testNestedIf ---\n";
        $tpl = '{if $a}{if $b}both{/if}{/if}';
        $out = $this->compile($tpl);
        $this->assert("nested if compiles", substr_count($out, 'if') >= 2, $out);

        $html = $this->execTemplate($tpl, array('a' => true, 'b' => true));
        $this->assert("nested if true shows both", trim($html) === 'both', "got: $html");

        $html2 = $this->execTemplate($tpl, array('a' => true, 'b' => false));
        $this->assert("nested if inner false hides", trim($html2) === '', "got: $html2");

        $html3 = $this->execTemplate($tpl, array('a' => false, 'b' => true));
        $this->assert("nested if outer false hides", trim($html3) === '', "got: $html3");
    }

    // ---- for 循环 ----
    function testFor(){
        echo "\n--- testFor ---\n";
        $out = $this->compile('{for $i=0; $i<3; $i++}[{$i}]{/for}');
        $this->assert("for compiled", strpos($out, 'for(') !== false, $out);

        $html = $this->execTemplate('{for $i=0; $i<3; $i++}[{$i}]{/for}');
        $this->assert("for executes 3 iterations", $this->compact($html) === '[0][1][2]', "got: $html");
    }

    // ---- 函数调用 ----
    function testFunctionCall(){
        echo "\n--- testFunctionCall ---\n";
        $out = $this->compile('{strtoupper($name)}');
        $this->assert("function call compiled", strpos($out, 'echo strtoupper($name)') !== false, $out);

        $html = $this->execTemplate('{strtoupper($name)}', array('name' => 'hello'));
        $this->assert("function call executes", $html === 'HELLO', "got: $html");
    }

    // ---- 函数黑名单 ----
    function testFunctionBlacklist(){
        echo "\n--- testFunctionBlacklist ---\n";
        $out = $this->compile('{system("whoami")}');
        $this->assert("blacklist: system() blocked", strpos($out, 'blocked') !== false && strpos($out, 'system("whoami")') === false, $out);

        $out2 = $this->compile('{exec("ls")}');
        $this->assert("blacklist: exec() blocked", strpos($out2, 'blocked') !== false, $out2);

        $out3 = $this->compile('{phpinfo()}');
        $this->assert("blacklist: phpinfo() blocked", strpos($out3, 'blocked') !== false, $out3);

        $out4 = $this->compile('{unlink("/tmp/x")}');
        $this->assert("blacklist: unlink() blocked", strpos($out4, 'blocked') !== false, $out4);

        // 安全函数不受影响
        $out5 = $this->compile('{strtoupper($x)}');
        $this->assert("blacklist: strtoupper() allowed", strpos($out5, 'blocked') === false, $out5);
    }

    // ---- 全局变量 {var:name} ----
    function testGlobalVar(){
        echo "\n--- testGlobalVar ---\n";
        $out = $this->compile('{var:title}');
        $this->assert("global var compiled", strpos($out, 'bootstrap::getVar("title")') !== false, $out);
        $this->assert("global var uses htmlspecialchars", strpos($out, 'htmlspecialchars') !== false, $out);
    }

    // ---- 全局变量注入防护 ----
    function testGlobalVarInjection(){
        echo "\n--- testGlobalVarInjection ---\n";
        // 尝试注入: {var:name);system('whoami');//}
        $out = $this->compile("{var:name);system('whoami');//}");
        $this->assert("injection: system() not in output", strpos($out, "system") === false, $out);
        $this->assert("injection: args stripped", strpos($out, ");system") === false, $out);
    }

    // ---- include ----
    function testInclude(){
        echo "\n--- testInclude ---\n";
        $out = $this->compile("{include('test/header')}");
        $this->assert("include compiled", strpos($out, "bootstrap::renderer('test/header',null,1)") !== false, $out);
    }

    // ---- {php}...{/php} 块 ----
    function testPhpBlock(){
        echo "\n--- testPhpBlock ---\n";
        $out = $this->compile('{php} echo "raw"; {/php}');
        $this->assert("php block preserved", strpos($out, '<?php') !== false && strpos($out, 'echo "raw";') !== false, $out);

        $html = $this->execTemplate('{php} echo "raw"; {/php}');
        $this->assert("php block executes", $html === 'raw', "got: $html");
    }

    // ---- BOM 移除 ----
    function testBomRemoval(){
        echo "\n--- testBomRemoval ---\n";
        $bom = "\xEF\xBB\xBF";
        $out = $this->compile($bom . 'hello');
        $this->assert("BOM removed", strncmp($out, $bom, 3) !== 0, "BOM still present");
        $this->assert("BOM content preserved", strpos($out, 'hello') !== false);
    }

    // ---- 综合执行测试 ----
    function testCompiledExecution(){
        echo "\n--- testCompiledExecution ---\n";
        $tpl = '{foreach $users as $u}{if $u.active}[{$u.name}]{/if}{/foreach}';
        $html = $this->execTemplate($tpl, array('users' => array(
            (object)array('name' => 'Alice', 'active' => true),
            (object)array('name' => 'Bob', 'active' => false),
            (object)array('name' => 'Charlie', 'active' => true),
        )));
        $this->assert("complex template executes", $this->compact($html) === '[Alice][Charlie]', "got: $html");
    }

    // ---- 路径穿越防护 ----
    function testPathTraversal(){
        echo "\n--- testPathTraversal ---\n";
        $path = bootstrap::app_path("/views/../../system/bootstrap");
        $this->assert("path traversal: ../ stripped", strpos($path, '../') === false, "got: $path");

        $path2 = bootstrap::app_path("/config/..\\..\\system");
        $this->assert("path traversal: ..\\ stripped", strpos($path2, '..\\') === false, "got: $path2");

        // 确保 ../ 被移除后路径留在 views 目录内 / Ensure path stays within views directory after ../ removal
        $full = bootstrap::app_path("/views/../../etc/passwd");
        $this->assert("path traversal: stays under views/", strpos($full, 'views/etc/passwd') !== false && strpos($full, '../') === false, "got: $full");
    }
}
