<?php
class http{
    /**
     * @param mixed $url
     * @return httpRequest
     */
    static function get($url){
        return new httpRequest('GET',$url);
    }
    /**
     * @param mixed $url
     * @return httpRequest
     */
    static function post($url){
        return new httpRequest('POST',$url);
    }
    /**
     * @param mixed $url
     * @return httpRequest
     */
    static function put($url){
        return new httpRequest('PUT',$url);
    }
    /**
     * @param mixed $url
     * @return httpRequest
     */
    static function options($url){
        return new httpRequest('OPTIONS',$url);
    }
    /**
     * @param mixed $url
     * @return httpRequest
     */
    static function delete($url){
        return new httpRequest('DELETE',$url);
    }
}

class httpRequest{
    private $method;
    private $url;
    private $url_param;
    private $form;
    private $headers;
    private $https_enabled=false;
    private $cert_path=NULL;
    private $callback=NULL;
    function __construct($method,$url)
    {
        $this->url=$url;
        $this->method=$method;
        $this->headers=array();
    }

    function verifySSL($checkable=false,$path=NULL){
        $this->https_enabled=$checkable;
        $this->cert_path=$path;
        return $this;
    }

    /**
     * GET
     */
    function withQuery($array){
        $this->url_param=http_build_query($array);
        return $this;
    }

    /**
     * POST
     */
    function withForm($array){
        $this->withHeader("Content-Type","application/x-www-form-urlencoded");
        $this->form=http_build_query($array);
        return $this;
    }

    /**
     * POST JSON
     */
    function withJson($array){
        $this->withHeader("Content-Type","application/json");
        $this->form=json_encode($array);
        return $this;
    }

    /**
     * POST RAW DATA
     */
    function withData($rawData){
        $this->withHeader("Content-Type","application/octet-stream");
        $this->form=$rawData;
        return $this;
    }

    /**
     * POST RAW DATA
     */
    function withTextData($text){
        $this->withHeader("Content-Type","text/plain");
        $this->form=$text;
        return $this;
    }

    /**
     * HEADER
     */
    function withHeader($name,$value){
        $this->headers[$name]=$value;
        return $this;
    }

    /**
     * HEADERS
     */
    function withHeaders($headers){
        foreach($headers as $name=>$value){
            $this->headers[$name]=$value;
        }
        return $this;
    }

    /**
     * CALLBACK
     */
    function onReady($callback=NULL){
        $this->callback=$callback;
        return $this;
    }

    function submit(){
        $is_https=strpos(strtolower($this->url),"https://")==0;
        $url=trim($this->url," \n\r\t\v\0&\?");
        $method=strtoupper($this->method);
        $form=$this->form;
        if(!empty($this->url_param)){
            $url.=(strpos($this->url,"?")>0?"&":"?").$this->url_param;
        }
        $ch=curl_init($url);
        if($method=="POST"){
            curl_setopt($ch, CURLOPT_POST, true);
        }else{
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if(!empty($form)){
            curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
        }
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

        $headers=array();
        foreach($this->headers as $k=>$v){
            $headers[]=$k.": ".$v;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $response = curl_exec($ch);
        $ret=array();
        if (curl_errno($ch)) {
            $ret['success']=false;
            $ret['err']=curl_error($ch);
        }else{
            $ret['success']=true;
            $info = curl_getinfo($ch);
            $headerSize = $info['header_size'];
            $header = mb_substr($response, 0, $headerSize);
            $body = mb_substr($response, $headerSize);
            $ret['header']=$header;
            $ret['body']=$body;
        }
        curl_close($ch);
        $callback=$this->callback;
        if(!empty($callback) && is_callable($callback)){
            $callback($ret);
        }
    }
}
