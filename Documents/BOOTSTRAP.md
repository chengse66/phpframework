# Bootstrap 核心引导类

`bootstrap` 是框架的服务容器、路由器和自动加载器，所有框架服务通过它的静态方法或全局快捷函数访问。

---

## 初始化

```php
define("DEBUG", true);              // 开启调试模式（必须在引入 bootstrap 之前定义）
require 'system/bootstrap.php';
bootstrap::start("app");            // "app" 为应用目录名
```

`DEBUG` 模式的影响：
- 模板每次请求重新编译（不走缓存）
- 错误信息完整输出（含文件、行号、堆栈）
- 缓存文件使用可读文件名（非 md5）

---

## bootstrap 类方法

### start($appName)

启动框架，初始化内部状态。

```php
bootstrap::start("app");
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$appName` | string | 应用目录名，默认 `"app"` |

---

### controller($name, $method)

加载并返回控制器实例。控制器类名自动追加 `Controller` 后缀。

```php
// ?c=Test 时自动映射到 TestController
$instance = bootstrap::controller("Test", "home");
// 返回 TestController 实例（若类存在且方法存在），否则返回 false
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$name` | string | 控制器名，不含 `Controller` 后缀（也可含后缀） |
| `$method` | string | 要校验的方法名 |
| **返回** | object\|false | 控制器实例或 false |

控制器实例按类名单例缓存，同一次请求内重复调用返回同一对象。

---

### model($name)

加载并返回模型实例。模型类名自动追加 `Model` 后缀。

```php
$userModel = bootstrap::model("User");   // 映射到 UserModel
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$name` | string | 模型名，不含 `Model` 后缀 |
| **返回** | object\|null | 模型实例，不存在则返回 null |

---

### route($name, $method, $params)

执行控制器方法（路由调度）。

```php
bootstrap::route("Test", "home");                              // 调用 TestController::home()
bootstrap::route("User", "detail", array($id));                // 带参数调用
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$name` | string | 控制器名 |
| `$method` | string | 方法名 |
| `$params` | array | 传递给方法的参数，默认 `[]` |
| **返回** | bool | 成功调度返回 true，否则 false |

---

### dao($section, $configFile)

获取数据库 DAO 实例。

```php
// 加载 app/config/config.php 中 "default" 节点的配置
$db = bootstrap::dao("default", "config");
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$section` | string | 配置文件中的节点名 |
| `$configFile` | string | 配置文件名（不含 .php 后缀），对应 `app/config/` 下的文件 |
| **返回** | database | database 实例（单例缓存） |

配置文件格式示例（`app/config/config.php`）：

```php
return array(
    "default" => array(
        "dsn"   => "mysql:host=localhost;dbname=test",
        "user"  => "root",
        "passwd" => "root"
    ),
    "slave" => array(
        "dsn"   => "mysql:host=slave host;dbname=test",
        "user"  => "reader",
        "passwd" => "reader_pass"
    )
);
```

---

### config($name)

加载并返回配置数组。

```php
$config = bootstrap::config("config");      // 加载 app/config/config.php
$dbConfig = bootstrap::config("test_db");   // 加载 app/config/test_db.php
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$name` | string | 配置文件名（不含 .php 后缀） |
| **返回** | array\|null | 配置数组，文件不存在返回 null |

配置按文件路径单例缓存。

---

### renderer($path, $params, $mode)

渲染视图模板。

```php
// 直接输出（默认）
bootstrap::renderer("/welcome", array('name' => 'World'));

// 获取编译后的 PHP 文件路径
$cachePath = bootstrap::renderer("/welcome", array(), RENDERER_PATH);
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$path` | string | 视图路径，对应 `app/views/` 下的 `.html` 文件 |
| `$params` | array | 传递给模板的变量，默认 `[]` |
| `$mode` | int | `RENDERER_BODY`（默认，直接输出）或 `RENDERER_PATH`（返回缓存文件路径） |
| **返回** | string\|void | `RENDERER_PATH` 模式返回文件路径 |

---

### import($path)

导入 `app/libs/` 目录下的库文件。

```php
bootstrap::import("/utils/Helper");   // 加载 app/libs/utils/Helper.php
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$path` | string | 相对于 `app/libs/` 的路径（不含 .php 后缀） |

文件不存在时触发用户级错误。

---

### setVar($key, $value) / getVar($key)

设置/获取视图全局变量。全局变量在所有模板渲染时自动可用。

```php
bootstrap::setVar("site_name", "我的网站");
$name = bootstrap::getVar("site_name");
```

---

### app_path($path)

获取应用目录的绝对路径。

```php
$fullPath = bootstrap::app_path("/controllers/TestController.php");
// 例：/var/www/html/app/controllers/TestController.php
```

---

### webroot()

获取入口文件（index.php）所在目录的绝对路径。

```php
$root = bootstrap::webroot();   // 例：/var/www/html
```

---

## 全局快捷函数 ww_*

所有 `ww_*` 函数都是 `bootstrap` 对应方法的快捷包装：

| 函数 | 等价调用 |
|------|----------|
| `ww_view($path, $params, $mode)` | `bootstrap::renderer($path, $params, $mode)` |
| `ww_route($name, $method, $params)` | `bootstrap::route($name, $method, $params)` |
| `ww_model($name)` | `bootstrap::model($name)` |
| `ww_import($path)` | `bootstrap::import($path)` |
| `ww_config($name)` | `bootstrap::config($name)` |
| `ww_dao($section, $configFile)` | `bootstrap::dao($section, $configFile)` |
| `ww_setVar($key, $value)` | `bootstrap::setVar($key, $value)` |
| `ww_getVar($key)` | `bootstrap::getVar($key)` |

### 使用示例

```php
// 获取数据库
$db = ww_dao("default", "config");

// 加载配置
$config = ww_config("config");

// 渲染视图并传递变量
ww_view("/user/profile", array('user' => $user));

// 设置视图全局变量
ww_setVar("title", "用户中心");

// 导入库文件
ww_import("/utils/Paginator");

// 路由调度
ww_route("User", "list");
```

---

## 自动加载机制

框架注册了 `spl_autoload_register`，当使用未定义的类时自动在 `app/libs/` 目录下查找同名 `.php` 文件：

```
$class_name → app/libs/$class_name.php
```

---

## 错误与异常处理

- **自定义错误处理：** DEBUG 模式下 `E_ERROR` / `E_USER_ERROR` 会输出详细信息并终止程序。
- **异常处理：** DEBUG 模式返回 JSON 格式的异常详情（message、file、line、stackTrace）；非 DEBUG 模式仅返回 `{"message":"Internal Server Error"}`。

---

## 请求生命周期

```
index.php
  ├── define("DEBUG", true)
  ├── require bootstrap.php
  │     ├── 加载核心类 (database, template, http)
  │     ├── 设置错误/异常处理器
  │     ├── 注册自动加载
  │     └── 定义全局快捷函数
  ├── bootstrap::start("app")
  ├── 从 $_REQUEST 获取 c 和 do（过滤非字母数字下划线字符）
  ├── bootstrap::controller($c, $do)   → 尝试加载控制器
  │     └── 成功 → ww_route($c, $do)   → 执行控制器方法
  │     └── 失败 → ww_route($c, "home") → 回退到 home 方法
  └── 结束
```
