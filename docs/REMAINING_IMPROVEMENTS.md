# 🔲 Routini 剩餘改善項目清單

**更新日期**: 2025-12-21
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

### A2. 加入核心介面 🟡 部分完成

| 介面 | 狀態 | 優先級 | 預估時間 |
|:---|:---:|:---:|:---:|
| RouteMatcherInterface | ✅ 完成 | - | - |
| MiddlewareInterface | ✅ 完成 | - | - |
| **RouteInterface** | ❌ 待完成 | 🔴 高 | 30 分鐘 |
| **RouteCollectionInterface** | ❌ 待完成 | 🔴 高 | 30 分鐘 |
| **UrlGeneratorInterface** | ❌ 待完成 | 🟡 中 | 20 分鐘 |
| RouteDispatcherInterface | ⚪ 可選 | 🟢 低 | 20 分鐘 |

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

### A3. 改善 RouteItem 封裝 ❌ 未開始

**當前狀態**: 🔴 高優先級
**預估時間**: 1 小時

#### 問題描述

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

### 🔴 建議優先處理

**1. 加入核心介面**（總計 ~1.5 小時）
- RouteInterface（30 分鐘）
- RouteCollectionInterface（30 分鐘）
- UrlGeneratorInterface（20 分鐘）
- 更新現有類別實作介面（20 分鐘）

**理由**:
- ✅ 風險極低（僅加入介面）
- ✅ 不影響現有功能
- ✅ 為後續改善鋪路
- ✅ 符合 SOLID 原則

---

**2. 改善 RouteItem 封裝**（1 小時）
- 私有屬性 + getters
- 實作 RouteInterface
- 向後相容魔術方法

**理由**:
- ✅ 提升封裝性
- ⚠️ 需處理向後相容
- ✅ 完成後 RouteItem 完全符合 OOP 標準

---

### 🟡 可選處理

**3. 提取 RouteDispatcher**（1-2 小時）
- 分離路由執行邏輯
- 減少 Router 職責

**理由**:
- 🟡 非必要，但有益
- ✅ 進一步降低 Router 複雜度
- ✅ 可獨立測試執行邏輯

---

**4. 提取 RouteGroupStack**（1 小時）
- 分離群組堆疊管理
- Router 職責再減少

**理由**:
- 🟢 錦上添花
- ✅ Router 會更簡潔
- ⚪ 可之後再處理

---

## 🎯 建議執行順序

### 方案 A：快速完成核心（推薦）

**總時間**: ~2.5 小時

```
1. 加入核心介面（1.5 小時）
   ├─ RouteInterface
   ├─ RouteCollectionInterface
   └─ UrlGeneratorInterface

2. 改善 RouteItem 封裝（1 小時）
   ├─ 私有屬性 + getters
   ├─ 實作 RouteInterface
   └─ 向後相容處理

3. ✅ 完成核心架構改善
```

**完成後狀態**:
- ✅ 所有核心類別都有介面
- ✅ RouteItem 完全封裝
- ✅ 100% 向後相容
- ✅ 符合 SOLID 原則
- ✅ 適合 Production 使用

---

### 方案 B：完整重構（徹底）

**總時間**: ~5 小時

```
1. 加入核心介面（1.5 小時）
2. 改善 RouteItem 封裝（1 小時）
3. 提取 RouteDispatcher（1.5 小時）
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

### 建議優先 🔴
- RouteInterface
- RouteCollectionInterface
- UrlGeneratorInterface
- RouteItem 封裝改善

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

**如果你想要...**

1. **快速完成** → 方案 A（2.5 小時）
   - 核心介面 + RouteItem 封裝
   - 達到生產級品質

2. **徹底重構** → 方案 B（5 小時）
   - 完整拆分 Router
   - 企業級架構

3. **先用再說** → 方案 C（0 小時）
   - 目前已夠用
   - 之後再改善

**你想選擇哪一個方案？**
