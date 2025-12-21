<?php

use Routini\Router;
use Routini\Route;
use Routini\Http\Request;
use Routini\Http\Response;
use Routini\Contracts\MiddlewareInterface;

beforeEach(function () {
    Route::reset();
});

describe('Router Middleware Integration', function () {
    it('executes global middleware for all routes', function () {
        $router = new Router();
        $executionLog = [];

        // 註冊全域中介軟體
        $router->middleware(function (Request $request, callable $next) use (&$executionLog) {
            $executionLog[] = 'global-before';
            $response = $next($request);
            $executionLog[] = 'global-after';
            return $response;
        });

        $router->add('GET', '/test', function () use (&$executionLog) {
            $executionLog[] = 'route-action';
            return 'Test';
        });

        $request = new Request('/test', 'GET');
        $router->dispatch($request);

        expect($executionLog)->toBe(['global-before', 'route-action', 'global-after']);
    });

    it('executes route-specific middleware', function () {
        $router = new Router();
        $executionLog = [];

        $route = $router->add('GET', '/test', function () use (&$executionLog) {
            $executionLog[] = 'route-action';
            return 'Test';
        });

        // 路由特定的中介軟體
        $route->middleware(function (Request $request, callable $next) use (&$executionLog) {
            $executionLog[] = 'route-middleware-before';
            $response = $next($request);
            $executionLog[] = 'route-middleware-after';
            return $response;
        });

        $request = new Request('/test', 'GET');
        $router->dispatch($request);

        expect($executionLog)->toBe(['route-middleware-before', 'route-action', 'route-middleware-after']);
    });

    it('executes middleware in correct order: global → route → action', function () {
        $router = new Router();
        $executionLog = [];

        // 全域中介軟體
        $router->middleware(function (Request $request, callable $next) use (&$executionLog) {
            $executionLog[] = 'global-before';
            $response = $next($request);
            $executionLog[] = 'global-after';
            return $response;
        });

        $route = $router->add('GET', '/test', function () use (&$executionLog) {
            $executionLog[] = 'action';
            return 'Test';
        });

        // 路由中介軟體
        $route->middleware(function (Request $request, callable $next) use (&$executionLog) {
            $executionLog[] = 'route-before';
            $response = $next($request);
            $executionLog[] = 'route-after';
            return $response;
        });

        $request = new Request('/test', 'GET');
        $router->dispatch($request);

        expect($executionLog)->toBe([
            'global-before',
            'route-before',
            'action',
            'route-after',
            'global-after'
        ]);
    });

    it('supports multiple global middlewares', function () {
        $router = new Router();
        $executionLog = [];

        $router->middleware(function (Request $request, callable $next) use (&$executionLog) {
            $executionLog[] = 'global1';
            return $next($request);
        });

        $router->middleware(function (Request $request, callable $next) use (&$executionLog) {
            $executionLog[] = 'global2';
            return $next($request);
        });

        $router->add('GET', '/test', function () use (&$executionLog) {
            $executionLog[] = 'action';
            return 'Test';
        });

        $request = new Request('/test', 'GET');
        $router->dispatch($request);

        expect($executionLog)->toBe(['global1', 'global2', 'action']);
    });

    it('middleware can short-circuit and prevent route execution', function () {
        $router = new Router();
        $actionExecuted = false;

        // 驗證中介軟體
        $router->middleware(function (Request $request, callable $next) {
            // 檢查授權 header
            if ($request->getHeader('Authorization') !== 'Bearer valid-token') {
                return new Response('Unauthorized', 401);
            }
            return $next($request);
        });

        $router->add('GET', '/admin', function () use (&$actionExecuted) {
            $actionExecuted = true;
            return 'Admin Area';
        });

        // 測試未授權請求
        $request = new Request('/admin', 'GET');
        $response = $router->dispatch($request);

        expect($response->getStatus())->toBe(401);
        expect($response->getContent())->toBe('Unauthorized');
        expect($actionExecuted)->toBeFalse();
    });

    it('middleware can modify request', function () {
        $router = new Router();

        $router->middleware(function (Request $request, callable $next) {
            // 中介軟體添加自訂屬性
            $request->setAttribute('authenticated', true);
            $request->setAttribute('user_id', 123);
            return $next($request);
        });

        $capturedRequest = null;
        $router->add('GET', '/test', function () use (&$capturedRequest) {
            global $currentRequest;
            $capturedRequest = $currentRequest ?? null;
            return 'Test';
        });

        // 使用全域變數傳遞 request
        $request = new Request('/test', 'GET');
        $GLOBALS['currentRequest'] = $request;
        $router->dispatch($request);

        expect($request->getAttribute('authenticated'))->toBe(true);
        expect($request->getAttribute('user_id'))->toBe(123);
    });

    it('middleware can modify response', function () {
        $router = new Router();

        $router->middleware(function (Request $request, callable $next) {
            $response = $next($request);
            // 添加自訂 header
            return $response->withHeader('X-Custom-Header', 'middleware-value');
        });

        $router->add('GET', '/test', function () {
            return 'Test';
        });

        $request = new Request('/test', 'GET');
        $response = $router->dispatch($request);

        expect($response->getHeader('X-Custom-Header'))->toBe('middleware-value');
    });

    it('supports MiddlewareInterface instances', function () {
        $router = new Router();
        $executionLog = [];

        $customMiddleware = new class($executionLog) implements MiddlewareInterface {
            public function __construct(private array &$log) {}

            public function handle(Request $request, callable $next): Response {
                $this->log[] = 'custom-middleware';
                return $next($request);
            }
        };

        $router->middleware($customMiddleware);

        $router->add('GET', '/test', function () use (&$executionLog) {
            $executionLog[] = 'action';
            return 'Test';
        });

        $request = new Request('/test', 'GET');
        $router->dispatch($request);

        expect($executionLog)->toBe(['custom-middleware', 'action']);
    });

    it('middleware fluent API for chaining', function () {
        $router = new Router();

        $result = $router
            ->middleware(fn($req, $next) => $next($req))
            ->middleware(fn($req, $next) => $next($req));

        expect($result)->toBe($router);
    });

    it('global middleware applies to all routes', function () {
        $router = new Router();
        $route1Calls = 0;
        $route2Calls = 0;

        // 全域中介軟體計數
        $router->middleware(function (Request $request, callable $next) use (&$route1Calls, &$route2Calls) {
            if ($request->getPath() === '/route1') {
                $route1Calls++;
            } elseif ($request->getPath() === '/route2') {
                $route2Calls++;
            }
            return $next($request);
        });

        $router->add('GET', '/route1', fn() => 'Route 1');
        $router->add('GET', '/route2', fn() => 'Route 2');

        $router->dispatch(new Request('/route1', 'GET'));
        $router->dispatch(new Request('/route2', 'GET'));

        expect($route1Calls)->toBe(1);
        expect($route2Calls)->toBe(1);
    });

    it('middleware works with route parameters', function () {
        $router = new Router();

        $router->add('GET', '/user/{id}', function ($id) {
            return "User: {$id}";
        })->middleware(function (Request $request, callable $next) {
            // 中介軟體可以讀取路由參數
            expect($request->getAttribute('id'))->toBe('123');
            return $next($request);
        });

        $request = new Request('/user/123', 'GET');
        $response = $router->dispatch($request);

        expect($response->getStatus())->toBe(200);
        expect($response->getContent())->toBe('User: 123');
    });

    it('middleware stack with before and after logic', function () {
        $router = new Router();
        $log = [];

        $router->middleware(function (Request $request, callable $next) use (&$log) {
            $log[] = 'M1-before';
            $response = $next($request);
            $log[] = 'M1-after';
            return $response;
        });

        $router->middleware(function (Request $request, callable $next) use (&$log) {
            $log[] = 'M2-before';
            $response = $next($request);
            $log[] = 'M2-after';
            return $response;
        });

        $router->add('GET', '/test', function () use (&$log) {
            $log[] = 'ACTION';
            return 'Test';
        });

        $request = new Request('/test', 'GET');
        $router->dispatch($request);

        expect($log)->toBe([
            'M1-before',
            'M2-before',
            'ACTION',
            'M2-after',
            'M1-after'
        ]);
    });

    it('middleware can handle errors and exceptions', function () {
        $router = new Router();

        // 錯誤處理中介軟體
        $router->middleware(function (Request $request, callable $next) {
            try {
                return $next($request);
            } catch (\Exception $e) {
                return new Response('Error: ' . $e->getMessage(), 500);
            }
        });

        $router->add('GET', '/error', function () {
            throw new \Exception('Something went wrong');
        });

        $request = new Request('/error', 'GET');
        $response = $router->dispatch($request);

        expect($response->getStatus())->toBe(500);
        expect($response->getContent())->toBe('Error: Something went wrong');
    });
});
