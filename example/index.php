<?php
define("DEBUG", true);
require '../system/bootstrap.php';
bootstrap::start("app");
$c = isset($_GET["c"]) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET["c"]) : "index";
$m = isset($_GET["m"]) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET["m"]) : "home";

if(bootstrap::controller($c, $m)){
    ww_route($c, $m);
}else{
    ww_route($c, "home");
}