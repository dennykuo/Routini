# 🏗️ Routini 架構建議分析

## 分析日期
2025-12-19

## 目標
本文件專注於**架構設計層面**的改善建議，不涉及安全性議題。主要目標：
- ✅ 提升程式碼可維護性
- ✅ 增強可測試性
- ✅ 提高可擴展性
- ✅ 降低耦合度
- ✅ 符合 SOLID 原則

---

## 一、當前架構分析

### 現有類別結構

```
Routini\
├── Route.php (44 行)           - Facade 靜態入口
├── Router.php (228 行)         - 核心邏輯（過於肥大）
├── RouteItem.php (52 行)       - 路由資料物件
├── RouteRegistrar.php (63 行)  - 群組註冊輔助
└── helpers.php (17 行)         - 輔助函式
```

### 職責分析

| 類別 | 當前職責 | 程式碼行數 | 問題 |
|:---|:---|:---:|:---|
| **Route** | Facade + 單例管理 | 44 | 混合關注點 |
| **Router** | 路由管理、匹配、執行、URL生成、中介軟體、群組管理 | 228 | 🔴 職責過重 |
| **RouteItem** | 路由資料儲存 | 52 | 🟡 封裝不足（public 屬性） |
| **RouteRegistrar** | 群組屬性收集 | 63 | ✅ 職責單一 |

---

## 二、架構問題深度分析

### 問題 1：Router 類別違反單一職責原則（SRP）

#### 現況分析

`Router.php` 承擔了**至少 6 個不同職責**：

```php
class Router {
    // 1️⃣ 路由收集與儲存
    protected $routes = [];
    public function add($method, $uri, $action) { }

    // 2️⃣ 群組管理
    protected $groupStack = [];
    public function group(array $attributes, callable $routes) { }
    protected function mergeGroupAttributes() { }

    // 3️⃣ 路由匹配
    public function dispatch($requestUri, $requestMethod, $requestHost = null) { }
    protected function matchUri(RouteItem $route, $requestUri) { }

    // 4️⃣ 路由執行
    protected function runRoute(RouteItem $route) { }

    // 5️⃣ 中介軟體執行
    // 在 runRoute() 內部執行中介軟體

    // 6️⃣ URL 生成
    public function url($name, $parameters = []) { }
    protected function findRouteByName($name) { }
}
```

#### 影響

- ❌ **測試困難**：無法單獨測試某一職責
- ❌ **難以擴展**：修改一個功能可能影響其他功能
- ❌ **程式碼過長**：228 行，閱讀困難
- ❌ **無法替換實作**：所有邏輯綁定在一起

#### 建議重構

**拆分成專責類別：**

```php
// 1. 路由收集與管理
class Router {
    protected RouteCollection $routes;
    protected RouteGroupStack $groupStack;
    protected RouteMatcherInterface $matcher;
    protected RouteDispatcherInterface $dispatcher;
    protected UrlGeneratorInterface $urlGenerator;

    public function add(string $method, string $uri, $action): RouteInterface {
        $route = new RouteItem($method, $uri, $action);
        $this->routes->add($route);
        return $route;
    }

    public function dispatch(Request $request): Response {
        $route = $this->matcher->match($request);
        return $this->dispatcher->dispatch($route, $request);
    }
}

// 2. 路由集合容器
class RouteCollection implements \IteratorAggregate {
    protected array $routes = [];
    protected array $nameList = [];

    public function add(RouteInterface $route): void {
        $this->routes[] = $route;
        if ($route->getName()) {
            $this->nameList[$route->getName()] = $route;
        }
    }

    public function findByName(string $name): ?RouteInterface {
        return $this->nameList[$name] ?? null;
    }

    public function all(): array {
        return $this->routes;
    }

    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->routes);
    }
}

// 3. 路由匹配器
interface RouteMatcherInterface {
    public function match(Request $request): ?RouteInterface;
}

class RegexRouteMatcher implements RouteMatcherInterface {
    public function __construct(protected RouteCollection $routes) {}

    public function match(Request $request): ?RouteInterface {
        foreach ($this->routes as $route) {
            if ($this->matchRoute($route, $request)) {
                return $route;
            }
        }
        return null;
    }

    protected function matchRoute(RouteInterface $route, Request $request): bool {
        // 從 Router::matchUri() 移過來的邏輯
        if (!in_array($request->getMethod(), $route->getMethods())) {
            return false;
        }

        // 檢查網域
        if ($route->getDomain() && $route->getDomain() !== $request->getHost()) {
            return false;
        }

        // 正則匹配
        return $this->matchUri($route, $request->getPath());
    }

    protected function matchUri(RouteInterface $route, string $uri): bool {
        // 原 Router::matchUri() 的邏輯
    }
}

// 4. 路由執行器
interface RouteDispatcherInterface {
    public function dispatch(RouteInterface $route, Request $request): Response;
}

class RouteDispatcher implements RouteDispatcherInterface {
    public function __construct(
        protected MiddlewarePipelineInterface $pipeline,
        protected ControllerResolverInterface $resolver
    ) {}

    public function dispatch(RouteInterface $route, Request $request): Response {
        // 透過中介軟體管道執行
        return $this->pipeline->process($request, function($request) use ($route) {
            return $this->executeAction($route, $request);
        });
    }

    protected function executeAction(RouteInterface $route, Request $request): Response {
        $action = $route->getAction();

        if (is_callable($action)) {
            $result = call_user_func_array($action, $route->getParameters());
        } elseif (is_array($action)) {
            $result = $this->resolver->resolve($action, $route->getParameters());
        }

        return $this->toResponse($result);
    }
}

// 5. URL 生成器
interface UrlGeneratorInterface {
    public function generate(string $name, array $parameters = []): string;
}

class UrlGenerator implements UrlGeneratorInterface {
    public function __construct(protected RouteCollection $routes) {}

    public function generate(string $name, array $parameters = []): string {
        $route = $this->routes->findByName($name);

        if (!$route) {
            throw new \Exception("Route [{$name}] not defined.");
        }

        return $this->compileUri($route->getUri(), $parameters);
    }

    protected function compileUri(string $uri, array $parameters): string {
        // 從 Router::url() 移過來的邏輯
    }
}

// 6. 群組堆疊管理
class RouteGroupStack {
    protected array $stack = [];

    public function push(array $attributes): void {
        $this->stack[] = $attributes;
    }

    public function pop(): ?array {
        return array_pop($this->stack);
    }

    public function current(): array {
        return $this->merge();
    }

    protected function merge(): array {
        $final = ['prefix' => '', 'middleware' => [], 'name' => '', 'domain' => null];

        foreach ($this->stack as $group) {
            if (isset($group['prefix'])) {
                $final['prefix'] .= '/' . trim($group['prefix'], '/');
            }

            if (isset($group['middleware'])) {
                $middleware = is_array($group['middleware'])
                    ? $group['middleware']
                    : [$group['middleware']];
                $final['middleware'] = array_merge($final['middleware'], $middleware);
            }

            if (isset($group['name'])) {
                $final['name'] .= $group['name'];
            }

            if (isset($group['domain'])) {
                $final['domain'] = $group['domain'];
            }
        }

        return $final;
    }
}
```

