<?php

namespace Routini\Contracts;

/**
 * 路由項目介面
 *
 * 定義路由項目的標準契約
 */
interface RouteInterface
{
    /**
     * 取得 HTTP 方法陣列
     *
     * @return array 如 ['GET', 'POST']
     */
    public function getMethods(): array;

    /**
     * 取得路由 URI 模板
     *
     * @return string 如 '/user/{id}'
     */
    public function getUri(): string;

    /**
     * 取得路由動作（callable 或 controller@method）
     *
     * @return mixed
     */
    public function getAction();

    /**
     * 取得路由名稱
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * 取得中介軟體陣列
     *
     * @return array
     */
    public function getMiddlewares(): array;

    /**
     * 取得路由參數
     *
     * @return array
     */
    public function getParameters(): array;

    /**
     * 取得網域限制
     *
     * @return string|null
     */
    public function getDomain(): ?string;

    /**
     * 設定路由參數
     *
     * @param array $parameters 參數陣列
     * @return void
     */
    public function setParameters(array $parameters): void;

    /**
     * 設定路由名稱（Fluent API）
     *
     * @param string $name 路由名稱
     * @return self
     */
    public function name(string $name): self;

    /**
     * 新增中介軟體（Fluent API）
     *
     * @param mixed $middleware 中介軟體
     * @return self
     */
    public function middleware($middleware): self;
}
