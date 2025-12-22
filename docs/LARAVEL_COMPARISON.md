# Routini 與 Laravel 路由系統比較

**更新日期**: 2025-12-22
**Routini 版本**: 1.0.0
**Laravel 參考版本**: 11.x

---

## 📖 目錄

1. [概覽](#概覽)
2. [設計理念差異](#設計理念差異)
3. [功能比較](#功能比較)
4. [使用範例對照](#使用範例對照)
5. [優勢與劣勢](#優勢與劣勢)
6. [總結表](#總結表)

---

## 概覽

Routini 是一個**輕量級**、**獨立**的 PHP 路由套件，設計理念受 Laravel 啟發，但專注於提供**核心路由功能**，而不是完整的框架整合。

### 相似之處

- ✅ Facade 式的靜態介面（`Route::get()`, `Route::post()`）
- ✅ 路由群組（Prefix, Middleware, Name Prefix）
- ✅ 命名路由和反向路由
- ✅ 路由參數（必填、選填）
- ✅ 中介軟體系統
- ✅ Fluent API

### 主要差異

- ❌ 不依賴 Service Container（無依賴注入）
- ❌ 不支援路由模型綁定
- ❌ 不支援參數限制 (`where`)
- ❌ 不支援表單方法偽造 (`_method`)
- ❌ 不支援域名路由（目前）
- ✅ 獨立運行，無需框架

---

## 設計理念差異

### Laravel 路由系統

**理念**: 作為 Laravel 框架的一部分，深度整合整個生態系統。

**特色**:
- 依賴 Service Container 進行依賴注入
- 與 Eloquent ORM 整合（路由模型綁定）
- 與 Middleware Stack 深度整合
- 支援完整的 HTTP 基礎設施

**目標用戶**: Laravel 框架使用者

---

### Routini 路由系統

**理念**: 提供**獨立**、**輕量**、**安全**的路由功能，可整合到任何 PHP 專案。

**特色**:
- 無外部依賴（除 Composer autoload）
- 介面驅動設計，高度可擴展
- 內建安全性防護
- 簡單直觀的 API

**目標用戶**: 需要獨立路由套件的 PHP 開發者

---

## 功能比較

### 1. 基本路由與 HTTP 方法

#### Laravel

```php
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::patch('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'destroy']);
Route::options('/users', [UserController::class, 'options']);

// 多個方法
Route::match(['GET', 'POST'], '/users', function() { /* ... */ });

// 任何方法
Route::any('/users', function() { /* ... */ });
```

**支援**: ✅ 完整支援所有 HTTP 方法

---

#### Routini

```php
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);

// 透過 __callStatic 支援其他方法
Route::put('/users/{id}', [UserController::class, 'update']);     // ✅ 可用
Route::patch('/users/{id}', [UserController::class, 'update']);   // ✅ 可用
Route::delete('/users/{id}', [UserController::class, 'destroy']); // ✅ 可用

// 或使用通用 add 方法
$router->add(['GET', 'POST'], '/users', function() { /* ... */ });
$router->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '/users', function() { /* ... */ });
```

**支援**: ✅ 透過 `Route::__callStatic()` 和 `Router::add()` 支援所有 HTTP 方法

**差異**: Routini 沒有明確的 `match()` 和 `any()` 方法，但可透過 `add()` 達成相同效果。

---

### 2. 路由參數

#### Laravel

```php
// 必填參數
Route::get('/users/{id}', function($id) { /* ... */ });

// 選填參數
Route::get('/users/{name?}', function($name = 'Guest') { /* ... */ });

// 參數限制
Route::get('/users/{id}', function($id) { /* ... */ })
     ->where('id', '[0-9]+');

Route::get('/users/{username}', function($username) { /* ... */ })
     ->whereAlpha('username');

Route::get('/posts/{id}', function($id) { /* ... */ })
     ->whereNumber('id');

Route::get('/posts/{slug}', function($slug) { /* ... */ })
     ->whereAlphaNumeric('slug');

// 全域限制
Route::pattern('id', '[0-9]+');
Route::pattern('slug', '[a-z0-9-]+');
```

**支援**: ✅ 完整支援，包含參數限制

---

#### Routini

```php
// 必填參數
Route::get('/users/{id}', function($id) { /* ... */ });

// 選填參數
Route::get('/users/{name?}', function($name = 'Guest') { /* ... */ });

// ❌ 不支援參數限制
// 所有參數都使用正則 [^/]+ 匹配（任何非斜線字元）

// 手動驗證參數
Route::get('/users/{id}', function($id) {
    if (!is_numeric($id)) {
        return Response::error('Invalid ID', 400);
    }
    // ...
});
```

**支援**: ✅ 必填和選填參數，❌ 無內建參數限制

**替代方案**: 在控制器/動作中手動驗證參數

---

### 3. 路由群組

#### Laravel

```php
// Prefix
Route::prefix('admin')->group(function() {
    Route::get('/users', function() { /* /admin/users */ });
});

// Middleware
Route::middleware(['auth', 'verified'])->group(function() {
    Route::get('/dashboard', function() { /* ... */ });
});

// Name Prefix
Route::name('admin.')->group(function() {
    Route::get('/users', function() { /* ... */ })->name('users'); // admin.users
});

// Domain
Route::domain('api.example.com')->group(function() {
    Route::get('/users', function() { /* ... */ });
});

// Namespace (控制器命名空間)
Route::namespace('Admin')->group(function() {
    Route::get('/users', 'UserController@index'); // Admin\UserController
});

// 組合多個屬性
Route::prefix('admin')
     ->middleware('auth')
     ->name('admin.')
     ->group(function() {
         // ...
     });
```

**支援**: ✅ 完整支援（prefix, middleware, name, domain, namespace）

---

#### Routini

```php
// Prefix
Route::prefix('admin')->group(function($router) {
    Route::get('/users', function() { /* /admin/users */ });
});

// Middleware
Route::middleware(AuthMiddleware::class)->group(function($router) {
    Route::get('/dashboard', function() { /* ... */ });
});

// Name Prefix
Route::name('admin.')->group(function($router) {
    Route::get('/users', function() { /* ... */ })->name('users'); // admin.users
});

// ❌ Domain - 目前不支援

// ❌ Namespace - 不適用（使用完整類別名稱）

// 組合多個屬性
Route::prefix('admin')
     ->middleware(AuthMiddleware::class)
     ->name('admin.')
     ->group(function($router) {
         // ...
     });

// 或使用陣列語法
$router->group([
    'prefix' => 'admin',
    'middleware' => AuthMiddleware::class,
    'name' => 'admin.'
], function($router) {
    // ...
});
```

**支援**:
- ✅ Prefix
- ✅ Middleware
- ✅ Name Prefix
- ❌ Domain（計畫中）
- ❌ Namespace（不適用，PHP 8+ 使用 `::class`）

---

### 4. 中介軟體 (Middleware)

#### Laravel

```php
// 全域中介軟體（在 Kernel 中註冊）
protected $middleware = [
    \App\Http\Middleware\TrustProxies::class,
    \Illuminate\Http\Middleware\HandleCors::class,
];

// 路由中介軟體別名（在 Kernel 中定義）
protected $middlewareAliases = [
    'auth' => \App\Http\Middleware\Authenticate::class,
    'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
];

// 使用別名
Route::middleware('auth')->group(function() { /* ... */ });

// 中介軟體參數
Route::middleware('role:admin,editor')->group(function() { /* ... */ });

// 中介軟體群組
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
    ],
    'api' => [
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];

// Terminable Middleware
class LogAfterResponse implements MiddlewareInterface
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    public function terminate($request, $response)
    {
        // 在回應發送後執行
    }
}
```

**支援**: ✅ 別名、參數、群組、Terminable Middleware

---

#### Routini

```php
// 全域中介軟體
$router = Route::getInstance();
$router->middleware(AuthMiddleware::class);
$router->middleware(LogMiddleware::class);

// 路由中介軟體（使用完整類別名稱）
Route::middleware(AuthMiddleware::class)->group(function($router) { /* ... */ });

// 或使用 Closure
Route::middleware(function($request, $next) {
    // Before logic
    $response = $next($request);
    // After logic
    return $response;
})->group(function($router) { /* ... */ });

// ❌ 無別名系統（必須使用完整類別名稱或 Closure）
// ❌ 無參數支援
// ❌ 無中介軟體群組
// ❌ 無 Terminable Middleware

// 中介軟體介面
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!$this->isAuthenticated($request)) {
            return Response::error('Unauthorized', 401);
        }

        return $next($request);
    }
}
```

**支援**:
- ✅ 全域中介軟體
- ✅ 路由中介軟體
- ✅ Closure 中介軟體
- ✅ 洋蔥模式執行
- ❌ 無別名
- ❌ 無參數
- ❌ 無群組
- ❌ 無 Terminable Middleware

**替代方案**: 使用完整類別名稱或 Closure，在中介軟體內部處理參數邏輯

---

### 5. 控制器與動作

#### Laravel

```php
// 陣列語法
Route::get('/users', [UserController::class, 'index']);

// 字串語法
Route::get('/users', 'UserController@index');

// Invokable Controllers
class ShowProfile
{
    public function __invoke($id)
    {
        // ...
    }
}

Route::get('/profile/{id}', ShowProfile::class);

// 控制器依賴注入
class UserController extends Controller
{
    public function __construct(
        private UserRepository $users,
        private Logger $logger
    ) {}

    public function show(Request $request, $id)
    {
        // $request 自動注入
        // $id 從路由參數提取
    }
}
```

**支援**: ✅ 陣列、字串、Invokable，完整依賴注入

---

#### Routini

```php
// ✅ 陣列語法
Route::get('/users', [UserController::class, 'index']);

// ✅ Closure
Route::get('/users', function() { /* ... */ });

// ❌ 字串語法（不支援 'UserController@index'）

// ✅ Invokable Controllers（透過 __invoke 方法）
class ShowProfile
{
    public function __invoke($id)
    {
        // ...
    }
}

Route::get('/profile/{id}', ShowProfile::class); // ✅ 可用

// ❌ 無依賴注入（僅傳遞路由參數）
class UserController
{
    public function show($id)
    {
        // 只接收路由參數
        // 無法自動注入 Request 或其他依賴
    }
}

// 替代方案：手動創建 Request
Route::get('/users/{id}', function($id) {
    $request = new Request(/* ... */);
    return (new UserController())->show($request, $id);
});
```

**支援**:
- ✅ 陣列語法 `[Class, 'method']`
- ✅ Closure
- ✅ Invokable (`__invoke`)
- ❌ 字串語法 `'Controller@method'`
- ❌ 依賴注入（無 Service Container）

**注意**: Routini 的 `dispatch()` 方法接收 `Request` 物件，但不會自動注入到控制器方法。

---

### 6. 路由模型綁定 (Route Model Binding)

#### Laravel

```php
// 隱式綁定
Route::get('/users/{user}', function(User $user) {
    // $user 自動從資料庫載入（根據 ID）
    return $user->name;
});

// 自訂鍵名
Route::get('/posts/{post:slug}', function(Post $post) {
    // 使用 slug 欄位載入
    return $post->title;
});

// 顯式綁定（在 RouteServiceProvider）
Route::model('user', User::class);

Route::get('/users/{user}', function($user) {
    // $user 是 User 模型實例
});
```

**支援**: ✅ 隱式和顯式綁定

---

#### Routini

```php
// ❌ 不支援路由模型綁定

Route::get('/users/{id}', function($id) {
    // $id 是字串，不是模型實例
    // 需要手動查詢
    $user = User::find($id);
    return $user->name;
});

// 手動實作類似功能
Route::get('/users/{id}', function($id) {
    $user = User::find($id);

    if (!$user) {
        return Response::notFound('User not found');
    }

    return Response::json($user);
});
```

**支援**: ❌ 無路由模型綁定

**原因**: Routini 不依賴任何 ORM，保持獨立性

---

### 7. 反向路由 (URL Generation)

#### Laravel

```php
// 命名路由
Route::get('/users/{id}', function($id) { /* ... */ })->name('user.show');

// 生成 URL
$url = route('user.show', ['id' => 1]);
// 結果: http://example.com/users/1

// 生成相對 URL
$url = route('user.show', ['id' => 1], false);
// 結果: /users/1

// 額外參數作為查詢字串
$url = route('user.index', ['page' => 2, 'sort' => 'name']);
// 結果: http://example.com/users?page=2&sort=name

// 簽名 URL
$url = URL::signedRoute('user.verify', ['id' => 1]);
$url = URL::temporarySignedRoute('user.verify', now()->addMinutes(30), ['id' => 1]);
```

**支援**: ✅ 完整支援，包含絕對 URL、簽名 URL

---

#### Routini

```php
// 命名路由
Route::get('/users/{id}', function($id) { /* ... */ })->name('user.show');

// 生成 URL（相對路徑）
$url = route('user.show', ['id' => 1]);
// 結果: /users/1

// 額外參數作為查詢字串
$url = route('user.index', ['page' => 2, 'sort' => 'name']);
// 結果: /users?page=2&sort=name

// ❌ 無絕對 URL 生成（僅相對路徑）
// ❌ 無簽名 URL

// 手動生成絕對 URL
$baseUrl = 'https://example.com';
$url = $baseUrl . route('user.show', ['id' => 1]);
```

**支援**:
- ✅ 命名路由
- ✅ 參數替換
- ✅ 查詢字串附加
- ❌ 絕對 URL
- ❌ 簽名 URL

**替代方案**: 手動拼接基礎 URL，或擴展 `UrlGenerator`

---

### 8. 其他進階功能

#### Laravel

```php
// 表單方法偽造
// HTML 表單只支援 GET 和 POST
<form method="POST" action="/users/1">
    @csrf
    @method('DELETE')
    <button type="submit">Delete</button>
</form>

// Laravel 會檢查 _method 欄位並視為 DELETE 請求

// 速率限制
Route::middleware('throttle:60,1')->group(function() {
    // 每分鐘 60 次請求
});

// CSRF 保護
Route::post('/users', function() {
    // 自動驗證 CSRF token
});

// 路由快取
php artisan route:cache

// 當前路由資訊
$route = Route::current();
$name = Route::currentRouteName();
$action = Route::currentRouteAction();
```

**支援**: ✅ 完整支援

---

#### Routini

```php
// ❌ 無表單方法偽造
// HTML 表單只能使用真正的 GET 和 POST

// ❌ 無內建速率限制
// 可透過自訂中介軟體實作

// ❌ 無內建 CSRF 保護
// 可透過自訂中介軟體實作（參考 ARCHITECTURE.md）

// ❌ 無路由快取

// ❌ 無當前路由存取 API
```

**支援**: ❌ 無這些進階功能

**替代方案**: 透過自訂中介軟體實作這些功能

---

## 使用範例對照

### 範例 1: 基本 CRUD 路由

#### Laravel

```php
Route::resource('posts', PostController::class);

// 自動生成:
// GET    /posts              index
// GET    /posts/create       create
// POST   /posts              store
// GET    /posts/{post}       show
// GET    /posts/{post}/edit  edit
// PUT    /posts/{post}       update
// DELETE /posts/{post}       destroy
```

---

#### Routini

```php
// 需要手動定義
Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::get('/posts/create', [PostController::class, 'create'])->name('posts.create');
Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
Route::get('/posts/{id}', [PostController::class, 'show'])->name('posts.show');
Route::get('/posts/{id}/edit', [PostController::class, 'edit'])->name('posts.edit');
Route::put('/posts/{id}', [PostController::class, 'update'])->name('posts.update');
Route::delete('/posts/{id}', [PostController::class, 'destroy'])->name('posts.destroy');
```

---

### 範例 2: API 路由群組

#### Laravel

```php
Route::prefix('api/v1')
     ->middleware('auth:sanctum')
     ->namespace('Api\V1')
     ->name('api.v1.')
     ->group(function() {
         Route::apiResource('users', UserController::class);
         Route::apiResource('posts', PostController::class);
     });
```

---

#### Routini

```php
Route::prefix('api/v1')
     ->middleware(AuthMiddleware::class)
     ->name('api.v1.')
     ->group(function($router) {
         // 手動定義 API 路由
         Route::get('/users', [UserController::class, 'index'])->name('users.index');
         Route::post('/users', [UserController::class, 'store'])->name('users.store');
         Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
         Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
         Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
     });
```

---

### 範例 3: 中介軟體管道

#### Laravel

```php
Route::middleware(['auth', 'verified', 'role:admin'])
     ->prefix('admin')
     ->group(function() {
         Route::get('/dashboard', function() {
             return view('admin.dashboard');
         });
     });
```

---

#### Routini

```php
Route::middleware([
    AuthMiddleware::class,
    VerifiedMiddleware::class,
    RoleMiddleware::class // 需要在類別內處理 'admin' 邏輯
])
->prefix('admin')
->group(function($router) {
    Route::get('/dashboard', function() {
        return Response::html('<h1>Admin Dashboard</h1>');
    });
});
```

---

## 優勢與劣勢

### Routini 的優勢 ✅

1. **獨立性**:
   - 無需完整框架
   - 可整合到任何 PHP 專案
   - 零外部依賴（除 Composer）

2. **輕量級**:
   - 核心程式碼簡潔
   - 快速啟動
   - 低記憶體佔用

3. **安全性內建**:
   - Host Header 驗證 (S1)
   - 控制器白名單驗證 (S2)
   - 安全重定向 (S3)
   - 35 個安全性測試

4. **介面驅動**:
   - 高度可擴展
   - 可替換核心組件
   - 符合 SOLID 原則

5. **簡單直觀**:
   - 學習曲線低
   - API 清晰易懂
   - 文件完整

6. **現代 PHP**:
   - 支援 PHP 8.0+ 特性
   - 型別提示
   - 命名參數

---

### Routini 的劣勢 ❌

1. **功能較少**:
   - 無路由模型綁定
   - 無參數限制 (`where`)
   - 無表單方法偽造
   - 無依賴注入

2. **生態系統小**:
   - 無龐大的套件生態
   - 社群支援較少
   - 範例和教學較少

3. **進階功能缺乏**:
   - 無路由快取
   - 無速率限制（內建）
   - 無 CSRF 保護（內建）
   - 無當前路由 API

4. **需要更多手動配置**:
   - 無 `resource()` 便捷方法
   - 中介軟體無別名系統
   - 需要手動處理參數驗證

---

### Laravel 路由的優勢 ✅

1. **功能完整**:
   - 所有企業級功能
   - 深度整合 Laravel 生態系統
   - 持續更新和維護

2. **強大的生態系統**:
   - 龐大的社群
   - 豐富的套件
   - 大量教學資源

3. **依賴注入**:
   - 自動解析依賴
   - 與 Service Container 整合
   - 路由模型綁定

4. **便捷方法**:
   - `resource()` 一鍵生成 CRUD 路由
   - `apiResource()` 生成 API 路由
   - 中介軟體別名和群組

---

### Laravel 路由的劣勢 ❌

1. **框架依賴**:
   - 必須使用 Laravel 框架
   - 無法獨立使用
   - 較重的依賴

2. **複雜度高**:
   - 學習曲線較陡
   - 過多的「魔法」行為
   - 除錯較困難

3. **效能開銷**:
   - Service Container 解析
   - 更多的記憶體佔用
   - 較慢的啟動時間

---

## 總結表

| 功能 | Laravel | Routini | 備註 |
|:---|:---:|:---:|:---|
| **基本路由** | ✅ | ✅ | 兩者皆支援 |
| **HTTP 方法** | ✅ | ✅ | Routini 透過 `add()` 支援 |
| **必填參數** | ✅ | ✅ | |
| **選填參數** | ✅ | ✅ | |
| **參數限制 (`where`)** | ✅ | ❌ | Routini 需手動驗證 |
| **路由群組 (Prefix)** | ✅ | ✅ | |
| **路由群組 (Middleware)** | ✅ | ✅ | |
| **路由群組 (Name)** | ✅ | ✅ | |
| **路由群組 (Domain)** | ✅ | ❌ | Routini 未實作 |
| **路由群組 (Namespace)** | ✅ | ❌ | Routini 使用 `::class` |
| **中介軟體** | ✅ | ✅ | |
| **中介軟體別名** | ✅ | ❌ | Routini 使用完整類別名稱 |
| **中介軟體參數** | ✅ | ❌ | Routini 需在類別內處理 |
| **中介軟體群組** | ✅ | ❌ | |
| **控制器陣列語法** | ✅ | ✅ | `[Class, 'method']` |
| **控制器字串語法** | ✅ | ❌ | `'Controller@method'` 不支援 |
| **Invokable Controllers** | ✅ | ✅ | |
| **依賴注入** | ✅ | ❌ | Routini 無 Service Container |
| **路由模型綁定** | ✅ | ❌ | Routini 需手動查詢 |
| **反向路由** | ✅ | ✅ | |
| **絕對 URL 生成** | ✅ | ❌ | Routini 僅相對路徑 |
| **簽名 URL** | ✅ | ❌ | |
| **表單方法偽造** | ✅ | ❌ | `_method` 欄位 |
| **速率限制** | ✅ | ❌ | Routini 可透過中介軟體實作 |
| **CSRF 保護** | ✅ | ❌ | Routini 可透過中介軟體實作 |
| **路由快取** | ✅ | ❌ | |
| **當前路由資訊** | ✅ | ❌ | `Route::current()` |
| **Resource 路由** | ✅ | ❌ | Routini 需手動定義 |
| **API Resource 路由** | ✅ | ❌ | Routini 需手動定義 |
| **Request/Response 物件** | ✅ | ✅ | |
| **洋蔥模式中介軟體** | ✅ | ✅ | |
| **策略模式匹配器** | ❌ | ✅ | Routini 特色 |
| **介面驅動設計** | 部分 | ✅ | Routini 特色 |
| **內建安全性防護** | 部分 | ✅ | Routini S1, S2, S3 |
| **獨立運行** | ❌ | ✅ | Routini 優勢 |
| **框架整合** | ✅ | ❌ | Laravel 優勢 |

---

## 使用建議

### 選擇 Laravel 路由，如果你：

- ✅ 已經在使用 Laravel 框架
- ✅ 需要完整的框架整合
- ✅ 需要路由模型綁定和依賴注入
- ✅ 希望使用龐大的 Laravel 生態系統
- ✅ 想要開箱即用的進階功能

---

### 選擇 Routini，如果你：

- ✅ 需要獨立的路由套件
- ✅ 正在開發輕量級應用或 API
- ✅ 想要完全控制路由行為
- ✅ 重視安全性和可擴展性
- ✅ 不需要依賴注入和 ORM 整合
- ✅ 想要簡單直觀的 API

---

## 遷移考量

### 從 Laravel 遷移到 Routini

**簡單遷移**:
- ✅ 基本路由定義（幾乎相同）
- ✅ 路由群組（Prefix, Middleware, Name）
- ✅ 命名路由和反向路由

**需要改寫**:
- ⚠️ 控制器依賴注入 → 手動建立依賴
- ⚠️ 路由模型綁定 → 手動查詢模型
- ⚠️ 參數限制 (`where`) → 手動驗證
- ⚠️ 中介軟體別名 → 使用完整類別名稱
- ⚠️ `resource()` → 手動定義所有路由

---

### 從 Routini 遷移到 Laravel

**簡單遷移**:
- ✅ 幾乎所有 Routini 程式碼可直接在 Laravel 中運行
- ✅ 路由定義語法幾乎相同

**可選改善**:
- ⚠️ 使用中介軟體別名
- ⚠️ 使用路由模型綁定
- ⚠️ 使用依賴注入
- ⚠️ 使用 `resource()` 簡化路由定義

---

## 總結

**Routini** 和 **Laravel 路由** 各有優勢：

- **Laravel 路由** 適合需要完整框架整合、豐富生態系統和進階功能的專案
- **Routini** 適合需要獨立、輕量、安全且可擴展的路由解決方案

兩者都是優秀的選擇，取決於你的專案需求和偏好。

---

## 參考資源

- **Routini 文件**:
  - [ARCHITECTURE.md](./ARCHITECTURE.md) - 架構設計
  - [SECURITY.md](./SECURITY.md) - 安全性文件
  - [README.md](../README.md) - 使用指南

- **Laravel 文件**:
  - [Laravel Routing](https://laravel.com/docs/routing)
  - [Laravel Middleware](https://laravel.com/docs/middleware)
  - [Laravel Controllers](https://laravel.com/docs/controllers)
