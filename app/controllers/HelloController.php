<?php
class HelloController{
	function say($name){
		echo "Helloworld $name";
		$json=array();
		$json->a=1212;
	}
}