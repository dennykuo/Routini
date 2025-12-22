<?php

namespace Routini;

use Routini\Contracts\UrlGeneratorInterface;
use Routini\Contracts\RouteCollectionInterface;

/**
 * URL 生成器
 *
 * 負責根據路由名稱生成 URL
 */
class UrlGenerator implements UrlGeneratorInterface
{
    /**
     * 建構函式
     *
     * @param RouteCollectionInterface $routes 路由集合
     */
    public function __construct(
        protected RouteCollectionInterface $routes
    ) {}

    /**
     * 根據路由名稱生成 URL
     *
     * @param string $name 路由名稱
     * @param array $parameters 參數 ['id' => 1, 'tab' => 'settings']
     * @return string 生成的 URL
     * @throws \Exception 當路由不存在時
     */
    public function generate(string $name, array $parameters = []): string
    {
        // 1. 尋找對應名稱的路由
        $route = $this->routes->findByName($name);

        if (!$route) {
            throw new \Exception("Route [{$name}] not defined.");
        }

        // 2. 編譯 URI（替換參數）
        return $this->compileUri($route->getUri(), $parameters);
    }

    /**
     * 編譯 URI，替換路由參數並處理 Query String
     *
     * @param string $uri 路由 URI 模板（如 /user/{id}/profile）
     * @param array $parameters 參數陣列
     * @return string 編譯後的 URI
     */
    protected function compileUri(string $uri, array $parameters): string
    {
        // 複製參數陣列，避免修改原始陣列
        $params = $parameters;

        // 替換 URI 中的參數
        foreach ($params as $key => $value) {
            $replaced = false;

            // 檢查是否有必填參數 {key}
            if (strpos($uri, '{' . $key . '}') !== false) {
                $uri = str_replace('{' . $key . '}', (string)$value, $uri);
                $replaced = true;
            }

            // 檢查是否有選填參數 {key?}
            if (strpos($uri, '{' . $key . '?}') !== false) {
                $uri = str_replace('{' . $key . '?}', (string)$value, $uri);
                $replaced = true;
            }

            // 如果參數已被使用，從陣列中移除
            if ($replaced) {
                unset($params[$key]);
            }
        }

        // 清理未替換的選填參數（例如 /user/{id?} -> /user）
        $uri = preg_replace('/\/\{[a-zA-Z0-9_]+\?\}/', '', $uri);

        // 剩餘參數轉為 Query String
        if (!empty($params)) {
            $uri .= '?' . http_build_query($params);
        }

        return $uri;
    }

    /**
     * 檢查路由名稱是否存在
     *
     * @param string $name 路由名稱
     * @return bool
     */
    public function hasRoute(string $name): bool
    {
        return $this->routes->hasRoute($name);
    }

    /**
     * 取得路由集合（用於測試或擴展）
     *
     * @return RouteCollectionInterface
     */
    public function getRoutes(): RouteCollectionInterface
    {
        return $this->routes;
    }
}
