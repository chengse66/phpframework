<?php
	define("DEBUG",true);
    require 'system/bootstrap.php';
    require 'system/short_func.php';
    bootstrap::start("app");
	echo bootstrap::guid();
	//echo time();