#### 重構後的優勢

| 優勢 | 說明 |
|:---|:---|
| ✅ **職責清晰** | 每個類別只負責一件事 |
| ✅ **易於測試** | 可以獨立測試每個組件 |
| ✅ **可替換** | 例如：替換成快取版的 RouteMatcher |
| ✅ **易於維護** | 修改匹配邏輯不影響 URL 生成 |
| ✅ **更小的類別** | 每個類別 < 100 行 |

---

### 問題 2：缺少介面抽象（違反依賴反轉原則 DIP）

#### 現況分析

目前所有類別都是**具體實作**，沒有介面：

```php
// ❌ 沒有介面定義
class Router { }
class RouteItem { }
class RouteMatcher { }
```

#### 影響

- ❌ **無法替換實作**：想換成快取版、編譯版都做不到
- ❌ **難以單元測試**：無法 mock 依賴
- ❌ **高耦合**：程式碼依賴具體實作而非抽象
- ❌ **違反 DIP**：高層模組依賴低層模組

#### 建議設計

**定義核心介面：**

```php
namespace Routini\Contracts;

// 路由介面
interface RouteInterface {
    public function getMethods(): array;
    public function getUri(): string;
    public function getAction();
    public function getName(): ?string;
    public function getDomain(): ?string;
    public function getMiddlewares(): array;
    public function getParameters(): array;

    public function setParameters(array $parameters): void;

    // Fluent API
    public function name(string $name): self;
    public function middleware($middleware): self;
}

// 路由器介面
interface RouterInterface {
    public function add(string|array $method, string $uri, $action): RouteInterface;
    public function group(array $attributes, callable $callback): void;
    public function dispatch(Request $request): Response;
}

// 路由匹配器介面
interface RouteMatcherInterface {
    public function match(Request $request): ?RouteInterface;
}

// 路由執行器介面
interface RouteDispatcherInterface {
    public function dispatch(RouteInterface $route, Request $request): Response;
}

// URL 生成器介面
interface UrlGeneratorInterface {
    public function generate(string $name, array $parameters = []): string;
}

// 中介軟體介面
interface MiddlewareInterface {
    public function handle(Request $request, callable $next): Response;
}

// 中介軟體管道介面
interface MiddlewarePipelineInterface {
    public function pipe($middleware): self;
    public function process(Request $request, callable $destination): Response;
}

// 控制器解析器介面
interface ControllerResolverInterface {
    public function resolve(array $action, array $parameters): mixed;
}
```

**具體實作：**

```php
namespace Routini;

use Routini\Contracts\RouterInterface;
use Routini\Contracts\RouteInterface;

class Router implements RouterInterface {
    // 依賴介面而非具體實作
    public function __construct(
        protected RouteMatcherInterface $matcher,
        protected RouteDispatcherInterface $dispatcher,
        protected UrlGeneratorInterface $urlGenerator
    ) {}
}

class RouteItem implements RouteInterface {
    // ...
}

class RegexRouteMatcher implements RouteMatcherInterface {
    // ...
}
```

#### 使用範例

```php
// 可以輕鬆替換實作
$router = new Router(
    matcher: new CachedRouteMatcher(
        new RegexRouteMatcher($routes)
    ),
    dispatcher: new RouteDispatcher($pipeline, $resolver),
    urlGenerator: new UrlGenerator($routes)
);

// 測試時可以 mock
$mockMatcher = $this->createMock(RouteMatcherInterface::class);
$router = new Router($mockMatcher, ...);
```

---

### 問題 3：RouteItem 封裝性不足

#### 現況分析

```php
class RouteItem {
    public $methods = [];      // ❌ public - 可以被外部任意修改
    public $uri;               // ❌ public
    public $action;            // ❌ public
    public $name = null;       // ❌ public
    public $domain = null;     // ❌ public
    public $middlewares = [];  // ❌ public
    public $parameters = [];   // ❌ public
    protected $groupPrefix = '';
}
```

#### 問題

- ❌ **破壞封裝**：外部可以直接修改內部狀態
- ❌ **無法驗證**：無法在 setter 中進行驗證
- ❌ **難以除錯**：不知道誰修改了屬性
- ❌ **缺少不可變性**：路由建立後仍可修改

#### 建議改善

**方案 1：使用 Getter/Setter（傳統方式）**

