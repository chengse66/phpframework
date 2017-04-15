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
    function ww_error_handle($errno, $errstr, $errfile, $errline)
    {
    	if(DEVELOPMENT){
	        switch ($errno) {
	        	case E_USER_ERROR:
	            case E_ERROR:
	            case E_WARNING:
	                echo "<h3><font color='#ff0000'>[Error] $errstr</font></h3>\n";
	                echo "<h5>on line <font color='#ff0000'>$errline</font> in file <font color='#ff0000'>$errfile</font></h5>";
	                exit(1);
	                break;
	        }
    	}
        return true;
    }
    set_error_handler("ww_error_handle");
}