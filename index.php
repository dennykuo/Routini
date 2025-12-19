<?php

/**
 * Demo
 *
 * 這是一個展示 MiniLaravel Router 所有功能的範例檔案。
 * 包含了：
 * 1. 自動載入 (Composer & Manual)
 * 2. 基本路由與參數
 * 3. 命名路由與 URL 生成
 * 4. 路由群組 (Prefix, Middleware, Domain)
 * 5. 測試用的 Controllers 與 Middlewares
 */

// --------------------------------------------------------------------------
// 1. 環境設定與自動載入
// --------------------------------------------------------------------------

// 嘗試載入 Composer Autoload，一般套件的使用情況是由 Composer 自動載入
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}
// 本機開發或無 Composer 環境時的 Fallback
elseif (file_exists(__DIR__ . '/autoload_manual.php')) {
    require __DIR__ . '/autoload_manual.php';
}
else {
    die("Error: 無法載入 autoload 檔案。請執行 'composer install' 或確保 'autoload_manual.php' 存在。");
}

use Routini\Route;

// --------------------------------------------------------------------------
// 2. 路由定義
// --------------------------------------------------------------------------

/*
 * 基本 GET 路由
 * 測試方法: 瀏覽器訪問 /
 */
Route::get('/', function () {
    echo "<h1>MiniLaravel Router Demo</h1>";
    echo "<p>歡迎來到測試首頁。請嘗試點擊以下連結測試各種路由功能：</p>";

    echo "<h3>1. 基礎與參數路由</h3>";
    echo "<ul>";
    echo "<li><a href='/hello'>/hello</a> (選填參數 Default)</li>";
    echo "<li><a href='/hello/John'>/hello/John</a> (選填參數 'John')</li>";

    // 使用命名路由生成連結
    $userUrl = route('user.profile', ['id' => 9527]);
    echo "<li><a href='$userUrl'>$userUrl</a> (Named Route: user.profile)</li>";
    echo "</ul>";

    echo "<h3>2. 群組與權限 (Middleware)</h3>";
    echo "<ul>";
    echo "<li><a href='/admin/dashboard'>/admin/dashboard</a> (無 Token -> 403)</li>";
    echo "<li><a href='" . route('admin.dashboard') . "?token=secret'>/admin/dashboard?token=secret</a> (有 Token -> 通過)</li>";
    echo "<li><a href='/admin/settings/general?token=secret'>/admin/settings/general</a> (Nested Group)</li>";
    echo "</ul>";

    echo "<h3>3. POST 請求測試</h3>";
    echo "<form action='/submit' method='post' style='margin-left: 20px;'>
            <button type='submit'>發送 POST 到 /submit</button>
          </form>";

    echo "<h3>4. Route::any 測試</h3>";
    echo "<ul>";
    echo "<li><a href='/anyRequest'>/anyRequest</a> (GET)</li>";
    echo "<li>
            <form action='/anyRequest' method='post' style='display:inline;'>
                <button type='submit'>發送 POST 到 /anyRequest</button>
            </form>
          </li>";
    echo "</ul>";

    echo "<h3>5. Domain Routing</h3>";
    echo "<ul>";
    echo "<li><a href='http://api.localhost/version'>http://api.localhost/version</a> (需設定 hosts 或使用 curl)</li>";
    echo "</ul>";
});

/*
 * 基本 POST 路由
 * 測試方法: 使用 curl
 * curl -X POST -d "name=Test" http://localhost/submit
 */
Route::post('/submit', function () {
    echo "<h1>Form Submitted</h1>";
    echo "<p>這是一個 POST 請求。</p>";
});

/*
 * Route::any 路由 (支援所有 HTTP 方法)
 * 測試方法: 瀏覽器 (GET) 或 Postman (POST/PUT/DELETE)
 */
Route::any('/anyRequest', function () {
    $method = $_SERVER['REQUEST_METHOD'];
    echo "<h1>Any Route Matched</h1>";
    echo "<p>Method: $method</p>";
});

/*
 * 帶參數的路由 (必填與選填)
 * 測試方法: 瀏覽器訪問 /hello/John, /hello
 */
Route::get('/hello/{name?}', function ($name = 'Guest') {
    echo "Hello, " . htmlspecialchars($name) . "!";
});

/*
 * 使用 Controller 的路由 & 命名路由
 * 測試方法: 瀏覽器訪問 /user/123
 */
Route::get('/user/{id}', [DemoController::class, 'profile'])->name('user.profile');

/*
 * 路由群組示範 (Prefix + Middleware)
 * 測試方法: 瀏覽器訪問 /admin/dashboard?token=secret
 * 如果沒有帶 token，會被 AuthMiddleware 擋下
 */
Route::prefix('admin')
    ->name('admin.') // 設定群組名稱前綴 (admin.*)
    ->middleware([AuthMiddleware::class])
    ->group(function () {

        // 此路由名稱為 admin.dashboard
        Route::get('/dashboard', function () {
            echo "<h1>Admin Dashboard</h1>";
            echo "<p>您已通過驗證進入後台。</p>";
        })->name('dashboard');

        // 巢狀群組示範
        Route::prefix('settings')->group(function () {
            // 此路由路徑為 /admin/settings/general
            Route::get('/general', function () {
                echo "<h1>Admin Settings > General</h1>";
            });
        });
    });

/*
 * Domain Routing 示範
 * 測試方法: 需修改 hosts 檔案或使用 curl 指定 Host header
 * curl -H "Host: api.localhost" http://localhost/version
 */
Route::domain('api.localhost')->group(function () {
    Route::get('/version', function () {
        header('Content-Type: application/json');
        echo json_encode(['version' => '1.0.0', 'domain' => 'api.localhost']);
    });
});

// --------------------------------------------------------------------------
// 3. 請求分發 (Dispatch)
// --------------------------------------------------------------------------

// 為了支援 Domain Routing 本機測試，若環境變數未設定 HTTP_HOST，預設為 localhost
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

Route::dispatch();

// --------------------------------------------------------------------------
// 4. 輔助類別定義 (用於測試)
// --------------------------------------------------------------------------

/**
 * 測試用的 Controller
 */
class DemoController
{
    public function profile($id)
    {
        echo "<h1>User Profile</h1>";
        echo "<p>User ID: " . htmlspecialchars($id) . "</p>";
    }
}

/**
 * 測試用的 Middleware
 * 簡單驗證 URL query string 是否包含 token=secret
 */
class AuthMiddleware
{
    public function handle()
    {
        $token = $_GET['token'] ?? '';

        if ($token !== 'secret') {
            http_response_code(403);
            echo "<h1>403 Forbidden</h1>";
            echo "<p>Access Denied. 請在網址加上 ?token=secret</p>";
            // 回傳 false 以中斷請求
            return false;
        }

        // 回傳 true 繼續執行
        return true;
    }
}