```php
class RouteItem implements RouteInterface {
    protected array $methods;
    protected string $uri;
    protected $action;
    protected ?string $name = null;
    protected ?string $domain = null;
    protected array $middlewares = [];
    protected array $parameters = [];
    protected string $groupPrefix = '';

    public function __construct(array $methods, string $uri, $action) {
        $this->methods = $methods;
        $this->uri = $uri;
        $this->action = $action;
    }

    // Getters
    public function getMethods(): array {
        return $this->methods;
    }

    public function getUri(): string {
        return $this->uri;
    }

    public function getAction() {
        return $this->action;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function getDomain(): ?string {
        return $this->domain;
    }

    public function getMiddlewares(): array {
        return $this->middlewares;
    }

    public function getParameters(): array {
        return $this->parameters;
    }

    // Setters (可加入驗證)
    public function setParameters(array $parameters): void {
        $this->parameters = $parameters;
    }

    // Fluent API
    public function name(string $name): self {
        $this->name = $this->groupPrefix . $name;
        return $this;
    }

    public function middleware($middleware): self {
        if (is_array($middleware)) {
            $this->middlewares = array_merge($this->middlewares, $middleware);
        } else {
            $this->middlewares[] = $middleware;
        }
        return $this;
    }

    public function setGroupPrefix(string $prefix): self {
        $this->groupPrefix = $prefix;
        return $this;
    }
}
```

**方案 2：不可變物件（Immutable，更進階）**

```php
class RouteItem implements RouteInterface {
    protected array $methods;
    protected string $uri;
    protected $action;
    protected ?string $name = null;
    protected array $middlewares = [];

    public function __construct(
        array $methods,
        string $uri,
        $action,
        ?string $name = null,
        array $middlewares = []
    ) {
        $this->methods = $methods;
        $this->uri = $uri;
        $this->action = $action;
        $this->name = $name;
        $this->middlewares = $middlewares;
    }

    // Getters (同上)

    // 不可變的 setters - 回傳新實例
    public function withName(string $name): self {
        $clone = clone $this;
        $clone->name = $name;
        return $clone;
    }

    public function withMiddleware($middleware): self {
        $clone = clone $this;
        if (is_array($middleware)) {
            $clone->middlewares = array_merge($clone->middlewares, $middleware);
        } else {
            $clone->middlewares[] = $middleware;
        }
        return $clone;
    }
}

// 使用方式
$route = Route::get('/user/{id}', $action)
    ->withName('user.show')
    ->withMiddleware(['auth']);
// 原始的 $route 不受影響
```

**方案 3：使用 PHP 8.0+ 建構子屬性提升（推薦）**

```php
class RouteItem implements RouteInterface {
    protected string $groupPrefix = '';
    protected array $parameters = [];

    public function __construct(
        protected array $methods,
        protected string $uri,
        protected $action,
        protected ?string $name = null,
        protected ?string $domain = null,
        protected array $middlewares = []
    ) {}

    // Getters
    public function getMethods(): array {
        return $this->methods;
    }

    public function getUri(): string {
        return $this->uri;
    }

    // ... 其他 getters

    // Fluent setters
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

    public function setParameters(array $parameters): void {
        $this->parameters = $parameters;
    }

    public function getParameters(): array {
        return $this->parameters;
    }
}
```

---

### 問題 4：路由匹配邏輯寫死（違反開放封閉原則 OCP）

#### 現況分析

`Router.php:180-197` 的路由匹配邏輯：

```php
protected function matchUri(RouteItem $route, $requestUri) {
    // 正則表達式寫死
    $pattern = preg_replace('/\/{([a-zA-Z0-9_]+)\?\}/', '(?:/(?P<\1>[^/]+))?', $route->uri);
    $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[^/]+)', $pattern);
    $pattern = "~^" . $pattern . "$~";

    if (preg_match($pattern, $requestUri, $matches)) {
        $route->parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        return true;
    }
    return false;
}
```

#### 問題

- ❌ **無法替換策略**：想改用其他匹配方式（精確匹配、編譯路由、Trie 樹）都做不到
- ❌ **違反 OCP**：要擴展功能必須修改原始碼
- ❌ **無法優化**：無法針對不同場景使用不同演算法

#### 建議：使用策略模式（Strategy Pattern）

```php
// 1. 定義策略介面
interface RouteMatcherInterface {
    public function match(RouteInterface $route, string $uri): bool;
    public function extractParameters(RouteInterface $route, string $uri): array;
}

// 2. 實作不同策略

// 策略 1：正則匹配（目前的方式）
class RegexMatcher implements RouteMatcherInterface {
    public function match(RouteInterface $route, string $uri): bool {
        $pattern = $this->compilePattern($route->getUri());
        return (bool) preg_match($pattern, $uri);
    }

    public function extractParameters(RouteInterface $route, string $uri): array {
        $pattern = $this->compilePattern($route->getUri());
        preg_match($pattern, $uri, $matches);
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    protected function compilePattern(string $uri): string {
        // 選填參數
        $pattern = preg_replace(
            '/\/{([a-zA-Z0-9_]+)\?\}/',
            '(?:/(?P<\1>[^/]+))?',
            $uri
        );
        // 必填參數
        $pattern = preg_replace(
            '/\{([a-zA-Z0-9_]+)\}/',
            '(?P<\1>[^/]+)',
            $pattern
        );
        return "~^{$pattern}$~";
    }
}

// 策略 2：精確匹配（靜態路由，效能最佳）
class ExactMatcher implements RouteMatcherInterface {
    public function match(RouteInterface $route, string $uri): bool {
        return $route->getUri() === $uri;
    }

    public function extractParameters(RouteInterface $route, string $uri): array {
        return [];
    }
}

// 策略 3：編譯路由（效能優化）
class CompiledMatcher implements RouteMatcherInterface {
    protected array $compiled = [];

    public function match(RouteInterface $route, string $uri): bool {
        $pattern = $this->getCompiledPattern($route);
        return (bool) preg_match($pattern, $uri);
    }

    protected function getCompiledPattern(RouteInterface $route): string {
        $routeUri = $route->getUri();

        if (!isset($this->compiled[$routeUri])) {
            // 預先編譯並快取
            $this->compiled[$routeUri] = $this->compile($routeUri);
        }

        return $this->compiled[$routeUri];
    }

    protected function compile(string $uri): string {
        // 編譯邏輯
    }
}

// 策略 4：Trie 樹匹配（大量路由時效能最佳）
class TrieMatcher implements RouteMatcherInterface {
    protected TrieNode $root;

    public function __construct() {
        $this->root = new TrieNode();
    }

    public function match(RouteInterface $route, string $uri): bool {
        // Trie 樹查找邏輯
    }
}

// 3. 在 Router 中使用

class Router implements RouterInterface {
    protected RouteMatcherInterface $matcher;

    public function __construct(RouteMatcherInterface $matcher = null) {
        $this->matcher = $matcher ?? new RegexMatcher();
    }

    public function setMatcher(RouteMatcherInterface $matcher): void {
        $this->matcher = $matcher;
    }

    protected function matchRoute(RouteInterface $route, string $uri): bool {
        return $this->matcher->match($route, $uri);
    }
}

// 4. 使用範例

// 預設使用正則匹配
$router = new Router();

// 切換成編譯版（效能更好）
$router->setMatcher(new CompiledMatcher());

// 大量路由時使用 Trie 樹
$router->setMatcher(new TrieMatcher());

// 靜態路由使用精確匹配
$router->setMatcher(new ExactMatcher());
```

