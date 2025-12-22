# Routini 安全性指南

**文件版本**: 1.0
**最後更新**: 2025-12-22

---

## 📋 目錄

1. [安全性概述](#安全性概述)
2. [已識別的安全風險](#已識別的安全風險)
3. [已實作的防護措施](#已實作的防護措施)
4. [使用者責任](#使用者責任)
5. [安全最佳實踐](#安全最佳實踐)
6. [待改善項目](#待改善項目)

---

## 安全性概述

Routini 是一個輕量級 PHP 路由框架，專注於提供核心路由功能。作為底層框架，某些安全防護需要由應用層實作。

### 安全設計原則

- ✅ **最小權限原則**: 類別屬性使用私有封裝
- ✅ **介面隔離**: 明確的契約定義，減少誤用
- ⚠️ **輸入驗證**: 需由應用層實作
- ⚠️ **輸出編碼**: 需由應用層實作

---

## 已識別的安全風險

### 🔴 高風險

#### 1. Host Header Injection (主機標頭注入)

**風險描述**:
```php
// src/Router.php:168
server: ['HTTP_HOST' => $requestHost ?? ($_SERVER['HTTP_HOST'] ?? '')]
```

**問題**:
- 直接使用 `$_SERVER['HTTP_HOST']` 而未驗證
- 攻擊者可偽造 Host 標頭進行快取污染、密碼重設攻擊等

**影響**:
- 快取污染 (Cache Poisoning)
- 釣魚攻擊 (Phishing)
- 密碼重設連結劫持

**建議修復**:
```php
// 驗證 Host 標頭
private function validateHost(string $host, array $allowedHosts): bool
{
    return in_array($host, $allowedHosts, true);
}
```

**暫時解決方案**:
```php
// 使用者應在 web server 層級設定允許的 Host
// Nginx: server_name example.com;
// Apache: ServerName example.com
```

---

#### 2. Arbitrary Code Execution (任意程式碼執行)

**風險描述**:
```php
// src/Router.php:304-306
if (is_callable($route->getAction())) {
    $result = call_user_func_array($route->getAction(), $params);
} elseif (is_array($route->getAction())) {
    [$controller, $method] = $route->getAction();
    $instance = new $controller();  // ⚠️ 直接實例化
    $result = call_user_func_array([$instance, $method], $params);
}
```

**問題**:
- 如果 `$controller` 來自不可信來源，可能執行任意類別
- 雖然路由定義通常在程式碼中，但仍需防範

**影響**:
- 遠端程式碼執行 (RCE)
- 系統完全被控制

**建議修復**:
```php
// 1. 白名單驗證控制器
private array $allowedControllers = [];

// 2. 驗證控制器類別存在且符合規範
private function validateController(string $controller): bool
{
    if (!class_exists($controller)) {
        throw new \Exception("Controller not found: {$controller}");
    }

    // 檢查是否實作特定介面
    if (!is_subclass_of($controller, ControllerInterface::class)) {
        throw new \Exception("Invalid controller: {$controller}");
    }

    return true;
}
```

**目前狀態**: ✅ 低風險（路由定義在程式碼中，非來自使用者輸入）

---

### 🟡 中風險

#### 3. Path Traversal (路徑遍歷)

**風險描述**:
```php
// 路由參數: /files/{filename}
// 惡意請求: /files/../../etc/passwd
```

**問題**:
- 如果路由參數用於檔案系統操作，可能讀取任意檔案

**影響**:
- 讀取系統敏感檔案
- 資訊洩漏

**目前狀態**: ✅ RegexMatcher 已防止基本攻擊，但應用層仍需驗證

**建議**:
```php
// 應用層驗證
$filename = $request->getAttribute('filename');
$filename = basename($filename); // 移除路徑部分
$realPath = realpath($basePath . '/' . $filename);
if (strpos($realPath, $basePath) !== 0) {
    throw new \Exception('Invalid path');
}
```

---

#### 4. Mass Assignment (大量賦值)

**風險描述**:
```php
// 路由參數直接設定到 Request attributes
$request->setAttributes($route->getParameters());
```

**問題**:
- 如果控制器直接使用這些參數建立/更新模型，可能修改不應修改的欄位

**影響**:
- 權限提升
- 資料篡改

**建議**:
```php
// 控制器中使用白名單
public function update(Request $request)
{
    $allowed = ['name', 'email']; // 允許的欄位
    $data = array_intersect_key(
        $request->all(),
        array_flip($allowed)
    );
    $user->update($data);
}
```

---

#### 5. Open Redirect (開放重定向)

**風險描述**:
```php
// Response::redirect($url)
// 如果 $url 來自使用者輸入，可能導致釣魚攻擊
```

**問題**:
- 未驗證重定向目標

**影響**:
- 釣魚攻擊
- 憑證竊取

**建議修復**:
```php
// 在 Response::redirect() 中加入驗證
public static function redirect(string $url, int $status = 302): self
{
    // 驗證 URL 是否為相對路徑或同域
    if (!self::isSafeRedirect($url)) {
        throw new \Exception('Invalid redirect URL');
    }

    return (new self('', $status))
        ->addHeader('Location', $url);
}

private static function isSafeRedirect(string $url): bool
{
    // 允許相對 URL
    if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
        return true;
    }

    // 允許同域 URL
    $parsed = parse_url($url);
    if (isset($parsed['host'])) {
        return $parsed['host'] === $_SERVER['HTTP_HOST'];
    }

    return false;
}
```

---

### 🟢 低風險（需注意）

#### 6. Denial of Service (阻斷服務攻擊)

**問題**:
- 無內建速率限制
- 複雜的正則表達式可能導致 ReDoS

**建議**:
- 在 web server 層級實作速率限制
- 避免過於複雜的路由模式

---

#### 7. Information Disclosure (資訊洩漏)

**問題**:
- 錯誤訊息可能洩漏系統資訊

**建議**:
```php
// 生產環境關閉詳細錯誤訊息
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE);
```

---

## 已實作的防護措施

### ✅ 1. 屬性封裝

```php
// RouteItem 使用私有屬性
class RouteItem {
    public function __construct(
        private array $methods,    // ✅ 私有，無法被外部直接修改
        private string $uri,
        private $action,
        // ...
    ) {}
}
```

**防護**: 防止惡意修改路由定義

---

### ✅ 2. HTTP Method 驗證

```php
// Router.php 驗證 HTTP 方法
if (!in_array($requestMethod, $route->getMethods())) {
    continue;
}
```

**防護**: 防止方法覆蓋攻擊

---

### ✅ 3. 型別安全

```php
// 介面定義明確的型別
public function setParameters(array $parameters): void;
public function getMethods(): array;
```

**防護**: 防止型別混淆攻擊

---

### ✅ 4. 中介軟體洋蔥模型

```php
// 支援在中介軟體層實作安全檢查
$router->middleware(AuthMiddleware::class);
$router->middleware(CsrfMiddleware::class);
```

**防護**: 提供統一的安全檢查點

---

## 使用者責任

作為輕量級框架，以下安全措施需由使用者實作：

### 🔒 必須實作

1. **CSRF 保護**
   ```php
   class CsrfMiddleware implements MiddlewareInterface
   {
       public function handle(Request $request, callable $next)
       {
           if (!$this->validateCsrfToken($request)) {
               return Response::error('Invalid CSRF token', 403);
           }
           return $next($request);
       }
   }
   ```

2. **輸入驗證**
   ```php
   $email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);
   if (!$email) {
       return Response::error('Invalid email', 400);
   }
   ```

3. **輸出編碼**
   ```php
   // 使用 htmlspecialchars 防止 XSS
   echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
   ```

4. **SQL 注入防護**
   ```php
   // 使用參數化查詢（PDO）
   $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
   $stmt->execute([$userId]);
   ```

5. **認證與授權**
   ```php
   class AuthMiddleware implements MiddlewareInterface
   {
       public function handle(Request $request, callable $next)
       {
           if (!$this->isAuthenticated($request)) {
               return Response::error('Unauthorized', 401);
           }
           return $next($request);
       }
   }
   ```

---

## 安全最佳實踐

### 1. 路由定義

```php
// ✅ 好的做法
Route::get('/users/{id}', [UserController::class, 'show'])
    ->middleware(AuthMiddleware::class);

// ❌ 避免
Route::get('/users/{id}', $_GET['controller']); // 永遠不要這樣做！
```

### 2. 參數驗證

```php
// ✅ 好的做法
public function show(Request $request)
{
    $id = filter_var(
        $request->getAttribute('id'),
        FILTER_VALIDATE_INT
    );

    if ($id === false || $id <= 0) {
        return Response::error('Invalid ID', 400);
    }

    // ...
}
```

### 3. 檔案操作

```php
// ✅ 好的做法
$filename = basename($request->getAttribute('file'));
$path = realpath($uploadDir . '/' . $filename);

if (strpos($path, $uploadDir) !== 0) {
    return Response::error('Invalid path', 400);
}
```

### 4. 重定向

```php
// ✅ 好的做法
$allowedRedirects = ['/dashboard', '/profile'];
$redirect = $request->input('redirect');

if (in_array($redirect, $allowedRedirects, true)) {
    return Response::redirect($redirect);
}

// ❌ 避免
return Response::redirect($request->input('url')); // 未驗證！
```

---

## 待改善項目

### 計劃中的安全性改善

#### S1. Host Header 驗證 🔴 高優先級

**預估時間**: 30 分鐘

**實作內容**:
- 新增 `Request::validateHost()` 方法
- 在 `Router::dispatchRequest()` 中驗證
- 提供設定允許的 Host 清單

---

#### S2. 控制器白名單驗證 🔴 高優先級

**預估時間**: 45 分鐘

**實作內容**:
- 新增 `ControllerInterface`
- 在 `Router::runRoute()` 中驗證控制器類別
- 提供控制器註冊機制

---

#### S3. 安全的重定向輔助方法 🟡 中優先級

**預估時間**: 30 分鐘

**實作內容**:
- 在 `Response::redirect()` 中加入 URL 驗證
- 新增 `Response::safeRedirect()` 方法
- 提供白名單機制

---

#### S4. 速率限制中介軟體範例 🟢 低優先級

**預估時間**: 1 小時

**實作內容**:
- 提供 `RateLimitMiddleware` 範例
- 支援基於 IP 的限制
- 文件說明

---

#### S5. 安全性測試 🟡 中優先級

**預估時間**: 2 小時

**實作內容**:
- 新增安全性測試案例
- 測試 Host Header Injection
- 測試 Path Traversal
- 測試控制器驗證

---

## 回報安全漏洞

如果您發現安全漏洞，請：

1. **不要** 在公開的 issue 中回報
2. 私下聯絡維護者
3. 提供詳細的複現步驟
4. 等待修復後再公開

---

## 變更歷史

- **2025-12-22**: 初始版本，識別主要安全風險
  - 記錄 Host Header Injection 風險
  - 記錄 Arbitrary Code Execution 風險
  - 記錄已實作的防護措施
  - 提出待改善項目

---

## 參考資源

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [CWE - Common Weakness Enumeration](https://cwe.mitre.org/)
