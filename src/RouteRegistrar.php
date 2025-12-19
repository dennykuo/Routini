<?php

namespace Routini;

class RouteRegistrar
{
    protected $router;
    protected $attributes = [];

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * 設定前綴
     * 用法: Route::prefix('admin')
     */
    public function prefix($prefix)
    {
        $this->attributes['prefix'] = $prefix;
        return $this;
    }

    /**
     * 設定中間件
     * 用法: Route::middleware(['auth'])
     */
    public function middleware($middleware)
    {
        $this->attributes['middleware'] = $middleware;
        return $this;
    }

    /**
     * 設定名稱前綴
     * 用法: Route::name('admin.')
     */
    public function name($name)
    {
        $this->attributes['name'] = $name;
        return $this;
    }

    /**
     * 設定網域限制
     * 用法: Route::domain('api.example.com')
     */
    public function domain($domain)
    {
        $this->attributes['domain'] = $domain;
        return $this;
    }

    /**
     * 設定群組並執行 callback
     * 用法: ->group(function() { ... })
     */
    public function group($callback)
    {
        $this->router->group($this->attributes, $callback);
    }
}