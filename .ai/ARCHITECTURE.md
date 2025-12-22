# Architecture Overview (架構概觀)

**最後更新**: 2025-12-22
**版本**: 1.0.0 (Production-Ready)

## 系統背景 (System Context)
這是一個輕量級的 PHP Routing 套件，旨在模仿 Laravel 路由系統 (`Illuminate\Routing`) 的 API 和行為。它不是一個完整的 Framework，而是一個獨立的組件，專注於提供企業級的路由功能。

**核心特色**:
- PSR-7 風格的 Request/Response 物件
- 洋蔥模式中介軟體管道 (Onion Pattern)
- 策略模式路由匹配 (Strategy Pattern)
- 介面驅動設計 (Interface-Driven Design)
- 內建安全性防護 (S1, S2, S3)
- 100% 向後相容

---

## 核心組件 (Core Components)

### 層次架構 (Layered Architecture)

```
Facade 層 (Route::get(), Route::post())
    ↓
Router 層 (路由註冊、分發、URL 生成)
    ↓
中介軟體管道層 (MiddlewarePipeline - 洋蔥模式)
    ↓
路由匹配層 (RegexMatcher - 策略模式)
    ↓
HTTP 抽象層 (Request/Response 物件)
    ↓
控制器/動作層 (Controller/Closure)
```

---

### 1. HTTP 抽象層

#### `Request` 類別
- **Path**: `src/Http/Request.php`
- **Role**: 封裝 HTTP 請求資訊
- **Key Methods**:
  - `getMethod()`: 取得 HTTP 方法
  - `getUri()`: 取得完整 URI
  - `getPath()`: 取得路徑（不含查詢字串）
  - `query($key)`: 取得查詢參數
  - `post($key)`: 取得 POST 參數
  - `header($key)`: 取得 Header
  - `validateHost($allowedHosts)`: 驗證 Host Header (S1 安全性)
  - `attribute($key, $value)`: 設定/取得自訂屬性（用於路由參數）

#### `Response` 類別
- **Path**: `src/Http/Response.php`
- **Role**: 封裝 HTTP 回應資訊
- **Key Methods**:
  - `getContent()`, `setContent($content)`: 內容管理
  - `getStatus()`, `setStatus($status)`: 狀態碼管理
  - `getHeaders()`, `addHeader($name, $value)`: Header 管理
  - `send()`: 發送回應到客戶端
  - **靜態工廠方法**:
    - `json($data)`: JSON 回應
    - `html($html)`: HTML 回應
    - `redirect($url)`: 重定向
    - `safeRedirect($url, $allowedDomains)`: 安全重定向 (S3 安全性)
    - `notFound()`: 404 回應
    - `error($message, $status)`: 錯誤回應

---

### 2. 路由層

#### `Route` (The Facade)
- **Path**: `src/Route.php`
- **Role**: 為使用者提供靜態介面
- **Behavior**:
  - 作為 Proxy (代理)
  - `Route::get(...)` → 代理至 `Router->add(...)`
  - `Route::prefix(...)` → 回傳 `RouteRegistrar` 以進行 Chaining
  - `Route::dispatch()` → 分發請求

#### `Router` (The Brain)
- **Path**: `src/Router.php`
- **Role**: 管理路由集合與 Dispatching 邏輯的 Singleton 類別
- **Key Responsibilities**:
  - **Route Registration**: 註冊路由到 `RouteCollection`
  - **Group Management**: 使用 `groupStack` 處理巢狀群組 (Prefix, Middleware, Name, Domain)
  - **Dispatching**:
    - 接收 `Request` 物件
    - 使用 `RegexMatcher` 匹配路由
    - 透過 `MiddlewarePipeline` 執行中介軟體
    - 執行路由動作
    - 返回 `Response` 物件
  - **URL Generation**: 委派給 `UrlGenerator`
  - **Controller Validation**: 可選的控制器驗證 (S2 安全性)

**重要屬性**:
```php
protected RouteMatcherInterface $matcher;        // 路由匹配器（策略模式）
protected RouteCollection $routes;               // 路由集合
protected UrlGenerator $urlGenerator;            // URL 生成器
protected array $globalMiddlewares = [];         // 全域中介軟體
protected bool $validateControllers = false;     // 控制器驗證開關 (S2)
```

