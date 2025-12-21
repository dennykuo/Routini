<?php

namespace Routini\Matching;

use Routini\Contracts\RouteMatcherInterface;
use Routini\RouteItem;

/**
 * 正則表達式路由匹配器
 *
 * 使用正則表達式匹配路由模式，支援：
 * - 必填參數：{id}
 * - 選填參數：{id?}
 */
class RegexMatcher implements RouteMatcherInterface
{
    /**
     * 檢查路由是否匹配給定的 URI
     */
    public function match(RouteItem $route, string $uri): bool
    {
        $pattern = $this->compilePattern($route->uri);
        return (bool) preg_match($pattern, $uri);
    }

    /**
     * 從 URI 中提取路由參數
     */
    public function extractParameters(RouteItem $route, string $uri): array
    {
        $pattern = $this->compilePattern($route->uri);

        if (preg_match($pattern, $uri, $matches)) {
            // 只保留具名參數（字串鍵）
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return [];
    }

    /**
     * 將路由 URI 編譯成正則表達式模式
     *
     * @param string $uri 路由 URI（如：/user/{id}/posts/{slug?}）
     * @return string 正則表達式模式
     */
    protected function compilePattern(string $uri): string
    {
        // 1. 處理選填參數 {param?}
        // 將 /{param?} 轉換為 (?:/(?P<param>[^/]+))?
        $pattern = preg_replace(
            '/\/{([a-zA-Z0-9_]+)\?\}/',
            '(?:/(?P<\1>[^/]+))?',
            $uri
        );

        // 2. 處理必填參數 {param}
        // 將 {param} 轉換為 (?P<param>[^/]+)
        $pattern = preg_replace(
            '/\{([a-zA-Z0-9_]+)\}/',
            '(?P<\1>[^/]+)',
            $pattern
        );

        // 3. 加上開始和結束錨點
        return "~^{$pattern}$~";
    }
}
