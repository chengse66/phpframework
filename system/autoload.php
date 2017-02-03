<?php
session_start();
if(!function_exists("ww_autoload")){
    function ww_autoload($_class_name){
        require_once __DIR__.'/core/'.$_class_name.'.php';
    }
    spl_autoload_register("ww_autoload");
}
if(!function_exists("ww_error_handle")){
    function ww_error_handle($errno, $errstr, $errfile, $errline)
    {
        var_dump(func_get_args());
        if (!(error_reporting() & $errno)) return;
        switch ($errno) {
            case E_USER_ERROR:
                echo "<b>My ERROR</b> [$errno] $errstr<br />\n";
                echo "  Fatal error on line $errline in file $errfile";
                echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
                exit(1);
                break;

            case E_USER_WARNING:
                echo "<b>My WARNING</b> [$errno] $errstr<br />\n";
                break;

            case E_USER_NOTICE:
                echo "<b>My NOTICE</b> [$errno] $errstr<br />\n";
                break;
            default:
                echo "Unknown error type: [$errno] $errstr<br />\n";
                break;
        }
        return true;
    }
    set_error_handler("ww_error_handle");
}