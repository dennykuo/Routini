<?php

namespace Routini;

use Routini\Contracts\RouteCollectionInterface;
use Routini\Contracts\RouteInterface;

/**
 * 路由集合
 *
 * 負責儲存和管理所有路由項目
 */
class RouteCollection implements RouteCollectionInterface
{
    /**
     * 所有路由項目
     *
     * @var RouteInterface[]
     */
    protected array $routes = [];

    /**
     * 路由名稱對應表（用於快速查找）
     *
     * @var array<string, RouteInterface>
     */
    protected array $nameMap = [];

    /**
     * 新增路由到集合
     *
     * @param RouteInterface $route 路由項目
     * @return void
     */
    public function add(RouteInterface $route): void
    {
        $this->routes[] = $route;

        // 如果路由有名稱，加入名稱對應表
        if ($route->getName() !== null) {
            $this->nameMap[$route->getName()] = $route;
        }
    }

    /**
     * 取得所有路由
     *
     * @return RouteItem[]
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * 根據名稱查找路由
     *
     * 優先使用名稱對應表查找（O(1)）
     * 如果找不到，遍歷所有路由查找（處理路由加入後才設定名稱的情況）
     *
     * @param string $name 路由名稱
     * @return RouteInterface|null
     */
    public function findByName(string $name): ?RouteInterface
    {
        // 優先從名稱對應表查找
        if (isset($this->nameMap[$name])) {
            return $this->nameMap[$name];
        }

        // 遍歷所有路由查找（向後相容：處理加入後才命名的路由）
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                // 找到後加入對應表，下次查找更快
                $this->nameMap[$name] = $route;
                return $route;
            }
        }

        return null;
    }

    /**
     * 取得路由總數
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * 檢查集合是否為空
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->routes);
    }

    /**
     * 清空所有路由
     *
     * @return void
     */
    public function clear(): void
    {
        $this->routes = [];
        $this->nameMap = [];
    }

    /**
     * 取得所有已命名的路由
     *
     * @return array<string, RouteItem>
     */
    public function getNamedRoutes(): array
    {
        return $this->nameMap;
    }

    /**
     * 檢查是否存在指定名稱的路由
     *
     * @param string $name 路由名稱
     * @return bool
     */
    public function hasRoute(string $name): bool
    {
        return isset($this->nameMap[$name]);
    }
}
