<?php

namespace Routini;

use Routini\Http\Request;
use Routini\Http\Response;
use Routini\Contracts\RouteMatcherInterface;
use Routini\Matching\RegexMatcher;
use Routini\Middleware\MiddlewarePipeline;

class Router
{
    // 路由集合（負責儲存和管理路由）
    protected RouteCollection $routes;

    // 用來儲存當前群組設定的堆疊 (支援巢狀群組)
    protected $groupStack = [];

    // 路由匹配器
    protected RouteMatcherInterface $matcher;

    // URL 生成器
    protected UrlGenerator $urlGenerator;

    // 全域中介軟體陣列
    protected array $globalMiddlewares = [];

    /**
     * 建構函式
     *
     * @param RouteMatcherInterface|null $matcher 路由匹配器（可選，預設使用 RegexMatcher）
     * @param RouteCollection|null $routes 路由集合（可選，預設建立新集合）
     * @param UrlGenerator|null $urlGenerator URL 生成器（可選，預設建立新生成器）
     */
    public function __construct(
        ?RouteMatcherInterface $matcher = null,
        ?RouteCollection $routes = null,
        ?UrlGenerator $urlGenerator = null
    ) {
        $this->matcher = $matcher ?? new RegexMatcher();
        $this->routes = $routes ?? new RouteCollection();
        $this->urlGenerator = $urlGenerator ?? new UrlGenerator($this->routes);
    }

    /**
     * 註冊全域中介軟體
     *
     * @param mixed $middleware 中介軟體（MiddlewareInterface、類別名稱或 callable）
     * @return $this
     */
    public function middleware($middleware): self
    {
        $this->globalMiddlewares[] = $middleware;
        return $this;
    }

    /**
     * 處理群組邏輯
     */
    public function group(array $attributes, callable $routes): void
    {
        // 1. 將目前的屬性推入堆疊
        $this->groupStack[] = $attributes;

        // 2. 執行閉包 (這時候裡面定義的路由會抓到 stack 裡的設定)
        call_user_func($routes, $this);

        // 3. 執行完畢，將屬性彈出堆疊 (恢復上一層狀態)
        array_pop($this->groupStack);
    }

    /**
     * 加入路由至集合 (已修改以支援群組)
     */
    public function add($method, $uri, $action): RouteItem
    {
        // 1. 取得目前所有的群組屬性 (Prefix, Middleware)
        $attributes = $this->mergeGroupAttributes();

        // 2. 合併 Prefix
        // 如果有前綴，拼接到 URI 前面
        if (isset($attributes['prefix']) && $attributes['prefix'] !== '') {
            $uri = rtrim($attributes['prefix'], '/') . '/' . ltrim($uri, '/');
        }

        // 3. 處理 HTTP Methods
        if ($method === 'ANY') {
            $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        } else {
            $methods = (array) $method;
        }

        // 4. 建立路由物件
        $route = new RouteItem($methods, $uri, $action);

        // [新增] 設定名稱前綴
        if (isset($attributes['name'])) {
            $route->setGroupPrefix($attributes['name']);
        }

        // 4. 合併 Middleware
        if (isset($attributes['middleware'])) {
            $route->middleware($attributes['middleware']);
        }

        // [新增] 設定網域
        if (isset($attributes['domain'])) {
            $route->domain = $attributes['domain'];
        }

        $this->routes->add($route);
        return $route;
    }

    /**
     * 計算當前堆疊中的所有屬性 (合併巢狀群組)
     */
    protected function mergeGroupAttributes(): array
    {
        $final = ['prefix' => '', 'middleware' => [], 'name' => ''];

        foreach ($this->groupStack as $group) {
            // 合併前綴
            if (isset($group['prefix'])) {
                $final['prefix'] .= '/' . trim($group['prefix'], '/');
            }

            // 合併中間件
            if (isset($group['middleware'])) {
                $middleware = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $final['middleware'] = array_merge($final['middleware'], $middleware);
            }

            // 合併名稱前綴
            if (isset($group['name'])) {
                $final['name'] .= $group['name'];
            }

            // 合併網域 (後蓋前)
            if (isset($group['domain'])) {
                $final['domain'] = $group['domain'];
            }
        }

        return $final;
    }

    // --- 以下為之前的核心邏輯 (已更新支援 Request/Response) ---

