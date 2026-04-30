<?php

/**
 * 加载核心类库 / Load core libraries
 */
require 'core/database.php';
require_once 'core/template.php';
require_once 'core/http.php';

/**
 * 初始化 PHP 运行环境 / Initialize PHP runtime settings
 */
ini_set("display_errors", "On");
error_reporting(E_ALL);
date_default_timezone_set('PRC');

/**
 * 定义框架常量 / Define framework constants
 *
 * ALLOW_ACCESS  - 模板安全守卫标识，防止直接访问编译后的模板文件 / Template security guard, prevents direct access to compiled template files
 * DEBUG         - 调试模式开关，影响模板缓存、错误输出等 / Debug mode toggle, affects template caching, error output, etc.
 * RENDERER_BODY - 渲染模式：直接输出 / Render mode: direct output
 * RENDERER_PATH - 渲染模式：返回缓存文件路径 / Render mode: return cache file path
 * RENDERER_HEAD - 编译模板头部的安全守卫代码 / Security guard code prepended to compiled templates
 * RENDERER_PREFIX - 变量名冲突时的前缀 / Prefix for avoiding variable name collisions
 */
define("ALLOW_ACCESS", true);
if (!defined("DEBUG")) define("DEBUG", false);
define("RENDERER_BODY", 0);
define("RENDERER_PATH", 1);
define("RENDERER_HEAD", '<?php if(!defined("ALLOW_ACCESS")) exit("not access");?>');
define("RENDERER_PREFIX", "m5e7a7l1u9k1r6y1e5s8c9g5e0p1p2n1");

/**
 * 启动会话 / Start session
 */
if (!session_id()) session_start();

/**
 * 注册自动加载器 / Register autoloader
 *
 * 当使用未定义的类时，自动在 app/libs/ 目录下查找同名 .php 文件 / When an undefined class is used, auto-searches for same-name .php file in app/libs/
 *
 * @param string $_class_name  类名 / Class name
 */
if (!function_exists("ww_autoload")) {
    function ww_autoload($_class_name)
    {
        $filename = bootstrap::app_path('/libs/' . $_class_name . '.php');
        if (file_exists($filename)) {
            require_once $filename;
        }
    }
    spl_autoload_register("ww_autoload");
}

/**
 * 注册自定义错误处理器 / Register custom error handler
 *
 * DEBUG 模式下输出详细错误信息 / Outputs detailed error info in DEBUG mode
 *
 * @param int    $errno   错误级别 / Error level
 * @param string $errstr  错误信息 / Error message
 * @param string $errfile 错误文件 / Error file
 * @param int    $errline 错误行号 / Error line number
 */
if (!function_exists("ww_error_handle")) {
    function ww_error_handle($errno, $errstr, $errfile, $errline)
    {
        bootstrap::error_handle($errno, $errstr, $errfile, $errline);
    }
    set_error_handler("ww_error_handle");
}

/**
 * 注册异常处理器 / Register exception handler
 *
 * DEBUG 模式返回异常详情 JSON，非 DEBUG 模式返回通用错误 / DEBUG mode returns exception details as JSON, non-DEBUG returns generic error
 *
 * @param Exception $exception  捕获的异常 / Caught exception
 */
if (!function_exists("ww_exception_handle")) {
    function ww_exception_handle($exception)
    {
        header('Content-Type: text/json;charset=utf-8');
        if (DEBUG) {
            exit(json_encode(array(
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'stackTrace' => nl2br($exception->getTraceAsString()),
            )));
        } else {
            exit(json_encode(array('message' => 'Internal Server Error')));
        }
    }
    set_exception_handler('ww_exception_handle');
}

/**
 * Bootstrap 核心引导类 / Core Bootstrap Class
 *
 * 框架的服务容器、路由器和自动加载器 / Framework service container, router, and autoloader
 * 所有框架服务通过静态方法访问 / All framework services accessed via static methods
 */
