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

        // 1. 如果呼叫的是 prefix 或 middleware，回傳 RouteRegistrar (為了之後接 ->group)
        if (in_array($name, ['prefix', 'middleware', 'name', 'domain'])) {
            $registrar = new RouteRegistrar($router);
            return $registrar->$name(...$arguments);
        }

        // 2. 如果是 get, post, put, delete，直接操作 Router
        return $router->add(strtoupper($name), $arguments[0], $arguments[1]);
    }

    public static function dispatch()
    {
        return self::getInstance()->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
    }
}