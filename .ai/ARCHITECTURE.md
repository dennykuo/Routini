# Architecture Overview (架構概觀)

## 系統背景 (System Context)
這是一個輕量級的 PHP Routing 套件，旨在模仿 Laravel 路由系統 (`Illuminate\Routing`) 的 API 和行為。它不是一個完整的 Framework，而是一個獨立的組件。

## 核心組件 (Core Components)

### 1. `Route` (The Facade)
- **Path**: `src/Route.php`
- **Role**: 為使用者提供靜態介面。
- **Behavior**:
    - 作為 Proxy (代理)。
    - `Route::get(...)` -> 代理至 `Router->add(...)`。
    - `Route::prefix(...)` -> 回傳 `RouteRegistrar` 以進行 Chaining。

### 2. `Router` (The Brain)
- **Path**: `src/Router.php`
- **Role**: 管理路由集合與 Dispatching 邏輯的 Singleton 類別。
- **Key Responsibilities**:
    - **Route Collection**: 儲存 `RouteItem` 物件。
    - **Group Management**: 使用 `groupStack` 處理巢狀群組 (Prefix, Middleware, Name, Domain)。
    - **Dispatching**: 比對 Request URI/Method/Host 與路由，並執行對應的 Action。
    - **URL Generation**: 從 Named Routes 重建 URL。

### 3. `RouteRegistrar` (The Helper)
- **Path**: `src/RouteRegistrar.php`
- **Role**: 處理在定義路由之前，鏈式設定 Group Attributes 的暫時狀態。
- **Usage**: 當你呼叫 `Route::prefix('admin')` 時，你會得到一個 Registrar。接著你可以鏈式呼叫 `->middleware(...)`，最後呼叫 `->group(...)`。

### 4. `RouteItem` (The Model)
- **Path**: `src/RouteItem.php`
- **Role**: 代表單一已定義路由的 DTO (Data Transfer Object)。
- **Properties**: URI, Method, Action, Name, Middleware list, Domain constraint。

## 請求生命週期 (Request Lifecycle)
1. **Definition**: 使用者在 `index.php` (或類似檔案) 中使用 `Route::get(...)` 定義路由。
2. **Dispatch**: 入口點呼叫 `Route::dispatch()`。
3. **Matching**: `Router` 迭代檢查路由：
    - 檢查 Method。
    - 檢查 Domain (若有設定)。
    - 檢查 URI (Regex match)。
4. **Execution**:
    - Middleware stack 先被執行。
    - 若全數通過，則執行 Controller/Closure。

## 與 Laravel 的主要差異 (Key Differences)
- 無 Service Container (Dependency Injection 受限)。
- 無基於 Reflection 的 Controller 解析 (需明確實例化)。
- 預設 Dispatch 依賴全域狀態 (`$_SERVER`)。
