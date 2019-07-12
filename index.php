<?php
define("DEBUG",true);
require 'system/bootstrap.php';
bootstrap::start("app");
ww_route("Hello", "test");