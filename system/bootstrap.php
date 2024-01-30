<?php
require 'core/database.php';
require_once 'core/template.php';
require_once 'core/http.php';

ini_set("display_errors", "On");
error_reporting(E_ALL);
date_default_timezone_set('PRC');
define("ALLOW_ACCESS", true);
if (!defined("DEBUG")) define("DEBUG", false);
define("RENDERER_BODY", 0);
define("RENDERER_PATH", 1);
define("RENDERER_HEAD", '<?php if(!defined("ALLOW_ACCESS")) exit("not access");?>');
define("RENDERER_PREFIX", "m5e7a7l1u9k1r6y1e5s8c9g5e0p1p2n1");

if (!session_id()) session_start();
if (!function_exists("ww_autoload")) {
    function ww_autoload($_class_name)
    {
        $filename=bootstrap::app_path('/libs/'.$_class_name.'.php');
        if(file_exists($filename)){
            require_once $filename;
        }
    }
    spl_autoload_register("ww_autoload");
}
if (!function_exists("ww_error_handle")) {
    function ww_error_handle($errno, $errstr, $errfile, $errline)
    {
        bootstrap::error_handle($errno, $errstr, $errfile, $errline);
    }
    set_error_handler("ww_error_handle");
}

if(!function_exists("ww_exception_handle")){
    function ww_exception_handle($exception) {
        header('Content-Type: text/json;charset=utf-8');
        exit(json_encode(array(
            'message'=>$exception->getMessage(),
            'file'=>$exception->getFile(),
            'line'=>$exception->getLine(),
            'stackTrace'=>nl2br($exception->getTraceAsString()),
        )));
    }
    set_exception_handler('ww_exception_handle');
}

class bootstrap
{
    private static $_app_name;
    private static $_app_path;
    private static $_controllers;
    private static $_config;
    private static $_dao;
    private static $_models;
    private static $_params;
    /**
     * 启动框架总逻辑
     * @param string $_appname 应用目录
     */
    static function start($_appname = "app")
    {
        self::$_controllers = array();
        self::$_app_name = $_appname;
        self::$_config = array();
        self::$_dao = array();
        self::$_models = array();
        self::$_params = array();
        self::$_app_path = str_replace("\\", '/', dirname($_SERVER["SCRIPT_FILENAME"]));
    }

    /**
     * @param string $_path APP下的路径
     * @return string
     */
    static function app_path($_path = '')
    {
        return self::$_app_path . '/' . self::$_app_name . self::cleanPath($_path);
    }

    /**
     * 清空URL
     * @param string $path
     * @return string
     */
    private static function cleanPath($path)
    {
        return '/' . preg_replace("/^(\\\|\\/)+/i", "", $path);
    }

    /**
     * 主文件所在的目录的绝对路径
     */
    static function webroot()
    {
        return self::$_app_path;
    }

