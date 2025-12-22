# 🔲 Routini 剩餘改善項目清單

**更新日期**: 2025-12-22
**基於**: 選項 A - 繼續架構重構

---

## ✅ 已完成項目（回顧）

### Phase 1 & 2：基礎重構
- ✅ RouteMatcherInterface + RegexMatcher（策略模式）
- ✅ MiddlewareInterface + MiddlewarePipeline（洋蔥模式）
- ✅ RouteCollection（路由儲存分離）
- ✅ UrlGenerator（URL 生成分離）

### Phase 3 & 4：Request/Response + 中介軟體
- ✅ Request/Response 物件
- ✅ 洋蔥模式中介軟體管道
- ✅ 向後相容舊版中介軟體

---

## 🚧 選項 A 剩餘項目

### A1. 拆分 Router 類別 🟡 部分完成

| 子項目 | 狀態 | 優先級 | 預估時間 |
|:---|:---:|:---:|:---:|
| RouteCollection | ✅ 完成 | - | - |
| UrlGenerator | ✅ 完成 | - | - |
| **RouteDispatcher** | ❌ 待完成 | 🟡 中 | 1-2 小時 |
| RouteGroupStack | ⚪ 可選 | 🟢 低 | 1 小時 |

#### RouteDispatcher 詳細說明

**目的**: 將路由執行邏輯從 Router 分離

**當前狀態**:
```php
// src/Router.php (351 行)
protected function runRoute(RouteItem $route, Request $request): Response
{
    // 建立中介軟體管道
    // 執行路由動作
    // 轉換結果為 Response
}
```

**建議拆分**:
```php
// src/RouteDispatcher.php
class RouteDispatcher
{
    public function __construct(
        protected array $globalMiddlewares = []
    ) {}

    public function dispatch(RouteItem $route, Request $request): Response
    {
        // 執行邏輯移到這裡
    }
}

// src/Router.php 簡化為
protected function runRoute(RouteItem $route, Request $request): Response
{
    return $this->dispatcher->dispatch($route, $request);
}
```

**預期收益**:
- Router 程式碼減少 50+ 行
- 路由執行邏輯可獨立測試
- 可替換執行策略（如快取執行器）

**風險**: 🟢 低
- 僅內部重構，不影響外部 API

---

### A2. 加入核心介面 ✅ 已完成

| 介面 | 狀態 | 優先級 | 預估時間 |
|:---|:---:|:---:|:---:|
| RouteMatcherInterface | ✅ 完成 | - | - |
| MiddlewareInterface | ✅ 完成 | - | - |
| **RouteInterface** | ✅ 完成 | - | - |
| **RouteCollectionInterface** | ✅ 完成 | - | - |
| **UrlGeneratorInterface** | ✅ 完成 | - | - |
| RouteDispatcherInterface | ⚪ 可選 | 🟢 低 | 20 分鐘 |

**完成日期**: 2025-12-22
**測試結果**: ✅ 168 tests passed (352 assertions)
**向後相容**: ✅ 100% 相容
**檔案變更**:
- 新增: `src/Contracts/RouteInterface.php`
- 新增: `src/Contracts/RouteCollectionInterface.php`
- 新增: `src/Contracts/UrlGeneratorInterface.php`
- 更新: `src/RouteItem.php` (實作 RouteInterface)
- 更新: `src/RouteCollection.php` (實作 RouteCollectionInterface)
- 更新: `src/UrlGenerator.php` (實作 UrlGeneratorInterface)

#### 詳細說明

##### RouteInterface（最重要）

**目的**: 定義路由項目的標準介面

```php
namespace Routini\Contracts;

interface RouteInterface
{
    // 取得路由資訊
    public function getMethods(): array;
    public function getUri(): string;
    public function getAction();
    public function getDomain(): ?string;

    // 取得路由元資料
    public function getName(): ?string;
    public function getMiddlewares(): array;
    public function getParameters(): array;

    // 設定路由參數
    public function setParameters(array $parameters): void;

    // Fluent API
    public function name(string $name): self;
    public function middleware($middleware): self;
}
```

**實作方式**:
```php
// src/RouteItem.php
class RouteItem implements RouteInterface
{
    // 實作介面方法
}
```

**收益**:
- 型別提示更明確
- 可模擬測試
- 符合依賴反轉原則

---

##### RouteCollectionInterface

**目的**: 定義路由集合的標準介面

