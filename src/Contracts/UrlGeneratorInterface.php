<?php

namespace Routini\Contracts;

/**
 * URL 生成器介面
 *
 * 定義 URL 生成器的標準契約
 */
interface UrlGeneratorInterface
{
    /**
     * 根據路由名稱生成 URL
     *
     * @param string $name 路由名稱
     * @param array $parameters 參數陣列（路由參數 + Query String）
     * @return string 生成的 URL
     * @throws \Exception 當路由不存在時
     */
    public function generate(string $name, array $parameters = []): string;

    /**
     * 檢查路由名稱是否存在
     *
     * @param string $name 路由名稱
     * @return bool
     */
    public function hasRoute(string $name): bool;
}
