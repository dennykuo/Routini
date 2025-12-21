# 📖 Routini 使用指南

## 目錄

- [基本路由](#基本路由)
- [Request/Response 物件](#requestresponse-物件)
- [中介軟體系統](#中介軟體系統)
- [路由群組](#路由群組)
- [路由參數](#路由參數)
- [進階功能](#進階功能)

---

## 基本路由

### 註冊路由

```php
use Routini\Route;

// GET 路由
Route::get('/users', function() {
    return 'Users list';
});

// POST 路由
Route::post('/users', function() {
    return 'Create user';
});

// 支援所有 HTTP 方法
Route::any('/ping', function() {
    return 'pong';
});

// 多個方法
Route::match(['GET', 'POST'], '/form', function() {
    return 'Form handler';
});
```

### 使用控制器

```php
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
```

---

## Request/Response 物件

### 使用 Request 物件

Routini 提供型別安全的 Request 物件，讓你更方便地處理請求。

#### 基本用法

```php
use Routini\Http\Request;
use Routini\Http\Response;

Route::get('/profile', function(Request $request) {
    // 取得 URI 和路徑
    $uri = $request->getUri();      // /profile?tab=settings
    $path = $request->getPath();    // /profile

    // 取得 HTTP 方法
    $method = $request->getMethod(); // GET

    // 取得 Host
    $host = $request->getHost();     // example.com

    return "Request to: {$path}";
});
```

#### 讀取請求參數

```php
Route::post('/login', function(Request $request) {
    // Query 參數 (?key=value)
    $redirect = $request->query('redirect', '/dashboard');
    $allQuery = $request->query();

    // POST 資料
    $username = $request->post('username');
    $password = $request->post('password');
    $allPost = $request->post();

    // Input（POST 優先，否則 Query）
    $email = $request->input('email');

    return Response::json([
        'username' => $username,
        'redirect' => $redirect
    ]);
});
```

#### 讀取 Headers

```php
Route::get('/api/data', function(Request $request) {
    // 取得特定 header
    $token = $request->getHeader('Authorization');
    $contentType = $request->getHeader('Content-Type');

    // 檢查 header 是否存在
    if ($request->hasHeader('X-API-Key')) {
        // ...
    }

    // 取得所有 headers
    $headers = $request->getHeaders();

    return Response::json(['token' => $token]);
});
```

#### 檢查請求類型

```php
Route::post('/api/endpoint', function(Request $request) {
    // 檢查是否為 AJAX 請求
    if ($request->isAjax()) {
        return Response::json(['message' => 'AJAX request']);
    }

    // 檢查是否為 JSON 請求
    if ($request->isJson()) {
        // 處理 JSON...
    }

    // 檢查 HTTP 方法
    if ($request->isPost()) {
        // ...
    }

    if ($request->isGet()) {
        // ...
    }

    return Response::html('<h1>Regular request</h1>');
});
```

#### 自訂屬性（用於中介軟體）

```php
Route::get('/dashboard', function(Request $request) {
    // 從中介軟體設定的屬性
    $userId = $request->getAttribute('user_id');
    $role = $request->getAttribute('role', 'guest');

    // 取得所有屬性
    $attributes = $request->getAttributes();

    return "User ID: {$userId}";
});
```

---

### 使用 Response 物件

#### 基本 Response

```php
use Routini\Http\Response;

Route::get('/hello', function() {
    // 基本回應
    return new Response('Hello, World!', 200);

    // 設定 headers
    return new Response('Content', 200, [
        'Content-Type' => 'text/plain',
        'X-Custom' => 'value'
    ]);
});
```

#### JSON 回應

```php
Route::get('/api/users', function() {
    $users = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob']
    ];

    // 方法 1：使用 json() 工廠方法
    return Response::json($users);

    // 方法 2：直接返回陣列（自動轉換為 JSON）
    return $users;

    // 方法 3：自訂狀態碼
    return Response::json(['error' => 'Not found'], 404);
});
```

#### HTML 回應

```php
Route::get('/page', function() {
    $html = '<html><body><h1>Welcome</h1></body></html>';

    return Response::html($html);

    // 或直接返回字串（自動轉換）
    return $html;
});
```

#### 重導向回應

```php
Route::get('/old-page', function() {
    // 302 重導向
    return Response::redirect('/new-page');

    // 301 永久重導向
    return Response::redirect('/new-page', 301);
});
```

#### 錯誤回應

```php
Route::get('/restricted', function() {
    // 404 Not Found
    return Response::notFound('Page not found');

    // 500 Internal Server Error
    return Response::error('Something went wrong', 500);

    // 自訂錯誤
    return new Response('Forbidden', 403);
});
```

#### 無內容回應

```php
Route::delete('/resource/{id}', function($id) {
    // 刪除資源...

    // 返回 204 No Content
    return Response::noContent();
});
```

#### 操作 Response

```php
Route::get('/api/data', function() {
    $response = Response::json(['data' => 'value']);

    // 新增 header（可變方法）
    $response->addHeader('X-RateLimit', '100');

    // 建立帶有新 header 的副本（不可變方法）
    $newResponse = $response->withHeader('X-Custom', 'value');

    // 設定狀態碼
    $response->setStatus(201);

    // 設定內容
    $response->setContent('New content');

    // 批次設定 headers
    $response->setHeaders([
        'X-Header-1' => 'value1',
        'X-Header-2' => 'value2'
    ]);

    return $response;
});
```

#### 檢查 Response 狀態

```php
$response = Response::json(['data' => 'value']);

// 檢查狀態類型
$response->isSuccessful();  // 2xx
$response->isRedirect();     // 3xx
$response->isClientError();  // 4xx
$response->isServerError();  // 5xx
$response->isError();        // 4xx or 5xx

// 檢查特定狀態
$response->isOk();          // 200
$response->isNotFound();    // 404
$response->isForbidden();   // 403

// 取得狀態文字
$statusText = $response->getStatusText(); // "OK", "Not Found", etc.
```

---

## 中介軟體系統

Routini 實作了標準的洋蔥模型（Onion Model）中介軟體系統，支援 before/after 邏輯。

### 基本中介軟體

#### 使用 Callable（推薦）

```php
use Routini\Http\Request;
use Routini\Http\Response;

// 全域中介軟體
$router->middleware(function(Request $request, callable $next) {
    // Before 邏輯
    echo "Before middleware\n";

    // 執行下一個中介軟體或路由
    $response = $next($request);

    // After 邏輯
    echo "After middleware\n";

    return $response;
});
```

#### 使用類別

```php
use Routini\Contracts\MiddlewareInterface;
use Routini\Http\Request;
use Routini\Http\Response;

class LogMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // 記錄請求
        error_log("Request: " . $request->getPath());

        $startTime = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $startTime;

        // 記錄回應
        error_log("Response: {$response->getStatus()} ({$duration}s)");

        return $response;
    }
}

// 註冊中介軟體
$router->middleware(LogMiddleware::class);
// 或
$router->middleware(new LogMiddleware());
```

---

### 全域中介軟體

全域中介軟體會套用到所有路由。

```php
use Routini\Router;

$router = new Router();

// 中介軟體 1：CORS
$router->middleware(function($request, $next) {
    $response = $next($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE');
});

// 中介軟體 2：認證
$router->middleware(function($request, $next) {
    $token = $request->getHeader('Authorization');

    if (!$token) {
        return new Response('Unauthorized', 401);
    }

    // 驗證通過，設定使用者資訊
    $request->setAttribute('user_id', 123);

    return $next($request);
});

// 中介軟體 3：日誌
$router->middleware(function($request, $next) {
    error_log("Request: " . $request->getPath());
    return $next($request);
});
```

**執行順序**（洋蔥模型）：
```
→ CORS (before)
  → 認證 (before)
    → 日誌 (before)
      → 路由動作
    ← 日誌 (after)
  ← 認證 (after)
← CORS (after)
```

---

### 路由專屬中介軟體

```php
// 只套用到特定路由
Route::get('/admin', function() {
    return 'Admin Dashboard';
})->middleware(function($request, $next) {
    // 檢查權限
    if ($request->getAttribute('role') !== 'admin') {
        return new Response('Forbidden', 403);
    }
    return $next($request);
});

// 多個中介軟體
Route::get('/api/data', function() {
    return ['data' => 'sensitive'];
})
->middleware(RateLimitMiddleware::class)
->middleware(function($request, $next) {
    // 驗證 API key
    return $next($request);
});
```

---

### 中介軟體範例

#### 1. 認證中介軟體

```php
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->getHeader('Authorization');

        if (!$token || !str_starts_with($token, 'Bearer ')) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        // 驗證 token（簡化範例）
        $userId = $this->validateToken(substr($token, 7));

        if (!$userId) {
            return Response::json(['error' => 'Invalid token'], 401);
        }

        // 設定使用者資訊到 Request
        $request->setAttribute('user_id', $userId);
        $request->setAttribute('authenticated', true);

        return $next($request);
    }

    private function validateToken(string $token): ?int
    {
        // 驗證邏輯...
        return 123; // 返回使用者 ID
    }
}
```

#### 2. CORS 中介軟體

```php
class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // 處理 preflight 請求
        if ($request->getMethod() === 'OPTIONS') {
            return new Response('', 204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization'
            ]);
        }

        $response = $next($request);

        // 新增 CORS headers
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}
```

#### 3. 速率限制中介軟體

```php
class RateLimitMiddleware implements MiddlewareInterface
{
    private array $requests = [];
    private int $maxRequests = 60;
    private int $timeWindow = 60; // 秒

    public function handle(Request $request, callable $next): Response
    {
        $ip = $request->getServer('REMOTE_ADDR');
        $now = time();

        // 清理過期記錄
        $this->requests[$ip] = array_filter(
            $this->requests[$ip] ?? [],
            fn($time) => $time > $now - $this->timeWindow
        );

        // 檢查速率限制
        if (count($this->requests[$ip] ?? []) >= $this->maxRequests) {
            return Response::json([
                'error' => 'Too many requests'
            ], 429);
        }

        // 記錄請求
        $this->requests[$ip][] = $now;

        $response = $next($request);

        // 新增速率限制資訊到 headers
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string)($this->maxRequests - count($this->requests[$ip])));
    }
}
```

#### 4. 錯誤處理中介軟體

```php
class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (\Exception $e) {
            // 記錄錯誤
            error_log($e->getMessage());

            // 根據環境返回不同的錯誤資訊
            if (getenv('APP_ENV') === 'production') {
                return Response::json([
                    'error' => 'Internal Server Error'
                ], 500);
            }

            return Response::json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
```

#### 5. JSON 格式驗證中介軟體

```php
class JsonMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // 只處理 POST/PUT/PATCH 請求
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        // 檢查 Content-Type
        if ($request->getHeader('Content-Type') !== 'application/json') {
            return Response::json([
                'error' => 'Content-Type must be application/json'
            ], 400);
        }

        $response = $next($request);

        // 確保回應也是 JSON
        if (!$response->hasHeader('Content-Type')) {
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $response;
    }
}
```

---

### 中介軟體短路

中介軟體可以選擇不呼叫 `$next()`，直接返回回應來中斷執行鏈。

```php
$router->middleware(function($request, $next) {
    // 維護模式
    if (file_exists(__DIR__ . '/maintenance.flag')) {
        return Response::html(
            '<h1>系統維護中</h1><p>請稍後再試。</p>',
            503
        );
        // 不呼叫 $next()，後續中介軟體和路由都不會執行
    }

    return $next($request);
});
```

---

### 修改 Request/Response

中介軟體可以修改請求和回應：

```php
// 修改 Request
$router->middleware(function($request, $next) {
    // 在 before 階段修改 request
    $request->setAttribute('start_time', microtime(true));
    $request->setAttribute('request_id', uniqid());

    return $next($request);
});

// 修改 Response
$router->middleware(function($request, $next) {
    $response = $next($request);

    // 在 after 階段修改 response
    $duration = microtime(true) - $request->getAttribute('start_time');

    return $response
        ->withHeader('X-Request-ID', $request->getAttribute('request_id'))
        ->withHeader('X-Response-Time', sprintf('%.3fms', $duration * 1000));
});
```

---

## 路由群組

### 基本群組

```php
Route::group(['prefix' => 'admin'], function() {
    Route::get('/users', fn() => 'Admin Users');     // /admin/users
    Route::get('/posts', fn() => 'Admin Posts');     // /admin/posts
});
```

### 群組中介軟體

```php
Route::group(['middleware' => AuthMiddleware::class], function() {
    Route::get('/dashboard', fn() => 'Dashboard');
    Route::get('/profile', fn() => 'Profile');
});
```

### 巢狀群組

```php
Route::group(['prefix' => 'api'], function() {
    Route::group(['prefix' => 'v1'], function() {
        Route::get('/users', fn() => 'API V1 Users');  // /api/v1/users
    });
});
```

---

## 路由參數

### 必填參數

```php
Route::get('/user/{id}', function($id) {
    return "User ID: {$id}";
});

// 也可以從 Request 取得
Route::get('/user/{id}', function(Request $request) {
    $id = $request->getAttribute('id');
    return "User ID: {$id}";
});
```

### 選填參數

```php
Route::get('/posts/{id?}', function($id = null) {
    if ($id) {
        return "Post ID: {$id}";
    }
    return "All posts";
});
```

### 多個參數

```php
Route::get('/category/{category}/post/{id}', function($category, $id) {
    return "Category: {$category}, Post: {$id}";
});
```

---

## 進階功能

### 命名路由與 URL 生成

```php
// 定義命名路由
Route::get('/user/{id}', function($id) {
    return "User {$id}";
})->name('user.show');

// 生成 URL
$url = $router->url('user.show', ['id' => 123]);  // /user/123
```

### 網域路由

```php
Route::group(['domain' => 'api.example.com'], function() {
    Route::get('/users', fn() => 'API Users');
});
```

### 自訂路由匹配器

```php
use Routini\Contracts\RouteMatcherInterface;

class CustomMatcher implements RouteMatcherInterface
{
    public function match(RouteItem $route, string $uri): bool {
        // 自訂匹配邏輯
    }

    public function extractParameters(RouteItem $route, string $uri): array {
        // 提取參數
    }
}

// 使用自訂匹配器
$router = new Router(new CustomMatcher());
```

---

## 完整範例

### RESTful API

```php
use Routini\Route;
use Routini\Http\Request;
use Routini\Http\Response;

// 全域中介軟體
$router->middleware(new CorsMiddleware());
$router->middleware(new ErrorHandlerMiddleware());

// API 路由群組
Route::group(['prefix' => 'api/v1', 'middleware' => AuthMiddleware::class], function() {

    // 使用者資源
    Route::get('/users', function(Request $request) {
        $users = User::all();
        return Response::json($users);
    });

    Route::get('/users/{id}', function(Request $request, $id) {
        $user = User::find($id);

        if (!$user) {
            return Response::json(['error' => 'User not found'], 404);
        }

        return Response::json($user);
    });

    Route::post('/users', function(Request $request) {
        $data = json_decode($request->post('data'), true);
        $user = User::create($data);

        return Response::json($user, 201);
    });

    Route::put('/users/{id}', function(Request $request, $id) {
        $user = User::find($id);

        if (!$user) {
            return Response::json(['error' => 'User not found'], 404);
        }

        $data = json_decode($request->post('data'), true);
        $user->update($data);

        return Response::json($user);
    });

    Route::delete('/users/{id}', function($id) {
        $user = User::find($id);

        if (!$user) {
            return Response::json(['error' => 'User not found'], 404);
        }

        $user->delete();

        return Response::noContent();
    });
});

// 執行路由
$request = Request::capture();
$response = $router->dispatch($request);
$response->send();
```

---

## 向後相容性

所有新功能都完全向後相容：

```php
// 舊版方式仍然可用
Route::get('/old-style', function() {
    return 'Still works!';
});

$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);

// 舊版中介軟體也可用
class OldMiddleware {
    public function handle() {
        // 返回 true 繼續，false 中斷
        return true;
    }
}
```

---

## 更多資源

- [架構建議](./ARCHITECTURE_SUGGESTIONS.md) - 深入的架構分析
- [架構進度](./ARCHITECTURE_PROGRESS.md) - 實作進度追蹤
- [完整分析報告](./CLAUDE_ANALYSIS_REPORT.md) - 套件整體分析
