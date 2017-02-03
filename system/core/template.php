<?php
class template{
    private $tags = array();
    private $parsephpcodes=array();

    /**
     * 移除BOM头
     * @param $s 字符串
     * @return string
     */
    private function RemoveBOM($s){
        if (strlen($s)>=3 && ord($s[0]) == 239 && ord($s[1]) == 187 && ord($s[2]) == 191) return substr($s, 3);
        return $s;
    }

    /**
     * 内容编译
     * @param string $content  编译的内容
     * @return string
     */
    function Compiling($content)
    {
        $this->parsePHP($content);
        $this->parse_template($content);
        $this->parse_vars($content);
        $this->parse_function($content);
        $this->parse_if($content);
        $this->parse_foreach($content);
        $this->parse_for($content);
        $this->parsePHP2($content);
        return $content;
    }

    /**
     * 处理PHP标识
     * @param string $content 编译内容
     */
    private function parsePHP(&$content)
    {
        $this->parsephpcodes=array();
        if($i=preg_match_all ( "/\{php\}([\D\d]+?)\{\/php\}/si" ,  $content ,  $matches )>0){
            if(isset($matches[1])) {
                foreach ($matches[1] as $j => $p) {
                    $content = str_replace($p, '<!--' . $j . '-->', $content);
                    $this->parsephpcodes[$j] = $p;
                }
            }
        }
    }
    /**
     * 处理注释
     * @param string $content 编译内容
     */
    private function parsePHP2(&$content)
    {
        foreach($this->parsephpcodes as $j=>$p) {
            $content = str_replace('{php}<!--'.$j.'-->{/php}','<'.'?php '.$p.' ?'.'>',$content);
        }
        $content = preg_replace('/\{php\}([\D\d]+?)\{\/php\}/', '<'.'?php $1 ?'.'>', $content);
        $this->parsephpcodes=array();
    }
    /**
     * 处理模版引用
     * @param string $content 编译内容
     */
    private function parse_template(&$content)
    {
        $content = preg_replace('/\{\@([^\}]+)\}/', '{php} include ww_view(\'$1\',null,1); {/php}', $content);
    }
    /**
     * 处理变量
     * @param string $content 编译内容
     */
    private function parse_vars(&$content)
    {
        $content = preg_replace_callback('#\{\$(?!\()([^\}]+)\}#',array($this,'parse_vars_replace_dot'), $content);
    }
    /**
     * 处理方法
     * @param string $content 编译内容
     */
    private function parse_function(&$content)
    {
        $content = preg_replace_callback('/\{([a-zA-Z0-9_]+?)\((.+?)\)\}/',array($this,'parse_funtion_replace_dot'), $content);
    }
    /**
     * 处理方法方法
     * @param string $content 编译内容
     */
    private function parse_if(&$content)
    {
        while(preg_match('/\{if [^\n\}]+\}.*?\{\/if\}/s', $content))
            $content = preg_replace_callback(
                '/\{if ([^\n\}]+)\}(.*?)\{\/if\}/s',
                array($this,'parse_if_sub'),
                $content
            );
    }

    /**
     * ELSE IF
     * @param $matches
     * @return string
     */
    private function parse_if_sub($matches)
    {
        $content = preg_replace_callback(
            '/\{elseif ([^\n\}]+)\}/',
            array($this, 'parse_elseif'),
            $matches[2]
        );
        $ifexp = str_replace($matches[1],$this->replace_dot($matches[1]),$matches[1]);
        $content = str_replace('{else}', '{php}}else{ {/php}', $content);
        return "<?php if ($ifexp) { ?>$content<?php } ?>";
    }

    /**
     * ELSEIF
     * @param array $matches
     * @return string
     */
    private function parse_elseif($matches)
    {
        $ifexp = str_replace($matches[1],$this->replace_dot($matches[1]),$matches[1]);
        return "{php}}elseif($ifexp) { {/php}";
    }

    /**
     * FOREACH
     * @param $content 编译内容
     */
    private function parse_foreach(&$content)
    {
        while(preg_match('/\{foreach(.+?)\}(.+?){\/foreach}/s', $content))
            $content = preg_replace_callback(
                '/\{foreach(.+?)\}(.+?){\/foreach}/s',
                array($this,'parse_foreach_sub'),
                $content
            );
    }

    /**
     * FOREACH
     * @param array $matches
     * @return string
     */
    private function parse_foreach_sub($matches)
    {
        $exp = $this->replace_dot($matches[1]);
        $code = $matches[2];
        return "{php} foreach ($exp) {{/php} $code{php} }  {/php}";
    }

    /**
     * FOR
     * @param $content 编译内容
     */
    private function parse_for(&$content)
    {
        while(preg_match('/\{for(.+?)\}(.+?){\/for}/s', $content))
            $content = preg_replace_callback(
                '/\{for(.+?)\}(.+?){\/for}/s',
                array($this,'parse_for_sub'),
                $content
            );
    }

    /**
     * FOR SUB
     * @param $matches
     * @return string
     */
    private function parse_for_sub($matches)
    {
        $exp = $this->replace_dot($matches[1]);
        $code = $matches[2];
        return "{php} for($exp) {{/php} $code{php} }  {/php}";
    }

    /**
     * ECHO VARS
     * @param array $matches
     * @return string
     */
    private function parse_vars_replace_dot($matches)
    {
        if(strpos($matches[1],'=')===false){
            return '{php} echo $' . $this->replace_dot($matches[1]) . '; {/php}';
        }else{
            return '{php} $' . $this->replace_dot($matches[1]) . '; {/php}';
        }
    }

    /**
     * ECHO FUNCTION NAME
     * @param $matches
     * @return string
     */
    private function parse_funtion_replace_dot($matches)
    {
        return '{php} echo ' . $matches[1] . '(' . $this->replace_dot($matches[2]) . '); {/php}';
    }
    /**
     * REPLACE CONTENT
     * @param $content
     * @return mixed
     */
    private function replace_dot($content)
    {
        $array=array();
        preg_match_all('/".+?"|\'.+?\'/', $content,$array,PREG_SET_ORDER);
        if(count($array)>0){
            foreach($array as $a){
                $a=$a[0];
                if(strstr($a,'.')!=false){
                    $b=str_replace('.','{%_dot_%}',$a);
                    $content=str_replace($a,$b,$content);
                }
            }
        }
        $content=str_replace(' . ',' {%_dot_%} ',$content);
        $content=str_replace('. ','{%_dot_%} ',$content);
        $content=str_replace(' .',' {%_dot_%}',$content);
        $content=str_replace('.','->',$content);
        $content=str_replace('{%_dot_%}','.',$content);
        return $content;
    }
}
?>