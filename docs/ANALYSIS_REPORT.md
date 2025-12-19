# 路由套件分析報告

## 概覽
目前的程式碼庫實作了一個簡化版的 Laravel 路由系統，支援基本的 HTTP 方法、路由參數、路由群組（前綴/中介軟體）以及反向路由。它使用類似 Facade 的靜態介面 (`Route::get`) 來與單例的 `Router` 實例進行互動。

## 架構
- **`Route` (Facade)**: 靜態入口點，將呼叫轉發給 `Router` 或 `RouteRegistrar`。
- **`Router`**: 核心邏輯。處理路由收集、群組（使用堆疊 stack）、分發和網址生成。
- **`RouteItem`**: 代表單一路由定義。
- **`RouteRegistrar`**: 輔助定義群組的幫手 (`Route::prefix(...)->group(...)`)。

## 與 Laravel 的比較

### 1. 基本路由與方法
- **Laravel**: 支援 `get`, `post`, `put`, `patch`, `delete`, `options` 以及嚴格的 `any`, `match`。
- **目前**: `Router::add` 接受任何方法字串，但 `Route` facade 只有提示 `get` 和 `post`。雖然 `Route::__callStatic` 作為代理，技術上 `Route::put(...)` 如果被呼叫是可以運作的（傳遞 'PUT' 給 `add`），但並沒有明確定義或記錄。

### 2. 路由參數
- **Laravel**:
    - **必填**: `{id}`
    - **選填**: `{id?}`
    - **限制 (Constraints)**: `->where('id', '[0-9]+')` 或 `->whereNumber('id')`
    - **全域限制**: `Route::pattern(...)`
- **目前**:
    - **必填**: 支援 `{id}`，使用正則表達式 `([^/]+)`。
    - **選填**: 支援（例如：`Route::get('/user/{name?}', ...)`，若未提供參數則視為選填）。
    - **限制**: 不支援。所有參數都會匹配任何非斜線字元。

### 3. 路由群組
- **Laravel**: 支援廣泛的群組屬性：`prefix`, `middleware`, `domain`, `name` (作為前綴), `namespace` (控制器命名空間)。
- **目前**:
    - **Prefix**: 支援（巢狀前綴可正確串接）。
    - **Middleware**: 支援（與父群組屬性合併）。
    - **Name Prefix**: 支援（例如：`Route::name('admin.')->group(...)` 會為子路由名稱加上前綴）。
    - **Domain**: 不支援。

### 4. 中介軟體 (Middleware)
- **Laravel**:
    - 支援別名 (Aliases，定義在 Kernel 中)。
    - 支援中介軟體群組 (`web`, `api`)。
    - 支援參數 (`middleware:role,admin`)。
    - Terminable Middleware 實作。
- **目前**:
    - 支援原始類別名稱 (`AuthMiddleware::class`) 或 Closure。
    - 無別名（必須使用完整類別名稱）。
    - 無參數。
    - 基本執行（實例化並呼叫 `handle`）。

### 5. 控制器與動作 (Controller & Action)
- **Laravel**:
    - 陣列語法: `[UserController::class, 'show']`
    - 字串語法: `'UserController@show'`
    - Invokable Controllers。
- **目前**:
    - 支援 Callable/Closure。
    - 支援陣列語法 `[Class, Method]`（在 `runRoute` 中明確處理）。
    - **缺少** 字串語法 `'UserController@show'` 的邏輯。

### 6. 路由模型綁定 (Route Model Binding)
- **Laravel**: 根據參數名稱自動解析 Eloquent 模型（隱式/顯式）。
- **目前**: 僅將 URI 正則匹配到的原始字串值傳遞給控制器。

### 7. 反向路由 (URL Generation)
- **Laravel**: `route('name', ['id' => 1])`。處理編碼、選填參數和複雜的查詢字串 (Query String)。
- **目前**: `route()` 輔助函式呼叫 `Router::url`。
    - 簡單的字串替換。
    - **問題**: 應確保替換參數的 URL 編碼。
    - **問題**: 路徑中未使用的參數正確地被附加為查詢字串。

### 8. 其他缺失功能
- **表單方法偽造 (Form Method Spoofing)**: Laravel 會檢查 POST 請求中的 `_method` 欄位以支援 PUT/DELETE。目前的 `dispatch` 僅查看原始 `$_SERVER['REQUEST_METHOD']`（因此 HTML 表單只能 GET/POST）。
- **速率限制 (Rate Limiting)**: 無 `Throttle` 機制。
- **CSRF 保護**: 無 CSRF 中介軟體。
- **路由快取**: 無法序列化以提升效能。
- **當前路由存取**: `Route::current()`, `Route::currentRouteName()` 未實作。

## 總結表

| 功能 | Laravel 標準 | 目前套件 |
|:---|:---|:---|
| **參數限制 (`where`)** | ✅ 支援 | ❌ 未實作 |
| **選填參數 (`{id?}`)** | ✅ 支援 | ✅ 支援 |
| **名稱前綴 (Name Prefix)** | ✅ 群組中使用 `as` / `name` | ✅ 支援 |
| **中介軟體別名** | ✅ 設定檔定義 | ❌ 僅限類別名稱 |
| **依賴注入** | ✅ Service Container | ❌ 僅路由參數 |
| **方法偽造 (`_method`)** | ✅ 支援 | ❌ 僅限 `REQUEST_METHOD` |
| **路由模型綁定** | ✅ 支援 | ❌ 僅原始字串 |