#### 優勢

| 優勢 | 說明 |
|:---|:---|
| ✅ **可替換** | 可以根據場景選擇最佳策略 |
| ✅ **符合 OCP** | 擴展新策略不需修改原始碼 |
| ✅ **效能優化** | 可以針對不同場景優化 |
| ✅ **易於測試** | 可以獨立測試每個策略 |

---

### 問題 5：中介軟體執行邏輯過於簡化

#### 現況分析

`Router.php:199-221` 的中介軟體執行：

```php
protected function runRoute(RouteItem $route) {
    // 簡單的循環執行
    foreach ($route->middlewares as $middleware) {
        if (is_callable($middleware)) {
            if ($middleware() === false) return;
        } elseif (class_exists($middleware)) {
            $instance = new $middleware();
            if (method_exists($instance, 'handle')) {
                if ($instance->handle() === false) return;
            }
        }
    }

    // 執行路由動作
    if (is_callable($route->action)) {
        return call_user_func_array($route->action, $route->parameters);
    }
    // ...
}
```

#### 問題

- ❌ **無法傳遞請求物件**：中介軟體無法訪問請求資料
- ❌ **無法修改回應**：中介軟體無法在執行後處理回應
- ❌ **無法提前終止**：除了回傳 false，沒有更好的方式
- ❌ **無法實作洋蔥模型**：Before/After 中介軟體無法實現
- ❌ **硬編碼實例化**：`new $middleware()` 無法依賴注入

#### 建議：實作中介軟體管道模式（Pipeline Pattern）

```php
// 1. 定義中介軟體介面
interface MiddlewareInterface {
    /**
     * 處理請求
     *
     * @param Request $request 請求物件
     * @param callable $next 下一個中介軟體
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}

// 2. 實作中介軟體管道
class MiddlewarePipeline implements MiddlewarePipelineInterface {
    protected array $middlewares = [];

    public function pipe($middleware): self {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * 執行中介軟體管道（洋蔥模型）
     */
    public function process(Request $request, callable $destination): Response {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            function ($next, $middleware) {
                return function ($request) use ($middleware, $next) {
                    // 解析中介軟體
                    $instance = $this->resolveMiddleware($middleware);

                    // 執行中介軟體的 handle 方法
                    return $instance->handle($request, $next);
                };
            },
            $destination
        );

        return $pipeline($request);
    }

    protected function resolveMiddleware($middleware): MiddlewareInterface {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_string($middleware) && class_exists($middleware)) {
            return new $middleware();
        }

        if (is_callable($middleware)) {
            return new CallableMiddleware($middleware);
        }

        throw new \InvalidArgumentException('Invalid middleware');
    }
}

// 3. Callable 包裝器
class CallableMiddleware implements MiddlewareInterface {
    public function __construct(protected $callable) {}

    public function handle(Request $request, callable $next): Response {
        $result = call_user_func($this->callable, $request, $next);

        if ($result instanceof Response) {
            return $result;
        }

        return $next($request);
    }
}

// 4. 中介軟體範例

// 認證中介軟體
class AuthMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next): Response {
        // Before - 請求前執行
        if (!$request->hasHeader('Authorization')) {
            return new Response('Unauthorized', 401);
        }

        // 傳遞到下一個中介軟體
        $response = $next($request);

        // After - 回應後執行
        $response->addHeader('X-Auth-User', 'john');

        return $response;
    }
}

// 日誌中介軟體
class LogMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next): Response {
        $startTime = microtime(true);

        // 執行下一個中介軟體
        $response = $next($request);

        // 記錄執行時間
        $duration = microtime(true) - $startTime;
        error_log("Request took {$duration}s");

        return $response;
    }
}

// CORS 中介軟體
class CorsMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next): Response {
        // OPTIONS 請求直接回應
        if ($request->getMethod() === 'OPTIONS') {
            return new Response('', 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE',
            ]);
        }

        $response = $next($request);

        // 加入 CORS headers
        $response->addHeader('Access-Control-Allow-Origin', '*');

        return $response;
    }
}

// 5. 使用範例

// 註冊中介軟體
Route::middleware([AuthMiddleware::class, LogMiddleware::class])
    ->group(function() {
        Route::get('/api/users', [UserController::class, 'index']);
    });

// 或使用 Closure
Route::middleware(function($request, $next) {
    // Before
    if (!$request->hasToken()) {
        return new Response('No token', 401);
    }

    // Next
    $response = $next($request);

    // After
    $response->addHeader('X-Custom', 'Value');

    return $response;
})->group(function() {
    // ...
});
```

#### 執行流程圖

```
請求進入
   │
   ▼
┌──────────────────┐
│ AuthMiddleware   │ ──► Before: 檢查 Token
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ LogMiddleware    │ ──► Before: 記錄開始時間
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ Route Action     │ ──► 執行控制器
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ LogMiddleware    │ ──► After: 記錄執行時間
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ AuthMiddleware   │ ──► After: 加入 Header
└────────┬─────────┘
         │
         ▼
      回應輸出
```

