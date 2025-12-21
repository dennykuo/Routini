<?php

use Routini\Router;
use Routini\Route;
use Routini\RouteItem;
use Routini\Contracts\RouteMatcherInterface;
use Routini\Matching\RegexMatcher;
use Routini\Http\Request;

beforeEach(function () {
    Route::reset();
});

describe('Router Matcher Strategy', function () {
    it('uses RegexMatcher by default', function () {
        $router = new Router();

        expect($router->getMatcher())->toBeInstanceOf(RegexMatcher::class);
    });

    it('can inject custom matcher via constructor', function () {
        $customMatcher = new class implements RouteMatcherInterface {
            public function match(RouteItem $route, string $uri): bool {
                return true; // 總是匹配
            }

            public function extractParameters(RouteItem $route, string $uri): array {
                return ['custom' => 'param'];
            }
        };

        $router = new Router($customMatcher);

        expect($router->getMatcher())->toBe($customMatcher);
    });

    it('can change matcher using setMatcher', function () {
        $router = new Router();
        $originalMatcher = $router->getMatcher();

        $newMatcher = new class implements RouteMatcherInterface {
            public function match(RouteItem $route, string $uri): bool {
                return $route->uri === $uri; // 精確匹配
            }

            public function extractParameters(RouteItem $route, string $uri): array {
                return [];
            }
        };

        $router->setMatcher($newMatcher);

        expect($router->getMatcher())->not->toBe($originalMatcher);
        expect($router->getMatcher())->toBe($newMatcher);
    });

    it('custom matcher affects route matching behavior', function () {
        // 創建一個只匹配精確 URI 的自定義 matcher
        $exactMatcher = new class implements RouteMatcherInterface {
            public function match(RouteItem $route, string $uri): bool {
                return $route->uri === $uri;
            }

            public function extractParameters(RouteItem $route, string $uri): array {
                return [];
            }
        };

        $router = new Router($exactMatcher);

        // 註冊帶參數的路由
        $router->add('GET', '/user/{id}', function () {
            return 'User route';
        });

        // 使用精確匹配器，/user/123 不會匹配 /user/{id}
        $request = new Request('/user/123', 'GET');
        $response = $router->dispatch($request);

        expect($response->getStatus())->toBe(404); // 不匹配
    });

    it('custom matcher can extract custom parameters', function () {
        // 創建一個自定義 matcher，總是返回固定參數
        $customMatcher = new class implements RouteMatcherInterface {
            public function match(RouteItem $route, string $uri): bool {
                return str_starts_with($uri, '/api/');
            }

            public function extractParameters(RouteItem $route, string $uri): array {
                // 提取 /api/ 後面的所有內容作為 'path' 參數
                return ['path' => substr($uri, 5)];
            }
        };

        $router = new Router($customMatcher);

        $capturedParams = null;
        $router->add('GET', '/api/', function ($path) use (&$capturedParams) {
            $capturedParams = $path;
            return 'API route';
        });

        $request = new Request('/api/users/123', 'GET');
        $response = $router->dispatch($request);

        expect($response->getStatus())->toBe(200);
        expect($capturedParams)->toBe('users/123');
    });

    it('RegexMatcher handles standard route patterns correctly', function () {
        $router = new Router(new RegexMatcher());

        $router->add('GET', '/user/{id}', function ($id) {
            return "User: {$id}";
        });

        $request = new Request('/user/123', 'GET');
        $response = $router->dispatch($request);

        expect($response->getStatus())->toBe(200);
        expect($response->getContent())->toBe('User: 123');
    });

    it('can switch matchers at runtime', function () {
        $router = new Router();

        // 初始使用 RegexMatcher
        $router->add('GET', '/user/{id}', function ($id) {
            return "User: {$id}";
        });

        $request1 = new Request('/user/123', 'GET');
        $response1 = $router->dispatch($request1);
        expect($response1->getStatus())->toBe(200);

        // 切換到精確匹配器
        $exactMatcher = new class implements RouteMatcherInterface {
            public function match(RouteItem $route, string $uri): bool {
                return $route->uri === $uri;
            }

            public function extractParameters(RouteItem $route, string $uri): array {
                return [];
            }
        };

        $router->setMatcher($exactMatcher);

        // 同樣的請求現在不會匹配
        $request2 = new Request('/user/123', 'GET');
        $response2 = $router->dispatch($request2);
        expect($response2->getStatus())->toBe(404);
    });

    it('matcher is used by all routes', function () {
        $callCount = 0;
        $trackingMatcher = new class($callCount) implements RouteMatcherInterface {
            public function __construct(private &$callCount) {}

            public function match(RouteItem $route, string $uri): bool {
                $this->callCount++;
                return $route->uri === $uri;
            }

            public function extractParameters(RouteItem $route, string $uri): array {
                return [];
            }
        };

        $router = new Router($trackingMatcher);

        // 註冊多個路由
        $router->add('GET', '/route1', fn() => '1');
        $router->add('GET', '/route2', fn() => '2');
        $router->add('GET', '/route3', fn() => '3');

        $request = new Request('/route2', 'GET');
        $router->dispatch($request);

        // matcher 應該被調用多次（嘗試匹配所有路由）
        expect($callCount)->toBeGreaterThan(0);
    });
});
