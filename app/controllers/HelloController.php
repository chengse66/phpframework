<?php
class HelloController{
	function test(){
		echo "this is a test";
		
		ww_view("/welcome");
		ww_import("/aa/bb");
	}
}