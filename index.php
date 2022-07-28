define("DEBUG", true);
require './../system/bootstrap.php';
bootstrap::start("app");
$do=$_REQUEST["do"];
if(empty($do)) $do="home";
if(!empty($do) && bootstrap::controller("test", $do)!==FALSE){
    ww_route("test", $do);
}else{
    ww_route("test", "home");
}
