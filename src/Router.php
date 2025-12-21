<?php

namespace Routini;

use Routini\Http\Request;
use Routini\Http\Response;
use Routini\Contracts\RouteMatcherInterface;
use Routini\Matching\RegexMatcher;

class Router
{
    protected $routes = [];

    // 用來儲存當前群組設定的堆疊 (支援巢狀群組)
    protected $groupStack = [];

    // 路由匹配器
    protected RouteMatcherInterface $matcher;

    /**
     * 建構函式
     *
     * @param RouteMatcherInterface|null $matcher 路由匹配器（可選，預設使用 RegexMatcher）
     */
    public function __construct(?RouteMatcherInterface $matcher = null)
    {
        $this->matcher = $matcher ?? new RegexMatcher();
    }

    /**
     * 處理群組邏輯
     */
    public function group(array $attributes, callable $routes)
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
    public function add($method, $uri, $action)
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

        $this->routes[] = $route;
        return $route;
    }

    /**
     * 計算當前堆疊中的所有屬性 (合併巢狀群組)
     */
    protected function mergeGroupAttributes()
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

        foreach ($this->routes as $route) {
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
    public function url($name, $parameters = [])
    {
        // 1. 尋找對應名稱的路由
        $route = $this->findRouteByName($name);

        if (!$route) {
            throw new \Exception("Route [{$name}] not defined.");
        }

        // 2. 替換 URI 中的參數
        $uri = $route->uri;

        foreach ($parameters as $key => $value) {
            // 檢查 URI 中是否有 {key}
            if (strpos($uri, '{' . $key . '}') !== false || strpos($uri, '{' . $key . '?}') !== false) {
                // 替換 {key} 為實際值
                $uri = str_replace('{' . $key . '}', $value, $uri);
                // 處理選填參數的情況 {key?}
                $uri = str_replace('{' . $key . '?}', $value, $uri);

                // 用過的參數從陣列中移除，剩下的要變成 Query String
                unset($parameters[$key]);
            }
        }

        // 清理未替換的選填參數 (例如 /user/{id?} -> /user)
        $uri = preg_replace('/\/\{[a-zA-Z0-9_]+\?\}/', '', $uri);

        // 3. 處理剩餘參數變成 Query String (例如 ?sort=desc)
        if (!empty($parameters)) {
            $uri .= '?' . http_build_query($parameters);
        }

        return $uri;
    }

    /**
     * [新增] 輔助方法：依名稱搜尋路由
     */
    protected function findRouteByName($name)
    {
        foreach ($this->routes as $route) {
            if ($route->name === $name) {
                return $route;
            }
        }
        return null;
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
        // 執行中介軟體（舊版簡化方式）
        foreach ($route->middlewares as $middleware) {
            if (is_callable($middleware)) {
                if ($middleware() === false) {
                    // 中介軟體已自行處理輸出，返回空回應
                    return new Response('', 403);
                }
            } elseif (class_exists($middleware)) {
                $instance = new $middleware();
                if (method_exists($instance, 'handle')) {
                    if ($instance->handle() === false) {
                        // 中介軟體已自行處理輸出，返回空回應
                        return new Response('', 403);
                    }
                }
            }
        }

        // 執行路由動作
        $result = null;

        // 將關聯陣列參數轉換為索引陣列，避免 PHP 8+ 具名參數問題
        $params = array_values($route->parameters);

        if (is_callable($route->action)) {
            $result = call_user_func_array($route->action, $params);
        } elseif (is_array($route->action)) {
            [$controller, $method] = $route->action;
            $instance = new $controller();
            $result = call_user_func_array([$instance, $method], $params);
        }

        // 將結果轉換為 Response
        return $this->toResponse($result);
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
    protected function sendNotFound()
    {
        $this->notFoundResponse()->send();
    }
}