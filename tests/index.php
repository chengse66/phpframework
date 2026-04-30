<?php
define("DEBUG", true);
require '../system/bootstrap.php';
bootstrap::start("app");

$c = isset($_GET["c"]) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET["c"]) : "test";
$m = isset($_GET["m"]) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET["m"]) : "home";
if(empty($c)) $c = "index";
if(empty($m)) $m = "home";

if(bootstrap::controller($c, $m)){
    ww_route($c, $m);
}else{
    ww_route($c, "home");
}