<?php
/**
 * 渲染视图
 * @param string $_name 路径
 * @param array $_param 渲染变量
 * @param int $mode 模式RENDERER_BODY,RENDERER_PATH
 * @return string
 */
function ww_view($_name, $_param=array(), $mode=0){return bootstrap::renderer($_name,$_param,$mode);}
/**
 * 总路由
 * @param string $_name 路由名称
 * @param mixed $_method    方法
 * @param mixed $_parmas 参数
 */
function ww_route($_name, $_method,$_parmas=array()){bootstrap::route($_name,$_method,$_parmas);}
/**
 * 模块对象
 * @param $_name 路由名
 * @return mixed
 */
function ww_model($_name){return bootstrap::model($_name);}
/**
 * 导入libs文件夹下的库
 * @param $_name 导入库文件
 */
function ww_import($_name){return bootstrap::import($_name);}
/**
 * 配置路由对象
 * @param string $_name
 * @return mixed|array 获取配置对象
 */
function ww_config($_name="config"){return bootstrap::config($_name);}
/**
 * 数据库操作DAO
 * @param $_name 对应数据库配置名称
 * @return database 数据库对象
 */
function ww_dao($_name="config"){return bootstrap::dao($_name);}