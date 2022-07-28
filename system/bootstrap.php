<?php
ini_set("display_errors","On");
error_reporting (E_ALL);
date_default_timezone_set ( 'PRC' );
define ( "ALLOW_ACCESS", true );
if(!defined("DEBUG")) define ("DEBUG",false);
define ( "RENDERER_BODY", 0 );
define ( "RENDERER_PATH", 1 );
define ( "RENDERER_HEAD", '<?php if(!defined("ALLOW_ACCESS")) exit("not access");?>' );
define ( "RENDERER_PREFIX","m5e7a7l1u9k1r6y1e5s8c9g5e0p1p2n1");

if(!session_id())session_start();
if(!function_exists("ww_autoload")){
    function ww_autoload($_class_name){
        $filename= __DIR__.'/core/'.$_class_name.'.php';
        if(file_exists($filename)){
            require_once __DIR__.'/core/'.$_class_name.'.php';
        }
    }
    spl_autoload_register("ww_autoload");
}
if(!function_exists("ww_error_handle")){
    function ww_error_handle($errno, $errstr, $errfile, $errline){
    	bootstrap::error_handle($errno, $errstr, $errfile, $errline);
    }
    set_error_handler("ww_error_handle");
}

class bootstrap{
    private static $_app_name;
    private static $_app_path;
    private static $_controllers;
    private static $_config;
    private static $_dao;

    /**
     * 启动框架总逻辑
     * @param string $_appname 应用目录
     */
    static function start($_appname="app"){
        self::$_controllers=array();
        self::$_app_name=$_appname;
        self::$_config=array();
        self::$_dao=array();
        self::$_app_path= str_replace("\\",'/', dirname ( $_SERVER ["SCRIPT_FILENAME"] ));
    }
    
    /**
     * @param string $_path APP下的路径
     * @return string
     */
    static function app_path($_path=''){
    	return self::$_app_path.'/'.self::$_app_name.self::cleanPath($_path);
    }
    
    /**
     * 清空URL
     * @param string $path
     * @return string
     */
    private static function cleanPath($path){
    	return '/'.preg_replace("/^(\\\|\\/)+/i", "", $path);
    }
    
    /**
     * 主文件所在的目录的绝对路径
     */
    static function webroot(){
        return self::$_app_path;
    }

    /**
     * 渲染视图
     * @param string $_dot_path 路径
     * @param array $_param 渲染变量
     * @param int $mode 模式RENDERER_BODY,RENDERER_PATH
     * @return string
     */
    static function renderer($path, $_param = array(), $mode = 0) {
    	$path=self::cleanPath($path);
        $src_file=self::app_path("/views$path").".html";
        $cache_file=self::app_path("/cache$path.cache.php");
        if(file_exists($src_file)){
            if(DEBUG || !file_exists($cache_file) || filemtime($cache_file)<filemtime($src_file)){
                if(!file_exists(dirname($cache_file))) mkdir(dirname($cache_file),0777,true);
                $tpl=new template();
                file_put_contents($cache_file, RENDERER_HEAD.$tpl->Compiling(file_get_contents($src_file)));
            }
            switch ($mode){
                case RENDERER_BODY:
                default:
                    extract($_param,EXTR_PREFIX_SAME,RENDERER_PREFIX);
                    require_once $cache_file;
                    break;
                case RENDERER_PATH:
                    return $cache_file;
                    break;
            }
        }
    }

    /**
     * 总路由
     * @param string $_name 路由名称
     * @param mixed $_method    方法
     * @param mixed $_params 参数
     */
    static function route($_name, $_method,$_params=array()){
        $_instance=self::controller($_name,$_method);
        if($_instance) call_user_func_array(array($_instance,$_method),$_params);
    }

     /* 获取当前的控制器
     * @param $_name    名称
     * @param $_method  方法
     * @return bool 当前控制器是否存在,存在则返回对象,不存在返回false
     */
    static function controller($_name,$_method){
        $pos=strpos("Controller",$_name);
        if($pos===false)$_name=$_name."Controller";
        $filename=self::app_path("/controllers/$_name.php");
        if(!isset(self::$_controllers[$_name])){
            if(!class_exists($_name,false) && file_exists($filename)) {
                require_once $filename;
            }
            if(class_exists($_name,false)){
                self::$_controllers[$_name]=new $_name();
            }
        }
        if(isset(self::$_controllers[$_name])){
            $_instance=self::$_controllers[$_name];
            if(method_exists($_instance,$_method)){
                return $_instance;
            }
        }
        //self::error_handle(E_USER_ERROR, $filename ." or class is not exists!", "", -1);
        return false;
    }

    /**
     * 导入libs文件夹下的库
     * @param $_dot_path 导入库文件
     */
    static function import($path){
    	$path=self::cleanPath($path);
        $path=self::app_path("/libs$path");
        $path=preg_replace("/.php$/i", "", $path).".php";
        if(file_exists($path)){
            require_once $path;
        }else{
        	self::error_handle(E_USER_ERROR, $path ." is not exists!", "", -1);
        }
    }