```php
namespace Routini\Contracts;

interface RouteCollectionInterface
{
    public function add(RouteInterface $route): void;
    public function all(): array;
    public function findByName(string $name): ?RouteInterface;
    public function count(): int;
    public function isEmpty(): bool;
    public function hasRoute(string $name): bool;
}
```

**實作方式**:
```php
// src/RouteCollection.php
class RouteCollection implements RouteCollectionInterface
{
    // 已有實作，只需加上 implements
}
```

**收益**:
- 可替換集合實作（如快取版本）
- 清楚的契約定義

---

##### UrlGeneratorInterface

**目的**: 定義 URL 生成器的標準介面

```php
namespace Routini\Contracts;

interface UrlGeneratorInterface
{
    public function generate(string $name, array $parameters = []): string;
    public function hasRoute(string $name): bool;
}
```

**實作方式**:
```php
// src/UrlGenerator.php
class UrlGenerator implements UrlGeneratorInterface
{
    // 已有實作，只需加上 implements
}
```

**收益**:
- 可替換生成器（如絕對 URL 生成器）
- 測試時可模擬

---

### A3. 改善 RouteItem 封裝 ✅ 已完成

**完成日期**: 2025-12-22
**測試結果**: ✅ 168 tests passed (352 assertions)
**向後相容**: ✅ 100% 相容（透過魔術方法）

**實作內容**:
- ✅ 使用建構子屬性提升（PHP 8.0+）將所有屬性改為私有
- ✅ 保留所有 getter 方法
- ✅ 新增 setDomain() 方法
- ✅ 實作魔術方法 __get、__set、__isset 保持向後相容
- ✅ __set 方法包含 E_USER_DEPRECATED 警告
- ✅ 更新 Router.php 使用 getter/setter 方法

**檔案變更**:
- 更新: `src/RouteItem.php` (私有屬性 + 魔術方法)
- 更新: `src/Router.php` (使用 getter/setter)

#### 問題描述（參考）

```php
// 當前：公開屬性
class RouteItem {
    public $methods;      // ❌ 可被外部直接修改
    public $uri;          // ❌ 可被外部直接修改
    public $action;       // ❌ 可被外部直接修改
    public $name = null;
    public $middlewares = [];
    public $domain = null;
    public $parameters = [];
}

// 使用範例
$route->methods = ['DELETE'];  // ❌ 危險：可意外修改
$route->uri = '/hacked';       // ❌ 危險：可意外修改
```

#### 建議改善

```php
// 改善：私有屬性 + getters
class RouteItem implements RouteInterface
{
    public function __construct(
        private array $methods,
        private string $uri,
        private $action,
        private ?string $name = null,
        private array $middlewares = [],
        private ?string $domain = null,
        private array $parameters = []
    ) {}

    // Getters
    public function getMethods(): array { return $this->methods; }
    public function getUri(): string { return $this->uri; }
    public function getAction() { return $this->action; }
    public function getName(): ?string { return $this->name; }
    public function getMiddlewares(): array { return $this->middlewares; }
    public function getDomain(): ?string { return $this->domain; }
    public function getParameters(): array { return $this->parameters; }

    // Setters（必要時）
    public function setParameters(array $parameters): void {
        $this->parameters = $parameters;
    }

    // Fluent API（修改並返回 $this）
    public function name(string $name): self {
        $this->name = $this->groupPrefix . $name;
        return $this;
    }

    public function middleware($middleware): self {
        $this->middlewares = is_array($middleware)
            ? array_merge($this->middlewares, $middleware)
            : [...$this->middlewares, $middleware];
        return $this;
    }
}
```

#### 遷移策略

**⚠️ 需要處理向後相容**:

**選項 1：魔術方法（完全相容）**
```php
class RouteItem implements RouteInterface
{
    private array $methods;

    // 向後相容：允許讀取公開屬性
    public function __get(string $name)
    {
        return match($name) {
            'methods' => $this->methods,
            'uri' => $this->uri,
            // ...
            default => throw new \Exception("Property {$name} not found")
        };
    }

    // 向後相容：允許設定公開屬性（但顯示警告）
    public function __set(string $name, $value)
    {
        trigger_error("Direct property access is deprecated", E_USER_DEPRECATED);
        // ...
    }
}
```

**選項 2：直接破壞（需要版本號升級）**
```php
// 直接改為私有屬性 + getters
// 需要升級到 v2.0.0
// 需要更新所有使用 $route->methods 的地方
```