#### 優勢

| 優勢 | 說明 |
|:---|:---|
| ✅ **洋蔥模型** | 支援 Before/After 邏輯 |
| ✅ **可傳遞資料** | Request/Response 物件 |
| ✅ **可提前終止** | 直接回傳 Response |
| ✅ **可修改回應** | After 階段處理 |
| ✅ **符合 PSR-15** | 業界標準 |

---

### 問題 6：缺少請求/回應抽象

#### 現況分析

目前直接使用全域變數：

```php
// Route.php:42
public static function dispatch() {
    return self::getInstance()->dispatch(
        $_SERVER['REQUEST_URI'],    // ❌ 直接使用全域變數
        $_SERVER['REQUEST_METHOD']  // ❌ 直接使用全域變數
    );
}

// Router.php:109
$requestHost = $requestHost ?? $_SERVER['HTTP_HOST'] ?? null;  // ❌
```

#### 問題

- ❌ **難以測試**：無法在測試中模擬請求
- ❌ **全域依賴**：依賴全域狀態
- ❌ **無法重複使用**：一次請求後狀態污染
- ❌ **缺少型別安全**：字串參數容易出錯

#### 建議：引入 Request/Response 物件

```php
// 1. Request 物件
class Request {
    protected string $uri;
    protected string $method;
    protected string $host;
    protected array $headers;
    protected array $query;
    protected array $post;
    protected array $files;
    protected array $server;
    protected array $attributes = []; // 自定義屬性（如路由參數）

    public function __construct(
        string $uri,
        string $method = 'GET',
        array $query = [],
        array $post = [],
        array $files = [],
        array $server = [],
        array $headers = []
    ) {
        $this->uri = $uri;
        $this->method = strtoupper($method);
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->server = $server;
        $this->headers = $headers;
        $this->host = $server['HTTP_HOST'] ?? '';
    }

    /**
     * 從全域變數建立請求
     */
    public static function capture(): self {
        return new self(
            uri: $_SERVER['REQUEST_URI'] ?? '/',
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            query: $_GET,
            post: $_POST,
            files: $_FILES,
            server: $_SERVER,
            headers: getallheaders() ?: []
        );
    }

    // Getters
    public function getUri(): string {
        return $this->uri;
    }

    public function getPath(): string {
        return parse_url($this->uri, PHP_URL_PATH) ?: '/';
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getHost(): string {
        return $this->host;
    }

    public function getHeader(string $name): ?string {
        return $this->headers[$name] ?? null;
    }

    public function hasHeader(string $name): bool {
        return isset($this->headers[$name]);
    }

    public function query(string $key, $default = null) {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, $default = null) {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, $default = null) {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    // 路由參數（由 Router 設定）
    public function getAttribute(string $key, $default = null) {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, $value): void {
        $this->attributes[$key] = $value;
    }

    public function getAttributes(): array {
        return $this->attributes;
    }
}

// 2. Response 物件
class Response {
    protected $content;
    protected int $status;
    protected array $headers;

    public function __construct(
        $content = '',
        int $status = 200,
        array $headers = []
    ) {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    // Getters
    public function getContent() {
        return $this->content;
    }

    public function getStatus(): int {
        return $this->status;
    }

    public function getHeaders(): array {
        return $this->headers;
    }

    // Setters
    public function setContent($content): self {
        $this->content = $content;
        return $this;
    }

    public function setStatus(int $status): self {
        $this->status = $status;
        return $this;
    }

    public function addHeader(string $name, string $value): self {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * 發送回應到客戶端
     */
    public function send(): void {
        // 設定 HTTP 狀態碼
        http_response_code($this->status);

        // 設定 Headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // 輸出內容
        echo $this->content;
    }

    /**
     * 便捷方法：JSON 回應
     */
    public static function json($data, int $status = 200): self {
        return new self(
            json_encode($data),
            $status,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * 便捷方法：重導向
     */
    public static function redirect(string $url, int $status = 302): self {
        return new self('', $status, ['Location' => $url]);
    }
}

// 3. 更新 Router 使用

class Router implements RouterInterface {
    public function dispatch(Request $request): Response {
        $path = $request->getPath();
        $method = $request->getMethod();
        $host = $request->getHost();

        foreach ($this->routes as $route) {
            if (!in_array($method, $route->getMethods())) {
                continue;
            }

            if ($route->getDomain() && $route->getDomain() !== $host) {
                continue;
            }

            if ($this->matcher->match($route, $path)) {
                // 將路由參數設定到 Request
                $params = $this->matcher->extractParameters($route, $path);
                foreach ($params as $key => $value) {
                    $request->setAttribute($key, $value);
                }

                return $this->dispatcher->dispatch($route, $request);
            }
        }

        return $this->notFound();
    }

    protected function notFound(): Response {
        return new Response('404 - 找不到頁面', 404);
    }
}

// 4. 更新 Facade

class Route {
    public static function dispatch(): void {
        $request = Request::capture();
        $response = self::getInstance()->dispatch($request);
        $response->send();
    }
}

// 5. 控制器中使用

class UserController {
    public function show(Request $request): Response {
        $id = $request->getAttribute('id');
        $user = User::find($id);

        return Response::json([
            'user' => $user
        ]);
    }

    public function store(Request $request): Response {
        $name = $request->post('name');
        $email = $request->post('email');

        $user = User::create(['name' => $name, 'email' => $email]);

        return Response::json([
            'message' => 'User created',
            'user' => $user
        ], 201);
    }
}
```

#### 使用範例

