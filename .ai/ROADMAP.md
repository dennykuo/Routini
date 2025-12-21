# Roadmap (開發藍圖)

本文件列出了 MiniLaravel Router 專案中尚未實作的功能，作為未來開發的導引。

## 待實作功能 (Missing Features)

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