class bootstrap
{
    /** @var string 应用目录名 / Application directory name */
    private static $_app_name;
    /** @var string 应用目录绝对路径 / Absolute path to application directory */
    private static $_app_path;
    /** @var array 控制器实例缓存（单例）/ Controller instance cache (singleton) */
    private static $_controllers;
    /** @var array 配置文件缓存 / Configuration file cache */
    private static $_config;
    /** @var array 数据库连接实例缓存 / Database connection instance cache */
    private static $_dao;
    /** @var array 模型实例缓存 / Model instance cache */
    private static $_models;
    /** @var array 视图全局变量 / View global variables */
    private static $_params;

    /**
     * 启动框架总逻辑 / Start the framework
     *
     * 初始化所有内部状态，必须在使用其他方法之前调用 / Initializes all internal state, must be called before using other methods
     *
     * @param string $_appname 应用目录名，默认 "app" / Application directory name, defaults to "app"
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
     * 获取应用目录下的完整路径 / Get full path under application directory
     *
     * 自动处理路径中的反斜杠和前缀斜杠 / Auto-handles backslashes and leading slashes
     *
     * @param string $_path APP 下的相对路径 / Relative path under app directory
     * @return string 完整绝对路径 / Full absolute path
     */
    static function app_path($_path = '')
    {
        return self::$_app_path . '/' . self::$_app_name . self::cleanPath($_path);
    }

    /**
     * 清理路径前缀 / Clean path prefix
     *
     * 确保路径以单个 / 开头，并移除路径穿越序列 / Ensures path starts with a single / and removes path traversal sequences
     *
     * @param string $path 原始路径 / Raw path
     * @return string 清理后的路径 / Cleaned path
     */
    private static function cleanPath($path)
    {
        $path = preg_replace("/^(\\\|\\/)+/i", "", $path);
        do {
            $path = str_replace(array('../', '..\\'), '', $path, $count);
        } while ($count > 0);
        return '/' . $path;
    }

    /**
     * 获取入口文件所在目录的绝对路径 / Get absolute path of the entry file's directory
     *
     * @return string 入口文件目录路径 / Entry file directory path
     */
    static function webroot()
    {
        return self::$_app_path;
    }

    /**
     * 渲染视图 / Render a view
     *
     * 查找 app/views/ 下的 .html 模板，编译为 PHP 缓存文件并执行 / Finds .html template under app/views/, compiles to PHP cache file and executes
     *
     * @param string $path    视图路径（不含 .html 后缀）/ View path (without .html extension)
     * @param array  $_param  传递给模板的变量 / Variables passed to template
     * @param int    $mode    渲染模式 / Render mode
     *                        RENDERER_BODY (0) - 直接输出 / Direct output
     *                        RENDERER_PATH (1) - 返回缓存文件路径 / Return cache file path
     * @return string|void  RENDERER_PATH 模式返回文件路径 / Returns file path in RENDERER_PATH mode
     */
    static function renderer($path, $_param = array(), $mode = 0)
    {
        $path = self::cleanPath($path);
        $src_file = self::app_path("/views$path") . ".html";
        // DEBUG 模式使用可读缓存路径，生产模式使用 md5 哈希 / DEBUG uses readable cache path, production uses md5 hash
        if (DEBUG) {
            $cache_file = self::app_path("/cache$path.cache.php");
        } else {
            $cache_file = self::app_path("/cache/" . md5($path) . ".php");
        }
        if (file_exists($src_file)) {
            // 满足任一条件时重新编译：DEBUG 模式 / 缓存不存在 / 源文件已更新 / Recompile when: DEBUG mode / no cache / source newer than cache
            if (DEBUG || !file_exists($cache_file) || filemtime($cache_file) < filemtime($src_file)) {
                if (!file_exists(dirname($cache_file))) mkdir(dirname($cache_file), 0777, true);
                $sys = new template();
                // 编译并添加安全守卫 / Compile and prepend security guard
                file_put_contents($cache_file, RENDERER_HEAD . $sys->Compiling(file_get_contents($src_file)));
            }
            switch ($mode) {
                case RENDERER_BODY:
                default:
                    // 合并全局变量和局部变量 / Merge global and local variables
                    extract(array_merge(self::$_params, $_param), EXTR_PREFIX_SAME, RENDERER_PREFIX);
                    include $cache_file;
                    break;
                case RENDERER_PATH:
                    return $cache_file;
                    break;
            }
        }
    }