#### `RouteRegistrar` (The Helper)
- **Path**: `src/RouteRegistrar.php`
- **Role**: 處理在定義路由之前，鏈式設定 Group Attributes 的暫時狀態
- **Usage**: `Route::prefix('admin')->middleware(...)->group(...)`

#### `RouteItem` (The Model)
- **Path**: `src/RouteItem.php`
- **Role**: 代表單一已定義路由的 DTO (Data Transfer Object)
- **實作**: `RouteInterface`
- **Properties** (私有，透過 getter/setter 存取):
  - `methods`: HTTP 方法陣列
  - `uri`: 路由 URI 模式
  - `action`: 控制器或 Closure
  - `name`: 路由名稱
  - `middlewares`: 中介軟體陣列
  - `domain`: 域名限制
  - `parameters`: 路由參數
- **特色**:
  - 私有屬性封裝 (A3 改善)
  - 魔術方法向後相容 (`__get`, `__set`, `__isset`)
  - Fluent API (`name()`, `middleware()`)

#### `RouteCollection`
- **Path**: `src/RouteCollection.php`
- **Role**: 儲存和管理路由集合
- **實作**: `RouteCollectionInterface`
- **Key Methods**:
  - `add(RouteInterface $route)`: 新增路由
  - `all()`: 取得所有路由
  - `findByName($name)`: 尋找命名路由
  - `count()`, `isEmpty()`: 統計方法
  - `clear()`: 清空集合

#### `UrlGenerator`
- **Path**: `src/UrlGenerator.php`
- **Role**: 生成命名路由的 URL
- **實作**: `UrlGeneratorInterface`
- **Key Methods**:
  - `generate($name, $parameters)`: 生成 URL
  - `hasRoute($name)`: 檢查路由是否存在

---

### 3. 中介軟體層

#### `MiddlewarePipeline`
- **Path**: `src/Middleware/MiddlewarePipeline.php`
- **Role**: 執行洋蔥模式的中介軟體管道
- **Pattern**: Pipeline Pattern (Chain of Responsibility)
- **Execution**: `Request → MW1 → MW2 → Action → MW2 → MW1 → Response`
- **Key Methods**:
  - `pipe($middleware)`: 新增中介軟體
  - `process(Request $request, callable $final)`: 執行管道

**中介軟體介面**:
```php
interface MiddlewareInterface {
    public function handle(Request $request, callable $next): Response;
}
```

---

### 4. 路由匹配層

#### `RegexMatcher` (預設匹配器)
- **Path**: `src/Matching/RegexMatcher.php`
- **Role**: 使用正則表達式匹配路由
- **實作**: `RouteMatcherInterface`
- **Pattern**: Strategy Pattern
- **Key Methods**:
  - `matches(RouteInterface $route, string $path)`: 檢查是否匹配
  - `extractParameters(RouteInterface $route, string $path)`: 提取參數

**支援的參數格式**:
- 必填參數: `{id}` → 匹配 `[^/]+`
- 選填參數: `{name?}` → 匹配 `[^/]*`

**可替換**: 透過 `Router` 建構函式注入自訂匹配器

---

### 5. 介面契約層

所有核心組件都有對應的介面，支援依賴反轉原則 (DIP):

- **RouteInterface**: 定義路由項目的標準契約
- **RouteCollectionInterface**: 定義路由集合的標準契約
- **UrlGeneratorInterface**: 定義 URL 生成器的標準契約
- **RouteMatcherInterface**: 定義路由匹配器的標準契約
- **MiddlewareInterface**: 定義中介軟體的標準契約
- **ControllerInterface**: 標記介面，用於控制器驗證 (S2)

---

## 請求生命週期 (Request Lifecycle)

### 完整流程

```
1. 定義階段 (Definition Phase)
   使用者使用 Route::get(...) 定義路由
   ↓
2. 創建 Request 物件
   Request::createFromGlobals() 或手動創建
   ↓
3. 分發 (Dispatch)
   Route::dispatch() 或 Router->dispatch(Request)
   ↓
4. 路由匹配 (Matching)
   Router 使用 RegexMatcher 迭代檢查路由：
   - 檢查 HTTP Method
   - 檢查 Domain（若有設定）
   - 檢查 URI Pattern
   - 提取路由參數
   ↓
5. 中介軟體管道 (Middleware Pipeline)
   MiddlewarePipeline 執行：
   - Global Middlewares (before)
   - Route-specific Middlewares (before)
   - 路由動作 (Controller/Closure)
   - Route-specific Middlewares (after)
   - Global Middlewares (after)
   ↓
6. 控制器驗證 (可選, S2)
   如果啟用 validateControllers：
   - 檢查控制器類別是否存在
   - 檢查是否實作 ControllerInterface
   ↓
7. 執行動作 (Execute Action)
   - 實例化控制器（若為陣列語法）
   - 呼叫方法/Closure
   - 傳遞路由參數
   ↓
8. 轉換為 Response
   - 字串 → Response::text()
   - 陣列 → Response::json()
   - Response 物件 → 直接返回
   ↓
9. 發送回應 (Send Response)
   Response->send() 發送到客戶端
```

