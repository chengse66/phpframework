# PHP Micro Framework

轻量级单文件 PHP MVC 微框架，无 Composer 依赖，开箱即用。

## 目录结构

```
├── system/                       框架核心
│   ├── bootstrap.php             引导类、服务容器、路由器
│   └── core/
│       ├── database.php          PDO 数据库封装
│       ├── template.php          模板编译引擎
│       └── http.php              cURL HTTP 客户端
│
├── example/                      示例应用
│   ├── index.php                 入口文件
│   └── app/
│       ├── config/               配置文件
│       ├── controllers/          控制器
│       ├── views/                模板 (.html)
│       ├── models/               模型（按需创建）
│       ├── libs/                 第三方库（按需创建）
│       └── cache/                模板编译缓存（自动生成）
│
├── tests/                        测试应用
│   ├── index.php                 测试入口
│   └── app/
│       ├── controllers/          测试控制器
│       └── views/test/           测试视图
│
├── Documents/                    详细文档
│   ├── BOOTSTRAP.md              Bootstrap 引导类
│   ├── DATABASE.md               Database 数据库类
│   ├── HTTP.md                   HTTP 客户端
│   └── TEMPLATE.md               模板引擎
│
└── CLAUDE.md                     Claude Code 项目指引
```

## 快速开始

启动内置服务器：

```bash
cd example
php -S localhost:8000
```

访问 `http://localhost:8000` 即可运行。

## 入口与路由

使用 URL 查询参数路由（无需 URL 重写）：

```
?c=UserController&do=profile     →  UserController::profile()
?c=User&do=profile               →  UserController::profile()（自动追加后缀）
?c=User&do=list&page=2           →  UserController::list($page=2)
```

控制器名和方法名仅保留 `a-zA-Z0-9_`，自动过滤危险字符。

入口文件模板：

```php
<?php
define("DEBUG", true);
require '../system/bootstrap.php';
bootstrap::start("app");

$c = isset($_GET["c"]) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET["c"]) : "index";
$m = isset($_GET["m"]) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET["m"]) : "home";

if (bootstrap::controller($c, $m)) {
    ww_route($c, $m);
} else {
    ww_route($c, "home");
}
```

## 核心功能

### 数据库

PDO 封装，支持 MySQL / SQL Server，参数化查询防 SQL 注入：

```php
$db = ww_dao("default", "config");

$db->insert('users', array('name' => 'Alice', 'age' => 25));
$row = $db->fetch("SELECT * FROM users WHERE id = :id", array(':id' => 1));
$rows = $db->fetchAll("SELECT * FROM users WHERE age > :age", array(':age' => 18));
$db->update('users', array('age' => 26), array('id' => 1));
$db->delete('users', array('status' => 0));   // 空条件拒绝执行，防误删全表
```

### 模板引擎

编译 `.html` 模板为 PHP 缓存文件，支持变量、条件、循环、函数调用：

```html
<h1>{$title}</h1>

{foreach $users as $user}
<div>
    <span>{$user.name}</span>
    {if $user.vip}<span class="badge">VIP</span>{/if}
</div>
{/foreach}

<p>{date('Y-m-d')}</p>
```

**XSS 防护：** `{$var}` 默认自动转义 HTML（`htmlspecialchars`）。需要输出原始 HTML 时使用 `{!$var}`。

### HTTP 客户端

cURL 封装，链式调用：

```php
$ret = http::post("https://api.example.com/users")
    ->withJson(array('name' => 'Alice'))
    ->withHeader("Authorization", "Bearer token")
    ->timeout(10)
    ->submit();

if ($ret['success']) {
    $data = json_decode($ret['body'], true);
}
```

## 模板语法速查

| 语法 | 说明 | 编译结果 |
|------|------|---------|
| `{$name}` | 变量输出（自动转义） | `htmlspecialchars($name)` |
| `{!$name}` | 原始输出（不转义） | `echo $name` |
| `{$count = 0}` | 变量赋值 | `$count = 0` |
| `{$user.name}` | 点号转箭头 | `$user->name` |
| `{if $x}...{elseif $y}...{else}...{/if}` | 条件判断 | `if/elseif/else` |
| `{foreach $list as $item}...{/foreach}` | 遍历循环 | `foreach` |
| `{for $i=0; $i<10; $i++}...{/for}` | 计数循环 | `for` |
| `{strtoupper($name)}` | 函数调用 | `echo strtoupper($name)` |
| `{var: title}` | 全局变量 | `bootstrap::getVar("title")` |
| `{include('/header')}` | 引入子模板 | `bootstrap::renderer(...)` |
| `{php} ... {/php}` | 原生 PHP 块 | `<?php ... ?>` |

## 全局函数

| 函数 | 用途 |
|------|------|
| `ww_view($path, $params)` | 渲染视图 |
| `ww_route($name, $method)` | 路由调度 |
| `ww_model($name)` | 获取模型实例 |
| `ww_dao($section, $config)` | 获取数据库实例 |
| `ww_config($name)` | 加载配置文件 |
| `ww_import($path)` | 导入库文件 |
| `ww_setVar($key, $val)` | 设置视图全局变量 |
| `ww_getVar($key)` | 获取视图全局变量 |

## 安全特性

- **SQL 注入：** CRUD 方法使用 PDO 预处理参数绑定；表名/字段名反引号转义
- **XSS：** 模板变量 `{$var}` 默认 `htmlspecialchars` 转义
- **模板代码注入：** 全局变量标签 `{var:}` 限制参数为安全字符；危险函数黑名单（`system`/`exec`/`phpinfo`/`unlink` 等）
- **路径穿越：** `cleanPath()` 循环移除 `../` 和 `..\\`
- **输入过滤：** 路由参数仅保留 `a-zA-Z0-9_`
- **模板安全：** 编译缓存文件自动添加 `ALLOW_ACCESS` 守卫
- **目录保护：** `system/`、`app/cache/`、`app/config/` 设有 `.htaccess` 拒绝浏览器直接访问

## 测试

浏览器访问测试页面：

```
http://localhost:8000/?c=TemplateTest&do=view    模板引擎测试
http://localhost:8000/?c=RouteTest&do=view       路由/引导测试
http://localhost:8000/?c=HttpTest&do=view         HTTP 客户端测试（需联网）
http://localhost:8000/?c=DatabaseTest&do=view     数据库测试（需 MySQL）
```

## 详细文档

参阅 [Documents/](Documents/) 目录：

| 文档 | 内容 |
|------|------|
| [BOOTSTRAP.md](Documents/BOOTSTRAP.md) | Bootstrap 引导类 API |
| [DATABASE.md](Documents/DATABASE.md) | Database 数据库 CRUD API |
| [HTTP.md](Documents/HTTP.md) | HTTP 客户端 API |
| [TEMPLATE.md](Documents/TEMPLATE.md) | 模板语法与编译流程 |

## 环境要求

- PHP 5.4+（推荐 7.0+）
- PDO 扩展（数据库功能）
- cURL 扩展（HTTP 客户端功能）
- Apache / Nginx / PHP 内置服务器