    /**
     * 总路由调度 / Main route dispatcher
     *
     * 加载控制器并调用指定方法 / Loads controller and calls specified method
     *
     * @param string $_name    控制器名称 / Controller name
     * @param mixed  $_method  方法名 / Method name
     * @param array  $_params  传递给方法的参数 / Parameters passed to method
     * @return bool  成功返回 true，失败返回 false / True on success, false on failure
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

    /**
     * 获取控制器实例 / Get controller instance
     *
     * 自动追加 Controller 后缀，按类名单例缓存 / Auto-appends Controller suffix, singleton cached by class name
     *
     * @param string $_name    控制器名（含或不含 Controller 后缀均可）/ Controller name (with or without Controller suffix)
     * @param string $_method  要校验的方法名 / Method name to validate
     * @return object|bool  控制器实例（类和方法均存在）或 false / Controller instance (if class and method exist) or false
     */
    static function controller($_name, $_method)
    {
        // 自动追加 Controller 后缀 / Auto-append Controller suffix
        $pos = strpos($_name, "Controller");
        if ($pos === false) $_name = $_name . "Controller";
        $filename = self::app_path("/controllers/$_name.php");
        if (!isset(self::$_controllers[$_name])) {
            // 加载控制器文件 / Load controller file
            if (!class_exists($_name, false) && file_exists($filename)) {
                require_once $filename;
            }
            // 实例化并缓存 / Instantiate and cache
            if (class_exists($_name, false)) {
                $instance = new $_name();
                self::$_controllers[$_name] = $instance;
            }
        }
        if (isset(self::$_controllers[$_name])) {
            $_instance = self::$_controllers[$_name];
            // 校验方法是否存在 / Validate method exists
            if (method_exists($_instance, $_method)) {
                return $_instance;
            }
        }
        return false;
    }

