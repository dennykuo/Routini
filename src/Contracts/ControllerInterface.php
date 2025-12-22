<?php

namespace Routini\Contracts;

/**
 * 控制器介面
 *
 * 所有控制器類別應實作此介面，以確保安全性和一致性
 *
 * 用途：
 * - 提供控制器的標準契約
 * - 用於控制器白名單驗證（防止任意類別實例化）
 * - 確保只有合法的控制器類別能被路由系統調用
 *
 * @package Routini\Contracts
 */
interface ControllerInterface
{
    // 這是一個標記介面（Marker Interface）
    // 控制器類別只需實作此介面，無需實作任何方法
    // 用於型別檢查和安全驗證
}
