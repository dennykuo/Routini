<?php

use Routini\Route;

/*
|--------------------------------------------------------------------------
| Domain Routing Tests
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    // Reset the singleton instance using Reflection
    $reflection = new ReflectionClass(Route::class);
    $property = $reflection->getProperty('instance');
    $property->setAccessible(true);
    $property->setValue(null, null);

    // Reset Server
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_HOST'] = 'example.com';
});

test('路由匹配特定網域', function () {
    Route::domain('api.example.com')->group(function () {
        Route::get('/users', function () {
            echo 'API Users';
        });
    });

    // 1. 正確的網域應該匹配
    ob_start();
    // 模擬 dispatch 傳入 host
    Route::getInstance()->dispatch('/users', 'GET', 'api.example.com');
    $output = ob_get_clean();
    expect($output)->toBe('API Users');

    // 2. 錯誤的網域應該 404
    ob_start();
    Route::getInstance()->dispatch('/users', 'GET', 'www.example.com');
    $outputFail = ob_get_clean();
    expect($outputFail)->toContain('404');
});

test('一般路由不限網域', function () {
    Route::get('/general', function () {
        echo 'General Content';
    });

    // 任何網域都應該可以訪問
    ob_start();
    Route::getInstance()->dispatch('/general', 'GET', 'any.com');
    $output = ob_get_clean();
    expect($output)->toBe('General Content');
});

test('巢狀群組網域覆蓋', function () {
    Route::domain('admin.example.com')->group(function () {
        Route::get('/dashboard', function () {
            echo 'Admin Dashboard';
        });

        // 雖然罕見，但測試內層 domain 覆蓋外層
        Route::domain('api.admin.example.com')->group(function () {
            Route::get('/stats', function () {
                echo 'Admin API Stats';
            });
        });
    });

    // 外層
    ob_start();
    Route::getInstance()->dispatch('/dashboard', 'GET', 'admin.example.com');
    expect(ob_get_clean())->toBe('Admin Dashboard');

    // 內層
    ob_start();
    Route::getInstance()->dispatch('/stats', 'GET', 'api.admin.example.com');
    expect(ob_get_clean())->toBe('Admin API Stats');

    // 內層用外層 domain 訪問應失敗
    ob_start();
    Route::getInstance()->dispatch('/stats', 'GET', 'admin.example.com');
    expect(ob_get_clean())->toContain('404');
});

test('使用預設 $_SERVER HTTP_HOST', function () {
    $_SERVER['HTTP_HOST'] = 'default.com';

    Route::domain('default.com')->group(function() {
        Route::get('/home', function () {
            echo 'Home';
        });
    });

    ob_start();
    // 不傳入 host，應使用 $_SERVER['HTTP_HOST']
    Route::getInstance()->dispatch('/home', 'GET');
    expect(ob_get_clean())->toBe('Home');

    // 更改 Server Host 模擬失敗
    $_SERVER['HTTP_HOST'] = 'other.com';
    ob_start();
    Route::getInstance()->dispatch('/home', 'GET');
    expect(ob_get_clean())->toContain('404');
});
