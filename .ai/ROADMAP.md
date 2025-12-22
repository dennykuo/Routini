# Roadmap (開發藍圖)

本文件列出了 Routini 專案中尚未實作的功能，作為未來開發的導引。

## 已完成的基礎架構 (Completed Infrastructure)

以下核心架構已完成，為未來功能提供基礎：

- ✅ **Request/Response 物件系統** - PSR-7 風格的 HTTP 抽象層
- ✅ **中介軟體管道** - 洋蔥模式 (Onion Pattern) 實作
- ✅ **介面驅動設計** - RouteInterface, MiddlewareInterface 等核心介面
- ✅ **策略模式路由匹配** - RouteMatcherInterface 和 RegexMatcher
- ✅ **安全性功能** - Host Header 驗證 (S1)、控制器驗證 (S2)、安全重定向 (S3)
- ✅ **完整測試覆蓋** - 203 tests (398 assertions)，包含 35 個安全性測試

---

## 待實作功能 (Missing Features)

以下功能尚未實作，可作為未來擴展方向：

### 1. 參數限制 (Parameter Constraints)
- **Status**: ❌ Pending
- **Description**: 支援正規表達式限制路由參數。
- **Usage**:
    ```php
    Route::get('/user/{id}', ...)->where('id', '[0-9]+');
    ```

### 2. 控制器字串語法 (Controller String Syntax)
- **Status**: ❌ Pending
- **Description**: 支援 Laravel 風格的字串呼叫。
- **Usage**:
    ```php
    Route::get('/profile', 'UserController@show');
    ```

### 3.路由模型綁定 (Route Model Binding)
- **Status**: ❌ Pending
- **Description**: 自動根據 ID 解析模型實例。
- **Usage**:
    ```php
    Route::get('/user/{user}', function (User $user) { ... });
    ```

### 4. 表單方法偽造 (Form Method Spoofing)
- **Status**: ❌ Pending
- **Description**: 支援 HTML 表單透過 `_method` 欄位發送 PUT/DELETE 請求。

### 5. 速率限制 (Rate Limiting)
- **Status**: ❌ Pending
- **Description**: 為路由增加 Throttle 中介軟體。

### 6. 依賴注入 (Dependency Injection)
- **Status**: ❌ Pending
- **Description**: 支援控制器方法的依賴注入 (不限於路由模型綁定)。

---

## 參考文件 (References)

- **架構設計**: `/docs/ARCHITECTURE.md` - 完整的技術架構文件
- **安全性指南**: `/docs/SECURITY.md` - 安全性功能說明和最佳實踐
- **Laravel 比較**: `/docs/LARAVEL_COMPARISON.md` - 與 Laravel 的詳細比較
- **改善項目**: `/docs/REMAINING_IMPROVEMENTS.md` - 可選的改善方向

---

**最後更新**: 2025-12-22
