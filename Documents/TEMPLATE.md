# Template 模板编译引擎

HTML 模板编译器，将 `.html` 模板文件编译为可执行的 PHP 缓存文件。

---

## 渲染视图

```php
// 直接输出渲染结果
ww_view("/welcome", array('name' => 'World', 'items' => $list));

// 获取编译后的缓存文件路径
$path = ww_view("/welcome", array(), RENDERER_PATH);
```

模板文件位于 `app/views/` 目录，路径对应 `.html` 文件：

```
ww_view("/user/profile")  → app/views/user/profile.html
```

---

## 缓存机制

- 编译后的 PHP 文件缓存到 `app/cache/` 目录
- **DEBUG 模式：** 缓存文件名保持可读路径（如 `app/cache/user/profile.cache.php`），每次请求检测模板修改时间自动重新编译
- **生产模式：** 缓存文件名使用 md5 哈希（如 `app/cache/a1b2c3d4.php`），仅在缓存不存在时编译

所有编译文件头部自动添加安全守卫：

```php
<?php if(!defined("ALLOW_ACCESS")) exit("not access");?>
```

防止通过 URL 直接访问缓存文件。

---

## 模板语法

### 变量输出

```
{$name}                → <?php echo $name; ?>
{$user.name}           → <?php echo $user->name; ?>    （点号自动转为 ->）
{$user.profile.age}    → <?php echo $user->profile->age; ?>
```

### 变量赋值

```
{$count = 0}           → <?php $count = 0; ?>
{$total = $price * 2}  → <?php $total = $price * 2; ?>
```

> 含 `=` 的变量标签执行赋值而非输出。

---

### 条件判断

```
{if $age > 18}
    <p>成年人</p>
{elseif $age > 12}
    <p>青少年</p>
{else}
    <p>儿童</p>
{/if}
```

支持嵌套，支持点号属性访问：

```
{if $user.vip}
    <p>VIP 用户</p>
{/if}
```

---

### foreach 循环

```
{foreach $items as $item}
    <li>{$item.name}</li>
{/foreach}
```

支持键值对形式：

```
{foreach $users as $key => $user}
    <p>{$key}: {$user.name}</p>
{/foreach}
```

支持嵌套。

---

### for 循环

```
{for $i = 0; $i < 10; $i++}
    <p>第 {$i} 项</p>
{/for}
```

---

### 函数调用

```
{date('Y-m-d')}                    → <?php echo date('Y-m-d'); ?>
{strlen($name)}                    → <?php echo strlen($name); ?>
{bootstrap::getVar('title')}       → <?php echo bootstrap::getVar('title'); ?>
```

---

### 模板引用

```
{include('/header')}    → 引入 app/views/header.html
{include('/layout/nav')} → 引入 app/views/layout/nav.html
```

---

### PHP 原生代码

```
{php}
    $arr = array(1, 2, 3);
    echo count($arr);
{/php}
```

---

### 全局变量输出

```
{var: title}            → <?php echo bootstrap::getVar("title"); ?>
{var: site_name;}       → <?php echo bootstrap::getVar("site_name"); ?>
```

用于输出通过 `ww_setVar()` 设置的全局变量。

---

### 点号转换规则

模板中的 `.` 在变量和表达式中自动转换为 PHP 的 `->` 对象访问符：

| 模板语法 | 编译结果 |
|----------|----------|
| `{$user.name}` | `<?php echo $user->name; ?>` |
| `{$order.item.price}` | `<?php echo $order->item->price; ?>` |
| `{if $user.active}` | `<?php if ($user->active) { ?>` |

字符串内的点号（引号包裹）不会被转换。

---

## 编译流程

```
Compiling($content)
  1. parsePHP     → 提取 {php}...{/php} 块，暂存为占位符
  2. parse_template → 处理 {include()} 模板引用
  3. parse_vars   → 处理 {$var} 变量输出/赋值
  4. parse_function → 处理 {func()} 函数调用
  5. parse_special → 处理 {var:xxx} 全局变量
  6. parse_if     → 处理 {if}/{elseif}/{else}/{/if}
  7. parse_foreach → 处理 {foreach}/{/foreach}
  8. parse_for    → 处理 {for}/{/for}
  9. parsePHP2    → 还原 {php} 块为原生 PHP 标签
```

---

## 使用示例

### 控制器

```php
class UserController {
    function profile() {
        ww_setVar("title", "用户资料");
        $user = (object)array('name' => 'Alice', 'age' => 25, 'vip' => true);
        $orders = array(
            (object)array('id' => 1, 'amount' => 100),
            (object)array('id' => 2, 'amount' => 200),
        );
        ww_view("/user/profile", array(
            'user'   => $user,
            'orders' => $orders
        ));
    }
}
```

### 模板 (`app/views/user/profile.html`)

```html
{include('/header')}

<h1>{$user.name} 的资料</h1>

{if $user.vip}
    <span class="badge">VIP</span>
{/if}

<h2>订单列表</h2>
<table>
{foreach $orders as $order}
    <tr>
        <td>{$order.id}</td>
        <td>{$order.amount}</td>
    </tr>
{/foreach}
</table>

<p>当前时间: {date('Y-m-d H:i:s')}</p>
<p>站点: {var: title}</p>
```
