# 框架文档 / Framework Documentation

轻量级 PHP MVC 微框架中文文档。

## 目录

| 文档 | 说明 |
|------|------|
| [BOOTSTRAP.md](./BOOTSTRAP.md) | 核心引导类 `bootstrap` 及全局函数 `ww_*` 使用说明 |
| [DATABASE.md](./DATABASE.md) | 数据库操作类 `database` 使用说明 |
| [HTTP.md](./HTTP.md) | HTTP 客户端 `http` / `httpRequest` 使用说明 |
| [TEMPLATE.md](./TEMPLATE.md) | 模板编译引擎 `template` 使用说明 |

## 快速开始

```php
<?php
define("DEBUG", true);
require 'system/bootstrap.php';
bootstrap::start("app");

// 获取数据库实例
$db = ww_dao("default", "config");
$row = $db->fetch("SELECT * FROM users WHERE id = :id", array(':id' => 1));

// 发起 HTTP 请求
$ret = http::get("https://example.com/api")->timeout(10)->submit();

// 渲染视图
ww_view("/welcome", array('name' => 'World'));
```
