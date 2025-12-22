<?php

namespace Routini;

use Routini\Contracts\RouteInterface;

class RouteItem implements RouteInterface
{
    public $methods = [];
    public $uri;
    public $action;
    public $name = null;
    public $domain = null;
    public $middlewares = [];
    public $parameters = [];
    protected $groupPrefix = '';

    public function __construct($methods, $uri, $action)
    {
        $this->methods = (array) $methods;
        $this->uri = $uri;
        $this->action = $action;
    }

    /**
     * 取得 HTTP 方法陣列
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * 取得路由 URI
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * 取得路由動作
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * 取得路由名稱
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * 取得中介軟體陣列
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * 取得路由參數
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * 取得網域限制
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * 設定路由參數
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * 設定路由名稱
     * 用法: ->name('user.profile')
     */
    public function name(string $name): RouteInterface
    {
        $this->name = $this->groupPrefix . $name;
        return $this;
    }

    public function setGroupPrefix($prefix)
    {
        $this->groupPrefix = $prefix;
        return $this;
    }

    /**
     * 設定中間件
     * 用法: ->middleware([AuthMiddleware::class])
     */
    public function middleware($middleware): RouteInterface
    {
        if (is_array($middleware)) {
            $this->middlewares = array_merge($this->middlewares, $middleware);
        } else {
            $this->middlewares[] = $middleware;
        }
        return $this;
    }
}