```php
// 路由定義
Route::get('/user/{id}', [UserController::class, 'show']);
Route::post('/user', [UserController::class, 'store']);

// Closure 中使用
Route::get('/hello', function(Request $request): Response {
    $name = $request->query('name', 'Guest');
    return new Response("Hello, {$name}!");
});

// JSON API
Route::get('/api/users', function(Request $request): Response {
    return Response::json([
        'users' => User::all()
    ]);
});

// 重導向
Route::get('/old-page', function(Request $request): Response {
    return Response::redirect('/new-page');
});

// 測試
$request = new Request('/user/123', 'GET');
$response = $router->dispatch($request);
assert($response->getStatus() === 200);
```

#### 優勢

| 優勢 | 說明 |
|:---|:---|
| ✅ **易於測試** | 可以建立模擬請求 |
| ✅ **型別安全** | IDE 自動完成 |
| ✅ **無全域依賴** | 純物件導向 |
| ✅ **可重複使用** | 不污染全域狀態 |
| ✅ **符合標準** | 類似 PSR-7 |

---

### 問題 7：Facade 模式使用不當

#### 現況分析

`Route.php:14-44`：

```php
class Route {
    private static $instance = null;  // ❌ 單例邏輯在 Facade 中

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Router();
        }
        return self::$instance;
    }

    public static function __callStatic($name, $arguments) {
        // ...
    }
}
```

#### 問題

- ❌ **職責混合**：Facade 不應該管理單例
- ❌ **難以測試**：無法替換底層實作
- ❌ **全域狀態**：單例造成測試污染

#### 建議：分離關注點

```php
// 1. 單例管理器（可測試的單例）
class RouterRegistry {
    protected static ?RouterInterface $instance = null;

    /**
     * 取得路由器實例
     */
    public static function getInstance(): RouterInterface {
        if (self::$instance === null) {
            self::$instance = self::createDefaultRouter();
        }
        return self::$instance;
    }

    /**
     * 設定路由器實例（測試時使用）
     */
    public static function setInstance(RouterInterface $router): void {
        self::$instance = $router;
    }

    /**
     * 重置（測試後清理）
     */
    public static function reset(): void {
        self::$instance = null;
    }

    /**
     * 建立預設路由器
     */
    protected static function createDefaultRouter(): RouterInterface {
        $routes = new RouteCollection();

        return new Router(
            matcher: new RegexRouteMatcher($routes),
            dispatcher: new RouteDispatcher(
                new MiddlewarePipeline(),
                new ControllerResolver()
            ),
            urlGenerator: new UrlGenerator($routes)
        );
    }
}

// 2. 純粹的 Facade（不含狀態）
class Route {
    /**
     * 註冊 GET 路由
     */
    public static function get(string $uri, $action): RouteInterface {
        return self::router()->add('GET', $uri, $action);
    }

    /**
     * 註冊 POST 路由
     */
    public static function post(string $uri, $action): RouteInterface {
        return self::router()->add('POST', $uri, $action);
    }

    /**
     * 註冊 PUT 路由
     */
    public static function put(string $uri, $action): RouteInterface {
        return self::router()->add('PUT', $uri, $action);
    }

    /**
     * 註冊 DELETE 路由
     */
    public static function delete(string $uri, $action): RouteInterface {
        return self::router()->add('DELETE', $uri, $action);
    }

    /**
     * 註冊 PATCH 路由
     */
    public static function patch(string $uri, $action): RouteInterface {
        return self::router()->add('PATCH', $uri, $action);
    }

    /**
     * 註冊多種方法的路由
     */
    public static function match(array $methods, string $uri, $action): RouteInterface {
        return self::router()->add($methods, $uri, $action);
    }

    /**
     * 註冊任意方法的路由
     */
    public static function any(string $uri, $action): RouteInterface {
        return self::router()->add('ANY', $uri, $action);
    }

    /**
     * 路由群組 - prefix
     */
    public static function prefix(string $prefix): RouteRegistrar {
        return (new RouteRegistrar(self::router()))->prefix($prefix);
    }

    /**
     * 路由群組 - middleware
     */
    public static function middleware($middleware): RouteRegistrar {
        return (new RouteRegistrar(self::router()))->middleware($middleware);
    }

    /**
     * 路由群組 - name
     */
    public static function name(string $name): RouteRegistrar {
        return (new RouteRegistrar(self::router()))->name($name);
    }

    /**
     * 路由群組 - domain
     */
    public static function domain(string $domain): RouteRegistrar {
        return (new RouteRegistrar(self::router()))->domain($domain);
    }

    /**
     * 分發請求
     */
    public static function dispatch(): void {
        $request = Request::capture();
        $response = self::router()->dispatch($request);
        $response->send();
    }

    /**
     * 取得路由器實例
     */
    protected static function router(): RouterInterface {
        return RouterRegistry::getInstance();
    }

    // ===== 測試輔助方法 =====

    /**
     * 替換路由器（測試時使用）
     */
    public static function swap(RouterInterface $router): void {
        RouterRegistry::setInstance($router);
    }

    /**
     * 重置路由器（測試清理）
     */
    public static function reset(): void {
        RouterRegistry::reset();
    }
}

// 3. 使用範例

// 一般使用（與之前完全相同）
Route::get('/user/{id}', [UserController::class, 'show']);

// 測試時替換實作
class RouteTest extends TestCase {
    protected function setUp(): void {
        Route::reset(); // 清理之前的狀態
    }

    public function testRouteMatching() {
        // 使用 Mock Router
        $mockRouter = $this->createMock(RouterInterface::class);
        $mockRouter->expects($this->once())
            ->method('add')
            ->with('GET', '/test', $this->anything());

        Route::swap($mockRouter);

        Route::get('/test', function() {});
    }

    protected function tearDown(): void {
        Route::reset(); // 測試後清理
    }
}
```

#### 優勢

| 優勢 | 說明 |
|:---|:---|
| ✅ **職責分離** | Facade 只負責轉發，Registry 負責管理實例 |
| ✅ **易於測試** | 可以替換底層實作 |
| ✅ **無狀態 Facade** | Route 類別本身無狀態 |
| ✅ **可重置** | 測試後可以清理狀態 |

---

## 三、建議的完整架構

### 目錄結構

