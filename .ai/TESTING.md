# Testing Guide (測試指南)

**最後更新**: 2025-12-22
**測試框架**: Pest 4.2.0
**當前測試數**: 203 tests (398 assertions)

## 框架 (Framework)
使用 **Pest** (基於 PHPUnit) 進行測試。

- **Config**: `phpunit.xml`
- **Test Directory**: `tests/`
    - `Feature/`: 功能測試 (RouteTest, DomainRoutingTest, SecurityTest)
    - `Unit/`: 單元測試 (Request, Response, MiddlewarePipeline, RegexMatcher 等)

## 測試統計 (Test Statistics)

| 類別 | 測試數 | 檔案 |
|:---|:---:|:---|
| **Feature Tests** | 23 | RouteTest, DomainRoutingTest |
| **Security Tests** | 35 | SecurityTest |
| **Unit Tests** | 145 | Http, Matching, Middleware, Router 等 |
| **總計** | **203** | 11 個測試檔案 |

## 執行測試 (Running Tests)

執行所有測試：
```bash
vendor/bin/pest
```

執行特定測試檔案：
```bash
vendor/bin/pest tests/Feature/RouteTest.php
vendor/bin/pest tests/Unit/Http/RequestTest.php
vendor/bin/pest tests/Feature/SecurityTest.php
```

執行特定測試群組：
```bash
vendor/bin/pest --filter="Security Features"
```

查看詳細輸出：
```bash
vendor/bin/pest --verbose
```

---

## 撰寫測試 (Writing Tests)

### 1. 使用 Request/Response 物件

**現代方式** (推薦):
```php
use Routini\Router;
use Routini\Http\Request;
use Routini\Http\Response;

test('handles GET request', function () {
    $router = new Router();
    $router->add('GET', '/users', function() {
        return 'Users list';
    });

    $request = new Request(uri: '/users', method: 'GET');
    $response = $router->dispatch($request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getContent())->toBe('Users list');
});
```

### 2. Singleton 重置 (Singleton Resetting)

因為 `Route` 使用 Singleton `Router` 實例，狀態會在測試之間保留。請在每個測試前重置：

```php
beforeEach(function () {
    $reflection = new ReflectionClass(\Routini\Route::class);
    $property = $reflection->getProperty('instance');
    $property->setAccessible(true);
    $property->setValue(null, null);
});
```

### 3. 測試路由參數

```php
test('extracts route parameters', function () {
    $router = new Router();

    $capturedId = null;
    $router->add('GET', '/users/{id}', function ($id) use (&$capturedId) {
        $capturedId = $id;
        return "User: {$id}";
    });

    $request = new Request(uri: '/users/123', method: 'GET');
    $response = $router->dispatch($request);

    expect($capturedId)->toBe('123');
    expect($response->getContent())->toBe('User: 123');
});
```

### 4. 測試中介軟體

```php
use Routini\Contracts\MiddlewareInterface;

test('middleware can modify request', function () {
    $middleware = new class implements MiddlewareInterface {
        public function handle(Request $request, callable $next): Response {
            $request->attribute('user_id', 42);
            return $next($request);
        }
    };

    $router = new Router();
    $router->middleware($middleware);

    $router->add('GET', '/test', function (Request $request) {
        return 'User: ' . $request->attribute('user_id');
    });

    $request = new Request(uri: '/test', method: 'GET');
    $response = $router->dispatch($request);

    expect($response->getContent())->toBe('User: 42');
});
```

### 5. 測試控制器

```php
use Routini\Contracts\ControllerInterface;

class UserController implements ControllerInterface {
    public function show($id) {
        return "User: {$id}";
    }
}

test('dispatches to controller', function () {
    $router = new Router();
    $router->add('GET', '/users/{id}', [UserController::class, 'show']);

    $request = new Request(uri: '/users/123', method: 'GET');
    $response = $router->dispatch($request);

    expect($response->getContent())->toBe('User: 123');
});
```

### 6. 測試 404 回應

```php
test('returns 404 for non-existent route', function () {
    $router = new Router();
    $router->add('GET', '/users', function() { return 'Users'; });

    $request = new Request(uri: '/not-found', method: 'GET');
    $response = $router->dispatch($request);

    expect($response->getStatus())->toBe(404);
});
```

### 7. 測試 JSON 回應

