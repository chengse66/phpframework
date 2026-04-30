<?php
/**
 * Template 模板编译引擎 / Template Compilation Engine
 *
 * 将 HTML 模板编译为可执行的 PHP 文件 / Compiles HTML templates into executable PHP files
 * 支持变量输出、条件判断、循环、模板引用和原生 PHP 块 / Supports variable output, conditionals, loops, template includes, and raw PHP blocks
 */
class template
{
    /** @var array 暂存的 PHP 代码块 / Buffered PHP code blocks */
    private $phpBlocks = [];

    /** @var array 模板中禁止调用的危险函数 / Dangerous functions blocked in templates */
    private static $blockedFunctions = array(
        'system', 'exec', 'passthru', 'shell_exec', 'popen', 'proc_open',
        'eval', 'assert', 'create_function',
        'file_put_contents', 'fwrite', 'fputs',
        'unlink', 'rmdir',
        'phpinfo',
    );

    /**
     * 编译模板内容 / Compile template content
     *
     * 编译流程：提取 PHP 块 → 处理 include → 处理变量 → 处理函数 → 处理全局变量 → 处理 if → 处理 foreach → 处理 for → 还原 PHP 块
     */
    function Compiling($content)
    {
        $this->extractPhpBlocks($content);
        $this->compileIncludes($content);
        $this->compileVariables($content);
        $this->compileFunctions($content);
        $this->compileGlobalVars($content);
        $this->compileIf($content);
        $this->compileForeach($content);
        $this->compileFor($content);
        $this->restorePhpBlocks($content);
        $this->removeBom($content);
        return $content;
    }

