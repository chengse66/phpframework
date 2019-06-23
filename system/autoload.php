<?php
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