**建議**: 使用**選項 1**，保持向後相容

#### 預期收益

- ✅ 封裝性提升
- ✅ 防止意外修改
- ✅ 符合 OOP 最佳實踐
- ✅ 實作 RouteInterface
- ⚠️ 需處理向後相容

---

## 📊 優先級總覽

### 🔴 安全性改善（建議優先）

#### S1. Host Header 驗證 ✅ 已完成

**完成日期**: 2025-12-22
**測試結果**: ✅ 168 tests passed (352 assertions)
**風險等級**: 高 → 已緩解

**實作內容**:
- ✅ `Request::validateHost()` - 驗證 Host Header
- ✅ `Request::getSanitizedHost()` - 取得已淨化的 Host（移除埠號）
- ✅ 使用嚴格比對 (`in_array($host, $allowedHosts, true)`)

**使用範例**:
```php
$allowedHosts = ['example.com', 'www.example.com'];
if (!$request->validateHost($allowedHosts)) {
    return Response::error('Invalid Host header', 400);
}
```

---

#### S2. 控制器白名單驗證 ✅ 已完成

**完成日期**: 2025-12-22
**測試結果**: ✅ 168 tests passed (352 assertions)
**風險等級**: 中 → 已緩解

**實作內容**:
- ✅ `ControllerInterface` - 標記介面for控制器類別
- ✅ `Router::validateController()` - 驗證控制器類別
- ✅ `Router::enableControllerValidation()` - 啟用驗證
- ✅ `Router::disableControllerValidation()` - 停用驗證
- ✅ `Router::isControllerValidationEnabled()` - 檢查狀態
- ✅ 可選驗證機制（預設關閉以保持向後相容）

**使用範例**:
```php
// 控制器實作 ControllerInterface
class UserController implements ControllerInterface
{
    public function show($id)
    {
        return "User: {$id}";
    }
}

// 方式 1: 建構函式啟用
$router = new Router(validateControllers: true);

// 方式 2: 動態啟用
$router->enableControllerValidation();

// 使用路由
Route::get('/users/{id}', [UserController::class, 'show']);

// 未實作介面的類別會被拒絕
Route::get('/bad', [stdClass::class, 'method']); // ❌ RuntimeException
```

---

#### S3. 安全的重定向方法 ✅ 已完成

**完成日期**: 2025-12-22
**測試結果**: ✅ 168 tests passed (352 assertions)
**風險等級**: 中 → 已緩解

**實作內容**:
- ✅ `Response::safeRedirect()` - 安全的重定向方法
- ✅ `Response::isValidRedirectUrl()` - 驗證 URL 安全性（私有方法）
- ✅ 支援相對 URL
- ✅ 支援域名白名單
- ✅ 在 `Response::redirect()` 加入安全警告註解

**使用範例**:
```php
// 允許相對 URL（安全）
Response::safeRedirect('/dashboard');

// 允許特定域名
$allowedDomains = ['example.com', 'trusted.com'];
Response::safeRedirect('https://example.com/page', $allowedDomains);

// 不安全的 URL 會拋出例外
try {
    Response::safeRedirect('https://evil.com/phishing');
} catch (\InvalidArgumentException $e) {
    // 處理錯誤
}
```

---

#### S5. 安全性測試 ✅ 已完成

**完成日期**: 2025-12-22
**測試結果**: ✅ 203 tests passed (398 assertions, +35 security tests)
**實際時間**: 1.5 小時

**實作內容**:
- ✅ 新增 `tests/Feature/SecurityTest.php` 測試檔案
- ✅ 測試 Host Header Injection 防護（8 個測試）
- ✅ 測試 Controller Validation（9 個測試）
- ✅ 測試 Safe Redirect（10 個測試）
- ✅ 測試 Path Traversal 防護（4 個測試）
- ✅ 測試其他安全功能（4 個測試）

**總測試覆蓋**:
```
✅ 35 個安全性測試
✅ 100% 覆蓋 S1, S2, S3 的實作
✅ Path Traversal 防護測試
✅ HTTP Method 驗證測試
✅ 中介軟體安全檢查測試
```

**檔案位置**:
- `tests/Feature/SecurityTest.php` (467 行，35 個測試)

---

### 🟡 可選處理

**1. 提取 RouteDispatcher**（1-2 小時）
- 分離路由執行邏輯
- 減少 Router 職責

