<?php

namespace Routini\Contracts;

/**
 * 路由集合介面
 *
 * 定義路由集合的標準契約
 */
interface RouteCollectionInterface
{
    /**
     * 新增路由到集合
     *
     * @param RouteInterface $route 路由項目
     * @return void
     */
    public function add(RouteInterface $route): void;

    /**
     * 取得所有路由
     *
     * @return RouteInterface[]
     */
    public function all(): array;

    /**
     * 根據名稱查找路由
     *
     * @param string $name 路由名稱
     * @return RouteInterface|null
     */
    public function findByName(string $name): ?RouteInterface;

    /**
     * 取得路由總數
     *
     * @return int
     */
    public function count(): int;

    /**
     * 檢查集合是否為空
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * 檢查是否存在指定名稱的路由
     *
     * @param string $name 路由名稱
     * @return bool
     */
    public function hasRoute(string $name): bool;

    /**
     * 清空所有路由
     *
     * @return void
     */
    public function clear(): void;

    /**
     * 取得所有已命名的路由
     *
     * @return array<string, RouteInterface>
     */
    public function getNamedRoutes(): array;
}