    /**
     * 渲染视图
     * @param string $_dot_path 路径
     * @param array $_param 渲染变量
     * @param int $mode 模式RENDERER_BODY,RENDERER_PATH
     * @return string
     */
    static function renderer($path, $_param = array(), $mode = 0)
    {
        $path = self::cleanPath($path);
        $src_file = self::app_path("/views$path") . ".html";
        if (DEBUG) {
            $cache_file = self::app_path("/cache$path.cache.php");
        } else {
            $cache_file = self::app_path("/cache/" . md5($path) . ".php");
        }
        if (file_exists($src_file)) {
            if (DEBUG || !file_exists($cache_file) || filemtime($cache_file) < filemtime($src_file)) {
                if (!file_exists(dirname($cache_file))) mkdir(dirname($cache_file), 0777, true);
                $sys = new template();
                file_put_contents($cache_file, RENDERER_HEAD . $sys->Compiling(file_get_contents($src_file)));
            }
            switch ($mode) {
                case RENDERER_BODY:
                default:
                    extract(array_merge(self::$_params, $_param), EXTR_PREFIX_SAME, RENDERER_PREFIX);
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
    static function route($_name, $_method, $_params = array())
    {
        $_instance = self::controller($_name, $_method);
        if ($_instance) {
            call_user_func_array(array($_instance, $_method), $_params);
            return true;
        }
        return false;
    }

    /* 获取当前的控制器
     * @param $_name    名称
     * @param $_method  方法
     * @return bool 当前控制器是否存在,存在则返回对象,不存在返回false
     */
    static function controller($_name, $_method)
    {
        $pos = strpos("Controller", $_name);
        if ($pos === false) $_name = $_name . "Controller";
        $filename = self::app_path("/controllers/$_name.php");
        if (!isset(self::$_controllers[$_name])) {
            if (!class_exists($_name, false) && file_exists($filename)) {
                require_once $filename;
            }
            if (class_exists($_name, false)) {
                $instance = new $_name();
                self::$_controllers[$_name] = $instance;
            }
        }
        if (isset(self::$_controllers[$_name])) {
            $_instance = self::$_controllers[$_name];
            if (method_exists($_instance, $_method)) {
                return $_instance;
            }
        }
        return false;
    }

    /* 获取当前的模块
     * @param $_name    名称
     * @return bool 当前控制器是否存在,存在则返回对象,不存在返回false
     */
    static function model($_name)
    {
        $pos = strpos("Model", $_name);
        if ($pos === false) $_name = $_name . "Model";
        $filename = self::app_path("/models/$_name.php");
        if (!isset(self::$_models[$_name])) {
            if (!class_exists($_name, false) && file_exists($filename)) {
                require_once $filename;
            }
            if (class_exists($_name, false)) {
                self::$_models[$_name] = new $_name();
            }
        }
        return self::$_models[$_name];
    }

    /**
     * 导入libs文件夹下的库
     * @param $_dot_path 导入库文件
     */
    static function import($path)
    {
        $path = self::cleanPath($path);
        $path = self::app_path("/libs$path");
        $path = preg_replace("/.php$/i", "", $path) . ".php";
        if (file_exists($path)) {
            require_once $path;
        } else {
            self::error_handle(E_USER_ERROR, $path . " is not exists!", "", -1);
        }
    }

    /**
     * 设置view 的全局渲染方法
     */
    static function setVar($_method,$_value){
        self::$_params[$_method]=$_value;
    }

    /**
     * 获取view的全局渲染方法
     */
    static function getVar($_method){
        return self::$_params[$_method];
    }

    /**
     * 配置路由对象
     * @param string $_name
     * @return mixed|array 获取配置对象
     */
    static function config($_name)
    {
        $name = self::cleanPath($_name);
        $_name = self::app_path("/config$name") . '.php';
        if (!isset(self::$_config[$_name])) {
            if (file_exists($_name)) {
                self::$_config[$_name] = require_once $_name;
            }
        }
        if (isset(self::$_config[$_name])) {
            return self::$_config[$_name];
        }
    }

    /**
     * 数据库操作DAO
     * @param $_name 对应数据库配置名称
     * @param $_section 节点
     * @return database 数据库对象
     */
    static function dao($_section,$_name)
    {
        $key=$_section."_".$_name;
        if (!isset(self::$_dao[$key])) {
            $config = self::config($_name)[$_section];
            $_pdo = new database($config["dsn"], $config["user"], $config["passwd"]);
            self::$_dao[$key] = $_pdo;
        }
        return self::$_dao[$key];
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
        if (DEBUG) {
            switch ($errno) {
                case E_USER_ERROR:
                case E_ERROR:
                    //case E_WARNING:
                    //case E_NOTICE:
                    echo "<h3><font color='#ff0000'>[Error] ".nl2br($errstr)."</font></h3>\n";
                    if ($errline >= 0) echo "<h5>on line <font color='#ff0000'>$errline</font> in file <font color='#ff0000'>$errfile</font></h5>";
                    exit(1);
                    break;
            }
        }
        return true;
    }
}

/**
 * 渲染视图
 * @param string $_name 路径
 * @param array $_param 渲染变量
 * @param int $mode 模式RENDERER_BODY,RENDERER_PATH
 * @return string
 */
function ww_view($_name, $_param = array(), $mode = 0)
{
    return bootstrap::renderer($_name, $_param, $mode);
}

/**
 * 总路由
 * @param string $_name 路由名称
 * @param mixed $_method    方法
 * @param mixed $_parmas 参数
 */
function ww_route($_name, $_method, $_parmas = array())
{
    return bootstrap::route($_name, $_method, $_parmas);
}

/**
 * 总路由
 * @param string $_name 模块
 */
function ww_model($_name)
{
    return bootstrap::model($_name);
}
/**
 * 导入libs文件夹下的库
 * @param $_name 导入库文件
 */
function ww_import($_name)
{
    return bootstrap::import($_name);
}
/**
 * 配置路由对象
 * @param string $_name
 * @return mixed|array 获取配置对象
 */
function ww_config($_name = "config")
{
    return bootstrap::config($_name);
}
/**
 * 数据库操作DAO
 * @param $_name 对应数据库配置名称
 * @param $_section 节点
 * @return database 数据库对象
 */
function ww_dao($_section="default",$_name = "config")
{
    return bootstrap::dao($_section,$_name);
}

/**
 * 设置变量
 */
function ww_setVar($method,$value){
    return bootstrap::setVar($method,$value);
}

/**
 * 获取变量
 */
function ww_getVar($method){
    return bootstrap::getVar($method);
}
