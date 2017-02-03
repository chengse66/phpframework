<?php
ini_set("display_errors","On");
error_reporting (E_ERROR);
date_default_timezone_set ( 'PRC' );
define ( "ALLOW_ACCESS", true );
define ("DEVELOPMENT",false);
define ( "RENDERER_BODY", 0 );
define ( "RENDERER_PATH", 1 );
define ( "RENDERER_HEAD", '<?php if(!defined("ALLOW_ACCESS")) exit("not access");?>' );
define ( "RENDERER_PREFIX","m5e7a7l1u9k1r6y1e5s8c9g5e0p1p2n1");
require "autoload.php";

class bootstrap{
    private static $_map;
    private static $_app_name;
    private static $_path_app;
    private static $_path_rel;
    private static $_controllers;
    private static $_models;
    private static $_config;
    private static $_dao;

    /**
     * 启动框架总逻辑
     * @param string $_appname 应用目录
     */
    static function start($_appname="app"){
        self::$_map=array();
        self::$_models=array();
        self::$_controllers=array();
        self::$_app_name=$_appname;
        self::$_config=array();
        self::$_dao=array();
        self::$_path_app= str_replace("\\",'/', dirname ( $_SERVER ["SCRIPT_FILENAME"] ));
        self::$_path_rel=trim(str_replace('\\', '/', dirname($_SERVER["SCRIPT_NAME"])),'/');
        $router=self::path_app("/http/router.php");
        if(file_exists($router)) require_once $router;
        self::__run();
    }

    /**
     * @param $_name 映射名称
     * @param mixed|string $_method 方法或字符Controller@method
     */
    static function map($_name, $_method){
    	$_name=rtrim($_name,"$");
    	$_name="/".ltrim(ltrim($_name,"^"),"/");
        $_name=preg_replace('/\\{\\w+\\}/', '(\\w+)',$_name);
        $_name=preg_replace('/\\//', '\\\/', $_name);
        $_name="/^$_name$/";
        self::$_map[$_name]=$_method;
    }

    /**
     * @param string $_path APP下的路径
     * @return string
     */
    private static function path_app($_path=''){return self::$_path_app.'/'.self::$_app_name.$_path;}

    /**
     * 相对路径
     * @param string $_path 相对路径
     * @return string
     */
    static function path_rel($_path=''){return self::$_path_rel.$_path;}

    /**
     * 包路径转文件路径
     * @param string $_path 路径
     * @param string $_prefix
     * @return string
     */
    private static function path_dot($_path,$_prefix=''){
        $_path=$_prefix.trim(str_replace('.','/',$_path),'/');
        $_path=self::path_app('/'.$_path);
        return $_path;
    }

    /**
     * 内容启动路由
     */
    private static function __run() {
        $URL=$_SERVER["REQUEST_URI"];
        $URL='/'.ltrim(substr($URL, 0,strpos($URL, "?")),"/");
        ksort(self::$_map,SORT_STRING);
        if(!empty($URL)) {
            $patten = '/' . trim(str_replace('/', '\\/', self::path_rel()),'/') . '/';
            $url = '/'.trim(preg_replace($patten, '', $URL),'/');
            foreach (self::$_map as $key => $value) {
                if (preg_match($key, $url, $matchs) > 0) {
                    array_shift($matchs);
                    $t=gettype($value);
                    switch ($t){
                        case "string":
                            $list = explode('@', $value);
                            if(count($list)==2) {
                                self::route($list[0], $list[1]);
                                return;
                            }
                            break;
                        case "object":
                            call_user_func_array($value, $matchs);
                            return;
                            break;
                    }
                }
            }
        }
    }

    /**
     * 渲染视图
     * @param string $_dot_path 路径
     * @param array $_param 渲染变量
     * @param int $mode 模式RENDERER_BODY,RENDERER_PATH
     * @return string
     */
    static function view($_dot_path, $_param = array(), $mode = 0) {
        $src_file=self::path_dot($_dot_path,'views/').".html";
        $cache_file=self::path_dot($_dot_path,'cache/').'.cache.php';
        if(file_exists($src_file)){
            if(DEVELOPMENT || !file_exists($cache_file) || filemtime($cache_file)<filemtime($src_file)){
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
        $pos=strpos("Controller",$_name);
        if($pos===false)$_name=$_name."Controller";
        $filename=self::path_app('/controllers/'.$_name.".php");

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
                call_user_func_array(array($_instance,$_method),$_params);
            }
        }
    }

    /**
     * 模块对象
     * @param $_name 路由名
     * @return mixed
     */
    static function model($_name){
        $pos=strpos("Model",$_name);
        if($pos===false)$_name=$_name."Model";
        $filename=self::path_app('/models/'.$_name.".php");
        if(!isset(self::$_models[$_name])){
            if(!class_exists($_name,false) && file_exists($filename)) {
                require_once $filename;
            }
            if(class_exists($_name,false)){
                self::$_models[$_name]=new $_name();
            }
        }
        if(isset(self::$_models[$_name])){
            return self::$_models[$_name];
        }
    }

    /**
     * 导入libs文件夹下的库
     * @param $_dot_path 导入库文件
     */
    static function import($_dot_path){
        $_dot_path=self::path_dot($_dot_path,'libs/').'.php';
        if(file_exists($_dot_path)){
            require_once $_dot_path;
        }
    }

    /**
     * 配置路由对象
     * @param string $_name
     * @return mixed|array 获取配置对象
     */
    static function config($_name){
        $_name=self::path_dot($_name,'config/').'.php';
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
}

/**
 * 渲染视图
 * @param string $_name 路径
 * @param array $_param 渲染变量
 * @param int $mode 模式RENDERER_BODY,RENDERER_PATH
 * @return string
 */
function ww_view($_name, $_param=array(), $mode=0){return bootstrap::view($_name,$_param,$mode);}
/**
 * 总路由
 * @param string $_name 路由名称
 * @param mixed $_method    方法
 * @param mixed $_parmas 参数
 */
function ww_route($_name, $_method,$_parmas=array()){bootstrap::route($_name,$_method,$_parmas);}
/**
 * 模块对象
 * @param $_name 路由名
 * @return mixed
 */
function ww_model($_name){return bootstrap::model($_name);}
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
 * @param $_name 映射名称
 * @param mixed|string $_method 方法或字符Controller@method
 */
function ww_map($_map, $_method){return bootstrap::map($_map,$_method);}