    /**
     * 配置路由对象
     * @param string $_name
     * @return mixed|array 获取配置对象
     */
    static function config($_name){
    	$name=self::cleanPath($_name);
        $_name=self::app_path("/config$name").'.php';
        if(!isset(self::$_config[$_name])){
            if(file_exists($_name)){
                self::$_config[$_name]=require_once $_name;
            }
        }
        if(isset(self::$_config[$_name])){
            return self::$_config[$_name];
        }
    }

    /**
     * 数据库操作DAO
     * @param $_name 对应数据库配置名称
     * @return database 数据库对象
     */
    static function dao($_name){
        if(!isset(self::$_dao[$_name])) {
            $config = self::config($_name);
            $_pdo=new database($config["dsn"],$config["user"],$config["passwd"]);
            self::$_dao[$_name]=$_pdo;
        }
        return self::$_dao[$_name];
    }
    
    /**
     * Http函数
     * @param string $url
     * @param string $method
     * @param mixed $data
     * @param mixed $header
     * @param number $timeout
     * @return mixed
     */
    static function curl($url,$method="get", $data = array(),$header = array(), $timeout = 5) {
    	if(empty($header) || count($header)==0) $header=array("");
    	$query = http_build_query($data);
    	if($timeout<=0) $timeout=5;
    	$ch = curl_init();
    	if($method=="post"){
    		curl_setopt($ch, CURLOPT_POST, true);
    		curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    	}else if(!empty($query)){
    		$url.=(strpos($url, "?")?"&":"?").$query;
    	}
    	curl_setopt($ch, CURLOPT_URL, $url);
    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    	curl_setopt($ch, CURLOPT_HEADER, 0);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    	$result = curl_exec($ch);
    	curl_close($ch);
    	return $result;
    }
    
    /**
     * 处理错误指令
     * @param number $errno
     * @param string $errstr
     * @param string $errfile
     * @param number $errline
     * @return boolean
     */
    static function error_handle($errno, $errstr, $errfile, $errline)
    {
    	if(DEBUG){
    		switch ($errno) {
    			case E_USER_ERROR:
    			case E_ERROR:
    			//case E_WARNING:
    			//case E_NOTICE:
    				echo "<h3><font color='#ff0000'>[Error] $errstr</font></h3>\n";
    				if($errline>=0) echo "<h5>on line <font color='#ff0000'>$errline</font> in file <font color='#ff0000'>$errfile</font></h5>";
    				exit(1);
    				break;
    		}
    	}
    	return true;
    }
    
    /**
     * 获取不重复的唯一标识ID
     * @return string
     */
    static function guid(){
        return sprintf('%s%s%s%s%s%s',
            dechex(intval(date('Y')) - 2010),
            dechex(date('m')),
            date('d'),
            substr(time(), -5),
            substr(microtime(), 2, 5),
            rand(10, 99));
    }
}

/**
 * 渲染视图
 * @param string $_name 路径
 * @param array $_param 渲染变量
 * @param int $mode 模式RENDERER_BODY,RENDERER_PATH
 * @return string
 */
function ww_view($_name, $_param=array(), $mode=0){return bootstrap::renderer($_name,$_param,$mode);}
/**
 * 总路由
 * @param string $_name 路由名称
 * @param mixed $_method    方法
 * @param mixed $_parmas 参数
 */
function ww_route($_name, $_method,$_parmas=array()){bootstrap::route($_name,$_method,$_parmas);}
/**
 * 导入libs文件夹下的库
 * @param $_name 导入库文件
 */
function ww_import($_name){return bootstrap::import($_name);}
/**
 * 配置路由对象
 * @param string $_name
 * @return mixed|array 获取配置对象
 */
function ww_config($_name="config"){return bootstrap::config($_name);}
/**
 * 数据库操作DAO
 * @param $_name 对应数据库配置名称
 * @return database 数据库对象
 */
function ww_dao($_name="config"){return bootstrap::dao($_name);}

/**
 * get url
 * @param string $url 路径
 * @param mixed $array 提交的数据
 */
function ww_get($url,$array=array()){
	return bootstrap::curl($url,"get",$array);
}

/**
 * post url
 * @param string $url	路径
 * @param mixed $array  提交的数据
 */
function ww_post($url,$array=array()){
	return bootstrap::curl($url,"post",$array);
}

/**
 * @param string $app 项目
 */
function ww_create($app="app"){
    global $folder;
    global $_app;

    $_app=$app;
    $folder=str_replace("\\","/",getcwd());

    function mk_dir($names=array()){
        global $folder,$_app;
        foreach($names as $name){
            $fullpath=$folder."/".$_app."/".$name;
            echo $fullpath;
            if(!file_exists($fullpath)){
                mkdir($fullpath,0777,true);
            }
        }
    }
    mk_dir(array("config","controllers","libs","views"));
    file_put_contents($folder."/config/config.php",'<?php
    return array(
        "dsn"=>"mysql:host=127.0.0.1;port=3306;dbname=数据库名称",
        "user"=>"账号",
        "passwd"=>"密码",
    );');
}