```
src/
├── Contracts/                          # 介面定義
│   ├── RouterInterface.php
│   ├── RouteInterface.php
│   ├── RouteMatcherInterface.php
│   ├── RouteDispatcherInterface.php
│   ├── UrlGeneratorInterface.php
│   ├── MiddlewareInterface.php
│   └── MiddlewarePipelineInterface.php
│
├── Http/                               # HTTP 抽象
│   ├── Request.php
│   └── Response.php
│
├── Routing/                            # 路由核心
│   ├── Router.php                      # 路由管理器
│   ├── RouteCollection.php             # 路由集合
│   ├── RouteItem.php                   # 路由項目
│   ├── RouteRegistrar.php              # 群組註冊器
│   └── RouteGroupStack.php             # 群組堆疊
│
├── Matching/                           # 路由匹配
│   ├── RegexMatcher.php                # 正則匹配
│   ├── ExactMatcher.php                # 精確匹配
│   ├── CompiledMatcher.php             # 編譯匹配
│   └── TrieMatcher.php                 # Trie 樹匹配
│
├── Dispatching/                        # 路由執行
│   ├── RouteDispatcher.php             # 路由分發器
│   └── ControllerResolver.php          # 控制器解析器
│
├── Middleware/                         # 中介軟體
│   ├── MiddlewarePipeline.php          # 中介軟體管道
│   └── CallableMiddleware.php          # Callable 包裝器
│
├── UrlGeneration/                      # URL 生成
│   └── UrlGenerator.php
│
├── Support/                            # 輔助類別
│   ├── Facade/
│   │   ├── Route.php                   # Facade
│   │   └── RouterRegistry.php          # 單例管理
│   └── helpers.php                     # 輔助函式
│
└── Exceptions/                         # 例外
    ├── RouteNotFoundException.php
    ├── MethodNotAllowedException.php
    └── RouterException.php
```

### 依賴關係圖

```
┌─────────────────────────────────────────────────────────────┐
│                     Application Layer                        │
│                    (User Code - routes.php)                  │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                      Facade Layer                            │
│  ┌──────────────┐          ┌─────────────────────┐          │
│  │    Route     │◄─────────┤  RouterRegistry     │          │
│  │   (Facade)   │          │  (Singleton Mgr)    │          │
│  └──────────────┘          └─────────────────────┘          │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                   Core Layer (Contracts)                     │
│  ┌──────────────────────────────────────────────────┐       │
│  │              RouterInterface                      │       │
│  ├──────────────────────────────────────────────────┤       │
│  │  + add(method, uri, action): RouteInterface      │       │
│  │  + dispatch(Request): Response                   │       │
│  │  + group(attributes, callback): void             │       │
│  └──────────────────────────────────────────────────┘       │
│                          ▲                                   │
│                          │ implements                        │
│  ┌───────────────────────┴──────────────────────────┐       │
│  │                    Router                         │       │
│  ├───────────────────────────────────────────────────┤       │
│  │  - routes: RouteCollection                        │       │
│  │  - groupStack: RouteGroupStack                    │       │
│  │  - matcher: RouteMatcherInterface        ◄───────┼───┐   │
│  │  - dispatcher: RouteDispatcherInterface  ◄───────┼─┐ │   │
│  │  - urlGenerator: UrlGeneratorInterface   ◄───────┼┐│ │   │
│  └───────────────────────────────────────────────────┘││ │   │
└────────────────────────────────────────────────────────┼┼─┼───┘
                                                         │││ │
         ┌───────────────────────────────────────────────┘││ │
         │       ┌─────────────────────────────────────────┘│ │
         │       │       ┌──────────────────────────────────┘ │
         ▼       ▼       ▼                                     │
┌─────────────────────────────────────────────────────────────┼┐
│                  Implementation Layer                        ││
│  ┌───────────────────┐  ┌──────────────────┐  ┌────────────┼┤
│  │  RouteMatcher     │  │ RouteDispatcher  │  │ UrlGenerat││││
│  │  Implementations  │  │                  │  │            ││││
│  ├───────────────────┤  ├──────────────────┤  └────────────┼┤
│  │ - RegexMatcher    │  │ - Pipeline       │               ││
│  │ - ExactMatcher    │  │ - Resolver       │               ││
│  │ - CompiledMatcher │  └──────────────────┘               ││
│  └───────────────────┘                                      ││
└────────────────────────────────────────────────────────────┼┘
                                                              │
         ┌────────────────────────────────────────────────────┘
         ▼
┌─────────────────────────────────────────────────────────────┐
│                     Data Layer                               │
│  ┌────────────┐  ┌──────────┐  ┌──────────┐                │
│  │ RouteItem  │  │ Request  │  │ Response │                │
│  └────────────┘  └──────────┘  └──────────┘                │
└─────────────────────────────────────────────────────────────┘
```

---

## 四、重構步驟建議（漸進式）

### Phase 1：基礎重構（不破壞現有功能）

**目標**：加入介面和型別宣告

1. **定義核心介面**
   ```php
   // src/Contracts/RouterInterface.php
   // src/Contracts/RouteInterface.php
   // 等等...
   ```

2. **讓現有類別實作介面**
   ```php
   class Router implements RouterInterface { }
   class RouteItem implements RouteInterface { }
   ```

3. **加入型別宣告**
   ```php
   public function add(string $method, string $uri, $action): RouteInterface
   ```

4. **加入單元測試**

**預期成果**：
- ✅ 現有功能完全不變
- ✅ 加入型別安全
- ✅ 為後續重構奠定基礎

---

### Phase 2：職責分離（保持向後相容）

**目標**：拆分 Router 類別

1. **提取 RouteCollection**
   ```php
   class RouteCollection {
       protected array $routes = [];
       public function add(RouteInterface $route): void { }
       public function all(): array { }
   }
   ```

2. **提取 RouteMatcher**
   ```php
   class RegexRouteMatcher implements RouteMatcherInterface {
       // 從 Router::matchUri() 移過來
   }
   ```