---

## 安全性架構 (Security Architecture)

### S1: Host Header 驗證
- **組件**: `Request::validateHost()`, `Request::getSanitizedHost()`
- **防護**: Host Header Injection 攻擊
- **使用**: 應用層驗證 Host Header

### S2: 控制器白名單驗證
- **組件**: `ControllerInterface`, `Router::validateController()`
- **防護**: 任意類別實例化攻擊
- **使用**: 可選驗證（預設關閉）
- **啟用**: `new Router(validateControllers: true)` 或 `$router->enableControllerValidation()`

### S3: 安全的重定向
- **組件**: `Response::safeRedirect()`, `Response::isValidRedirectUrl()`
- **防護**: 開放重定向 (Open Redirect) 攻擊
- **使用**: 取代 `Response::redirect()` 進行安全檢查

---

## 設計模式 (Design Patterns)

1. **Facade Pattern**: `Route` 類別
2. **Singleton Pattern**: `Router` 實例
3. **Strategy Pattern**: `RouteMatcherInterface` 和實作
4. **Pipeline Pattern**: `MiddlewarePipeline` (洋蔥模式)
5. **Factory Pattern**: `Response` 靜態工廠方法
6. **Fluent Interface**: 鏈式呼叫支援
7. **Dependency Injection**: 建構函式注入

---

## 與 Laravel 的主要差異 (Key Differences)

### 不支援的功能
- ❌ Service Container (Dependency Injection 受限)
- ❌ 基於 Reflection 的 Controller 解析
- ❌ 路由模型綁定 (Route Model Binding)
- ❌ 參數限制 (`where`)
- ❌ 表單方法偽造 (`_method`)
- ❌ 中介軟體別名和群組

### 獨特優勢
- ✅ 獨立運行，無需框架
- ✅ 輕量級（無依賴）
- ✅ 內建安全性防護 (S1, S2, S3)
- ✅ 介面驅動設計
- ✅ 策略模式允許替換核心組件

---

## 測試架構 (Testing Architecture)

- **框架**: Pest 4.2.0
- **總測試數**: 203 tests (398 assertions)
- **安全性測試**: 35 tests
- **覆蓋率**: 100% 核心功能

**測試結構**:
```
tests/
├── Feature/
│   ├── DomainRoutingTest.php
│   ├── RouteTest.php
│   └── SecurityTest.php (35 tests)
└── Unit/
    ├── Http/ (Request, Response)
    ├── Matching/ (RegexMatcher)
    ├── Middleware/ (MiddlewarePipeline)
    ├── RouteCollectionTest.php
    ├── RouterMatcherStrategyTest.php
    └── ...
```

---

## 擴展點 (Extension Points)

### 1. 自訂路由匹配器
實作 `RouteMatcherInterface` 並透過建構函式注入：
```php
$router = new Router(matcher: new CustomMatcher());
```

### 2. 自訂路由集合
實作 `RouteCollectionInterface`：
```php
$router = new Router(routes: new CachedRouteCollection());
```

### 3. 自訂 URL 生成器
實作 `UrlGeneratorInterface`：
```php
$router = new Router(urlGenerator: new AbsoluteUrlGenerator(...));
```

---

## 參考文件 (References)

- **完整架構文件**: `/docs/ARCHITECTURE.md`
- **安全性指南**: `/docs/SECURITY.md`
- **Laravel 比較**: `/docs/LARAVEL_COMPARISON.md`
- **使用指南**: `/docs/USAGE_GUIDE.md`
- **改善項目**: `/docs/REMAINING_IMPROVEMENTS.md`

---

**最後更新**: 2025-12-22
**狀態**: Production-Ready (v1.0.0)
