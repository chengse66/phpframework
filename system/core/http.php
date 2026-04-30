<?php
/**
 * HTTP 客户端 / HTTP Client
 *
 * 基于 cURL 的 HTTP 请求客户端，支持 GET/POST/PUT/DELETE/OPTIONS / cURL-based HTTP client supporting GET/POST/PUT/DELETE/OPTIONS
 * 采用链式调用风格 / Uses fluent builder pattern
 */
class http{
    /**
     * 创建 GET 请求 / Create a GET request
     *
     * @param mixed $url  请求地址 / Request URL
     * @return httpRequest  请求构建器 / Request builder
     */
    static function get($url){
        return new httpRequest('GET',$url);
    }
    /**
     * 创建 POST 请求 / Create a POST request
     *
     * @param mixed $url  请求地址 / Request URL
     * @return httpRequest  请求构建器 / Request builder
     */
    static function post($url){
        return new httpRequest('POST',$url);
    }
    /**
     * 创建 PUT 请求 / Create a PUT request
     *
     * @param mixed $url  请求地址 / Request URL
     * @return httpRequest  请求构建器 / Request builder
     */
    static function put($url){
        return new httpRequest('PUT',$url);
    }
    /**
     * 创建 OPTIONS 请求 / Create an OPTIONS request
     *
     * @param mixed $url  请求地址 / Request URL
     * @return httpRequest  请求构建器 / Request builder
     */
    static function options($url){
        return new httpRequest('OPTIONS',$url);
    }
    /**
     * 创建 DELETE 请求 / Create a DELETE request
     *
     * @param mixed $url  请求地址 / Request URL
     * @return httpRequest  请求构建器 / Request builder
     */
    static function delete($url){
        return new httpRequest('DELETE',$url);
    }
}

/**
 * HTTP 请求构建器 / HTTP Request Builder
 *
 * 通过链式调用设置请求参数，最终调用 submit() 发送请求 / Set request parameters via method chaining, then call submit() to send
 */
class httpRequest{
    /** @var string 请求方法 / HTTP method */
    private $method;
    /** @var string 请求地址 / Request URL */
    private $url;
    /** @var string URL 查询参数 / URL query parameters (built string) */
    private $url_param;
    /** @var string 请求体 / Request body */
    private $form;
    /** @var array 请求头 / Request headers */
    private $headers;
    /** @var bool 是否验证 SSL 证书 / Whether to verify SSL certificate */
    private $https_enabled=true;
    /** @var string|null CA 证书路径 / CA certificate file path */
    private $cert_path=NULL;
    /** @var callable|null 响应回调 / Response callback */
    private $callback=NULL;
    /** @var int 超时时间（秒）/ Timeout in seconds */
    private $timeout=30;
    /** @var bool 是否跟随重定向 / Whether to follow redirects */
    private $follow_redirects=true;

    /**
     * 构造函数 / Constructor
     *
     * @param string $method  HTTP 方法 / HTTP method
     * @param string $url     请求地址 / Request URL
     */
    function __construct($method,$url)
    {
        $this->url=$url;
        $this->method=$method;
        $this->headers=array();
    }

    /**
     * 设置 SSL 验证选项 / Configure SSL verification
     *
     * @param bool        $checkable  是否验证 SSL / Whether to verify SSL
     * @param string|null $path       CA 证书文件路径 / CA certificate file path
     * @return $this  支持链式调用 / Supports chaining
     */
    function verifySSL($checkable=true,$path=NULL){
        $this->https_enabled=$checkable;
        $this->cert_path=$path;
        return $this;
    }

    /**
     * 设置超时时间（秒）/ Set timeout in seconds
     *
     * 同时应用于连接超时和请求超时 / Applied to both connection and request timeout
     *
     * @param int $seconds  超时秒数 / Timeout in seconds
     * @return $this  支持链式调用 / Supports chaining
     */
    function timeout($seconds){
        $this->timeout=$seconds;
        return $this;
    }

    /**
     * 设置是否跟随重定向 / Set whether to follow redirects
     *
     * @param bool $follow  是否跟随 / Whether to follow
     * @return $this  支持链式调用 / Supports chaining
     */
    function followRedirects($follow=true){
        $this->follow_redirects=$follow;
        return $this;
    }

    /**
     * 设置 URL 查询参数 / Set URL query parameters
     *
     * 参数会被编码并追加到 URL 后 / Parameters are encoded and appended to the URL
     *
     * @param array $array  查询参数键值对 / Query parameter key-value pairs
     * @return $this  支持链式调用 / Supports chaining
     */
    function withQuery($array){
        $this->url_param=http_build_query($array);
        return $this;
    }

    /**
     * 设置表单数据 / Set form data (application/x-www-form-urlencoded)
     *
     * 自动设置 Content-Type 头 / Automatically sets Content-Type header
     *
     * @param array $array  表单字段键值对 / Form field key-value pairs
     * @return $this  支持链式调用 / Supports chaining
     */
    function withForm($array){
        $this->withHeader("Content-Type","application/x-www-form-urlencoded");
        $this->form=http_build_query($array);
        return $this;
    }

