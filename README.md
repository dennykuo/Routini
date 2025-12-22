# Routini

**Routini** 是一個輕量級的 PHP 路由套件，旨在模仿 Laravel 路由系統 (`Illuminate\Routing`) 的核心功能與簡潔語法。

設計目標是讓開發者在**非 Laravel 專案**（如 Legacy Code、小型專案或微服務）中，也能享受到優雅的路由定義體驗。

## 特色 (Features)

- **語法熟悉**: 幾乎與 Laravel Route API 一致 (`Route::get`, `Route::group`...)。
- **強大路由**: 支援 `GET`, `POST`, `PUT`, `DELETE` 等方法。
- **彈性參數**: 支援必填 (`{id}`) 與選填參數 (`{name?}`)。
- **巢狀群組**: 管理路由群組，支援前綴 (`prefix`)、中介軟體 (`middleware`)、網域 (`domain`) 繼承。
- **命名路由**: 支援命名路由 (`name`) 與反向網址生成 (`route()`)。
- **中介軟體**: 支援 Middleware 機制以攔截請求。

## 需求 (Requirements)

- PHP 8.0 或更高版本
- Composer

## 安裝 (Installation)

###透過 Composer (推薦)

```bash
composer require dennykuo/routini
```

### 手動安裝

若您的專案不使用 Composer，可以改為在入口檔案中引入此套件的 `autoload_manual.php` 作為套件引入：

```php
// 改成您的正確路徑
require_once '/path/to/autoload_manual.php';
```

## 快速開始 (Quick Start)

在您的進入點（例如 `index.php`）：

```php
<?php

// 若使用 composer 安裝
require 'vendor/autoload.php';

// 若未使用 composer，改用下列方式，並改成您的正確路徑
// require_once '/path/to/autoload_manual.php';

use Routini\Route;

// 1. 定義路由
Route::get('/', function () {
    return 'Hello Routini!';
});

// 2. 啟動分發
Route::dispatch();
```

---

## 使用指南 (Documentation)

### 基礎路由 (Basic Routing)

支援所有標準 HTTP 動詞：

```php
Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{id}', [UserController::class, 'update']);
Route::delete('/users/{id}', [UserController::class, 'delete']);

// 支援多種方法
Route::match(['get', 'post'], '/callback', function () {
    // Handle request...
});

// 支援所有方法
Route::any('/all', function () {
    // Handle request...
});
```

### 路由參數 (Parameters)

支援捕捉 URL 片段：

```php
// 必填參數
Route::get('/post/{id}', function ($id) {
    return "Post ID: " . $id;
});

// 選填參數 (必須給予預設值)
Route::get('/user/{name?}', function ($name = 'Guest') {
    return "Hello, " . $name;
});
```

### 命名路由 (Named Routes)

為路由命名以便後續生成連結：

```php
Route::get('/user/profile', function () {
    // ...
})->name('profile');

// 反向生成 URL
$url = route('profile');
echo $url; // /user/profile
```

### 路由群組 (Route Groups)

使用群組來共享屬性，支援無限巢狀：

```php
Route::prefix('admin')
    ->middleware([AuthMiddleware::class])
    ->name('admin.') // 路由名稱前綴
    ->group(function () {

        // URI: /admin/dashboard
        // Name: admin.dashboard
        Route::get('/dashboard', function () {
            return "Admin Dashboard";
        })->name('dashboard');

        // 巢狀群組: /admin/settings
        Route::prefix('settings')->group(function () {
            Route::get('/general', function () { /* ... */ });
        });
    });
```

### 網域路由 (Domain Routing)

限制路由僅在特定網域生效：

```php
Route::domain('api.myapp.com')->group(function () {
    Route::get('/', function () {
        return "API Home";
    });
});
```

## 測試 (Testing)

本專案使用 PEST 進行單元測試。

```bash
composer test
# 或者
vendor/bin/pest
```

## 文件 (Documentation)

更多詳細資訊請參考以下文件：

- **[ARCHITECTURE.md](docs/ARCHITECTURE.md)** - 架構設計文件
  - 核心組件說明
  - 設計模式與原則
  - 介面契約
  - 安全性設計

- **[SECURITY.md](docs/SECURITY.md)** - 安全性文件
  - 安全性風險分析
  - 防護措施說明
  - 使用範例

- **[LARAVEL_COMPARISON.md](docs/LARAVEL_COMPARISON.md)** - 與 Laravel 路由系統的比較
  - 功能差異對照
  - 使用範例比較
  - 遷移指南

- **[REMAINING_IMPROVEMENTS.md](docs/REMAINING_IMPROVEMENTS.md)** - 待改善項目清單
  - 已完成項目
  - 可選改善項目
  - 未來擴展方向

## 授權 (License)

Routini 採用 [MIT license](LICENSE) 授權。
