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
- **Target**: `Routinil\Route` 是靜態入口點。
- **Implementation**: 它將靜態呼叫委派給 `Routinil\Router` 的 Singleton 實例，或建立 `RouteRegistrar` 進行鏈式呼叫 (Chaining)。

### Chaining (鏈式呼叫)
設定路由或群組的方法（如 `middleware`, `prefix`, `name`）應回傳 `$this` 以允許 Method Chaining。

### Arrays (陣列)
請使用簡短陣列語法 `[]` 而非 `array()`。

## 文件與註解 (Documentation)
- 類別與複雜的方法請使用 PHPDoc 區塊。
- 在可能的情況下，完整註解程式碼。
- 在可能的情況下對方法參數和回傳類型使用 Type Hinting。