    /**
     * 分發請求到對應的路由
     *
     * 支援兩種用法：
     * 1. 新版：dispatch(Request $request): Response
     * 2. 舊版（向後相容）：dispatch($requestUri, $requestMethod, $requestHost = null)
     */
    public function dispatch($requestUri, $requestMethod = null, $requestHost = null)
    {
        // 檢查第一個參數是否為 Request 物件（新版用法）
        if ($requestUri instanceof Request) {
            return $this->dispatchRequest($requestUri);
        }

        // 舊版用法（向後相容）
        $request = new Request(
            uri: $requestUri,
            method: $requestMethod ?? 'GET',
            server: ['HTTP_HOST' => $requestHost ?? ($_SERVER['HTTP_HOST'] ?? '')]
        );

        $response = $this->dispatchRequest($request);

        // 舊版用法直接發送回應
        $response->send();
    }

    /**
     * 使用 Request 物件分發請求（內部方法）
     */
    protected function dispatchRequest(Request $request): Response
    {
        $requestPath = $request->getPath();
        $requestMethod = $request->getMethod();
        $requestHost = $request->getHost();

        foreach ($this->routes->all() as $route) {
            if (!in_array($requestMethod, $route->methods)) {
                continue;
            }

            // 檢查網域限制
            if ($route->domain && $route->domain !== $requestHost) {
                continue;
            }

            if ($this->matchUri($route, $requestPath)) {
                // 將路由參數設定到 Request
                $request->setAttributes($route->parameters);

                return $this->runRoute($route, $request);
            }
        }

        return $this->notFoundResponse();
    }

    /**
     * [新增] 根據名稱產生網址
     * @param string $name 路由名稱
     * @param array $parameters 參數 ['id' => 1]
     * @return string
     */
    public function url($name, $parameters = []): string
    {
        return $this->urlGenerator->generate($name, $parameters);
    }

    /**
     * 取得 URL 生成器
     *
     * @return UrlGenerator
     */
    public function getUrlGenerator(): UrlGenerator
    {
        return $this->urlGenerator;
    }

    /**
     * [新增] 輔助方法：依名稱搜尋路由
     */
    protected function findRouteByName($name)
    {
        return $this->routes->findByName($name);
    }

    /**
     * 檢查路由是否匹配給定的 URI（使用策略模式）
     *
     * @param RouteItem $route 路由項目
     * @param string $requestUri 請求 URI
     * @return bool 是否匹配
     */
    protected function matchUri(RouteItem $route, $requestUri): bool
    {
        // 使用注入的 matcher 策略進行匹配
        if ($this->matcher->match($route, $requestUri)) {
            // 提取參數並設定到路由項目
            $route->parameters = $this->matcher->extractParameters($route, $requestUri);
            return true;
        }

        return false;
    }

    /**
     * 設定路由匹配器
     *
     * @param RouteMatcherInterface $matcher 路由匹配器
     * @return void
     */
    public function setMatcher(RouteMatcherInterface $matcher): void
    {
        $this->matcher = $matcher;
    }

    /**
     * 取得當前使用的路由匹配器
     *
     * @return RouteMatcherInterface
     */
    public function getMatcher(): RouteMatcherInterface
    {
        return $this->matcher;
    }

    /**
     * 執行路由動作
     */
    protected function runRoute(RouteItem $route, Request $request): Response
    {
        // 建立中介軟體管道
        $pipeline = new MiddlewarePipeline();

        // 1. 先加入全域中介軟體
        foreach ($this->globalMiddlewares as $middleware) {
            $pipeline->pipe($middleware);
        }

        // 2. 再加入路由特定的中介軟體
        foreach ($route->middlewares as $middleware) {
            $pipeline->pipe($middleware);
        }

        // 3. 定義最終的路由處理器（管道的核心）
        $destination = function (Request $request) use ($route) {
            // 將關聯陣列參數轉換為索引陣列，避免 PHP 8+ 具名參數問題
            $params = array_values($route->parameters);

            $result = null;

            if (is_callable($route->action)) {
                $result = call_user_func_array($route->action, $params);
            } elseif (is_array($route->action)) {
                [$controller, $method] = $route->action;
                $instance = new $controller();
                $result = call_user_func_array([$instance, $method], $params);
            }

            // 將結果轉換為 Response
            return $this->toResponse($result);
        };

        // 4. 執行管道（洋蔥模式）
        return $pipeline->process($request, $destination);
    }

    /**
     * 將各種類型的結果轉換為 Response 物件
     */
    protected function toResponse($result): Response
    {
        // 已經是 Response 物件
        if ($result instanceof Response) {
            return $result;
        }

        // 陣列轉 JSON
        if (is_array($result)) {
            return Response::json($result);
        }

        // 其他類型轉字串
        return new Response((string) $result);
    }

    /**
     * 建立 404 回應
     */
    protected function notFoundResponse(): Response
    {
        return Response::notFound('404 - 找不到頁面');
    }

    /**
     * 舊版方法（向後相容）
     * @deprecated 請使用 notFoundResponse()
     */
    protected function sendNotFound(): void
    {
        $this->notFoundResponse()->send();
    }
}