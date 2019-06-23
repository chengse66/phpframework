<?php
	define("DEBUG",true);
    require 'system/bootstrap.php';
    require 'system/short_func.php';
    bootstrap::start("app");
	ww_route("Hello","say11",array("lili"));