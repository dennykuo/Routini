<?php

namespace Routini\Contracts;

use Routini\RouteItem;

/**
 * 路由匹配器介面
 *
 * 定義路由匹配的策略，允許使用不同的匹配演算法
 */
interface RouteMatcherInterface
{
    /**
     * 檢查路由是否匹配給定的 URI
     *
     * @param RouteItem $route 路由項目
     * @param string $uri 請求 URI
     * @return bool 是否匹配
     */
    public function match(RouteItem $route, string $uri): bool;

    /**
     * 從 URI 中提取路由參數
     *
     * @param RouteItem $route 路由項目
     * @param string $uri 請求 URI
     * @return array 參數陣列 ['param' => 'value']
     */
    public function extractParameters(RouteItem $route, string $uri): array;
}
