<?php

namespace Routini;

use Routini\Contracts\RouteInterface;

class RouteItem implements RouteInterface
{
    /**
     * 使用建構子屬性提升 (PHP 8.0+) 和私有屬性以提升封裝性
     */
    public function __construct(
        private array $methods,
        private string $uri,
        private $action,
        private ?string $name = null,
        private ?string $domain = null,
        private array $middlewares = [],
        private array $parameters = [],
        private string $groupPrefix = ''
    ) {
        $this->methods = (array) $methods;
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
     * 設定網域限制
     */
    public function setDomain(?string $domain): void
    {
        $this->domain = $domain;
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

    /**
     * 魔術方法：向後相容 - 允許讀取屬性
     *
     * @deprecated 直接存取屬性已被棄用，請使用 getter 方法
     * @param string $name 屬性名稱
     * @return mixed
     */
    public function __get(string $name)
    {
        return match($name) {
            'methods' => $this->methods,
            'uri' => $this->uri,
            'action' => $this->action,
            'name' => $this->name,
            'domain' => $this->domain,
            'middlewares' => $this->middlewares,
            'parameters' => $this->parameters,
            default => throw new \Exception("Property {$name} does not exist on RouteItem")
        };
    }

    /**
     * 魔術方法：向後相容 - 允許設定屬性（但發出警告）
     *
     * @deprecated 直接設定屬性已被棄用，請使用 setter 方法
     * @param string $name 屬性名稱
     * @param mixed $value 屬性值
     */
    public function __set(string $name, $value): void
    {
        trigger_error(
            "Direct property access to RouteItem::\${$name} is deprecated. Use getter/setter methods instead.",
            E_USER_DEPRECATED
        );

        match($name) {
            'methods' => $this->methods = (array) $value,
            'uri' => $this->uri = $value,
            'action' => $this->action = $value,
            'name' => $this->name = $value,
            'domain' => $this->domain = $value,
            'middlewares' => $this->middlewares = (array) $value,
            'parameters' => $this->parameters = (array) $value,
            default => throw new \Exception("Property {$name} does not exist on RouteItem")
        };
    }

    /**
     * 魔術方法：檢查屬性是否存在
     *
     * @param string $name 屬性名稱
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return in_array($name, [
            'methods', 'uri', 'action', 'name',
            'domain', 'middlewares', 'parameters'
        ]);
    }
}