    /**
     * 移除 BOM 头 / Remove UTF-8 BOM header
     */
    private function removeBom(&$content)
    {
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
        }
    }

    /**
     * 提取 {php}...{/php} 块为占位符，避免后续编译步骤修改 PHP 原生代码
     */
    private function extractPhpBlocks(&$content)
    {
        $this->phpBlocks = [];
        $content = preg_replace_callback(
            '/\{php\}([\s\S]+?)\{\/php\}/si',
            function ($m) {
                $id = count($this->phpBlocks);
                $this->phpBlocks[$id] = $m[1];
                return "<!--PHP_BLOCK_{$id}-->";
            },
            $content
        );
    }

    /**
     * 还原 PHP 占位符为 <?php ... ?> 标签
     */
    private function restorePhpBlocks(&$content)
    {
        foreach ($this->phpBlocks as $id => $code) {
            $content = str_replace("<!--PHP_BLOCK_{$id}-->", "<?php {$code} ?>", $content);
        }
        // 兜底：处理未被提取到的 {php} 块
        $content = preg_replace('/\{php\}([\s\S]+?)\{\/php\}/', '<?php $1 ?>', $content);
        $this->phpBlocks = [];
    }

    /**
     * 处理模板引用 {include('path')}
     */
    private function compileIncludes(&$content)
    {
        $content = preg_replace(
            '/\{include\([\'"](.*?)[\'"]\)\}/',
            '{php} include bootstrap::renderer(\'$1\',null,1); {/php}',
            $content
        );
    }

    /**
     * 处理变量标签 {$var} / {!$var}
     *
     * {$var}  — 自动转义输出（防 XSS）/ Auto-escaped output (XSS protection)
     * {!$var} — 原始输出（用于已知安全的 HTML）/ Raw output (for known-safe HTML)
     * 含 = 为赋值 / With = for assignment
     */
    private function compileVariables(&$content)
    {
        // 先处理原始输出 {!$var}，避免被下面的规则匹配 / Handle raw output first
        $content = preg_replace_callback(
            '#\{!\$(?!\()([^\}]+)\}#',
            function ($m) {
                $expr = $this->dotToArrow($m[1]);
                if (strpos($m[1], '=') === false) {
                    return "{php} echo \${$expr}; {/php}";
                }
                return "{php} \${$expr}; {/php}";
            },
            $content
        );
        // 转义输出 {$var} / Escaped output
        $content = preg_replace_callback(
            '#\{\$(?!\()([^\}]+)\}#',
            function ($m) {
                $expr = $this->dotToArrow($m[1]);
                if (strpos($m[1], '=') === false) {
                    return "{php} echo htmlspecialchars(\${$expr}, ENT_QUOTES, 'UTF-8'); {/php}";
                }
                return "{php} \${$expr}; {/php}";
            },
            $content
        );
    }

    /**
     * 处理函数调用标签 {func(args)}
     *
     * 禁止调用危险函数（命令执行、文件写入等）/ Blocks dangerous functions (command execution, file write, etc.)
     */
    private function compileFunctions(&$content)
    {
        $content = preg_replace_callback(
            '/\{([a-zA-Z_]\w*)\((.*)\)\}/',
            function ($m) {
                $fn = strtolower($m[1]);
                if (in_array($fn, self::$blockedFunctions)) {
                    return '{php} echo "[blocked: ' . $m[1] . ']"; {/php}';
                }
                return '{php} echo ' . $m[1] . '(' . $this->dotToArrow($m[2]) . '); {/php}';
            },
            $content
        );
    }

    /**
     * 处理全局变量标签 {var:name} — 输出通过 bootstrap::setVar() 设置的变量
     */
    private function compileGlobalVars(&$content)
    {
        $content = preg_replace_callback(
            '/\{var:\s*([a-zA-Z_]\w*)(.*?)(;?)\s*\}/',
            function ($m) {
                $name = $m[1];
                $args = $m[2];
                // 只允许安全的属性/方法链，阻止代码注入 / Only allow safe property/method chains, block code injection
                if ($args !== '' && !preg_match('/^(?:->[\w]+(?:\([^)]*\))?|\[[^\]]*\])*$/', $args)) {
                    $args = '';
                }
                return "<?php echo htmlspecialchars(bootstrap::getVar(\"{$name}\"){$args}, ENT_QUOTES, 'UTF-8'); ?>";
            },
            $content
        );
    }

    /**
     * 处理 {if}...{elseif}...{else}...{/if} 条件块，支持嵌套
     */
    private function compileIf(&$content)
    {
        while (preg_match('/\{if\s+[^\n\}]+\}.*?\{\/if\}/s', $content)) {
            $content = preg_replace_callback(
                '/\{if\s+([^\n\}]+)\}(.*?)\{\/if\}/s',
                function ($m) {
                    $condition = $this->dotToArrow($m[1]);
                    $body = $m[2];

                    // 处理 {elseif}
                    $body = preg_replace_callback(
                        '/\{elseif\s+([^\n\}]+)\}/',
                        function ($em) {
                            $cond = $this->dotToArrow($em[1]);
                            return "{php}}elseif({$cond}){ {/php}";
                        },
                        $body
                    );

                    // 处理 {else}
                    $body = str_replace('{else}', '{php}}else{ {/php}', $body);

                    return "<?php if ({$condition}) { ?>{$body}<?php } ?>";
                },
                $content
            );
        }
    }

    /**
     * 处理 {foreach}...{/foreach} 循环块，支持嵌套
     */
    private function compileForeach(&$content)
    {
        while (preg_match('/\{foreach(.+?)\}(.+?)\{\/foreach\}/s', $content)) {
            $content = preg_replace_callback(
                '/\{foreach(.+?)\}(.+?)\{\/foreach\}/s',
                function ($m) {
                    $expr = $this->dotToArrow($m[1]);
                    return "{php} foreach ({$expr}) {{/php} {$m[2]}{php} } {/php}";
                },
                $content
            );
        }
    }

    /**
     * 处理 {for}...{/for} 循环块，支持嵌套
     */
    private function compileFor(&$content)
    {
        while (preg_match('/\{for(.+?)\}(.+?)\{\/for\}/s', $content)) {
            $content = preg_replace_callback(
                '/\{for(.+?)\}(.+?)\{\/for\}/s',
                function ($m) {
                    $expr = $this->dotToArrow($m[1]);
                    return "{php} for({$expr}) {{/php} {$m[2]}{php} } {/php}";
                },
                $content
            );
        }
    }

    /**
     * 将点号 (.) 转换为 PHP 对象访问符 (->)
     *
     * 保护引号内的点号和字符串连接符（空格包裹的点号）不被转换
     */
    private function dotToArrow($content)
    {
        // 保护引号内的内容
        $placeholders = [];
        $content = preg_replace_callback(
            '/"[^"]*"|\'[^\']*\'/',
            function ($m) use (&$placeholders) {
                $key = '{%_PH_' . count($placeholders) . '_%}';
                $placeholders[$key] = $m[0];
                return $key;
            },
            $content
        );

        // 保护字符串连接符（空格包裹的点号）
        $content = str_replace(' . ', ' {%_DOT_%} ', $content);
        $content = str_replace('. ', '{%_DOT_%} ', $content);
        $content = str_replace(' .', ' {%_DOT_%}', $content);

        // 执行转换
        $content = str_replace('.', '->', $content);

        // 还原
        $content = str_replace('{%_DOT_%}', '.', $content);
        foreach ($placeholders as $key => $val) {
            $content = str_replace($key, $val, $content);
        }

        return $content;
    }
}
?>
