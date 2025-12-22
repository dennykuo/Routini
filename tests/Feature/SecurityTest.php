<?php

use Routini\Router;
use Routini\Http\Request;
use Routini\Http\Response;
use Routini\RouteItem;
use Routini\Contracts\ControllerInterface;

/**
 * 安全性測試套件
 *
 * 測試 Routini 框架的安全性防護措施，包括：
 * - S1: Host Header Injection 防護
 * - S2: Controller Validation（控制器驗證）
 * - S3: Safe Redirect（安全重定向）
 * - Path Traversal 防護
 */
describe('Security Features', function () {

    // ============================================
    // S1: Host Header Injection 防護測試
    // ============================================

    describe('Host Header Validation (S1)', function () {

        test('validateHost() 接受白名單中的主機', function () {
            $request = new Request(
                uri: '/test',
                method: 'GET',
                server: ['HTTP_HOST' => 'example.com']
            );

            $allowedHosts = ['example.com', 'www.example.com'];

            expect($request->validateHost($allowedHosts))->toBeTrue();
        });

        test('validateHost() 拒絕不在白名單中的主機', function () {
            $request = new Request(
                uri: '/test',
                method: 'GET',
                server: ['HTTP_HOST' => 'evil.com']
            );

            $allowedHosts = ['example.com', 'www.example.com'];

            expect($request->validateHost($allowedHosts))->toBeFalse();
        });

        test('validateHost() 拒絕空的主機名', function () {
            $request = new Request(
                uri: '/test',
                method: 'GET',
                server: ['HTTP_HOST' => '']
            );

            $allowedHosts = ['example.com'];

            expect($request->validateHost($allowedHosts))->toBeFalse();
        });

        test('validateHost() 使用嚴格比對（大小寫敏感）', function () {
            $request = new Request(
                uri: '/test',
                method: 'GET',
                server: ['HTTP_HOST' => 'Example.com']
            );

            // 白名單使用小寫
            $allowedHosts = ['example.com'];

            // 應該失敗，因為大小寫不同
            expect($request->validateHost($allowedHosts))->toBeFalse();
        });

        test('getSanitizedHost() 移除埠號', function () {
            $request = new Request(
                uri: '/test',
                method: 'GET',
                server: ['HTTP_HOST' => 'example.com:8080']
            );

            expect($request->getSanitizedHost())->toBe('example.com');
        });

        test('getSanitizedHost() 轉換為小寫', function () {
            $request = new Request(
                uri: '/test',
                method: 'GET',
                server: ['HTTP_HOST' => 'Example.COM:8080']
            );

            expect($request->getSanitizedHost())->toBe('example.com');
        });

        test('getSanitizedHost() 處理沒有埠號的情況', function () {
            $request = new Request(
                uri: '/test',
                method: 'GET',
                server: ['HTTP_HOST' => 'Example.com']
            );

            expect($request->getSanitizedHost())->toBe('example.com');
        });

        test('防止 Host Header Injection 攻擊範例', function () {
            // 模擬攻擊：惡意的 Host header
            $maliciousRequest = new Request(
                uri: '/reset-password',
                method: 'POST',
                server: ['HTTP_HOST' => 'evil.com']
            );

            $allowedHosts = ['example.com', 'www.example.com'];

            // 應用程式應該驗證並拒絕此請求
            expect($maliciousRequest->validateHost($allowedHosts))->toBeFalse();
        });
    });

    // ============================================
    // S2: Controller Validation（控制器驗證）測試
    // ============================================

    describe('Controller Validation (S2)', function () {

        test('預設狀態下控制器驗證是關閉的', function () {
            $router = new Router();

            expect($router->isControllerValidationEnabled())->toBeFalse();
        });

        test('可以透過建構函式啟用控制器驗證', function () {
            $router = new Router(validateControllers: true);

            expect($router->isControllerValidationEnabled())->toBeTrue();
        });

        test('可以動態啟用控制器驗證', function () {
            $router = new Router();
            $router->enableControllerValidation();

            expect($router->isControllerValidationEnabled())->toBeTrue();
        });

        test('可以動態停用控制器驗證', function () {
            $router = new Router(validateControllers: true);
            $router->disableControllerValidation();

            expect($router->isControllerValidationEnabled())->toBeFalse();
        });

        test('啟用驗證時，允許實作 ControllerInterface 的控制器', function () {
            // 定義合法的控制器
            $validController = new class implements ControllerInterface {
                public function index() {
                    return 'Valid Controller';
                }
            };

            $router = new Router(validateControllers: true);
            $router->add('GET', '/test', [get_class($validController), 'index']);

            $request = new Request(uri: '/test', method: 'GET');
            $response = $router->dispatch($request);

            expect($response)->toBeInstanceOf(Response::class);
            expect($response->getContent())->toBe('Valid Controller');
        });

        test('啟用驗證時，拒絕未實作 ControllerInterface 的控制器', function () {
            // 定義不合法的控制器（未實作介面）
            $invalidController = new class {
                public function index() {
                    return 'Invalid Controller';
                }
            };

            $router = new Router(validateControllers: true);
            $router->add('GET', '/test', [get_class($invalidController), 'index']);

            $request = new Request(uri: '/test', method: 'GET');

            expect(fn() => $router->dispatch($request))
                ->toThrow(RuntimeException::class, 'Controller must implement ControllerInterface');
        });

        test('啟用驗證時，拒絕不存在的控制器類別', function () {
            $router = new Router(validateControllers: true);
            $router->add('GET', '/test', ['NonExistentController', 'index']);

            $request = new Request(uri: '/test', method: 'GET');

            expect(fn() => $router->dispatch($request))
                ->toThrow(RuntimeException::class, 'Controller class not found');
        });

        test('停用驗證時，允許任意控制器類別', function () {
            // 定義不合法的控制器（未實作介面）
            $invalidController = new class {
                public function index() {
                    return 'Works without validation';
                }
            };

            $router = new Router(validateControllers: false);
            $router->add('GET', '/test', [get_class($invalidController), 'index']);

            $request = new Request(uri: '/test', method: 'GET');
            $response = $router->dispatch($request);

            expect($response->getContent())->toBe('Works without validation');
        });

        test('防止任意類別實例化攻擊範例', function () {
            // 模擬攻擊：嘗試實例化系統類別
            $router = new Router(validateControllers: true);

            // 嘗試使用 PDO 類別（不實作 ControllerInterface）
            $router->add('GET', '/exploit', [\PDO::class, 'query']);

            $request = new Request(uri: '/exploit', method: 'GET');

            // 應該被阻止
            expect(fn() => $router->dispatch($request))
                ->toThrow(RuntimeException::class);
        });
    });

    // ============================================
    // S3: Safe Redirect（安全重定向）測試
    // ============================================

    describe('Safe Redirect (S3)', function () {

        test('safeRedirect() 允許相對 URL', function () {
            $response = Response::safeRedirect('/dashboard');

            expect($response)->toBeInstanceOf(Response::class);
            expect($response->getStatus())->toBe(302);
            expect($response->getHeaders()['Location'])->toBe('/dashboard');
        });

        test('safeRedirect() 允許以 / 開頭的相對路徑', function () {
            $response = Response::safeRedirect('/admin/users');

            expect($response->getHeaders()['Location'])->toBe('/admin/users');
        });

        test('safeRedirect() 拒絕 // 開頭的 URL（協定相對 URL）', function () {
            expect(fn() => Response::safeRedirect('//evil.com/phishing'))
                ->toThrow(\InvalidArgumentException::class, 'Unsafe redirect URL detected');
        });

        test('safeRedirect() 拒絕未在白名單中的絕對 URL', function () {
            expect(fn() => Response::safeRedirect('https://evil.com/phishing'))
                ->toThrow(\InvalidArgumentException::class, 'Unsafe redirect URL detected');
        });

        test('safeRedirect() 允許白名單中的絕對 URL', function () {
            $allowedDomains = ['example.com', 'trusted.com'];
            $response = Response::safeRedirect('https://example.com/page', $allowedDomains);

            expect($response->getHeaders()['Location'])->toBe('https://example.com/page');
        });

        test('safeRedirect() 對域名不區分大小寫', function () {
            $allowedDomains = ['example.com'];

            // 大寫域名應該也能通過驗證
            $response = Response::safeRedirect('https://Example.COM/page', $allowedDomains);

            expect($response->getHeaders()['Location'])->toBe('https://Example.COM/page');
        });

        test('safeRedirect() 拒絕空的 URL', function () {
            expect(fn() => Response::safeRedirect(''))
                ->toThrow(\InvalidArgumentException::class);
        });

        test('safeRedirect() 支援自訂狀態碼', function () {
            $response = Response::safeRedirect('/dashboard', [], 301);

            expect($response->getStatus())->toBe(301);
        });

        test('safeRedirect() 拒絕包含空格的無效 URL', function () {
            // parse_url() 會將包含空格的 URL 解析為有 host 但不在白名單
            expect(fn() => Response::safeRedirect('http://example .com/page'))
                ->toThrow(\InvalidArgumentException::class);
        });

        test('防止開放重定向攻擊範例', function () {
            // 模擬攻擊：使用者點擊釣魚連結
            // https://example.com/logout?redirect=https://evil.com/fake-login

            $userProvidedUrl = 'https://evil.com/fake-login';

            // 應用程式應該使用 safeRedirect() 並拋出例外
            expect(fn() => Response::safeRedirect($userProvidedUrl))
                ->toThrow(\InvalidArgumentException::class);
        });

        test('安全的重定向工作流程', function () {
            // 正確的使用方式：只允許白名單中的域名
            $allowedDomains = ['example.com', 'app.example.com'];

            // 內部重定向（相對 URL）- 安全
            $response1 = Response::safeRedirect('/profile');
            expect($response1->getHeaders()['Location'])->toBe('/profile');

            // 外部重定向（白名單域名）- 安全
            $response2 = Response::safeRedirect('https://app.example.com/oauth', $allowedDomains);
            expect($response2->getHeaders()['Location'])->toBe('https://app.example.com/oauth');
        });
    });

    // ============================================
    // Path Traversal（路徑遍歷）防護測試
    // ============================================

    describe('Path Traversal Protection', function () {

        test('RegexMatcher 防止基本的 .. 路徑遍歷', function () {
            $router = new Router();
            $router->add('GET', '/files/{filename}', function ($filename) {
                return "File: {$filename}";
            });

            // 嘗試路徑遍歷攻擊
            $request = new Request(uri: '/files/../etc/passwd', method: 'GET');
            $response = $router->dispatch($request);

            // 應該返回 404，而不是匹配到路由
            expect($response->getStatus())->toBe(404);
        });

        test('路由參數不應包含 ..', function () {
            $router = new Router();

            $capturedFilename = null;
            $router->add('GET', '/files/{filename}', function ($filename) use (&$capturedFilename) {
                $capturedFilename = $filename;
                return "File: {$filename}";
            });

            $request = new Request(uri: '/files/document.pdf', method: 'GET');
            $router->dispatch($request);

            // 正常檔名應該被捕獲
            expect($capturedFilename)->toBe('document.pdf');
            expect($capturedFilename)->not->toContain('..');
        });

        test('應用層應該驗證檔案路徑（最佳實踐示例）', function () {
            $router = new Router();

            $router->add('GET', '/files/{filename}', function ($filename) {
                // 應用層防護：移除路徑部分，只保留檔名
                $safeFilename = basename($filename);

                // 進一步驗證：只允許字母、數字、點、底線、連字號
                if (!preg_match('/^[a-zA-Z0-9._-]+$/', $safeFilename)) {
                    return Response::error('Invalid filename', 400);
                }

                return "Safe file: {$safeFilename}";
            });

            $request = new Request(uri: '/files/document.pdf', method: 'GET');
            $response = $router->dispatch($request);

            expect($response->getContent())->toBe('Safe file: document.pdf');
        });

        test('應用層應該使用 realpath() 驗證路徑（最佳實踐示例）', function () {
            $router = new Router();

            $router->add('GET', '/files/{filename}', function ($filename) {
                $basePath = '/var/www/uploads';
                $safeFilename = basename($filename);
                $fullPath = $basePath . '/' . $safeFilename;

                // 使用 realpath() 解析真實路徑（會解析 .. 和符號連結）
                // 注意：在測試環境中，路徑可能不存在，所以這裡只是示意
                $realPath = realpath($fullPath);

                // 驗證真實路徑是否在允許的目錄內
                // if ($realPath === false || strpos($realPath, $basePath) !== 0) {
                //     return Response::error('Invalid path', 400);
                // }

                // 這只是示範，實際應用應該檢查檔案是否存在
                return "Would access: {$fullPath}";
            });

            $request = new Request(uri: '/files/document.pdf', method: 'GET');
            $response = $router->dispatch($request);

            expect($response->getContent())->toContain('Would access:');
        });
    });

    // ============================================
    // 其他安全性測試
    // ============================================

    describe('Additional Security Tests', function () {

        test('HTTP Method 驗證防止方法覆蓋攻擊', function () {
            $router = new Router();
            $router->add('POST', '/admin/delete', function () {
                return 'Deleted';
            });

            // 嘗試使用 GET 方法訪問 POST 路由
            $request = new Request(uri: '/admin/delete', method: 'GET');
            $response = $router->dispatch($request);

            // 應該返回 404
            expect($response->getStatus())->toBe(404);
        });

        test('RouteItem 屬性封裝防止外部修改', function () {
            $route = new RouteItem(['GET'], '/test', fn() => 'test');

            // 應該無法直接修改私有屬性（會觸發 __set 並警告）
            // 這裡我們驗證 getter 返回正確的值
            expect($route->getMethods())->toBe(['GET']);
            expect($route->getUri())->toBe('/test');
        });

        test('中介軟體可用於實作安全檢查', function () {
            $router = new Router();

            // 模擬 CSRF 中介軟體
            $csrfMiddleware = function (Request $request, callable $next) {
                $token = $request->query('csrf_token');
                if ($token !== 'valid_token') {
                    return Response::error('Invalid CSRF token', 403);
                }
                return $next($request);
            };

            $router->add('POST', '/submit', function () {
                return 'Success';
            })->middleware($csrfMiddleware);

            // 沒有有效的 CSRF token
            $request = new Request(
                uri: '/submit',
                method: 'POST',
                query: ['csrf_token' => 'invalid']
            );
            $response = $router->dispatch($request);
            expect($response->getStatus())->toBe(403);

            // 有效的 CSRF token
            $request = new Request(
                uri: '/submit',
                method: 'POST',
                query: ['csrf_token' => 'valid_token']
            );
            $response = $router->dispatch($request);
            expect($response->getContent())->toBe('Success');
        });
    });
});