**理由**:
- 🟡 非必要，但有益
- ✅ 進一步降低 Router 複雜度
- ✅ 可獨立測試執行邏輯

---

**2. 提取 RouteGroupStack**（1 小時）
- 分離群組堆疊管理
- Router 職責再減少

**理由**:
- 🟢 錦上添花
- ✅ Router 會更簡潔
- ⚪ 可之後再處理

---

## 🎯 建議執行順序

### 方案 A：快速完成核心（推薦）✅ 已完成

**總時間**: 已完成（原預估 2.5 小時）

```
1. ✅ 加入核心介面（已完成 2025-12-22）
   ├─ ✅ RouteInterface
   ├─ ✅ RouteCollectionInterface
   └─ ✅ UrlGeneratorInterface

2. ✅ 改善 RouteItem 封裝（已完成 2025-12-22）
   ├─ ✅ 私有屬性 + getters
   ├─ ✅ 魔術方法向後相容
   └─ ✅ 完善封裝性

3. ✅ 完成核心架構改善
```

**完成狀態**:
- ✅ 所有核心類別都有介面
- ✅ RouteItem 完全封裝
- ✅ 100% 向後相容
- ✅ 符合 SOLID 原則
- ✅ 適合 Production 使用

---

### 方案 B：完整重構（徹底）

**總時間**: ~2.5 小時（原預估 5 小時，A 部分已完成）

```
1. ✅ 加入核心介面（已完成 2025-12-22）
2. ✅ 改善 RouteItem 封裝（已完成 2025-12-22）
3. 提取 RouteDispatcher（1.5 小時）← 下一步
4. 提取 RouteGroupStack（1 小時）
5. ✅ 完成所有架構改善
```

**完成後狀態**:
- ✅ Router 完全拆分
- ✅ 每個類別單一職責
- ✅ 高度可測試和可維護
- ✅ 企業級架構標準

---

### 方案 C：保持現狀

**時間**: 0 小時

**理由**:
- ✅ 目前已可用於 Production
- ✅ 已完成重要改善
- ⚪ 剩餘項目為錦上添花

---

## 📝 快速參考

### 已完成 ✅
- Request/Response 物件
- 洋蔥模式中介軟體
- 策略模式路由匹配
- RouteCollection
- UrlGenerator
- RouteInterface
- RouteCollectionInterface
- UrlGeneratorInterface
- RouteItem 封裝改善（私有屬性 + 魔術方法）
- S1: Host Header 驗證
- S2: 控制器白名單驗證
- S3: 安全的重定向方法
- S5: 安全性測試（35 個測試）

### 可選改善 🟡
- RouteDispatcher
- RouteGroupStack

### 未來擴展 🟢
- 路由快取系統
- 事件系統
- 依賴注入容器
- 編譯路由

---

## 💡 我的建議

**目前狀態：方案 A 已全部完成！安全性改善全部完成！** 🎉

1. **✅ 方案 A 完成**
   - ✅ 核心介面已完成（A2）
   - ✅ RouteItem 封裝已完成（A3）
   - ✅ 達到生產級品質
   - ✅ 100% 向後相容
   - ✅ 符合 SOLID 原則

2. **✅ 安全性改善全部完成**
   - ✅ S1: Host Header 驗證完成
   - ✅ S2: 控制器白名單驗證完成
   - ✅ S3: 安全的重定向方法完成
   - ✅ S5: 安全性測試完成（35 個測試）
   - ✅ 總測試數: 203 tests (398 assertions)

3. **繼續方案 B（可選）** → 剩餘 2.5 小時
   - ✅ 方案 A 已全部完成
   - ✅ 安全性改善已全部完成
   - ⏳ 提取 RouteDispatcher（1.5 小時）
   - ⏳ 提取 RouteGroupStack（1 小時）
   - 企業級架構

4. **維持現狀（強烈推薦）** → 0 小時
   - ✅ 所有核心改善已完成
   - ✅ 所有安全性改善已完成並經過測試驗證
   - ✅ 適合 Production 使用
   - ⚪ 可之後再處理 RouteDispatcher

**接下來想處理哪一個項目？**
- **強烈推薦**：維持現狀，目前架構和安全性已達到生產級品質
- **可選 A1**：提取 RouteDispatcher（1.5 小時）
- **可選 A1+**：提取 RouteGroupStack（1 小時）
- **可選 S4**：Rate Limiting 中介軟體範例（1 小時，低優先級）
