# HTTP 客户端

基于 cURL 的 HTTP 客户端，支持 GET / POST / PUT / DELETE / OPTIONS 方法，采用链式调用风格。

---

## 创建请求

通过 `http` 类的静态方法创建 `httpRequest` 构建器：

```php
$req = http::get($url);       // GET 请求
$req = http::post($url);      // POST 请求
$req = http::put($url);       // PUT 请求
$req = http::delete($url);    // DELETE 请求
$req = http::options($url);   // OPTIONS 请求
```

---

## 链式调用方法

所有方法返回 `$this`，支持链式调用，最后用 `submit()` 发送请求。

### withForm($data)

发送表单数据（`application/x-www-form-urlencoded`）。

```php
$ret = http::post($url)->withForm(array(
    'username' => 'admin',
    'password' => '123456'
))->submit();
```

---

### withJson($data)

发送 JSON 数据（`application/json`）。

```php
$ret = http::post($url)->withJson(array(
    'name'  => 'Alice',
    'email' => 'alice@test.com'
))->submit();
```

---

### withData($rawData)

发送原始二进制数据（`application/octet-stream`）。

```php
$ret = http::post($url)->withData($binaryContent)->submit();
```

---

### withTextData($text)

发送纯文本数据（`text/plain`）。

```php
$ret = http::post($url)->withTextData("Hello World")->submit();
```

---

### withQuery($params)

追加 URL 查询参数（GET 请求常用）。

```php
$ret = http::get($url)->withQuery(array(
    'page' => 1,
    'size' => 20
))->submit();
// 实际请求：$url?page=1&size=20
```

---

### withHeader($name, $value)

设置单个请求头。

```php
$ret = http::get($url)
    ->withHeader("Authorization", "Bearer token123")
    ->submit();
```

---

### withHeaders($headers)

批量设置请求头。

```php
$ret = http::get($url)->withHeaders(array(
    'X-App-Id'  => 'myapp',
    'X-Api-Key' => 'secret'
))->submit();
```

---

### onReady($callback)

设置回调函数，请求完成后自动调用。

```php
$ret = http::get($url)->onReady(function($response) {
    if ($response['success']) {
        echo "状态码: " . $response['status'];
    }
})->submit();
// $ret 与回调参数结构相同
```

---

### timeout($seconds)

设置超时时间（秒），同时应用于连接超时和请求超时。默认 30 秒。

```php
$ret = http::get($url)->timeout(10)->submit();
```

---

### verifySSL($checkable, $certPath)

控制 SSL 证书验证。

```php
// 启用验证（默认行为）
$ret = http::get("https://example.com")->verifySSL(true)->submit();

// 跳过验证（开发环境）
$ret = http::get("https://self-signed.test")->verifySSL(false)->submit();

// 指定 CA 证书
$ret = http::get("https://example.com")->verifySSL(true, "/path/to/cacert.pem")->submit();
```

| 参数 | 类型 | 说明 |
|------|------|------|
| `$checkable` | bool | 是否验证 SSL，默认 `true` |
| `$certPath` | string\|null | CA 证书文件路径 |

---

### followRedirects($follow)

是否跟随重定向，默认开启（最多 5 次）。

```php
$ret = http::get($url)->followRedirects(false)->submit();
```

---

### submit()

发送请求，返回结果数组。

```php
$ret = http::get($url)->timeout(10)->submit();
```

---

## 返回值结构

`submit()` 返回关联数组：

```php
array(
    'success' => true,              // bool, cURL 是否成功执行
    'body'    => '{"key":"value"}', // string, 响应体
    'header'  => 'HTTP/1.1 200...', // string, 响应头（原始文本）
    'status'  => 200,               // int, HTTP 状态码
)
```

失败时：

```php
array(
    'success' => false,
    'err'     => 'Could not resolve host'  // string, cURL 错误信息
)
```

---

## 完整示例

### GET 请求

```php
$ret = http::get("https://api.example.com/users")
    ->withQuery(array('page' => 1))
    ->withHeader("Authorization", "Bearer token")
    ->timeout(10)
    ->submit();

if ($ret['success']) {
    $data = json_decode($ret['body'], true);
}
```

### POST JSON

```php
$ret = http::post("https://api.example.com/users")
    ->withJson(array('name' => 'Alice', 'age' => 25))
    ->withHeader("Authorization", "Bearer token")
    ->onReady(function($res) {
        // 请求完成后的回调
    })
    ->submit();
```

### PUT 更新

```php
$ret = http::put("https://api.example.com/users/1")
    ->withJson(array('name' => 'Bob'))
    ->submit();
```

### DELETE 删除

```php
$ret = http::delete("https://api.example.com/users/1")->submit();
```