    /**
     * 设置 JSON 数据 / Set JSON body (application/json)
     *
     * 自动设置 Content-Type 头并将数组编码为 JSON / Automatically sets Content-Type header and encodes array as JSON
     *
     * @param array $array  要发送的数据 / Data to send
     * @return $this  支持链式调用 / Supports chaining
     */
    function withJson($array){
        $this->withHeader("Content-Type","application/json");
        $this->form=json_encode($array);
        return $this;
    }

    /**
     * 设置原始二进制数据 / Set raw binary data (application/octet-stream)
     *
     * @param mixed $rawData  原始数据 / Raw data
     * @return $this  支持链式调用 / Supports chaining
     */
    function withData($rawData){
        $this->withHeader("Content-Type","application/octet-stream");
        $this->form=$rawData;
        return $this;
    }

    /**
     * 设置纯文本数据 / Set plain text data (text/plain)
     *
     * @param string $text  文本内容 / Text content
     * @return $this  支持链式调用 / Supports chaining
     */
    function withTextData($text){
        $this->withHeader("Content-Type","text/plain");
        $this->form=$text;
        return $this;
    }

    /**
     * 添加单个请求头 / Add a single request header
     *
     * @param string $name   头名称 / Header name
     * @param string $value  头值 / Header value
     * @return $this  支持链式调用 / Supports chaining
     */
    function withHeader($name,$value){
        $this->headers[$name]=$value;
        return $this;
    }

    /**
     * 批量添加请求头 / Add multiple request headers
     *
     * @param array $headers  头键值对数组 / Header key-value pairs
     * @return $this  支持链式调用 / Supports chaining
     */
    function withHeaders($headers){
        foreach($headers as $name=>$value){
            $this->headers[$name]=$value;
        }
        return $this;
    }

    /**
     * 设置响应回调 / Set response callback
     *
     * 请求完成后自动调用，接收返回值数组作为参数 / Automatically called when request completes, receives result array as parameter
     *
     * @param callable|null $callback  回调函数 / Callback function
     * @return $this  支持链式调用 / Supports chaining
     */
    function onReady($callback=NULL){
        $this->callback=$callback;
        return $this;
    }

    /**
     * 发送请求 / Submit the request
     *
     * 执行 cURL 请求并返回结果数组 / Executes cURL request and returns result array
     *
     * 返回结构 / Return structure:
     *   成功 / Success: array('success'=>true, 'body'=>string, 'header'=>string, 'status'=>int)
     *   失败 / Failure: array('success'=>false, 'err'=>string)
     *
     * @return array  响应结果 / Response result
     */
    function submit(){
        $is_https=strpos(strtolower($this->url),"https://")===0;
        $url=trim($this->url," \n\r\t\v\0&\?");
        $method=strtoupper($this->method);
        $form=$this->form;
        // 追加查询参数到 URL / Append query parameters to URL
        if(!empty($this->url_param)){
            $url.=(strpos($this->url,"?")!==false?"&":"?").$this->url_param;
        }
        $ch=curl_init($url);
        // cURL 初始化失败时通过回调返回错误 / Return error via callback when cURL init fails
        if(!$ch){
            $ret=array('success'=>false,'err'=>'Failed to initialize cURL');
            $callback=$this->callback;
            if(!empty($callback) && is_callable($callback)){
                $callback($ret);
            }
            return $ret;
        }
        // 设置请求方法 / Set request method
        if($method=="POST"){
            curl_setopt($ch, CURLOPT_POST, true);
        }else{
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        // 重定向设置 / Redirect settings
        if($this->follow_redirects){
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        }
        // 设置请求体 / Set request body
        if(!empty($form)){
            curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
        }
        // SSL 证书验证设置 / SSL certificate verification settings
        if($is_https){
            if($this->https_enabled){
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                if(!empty($this->cert_path)){
                    curl_setopt($ch, CURLOPT_CAINFO, $this->cert_path);
                }
            }else{
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            }
        }

        // 组装请求头 / Assemble request headers
        $headers=array();
        foreach($this->headers as $k=>$v){
            $headers[]=$k.": ".$v;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // 返回响应头信息 / Return response headers
        curl_setopt($ch, CURLOPT_HEADER, true);
        $response = curl_exec($ch);
        $ret=array();
        if (curl_errno($ch)) {
            // cURL 执行错误 / cURL execution error
            $ret['success']=false;
            $ret['err']=curl_error($ch);
        }else{
            // 成功时分离响应头和响应体 / Separate response headers and body on success
            $ret['success']=true;
            $info = curl_getinfo($ch);
            $headerSize = $info['header_size'];
            $header = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
            $ret['header']=$header;
            $ret['body']=$body;
            $ret['status']=$info['http_code'];
        }
        curl_close($ch);
        // 执行回调 / Execute callback
        $callback=$this->callback;
        if(!empty($callback) && is_callable($callback)){
            $callback($ret);
        }
        return $ret;
    }
}
