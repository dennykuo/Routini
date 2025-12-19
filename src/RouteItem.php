<?php

namespace Routini;

class RouteItem
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
     * 設定路由名稱
     * 用法: ->name('user.profile')
     */
    public function name($name)
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
    public function middleware($middlewares)
    {
        if (is_array($middlewares)) {
            $this->middlewares = array_merge($this->middlewares, $middlewares);
        } else {
            $this->middlewares[] = $middlewares;
        }
        return $this;
    }
}