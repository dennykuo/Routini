<?php

use Routini\Route;

/*
|--------------------------------------------------------------------------
| Test Case Setup
|--------------------------------------------------------------------------
|
| We need to reset the Router instance before each test to ensure
| a clean state. Since the Router is a Singleton, we'll use Reflection
| to reset the static instance.
|
*/

beforeEach(function () {
    // Reset the singleton instance using Reflection
    $reflection = new ReflectionClass(Route::class);
    $property = $reflection->getProperty('instance');
    $property->setAccessible(true);
    $property->setValue(null, null);

    // Mock $_SERVER globals to avoid warnings
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

/*
|--------------------------------------------------------------------------
| 基本路由測試 (Basic Routing)
|--------------------------------------------------------------------------
*/

test('路由 GET 請求', function () {
    Route::get('/hello', function () {
        echo 'Hello World';
    });

    ob_start();
    Route::getInstance()->dispatch('/hello', 'GET');
    $output = ob_get_clean();

    expect($output)->toBe('Hello World');
});

test('路由 POST 請求', function () {
    Route::post('/submit', function () {
        echo 'Submitted';
    });

    ob_start();
    Route::getInstance()->dispatch('/submit', 'POST');
    $output = ob_get_clean();

    expect($output)->toBe('Submitted');
});

test('Route::any 接受所有方法', function () {
    Route::any('/all', function () {
        echo 'Caught';
    });

    $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];

    foreach ($methods as $method) {
        ob_start();
        Route::getInstance()->dispatch('/all', $method);
        $output = ob_get_clean();
        expect($output)->toBe('Caught');
    }
});

test('不支援的方法回傳 404', function () {
    Route::get('/only-get', function () {
        echo 'Should not see this';
    });

    ob_start();
    // 模擬用 POST 請求訪問只有定義 GET 的路由
    Route::getInstance()->dispatch('/only-get', 'POST');
    $output = ob_get_clean();

    expect($output)->toContain('404');
});

test('未定義的路由回傳 404', function () {
    ob_start();
    Route::getInstance()->dispatch('/undefined', 'GET');
    $output = ob_get_clean();

    expect($output)->toContain('404');
});

/*
|--------------------------------------------------------------------------
| 路由參數測試 (Route Parameters)
|--------------------------------------------------------------------------
*/

test('帶參數的路由', function () {
    Route::get('/user/{id}', function ($id) {
        echo "User ID: {$id}";
    });

    ob_start();
    Route::getInstance()->dispatch('/user/123', 'GET');
    $output = ob_get_clean();

    expect($output)->toBe('User ID: 123');
});

test('多個參數的路由', function () {
    Route::get('/post/{pid}/comment/{cid}', function ($pid, $cid) {
        echo "Post: {$pid}, Comment: {$cid}";
    });

    ob_start();
    Route::getInstance()->dispatch('/post/100/comment/500', 'GET');
    $output = ob_get_clean();

    expect($output)->toBe('Post: 100, Comment: 500');
});

/*
|--------------------------------------------------------------------------
| 路由群組與中介軟體 (Route Groups & Middleware)
|--------------------------------------------------------------------------
*/

test('路由群組 Prefix', function () {
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', function () {
            echo 'Admin Dashboard';
        });
    });

    ob_start();
    Route::getInstance()->dispatch('/admin/dashboard', 'GET');
    $output = ob_get_clean();

    expect($output)->toBe('Admin Dashboard');
});

test('巢狀路由群組 Prefix', function () {
    Route::prefix('api')->group(function () {
        Route::prefix('v1')->group(function () {
            Route::get('/users', function () {
                echo 'API V1 Users';
            });
        });
    });

    ob_start();
    Route::getInstance()->dispatch('/api/v1/users', 'GET');
    $output = ob_get_clean();

    expect($output)->toBe('API V1 Users');
});

test('中介軟體攔截 (Middleware Rejection)', function () {
    // 定義一個總是回傳 false 的 Middleware
    class RejectMiddleware {
        public function handle() {
            echo 'Access Denied';
            return false;
        }
    }

    Route::middleware([RejectMiddleware::class])->group(function () {
        Route::get('/secret', function () {
            echo 'Secret Data';
        });
    });

    ob_start();
    Route::getInstance()->dispatch('/secret', 'GET');
    $output = ob_get_clean();

    // 應該只看到 Access Denied，看不到 Secret Data
    expect($output)->toBe('Access Denied');
});

test('中介軟體通過 (Middleware Pass)', function () {
    // 定義一個總是回傳 true 的 Middleware
    class PassMiddleware {
        public function handle() {
            return true;
        }
    }

    Route::middleware([PassMiddleware::class])->group(function () {
        Route::get('/public', function () {
            echo 'Public Data';
        });
    });

    ob_start();
    Route::getInstance()->dispatch('/public', 'GET');
    $output = ob_get_clean();

    expect($output)->toBe('Public Data');
});

/*
|--------------------------------------------------------------------------
| 反向路由測試 (Reverse Routing)
|--------------------------------------------------------------------------
*/

test('反向路由網址生成', function () {
    Route::get('/user/{id}/profile', function () { })->name('user.profile');

    // 獲取 Router 實例直接測試 url 方法 (因為 route() helper 依賴全域函數，這裡測試核心邏輯)
    $url = Route::getInstance()->url('user.profile', ['id' => 456]);

    expect($url)->toBe('/user/456/profile');
});

test('反向路由 Query String 生成', function () {
    Route::get('/search', function () { })->name('search');

    $url = Route::getInstance()->url('search', ['q' => 'laravel', 'page' => 2]);

    expect($url)->toBe('/search?q=laravel&page=2');
});

test('生成未定義的命名路由會拋出例外', function () {
    expect(function () {
        Route::getInstance()->url('undefined.route');
    })->toThrow(Exception::class);
});

test('群組名稱前綴 (Name Prefix)', function () {
    Route::name('admin.')->group(function () {
        Route::get('/users', function () { })->name('users.index'); // admin.users.index

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/general', function () { })->name('general'); // admin.settings.general
        });
    });

    $url1 = Route::getInstance()->url('admin.users.index');
    expect($url1)->toBe('/users');

    $url2 = Route::getInstance()->url('admin.settings.general');
    expect($url2)->toBe('/settings/general');
});

test('選填參數 (Optional Parameters)', function () {
    Route::get('/user/{name?}', function ($name = 'Guest') {
        echo "Hello {$name}";
    })->name('user.optional');

    // 1. 測試有提供參數
    ob_start();
    Route::getInstance()->dispatch('/user/Denny', 'GET');
    $output1 = ob_get_clean();
    expect($output1)->toBe('Hello Denny');

    // 2. 測試未提供參數 (使用預設值)
    ob_start();
    Route::getInstance()->dispatch('/user', 'GET');
    $output2 = ob_get_clean();
    expect($output2)->toBe('Hello Guest');

    // 3. 測試 URL 生成 (有參數)
    $url1 = Route::getInstance()->url('user.optional', ['name' => 'John']);
    expect($url1)->toBe('/user/John');

    // 4. 測試 URL 生成 (無參數)
    $url2 = Route::getInstance()->url('user.optional');
    expect($url2)->toBe('/user');
});