    /**
     * 获取模型实例 / Get model instance
     *
     * 自动追加 Model 后缀，按类名单例缓存 / Auto-appends Model suffix, singleton cached by class name
     *
     * @param string $_name 模型名（含或不含 Model 后缀均可）/ Model name (with or without Model suffix)
     * @return object|null  模型实例，不存在返回 null / Model instance, null if not found
     */
    static function model($_name)
    {
        // 自动追加 Model 后缀 / Auto-append Model suffix
        $pos = strpos($_name, "Model");
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
     * 导入 libs 目录下的库文件 / Import library file from libs directory
     *
     * @param string $path 相对于 app/libs/ 的路径（不含 .php 后缀）/ Path relative to app/libs/ (without .php extension)
     */
    static function import($path)
    {
        $path = self::cleanPath($path);
        $path = self::app_path("/libs$path");
        $path = preg_replace("/\.php$/i", "", $path) . ".php";
        if (file_exists($path)) {
            require_once $path;
        } else {
            self::error_handle(E_USER_ERROR, $path . " is not exists!", "", -1);
        }
    }

    /**
     * 设置视图全局变量 / Set view global variable
     *
     * 全局变量在所有模板渲染时自动可用 / Global variables are automatically available in all template renders
     *
     * @param string $_method  变量名 / Variable name
     * @param mixed  $_value   变量值 / Variable value
     */
    static function setVar($_method, $_value)
    {
        self::$_params[$_method] = $_value;
    }

    /**
     * 获取视图全局变量 / Get view global variable
     *
     * @param string $_method  变量名 / Variable name
     * @return mixed  变量值 / Variable value
     */
    static function getVar($_method)
    {
        return self::$_params[$_method];
    }

    /**
     * 加载配置文件 / Load configuration file
     *
     * 配置文件为返回关联数组的 PHP 文件，按文件路径单例缓存 / Config files are PHP files returning associative arrays, singleton cached by file path
     *
     * @param string $_name 配置文件名（不含 .php 后缀），对应 app/config/ 下的文件 / Config file name (without .php extension), corresponds to file in app/config/
     * @return array|null  配置数组，文件不存在返回 null / Config array, null if file doesn't exist
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
     * 获取数据库操作对象 / Get database operation object (DAO)
     *
     * 通过配置文件的节点名获取对应的数据库连接 / Gets database connection by section name in config file
     * 连接按 "section_configFile" 键名单例缓存 / Connections singleton cached by "section_configFile" key
     *
     * @param string $_section  配置节点名 / Config section name
     * @param string $_name     配置文件名（不含 .php 后缀）/ Config file name (without .php extension)
     * @return database  数据库操作实例 / Database operation instance
     */
    static function dao($_section, $_name)
    {
        $key = $_section . "_" . $_name;
        if (!isset(self::$_dao[$key])) {
            $config = self::config($_name)[$_section];
            $_pdo = new database($config["dsn"], $config["user"], $config["passwd"]);
            self::$_dao[$key] = $_pdo;
        }
        return self::$_dao[$key];
    }

    /**
     * 自定义错误处理 / Custom error handler
     *
     * DEBUG 模式下 E_ERROR/E_USER_ERROR 输出详细信息并终止 / In DEBUG mode, E_ERROR/E_USER_ERROR outputs details and exits
     *
     * @param int    $errno   错误级别 / Error level
     * @param string $errstr  错误信息 / Error message
     * @param string $errfile 错误文件 / Error file
     * @param int    $errline 错误行号 / Error line number
     * @return bool  始终返回 true / Always returns true
     */
    static function error_handle($errno, $errstr, $errfile, $errline)
    {
        if (DEBUG) {
            switch ($errno) {
                case E_USER_ERROR:
                case E_ERROR:
                    echo "<h3><font color='#ff0000'>[Error] " . nl2br($errstr) . "</font></h3>\n";
                    if ($errline >= 0) echo "<h5>on line <font color='#ff0000'>$errline</font> in file <font color='#ff0000'>$errfile</font></h5>";
                    exit(1);
                    break;
            }
        }
        return true;
    }
}

/**
 * 渲染视图 / Render view
 *
 * @param string $_name  视图路径 / View path
 * @param array  $_param 渲染变量 / Render variables
 * @param int    $mode   渲染模式 / Render mode
 * @return string|void
 */
function ww_view($_name, $_param = array(), $mode = 0)
{
    return bootstrap::renderer($_name, $_param, $mode);
}

/**
 * 总路由调度 / Main route dispatcher
 *
 * @param string $_name    路由名称 / Route name
 * @param mixed  $_method  方法名 / Method name
 * @param array  $_parmas  参数 / Parameters
 * @return bool
 */
function ww_route($_name, $_method, $_parmas = array())
{
    return bootstrap::route($_name, $_method, $_parmas);
}

/**
 * 获取模型实例 / Get model instance
 *
 * @param string $_name 模块名 / Model name
 * @return object|null
 */
function ww_model($_name)
{
    return bootstrap::model($_name);
}

/**
 * 导入 libs 目录下的库文件 / Import library from libs directory
 *
 * @param string $_name 导入库路径 / Library import path
 */
function ww_import($_name)
{
    return bootstrap::import($_name);
}

/**
 * 加载配置文件 / Load configuration file
 *
 * @param string $_name 配置文件名 / Config file name
 * @return array|null
 */
function ww_config($_name = "config")
{
    return bootstrap::config($_name);
}

/**
 * 获取数据库操作对象 / Get database DAO
 *
 * @param string $_section 配置节点名 / Config section name
 * @param string $_name    配置文件名 / Config file name
 * @return database
 */
function ww_dao($_section = "default", $_name = "config")
{
    return bootstrap::dao($_section, $_name);
}

/**
 * 设置视图全局变量 / Set view global variable
 *
 * @param string $method  变量名 / Variable name
 * @param mixed  $value   变量值 / Variable value
 */
function ww_setVar($method, $value)
{
    return bootstrap::setVar($method, $value);
}

/**
 * 获取视图全局变量 / Get view global variable
 *
 * @param string $method  变量名 / Variable name
 * @return mixed
 */
function ww_getVar($method)
{
    return bootstrap::getVar($method);
}
