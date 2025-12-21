<?php

namespace Routini;

/**
 * @method static RouteItem get(string $uri, callable|array $action)
 * @method static RouteItem post(string $uri, callable|array $action)
 * @method static RouteItem any(string $uri, callable|array $action)
 * @method static RouteItem match(array|string $methods, string $uri, callable|array $action)
 * @method static RouteRegistrar prefix(string $prefix)
 * @method static RouteRegistrar middleware(array|string $middleware)
 * @method static RouteRegistrar domain(string $domain)
 */
class Route
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Router();
        }
        return self::$instance;
    }

    public static function __callStatic($name, $arguments)
    {
        $router = self::getInstance();

        // 1. 如果呼叫的是 prefix、middleware、name 或 domain，回傳 RouteRegistrar (為了之後接 ->group)
        if (in_array($name, ['prefix', 'middleware', 'name', 'domain'])) {
            $registrar = new RouteRegistrar($router);
            return $registrar->$name(...$arguments);
        }

        // 2. 如果是 get, post, put, delete, any 等 HTTP 方法
        // 或是 match 行為
        // 則直接操作 Router
        return $router->add(strtoupper($name), $arguments[0], $arguments[1]);
    }

    public static function dispatch()
    {
        return self::getInstance()->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
    }

    /**
     * 重置路由器實例（主要用於測試）
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}