```php
test('returns JSON response', function () {
    $router = new Router();
    $router->add('GET', '/api/users', function() {
        return ['users' => ['John', 'Jane']];
    });

    $request = new Request(uri: '/api/users', method: 'GET');
    $response = $router->dispatch($request);

    expect($response->getHeaders()['Content-Type'])->toBe('application/json');
    expect(json_decode($response->getContent(), true))
        ->toBe(['users' => ['John', 'Jane']]);
});
```

---

## 安全性測試 (Security Testing)

### 1. Host Header 驗證測試

```php
test('validates host header', function () {
    $request = new Request(
        uri: '/test',
        method: 'GET',
        server: ['HTTP_HOST' => 'example.com']
    );

    $allowedHosts = ['example.com', 'www.example.com'];

    expect($request->validateHost($allowedHosts))->toBeTrue();
});
```

### 2. 控制器驗證測試

```php
test('validates controller interface', function () {
    $router = new Router(validateControllers: true);

    // 未實作 ControllerInterface 的類別
    $router->add('GET', '/test', [stdClass::class, 'method']);

    $request = new Request(uri: '/test', method: 'GET');

    expect(fn() => $router->dispatch($request))
        ->toThrow(RuntimeException::class);
});
```

### 3. 安全重定向測試

```php
test('safe redirect prevents open redirect', function () {
    expect(fn() => Response::safeRedirect('https://evil.com'))
        ->toThrow(InvalidArgumentException::class);
});
```

---

## 測試最佳實踐 (Best Practices)

### 1. 使用描述性的測試名稱
```php
// ✅ Good
test('returns 404 when route not found', function () { /* ... */ });

// ❌ Bad
test('test1', function () { /* ... */ });
```

### 2. 每個測試只測試一件事
```php
// ✅ Good
test('extracts single parameter', function () { /* ... */ });
test('extracts multiple parameters', function () { /* ... */ });

// ❌ Bad
test('route parameters', function () {
    // 測試太多東西
});
```

### 3. 使用 describe 組織測試
```php
describe('Router', function () {
    describe('dispatch()', function () {
        test('handles GET request', function () { /* ... */ });
        test('handles POST request', function () { /* ... */ });
    });

    describe('url()', function () {
        test('generates URL for named route', function () { /* ... */ });
    });
});
```

### 4. 使用 beforeEach 和 afterEach
```php
describe('Feature Tests', function () {
    beforeEach(function () {
        // 重置狀態
        $this->router = new Router();
    });

    afterEach(function () {
        // 清理
    });

    test('...', function () {
        $this->router->add(...);
    });
});
```

### 5. 使用資料提供者 (Data Providers)
```php
test('matches various HTTP methods', function ($method) {
    $router = new Router();
    $router->add($method, '/test', fn() => 'OK');

    $request = new Request(uri: '/test', method: $method);
    $response = $router->dispatch($request);

    expect($response->getContent())->toBe('OK');
})->with(['GET', 'POST', 'PUT', 'DELETE', 'PATCH']);
```

---

## 測試涵蓋範圍 (Test Coverage)

### 核心功能測試
- ✅ 基本路由匹配
- ✅ HTTP 方法驗證
- ✅ 路由參數提取
- ✅ 中介軟體執行
- ✅ 控制器分發
- ✅ URL 生成
- ✅ 路由群組

### 安全性測試
- ✅ Host Header 驗證 (S1)
- ✅ 控制器白名單驗證 (S2)
- ✅ 安全重定向 (S3)
- ✅ Path Traversal 防護
- ✅ HTTP Method 驗證

### 邊緣案例測試
- ✅ 404 錯誤處理
- ✅ 空路由集合
- ✅ 無效的 URL 格式
- ✅ 特殊字元處理

---

## 持續整合 (Continuous Integration)

測試應在以下情況執行：
- 每次 commit 前
- Pull Request 建立時
- 合併到主分支前

```bash
# 在 CI 環境中執行
composer install
vendor/bin/pest --no-coverage
```

---

## 參考資源 (References)

- **Pest 文件**: https://pestphp.com/
- **專案測試**: `/tests/`
- **安全性測試**: `/tests/Feature/SecurityTest.php`
- **架構文件**: `/docs/ARCHITECTURE.md`

---

**最後更新**: 2025-12-22
**測試覆蓋率**: 100% 核心功能
