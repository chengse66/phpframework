经过大量的测试后,发现URL重写并不是很友好,需要做大量的配置,而且有的服务端支持不是很好,所以放弃了这一部分功能,更改为传统的版本.

**首先了解下项目目录结构:**

- **system** 主文件路径,抱歉对vender不是太感冒
	- **bootstrap.php**->主框架核心
	- **autoload.php**->一个简化版的文件加载和报错处理
	- **core/template.php**->参考ZBLOG的模块编译库,蛮好用的.
	- **core/database.php**->一个改良的数据库类

- **app**->主项目目录文件夹
	- **config**->配置文件夹,包括数据库配置和其他配置选项
	- **controllers**->由Controller结尾的控制器类(逻辑视图调用)
	- **libs**->库文件目录
	- **views**->一大堆HTML模版文件
	- **cache**->这个默认是没有的由views模版进行编译

以上的结构和第一代框架几乎差不多,做项目足够了.
**index.php**: 
	
	<?php
    require 'system/bootstrap.php';
    bootstrap::start();
    ww_route("Hello","say",array("lili"));

整个框架分为6个函数:
	model 模块
	view  视图
	route 控制器
	import 库文件导入
	dao	   数据库控制函数
	config 配置文件读取函数

app/controllers/HelloworldController.php

	<?php
	class HelloworldController
	{
	    function say(){
	        //var_dump(bootstrap::model("sample")->getList());
	    }
	}

http://localhost/

	array(3) {
	  [0]=>
	  string(1) "a"
	  [1]=>
	  string(1) "b"
	  [2]=>
	  string(1) "c"
	}

**bootstrap::renderer($viewname,$params=array(),$mode=0)** 视图调用
	
	bootstrap::renderer("/helloworld",array("name"=>"lili"));

app/view/helloworld.html
	
	<html><body>hello{$name}</body></html>
浏览器输出:

	hellolili


**bootstrap::route($controlname,$method)**手动路由模式,如果你有特别需要
	
	bootstrap::route("Helloworld","say");

**import($path)** 导入libs下的文件
	
	ww_import('/excel/PHPExcel.php')


**dao($name)**数据库连接器,单独作为类使用用的PDO驱动,目前的话基本都支持PDO驱动的.
	
	bootstrap::dao("config");
对应配置文件:app/config/config.php
	
	<?php
	return array(
	    "dsn"=>"mysql:host=localhost;dbname=sample",
	    "user"=>"root",
	    "passwd"=>"root"
	);

有如下方法:
	
	bootstrap::dao()->fetch()
	bootstrap::dao()->fetchAll();
	bootstrap::dao()->lastInsertId();
	...

如果要使用简拼的方法名称:
	
	index.php
	<?php
    require 'system/bootstrap.php';
    require 'system/short_func.php';
    bootstrap::start("app");

	ww_view=bootstrap::renderer
	ww_route=bootstrap::route
	ww_import=bootstrap::import
	ww_config=bootstrap::config
	ww_dao=bootstrap::dao
	ww_post POST方法用来读取HTTP
	ww_get GET方法用来读取HTTP
	ww_create 创建初始目录
	
	bootstrap::controller($classname,$method); 检测方法是否存在

简单的模板语法：

	{@/header}
	外部文件引用
	{$item['name']}
	显示数组的名称
	{foreach $a as $k} 循环处理 {/foreach}
	{if $a==1}{else}{/if} 判断处理
	具体可以参考ZBLOG的模板库，这个模板处理库修改自ZBLOG，如果有侵权请联系我。

更多功能等着你去发现.QQ:1491247