3. **Router 注入依賴**
   ```php
   class Router {
       public function __construct(
           protected RouteCollection $routes,
           protected RouteMatcherInterface $matcher
       ) {}
   }
   ```

4. **保持 Facade 向後相容**
   ```php
   // 使用者程式碼不需要修改
   Route::get('/test', function() {});
   ```

**預期成果**：
- ✅ Router 類別變小（< 150 行）
- ✅ 可以替換 Matcher 實作
- ✅ 使用者程式碼不需修改

---

### Phase 3：引入 Request/Response

**目標**：統一請求回應處理

1. **建立 Request 類別**
   ```php
   class Request {
       public static function capture(): self { }
   }
   ```

2. **建立 Response 類別**
   ```php
   class Response {
       public function send(): void { }
   }
   ```

3. **更新 Router::dispatch()**
   ```php
   public function dispatch(Request $request): Response
   ```

4. **保持向後相容**
   ```php
   // 舊的方式仍然可用
   Route::dispatch();

   // 新的方式
   $request = Request::capture();
   $response = Route::getInstance()->dispatch($request);
   $response->send();
   ```

**預期成果**：
- ✅ 易於測試
- ✅ 型別安全
- ✅ 支援兩種方式

---

### Phase 4：中介軟體管道

**目標**：實作標準的中介軟體系統

1. **定義 MiddlewareInterface**
   ```php
   interface MiddlewareInterface {
       public function handle(Request $request, callable $next): Response;
   }
   ```

2. **實作 MiddlewarePipeline**
   ```php
   class MiddlewarePipeline { }
   ```

3. **更新 RouteDispatcher**
   ```php
   class RouteDispatcher {
       public function __construct(
           protected MiddlewarePipelineInterface $pipeline
       ) {}
   }
   ```

4. **向後相容**
   ```php
   // 舊的中介軟體（自動包裝）
   Route::middleware(function() {
       // 舊格式
   });

   // 新的中介軟體
   Route::middleware(AuthMiddleware::class);
   ```

**預期成果**：
- ✅ 洋蔥模型
- ✅ Before/After 邏輯
- ✅ 向後相容

---

### Phase 5：優化與擴展

**目標**：效能優化和進階功能

1. **路由快取**
   ```php
   class CachedRouteMatcher implements RouteMatcherInterface { }
   ```

2. **編譯路由**
   ```php
   class CompiledMatcher implements RouteMatcherInterface { }
   ```

3. **事件系統**
   ```php
   Route::listen('route.matched', function($route) {});
   ```

**預期成果**：
- ✅ 效能提升
- ✅ 更多擴展點
- ✅ 保持靈活性

---

## 五、設計模式總結

| 模式 | 應用位置 | 目的 | 優先級 |
|:---|:---|:---|:---:|
| **Strategy** | RouteMatcher | 可替換的路由匹配策略 | 🔴 高 |
| **Pipeline** | Middleware | 中介軟體鏈執行（洋蔥模型） | 🔴 高 |
| **Facade** | Route | 簡化 API 介面 | 🟢 已有 |
| **Singleton** | RouterRegistry | 可測試的單例管理 | 🟡 中 |
| **Factory** | ControllerResolver | 解析控制器實例 | 🟡 中 |
| **Builder** | RouteItem | 流暢的路由建構 API | 🟢 已有 |
| **Repository** | RouteCollection | 路由儲存與查詢 | 🔴 高 |
| **Observer** | Event System | 路由事件監聽 | ⚪ 低 |

---

## 六、SOLID 原則檢查

### 目前狀況

| 原則 | 目前 | 問題 | 改善後 |
|:---|:---:|:---|:---:|
| **SRP** 單一職責 | ❌ | Router 承擔 6 個職責 | ✅ |
| **OCP** 開放封閉 | ❌ | 匹配邏輯寫死，無法擴展 | ✅ |
| **LSP** 里氏替換 | ⚠️ | 沒有介面，無從談起 | ✅ |
| **ISP** 介面隔離 | ❌ | 沒有介面定義 | ✅ |
| **DIP** 依賴反轉 | ❌ | 依賴具體實作 | ✅ |

---

## 七、總結

### 核心架構改善建議

1. **拆分 Router 類別**（最重要）
   - 職責分離成：Collection、Matcher、Dispatcher、UrlGenerator
   - 從 228 行拆成 4-5 個 < 100 行的類別

2. **定義介面抽象**（第二重要）
   - 所有核心類別都應該有對應介面
   - 依賴介面而非具體實作

3. **引入 Request/Response**（提升可測試性）
   - 統一請求回應處理
   - 消除全域依賴

4. **實作中介軟體管道**（提升靈活性）
   - 標準的洋蔥模型
   - Before/After 邏輯

5. **使用策略模式**（提升可擴展性）
   - 可替換的 Matcher
   - 可替換的 Dispatcher

### 優先級排序

#### 🔴 最高優先級（影響最大）
1. 加入介面定義
2. 拆分 Router 類別
3. 提取 RouteMatcher

#### 🟡 中優先級（重要但不緊急）
4. 引入 Request/Response
5. 實作 MiddlewarePipeline
6. 改善 RouteItem 封裝

#### 🟢 低優先級（錦上添花）
7. 路由快取
8. 事件系統
9. 編譯路由

### 預期收益

實施上述架構改善後，預期可以達到：

| 指標 | 改善前 | 改善後 |
|:---|:---:|:---:|
| **可測試性** | 3/10 | 9/10 |
| **可維護性** | 5/10 | 9/10 |
| **可擴展性** | 4/10 | 9/10 |
| **程式碼品質** | 6/10 | 9/10 |
| **SOLID 符合度** | 2/10 | 9/10 |

---

**分析完成**

架構改善建議完全不涉及安全性，純粹從軟體工程角度出發，目標是打造一個：
- ✅ 易於測試
- ✅ 易於維護
- ✅ 易於擴展
- ✅ 符合最佳實踐

的現代化 PHP 路由套件。
