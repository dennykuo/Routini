# Coding Style Guide (撰寫風格指南)

## 概覽 (Overview)
本專案遵循現代 PHP 標準，並採用簡化的 Laravel 風格架構。

## 標準 (Standards)
- **PHP Version**: 8.0+
- **Style**: 必須遵守 [PSR-12](https://www.php-fig.org/psr/psr-12/) 規範。
- **Indentation (縮排)**: 4 個空白 (spaces)。
- **Line Ending (換行)**: LF。

## 命名慣例 (Naming Conventions)
- **Classes (類別)**: PascalCase (`RouteItem`, `Router`).
- **Methods (方法)**: camelCase (`matchUri`, `dispatch`).
- **Variables (變數)**: camelCase (`$requestUri`, `$instance`).
- **Constants (常數)**: UPPER_SNAKE_CASE.

## 專案特定模式 (Project Specific Patterns)

### Facade Pattern (外觀模式)
我們使用透過 `__callStatic` 實作的簡化版 Facade 模式。
- **Target**: `Routini\Route` 是靜態入口點。
- **Implementation**: 它將靜態呼叫委派給 `Routini\Router` 的 Singleton 實例，或建立 `RouteRegistrar` 進行鏈式呼叫 (Chaining)。

### Chaining (鏈式呼叫)
設定路由或群組的方法（如 `middleware`, `prefix`, `name`）應回傳 `$this` 以允許 Method Chaining。

### Arrays (陣列)
請使用簡短陣列語法 `[]` 而非 `array()`。

## 文件與註解 (Documentation)
- 類別與複雜的方法請使用 PHPDoc 區塊。
- 在可能的情況下，完整註解程式碼。
- 在可能的情況下對方法參數和回傳類型使用 Type Hinting。

## 類型宣告 (Type Declarations)
- 優先使用嚴格型別宣告 `declare(strict_types=1);`
- 所有方法參數應有型別提示
- 所有方法應有回傳型別宣告

## 介面與抽象 (Interfaces & Abstractions)
- 核心組件應實作對應的介面（例如：`RouteInterface`, `MiddlewareInterface`）
- 使用依賴注入（Dependency Injection）而非直接實例化
- 遵循依賴反轉原則（Dependency Inversion Principle）

## 安全性最佳實踐 (Security Best Practices)
- 避免使用全域變數
- 驗證所有輸入資料
- 使用參數化查詢防止 SQL 注入
- 防範 XSS、CSRF、開放重定向等常見攻擊
- 參考 `/docs/SECURITY.md` 了解專案特定的安全措施

## Request/Response 處理
- 使用 `Request` 和 `Response` 物件而非直接存取 `$_SERVER` 和 `echo`
- `Request` 物件封裝所有請求資訊
- `Response` 物件提供型別安全的回應建立方法

## 中介軟體設計
- 實作 `MiddlewareInterface`
- 遵循洋蔥模式（Onion Pattern）
- 支援 Before 和 After 邏輯

## 測試要求 (Testing Requirements)
- 所有新功能必須有對應的測試
- 使用 Pest 框架撰寫測試
- 測試覆蓋率目標：100% 核心功能
- 執行測試：`vendor/bin/pest`

---

**最後更新**: 2025-12-22
