<?php
    require 'system/bootstrap.php';
    require 'system/short_func.php';
    bootstrap::start("app");
	ww_route("Hello","say",array("lili"));