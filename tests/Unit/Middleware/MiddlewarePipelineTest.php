<?php

use Routini\Middleware\MiddlewarePipeline;
use Routini\Contracts\MiddlewareInterface;
use Routini\Http\Request;
use Routini\Http\Response;

describe('MiddlewarePipeline', function () {
    it('executes middleware in correct order (onion model)', function () {
        $pipeline = new MiddlewarePipeline();
        $executionOrder = [];

        // 第一層中介軟體
        $middleware1 = new class($executionOrder) implements MiddlewareInterface {
            public function __construct(private array &$order) {}

            public function handle(Request $request, callable $next): Response {
                $this->order[] = 'middleware1-before';
                $response = $next($request);
                $this->order[] = 'middleware1-after';
                return $response;
            }
        };

        // 第二層中介軟體
        $middleware2 = new class($executionOrder) implements MiddlewareInterface {
            public function __construct(private array &$order) {}

            public function handle(Request $request, callable $next): Response {
                $this->order[] = 'middleware2-before';
                $response = $next($request);
                $this->order[] = 'middleware2-after';
                return $response;
            }
        };

        $pipeline->pipe($middleware1)->pipe($middleware2);

        $request = new Request('/test', 'GET');
        $destination = function ($request) use (&$executionOrder) {
            $executionOrder[] = 'destination';
            return new Response('OK');
        };

        $response = $pipeline->process($request, $destination);

        // 洋蔥模型：外層先執行 before，內層先執行 after
        expect($executionOrder)->toBe([
            'middleware1-before',
            'middleware2-before',
            'destination',
            'middleware2-after',
            'middleware1-after'
        ]);

        expect($response->getContent())->toBe('OK');
    });

    it('passes request through all middleware layers', function () {
        $pipeline = new MiddlewarePipeline();

        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response {
                // 中介軟體可以檢查並修改 request
                expect($request->getUri())->toBe('/test');
                return $next($request);
            }
        };

        $pipeline->pipe($middleware);

        $request = new Request('/test', 'GET');
        $response = $pipeline->process($request, fn($req) => new Response('Success'));

        expect($response->getContent())->toBe('Success');
    });

    it('allows middleware to modify response', function () {
        $pipeline = new MiddlewarePipeline();

        $middleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response {
                $response = $next($request);
                // 在 after 階段修改回應
                return $response->withHeader('X-Custom', 'Modified');
            }
        };

        $pipeline->pipe($middleware);

        $request = new Request('/test', 'GET');
        $response = $pipeline->process($request, fn($req) => new Response('Original'));

        expect($response->getHeader('X-Custom'))->toBe('Modified');
        expect($response->getContent())->toBe('Original');
    });

    it('allows middleware to short-circuit the pipeline', function () {
        $pipeline = new MiddlewarePipeline();
        $destinationCalled = false;

        // 第一層：直接返回，不呼叫 next
        $middleware1 = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response {
                // 短路：直接返回回應
                return new Response('Short-circuited', 403);
            }
        };

        // 第二層：永遠不會執行
        $middleware2 = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response {
                throw new \Exception('This should never execute');
            }
        };

        $pipeline->pipe($middleware1)->pipe($middleware2);

        $request = new Request('/test', 'GET');
        $destination = function ($request) use (&$destinationCalled) {
            $destinationCalled = true;
            return new Response('Destination');
        };

        $response = $pipeline->process($request, $destination);

        expect($response->getContent())->toBe('Short-circuited');
        expect($response->getStatus())->toBe(403);
        expect($destinationCalled)->toBeFalse();
    });

    it('supports callable middleware', function () {
        $pipeline = new MiddlewarePipeline();

        $callableMiddleware = function (Request $request, callable $next) {
            $response = $next($request);
            return $response->withHeader('X-Callable', 'true');
        };

        $pipeline->pipe($callableMiddleware);

        $request = new Request('/test', 'GET');
        $response = $pipeline->process($request, fn($req) => new Response('OK'));

        expect($response->getHeader('X-Callable'))->toBe('true');
    });

    it('supports middleware class names', function () {
        $pipeline = new MiddlewarePipeline();

        // 使用完整類別名稱
        $className = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response {
                $response = $next($request);
                return $response->withHeader('X-Class', 'instantiated');
            }
        };
        $className = get_class($className);

        $pipeline->pipe($className);

        $request = new Request('/test', 'GET');
        $response = $pipeline->process($request, fn($req) => new Response('OK'));

        expect($response->getHeader('X-Class'))->toBe('instantiated');
    });

    it('handles empty pipeline', function () {
        $pipeline = new MiddlewarePipeline();

        $request = new Request('/test', 'GET');
        $response = $pipeline->process($request, fn($req) => new Response('Direct'));

        expect($response->getContent())->toBe('Direct');
    });

    it('handles multiple middleware layers', function () {
        $pipeline = new MiddlewarePipeline();
        $headers = [];

        for ($i = 1; $i <= 5; $i++) {
            $pipeline->pipe(new class($i, $headers) implements MiddlewareInterface {
                public function __construct(
                    private int $number,
                    private array &$headers
                ) {}

                public function handle(Request $request, callable $next): Response {
                    $response = $next($request);
                    $this->headers["X-Layer-{$this->number}"] = "passed";
                    return $response->withHeader("X-Layer-{$this->number}", 'passed');
                }
            });
        }

        $request = new Request('/test', 'GET');
        $response = $pipeline->process($request, fn($req) => new Response('OK'));

        expect($response->getContent())->toBe('OK');
        expect($response->getHeader('X-Layer-1'))->toBe('passed');
        expect($response->getHeader('X-Layer-5'))->toBe('passed');
    });

    it('throws exception for invalid middleware type', function () {
        $pipeline = new MiddlewarePipeline();
        $pipeline->pipe('NonExistentClass');

        $request = new Request('/test', 'GET');

        expect(fn() => $pipeline->process($request, fn($req) => new Response('OK')))
            ->toThrow(\InvalidArgumentException::class, 'Middleware must be an instance of MiddlewareInterface, a class name, or a callable');
    });

    it('allows callable middleware to return null and continue', function () {
        $pipeline = new MiddlewarePipeline();

        $middleware = function (Request $request, callable $next) {
            // 返回 null，讓 CallableMiddleware 繼續執行
            return null;
        };

        $pipeline->pipe($middleware);

        $request = new Request('/test', 'GET');
        $response = $pipeline->process($request, fn($req) => new Response('Continued'));

        expect($response->getContent())->toBe('Continued');
    });

    it('allows callable middleware to return string', function () {
        $pipeline = new MiddlewarePipeline();

        $middleware = function (Request $request, callable $next) {
            // 返回字串，應該被包裝成 Response
            return 'String response';
        };

        $pipeline->pipe($middleware);

        $request = new Request('/test', 'GET');
        $response = $pipeline->process($request, fn($req) => new Response('Should not reach'));

        expect($response->getContent())->toBe('String response');
    });

    it('can pipe middleware fluently', function () {
        $pipeline = new MiddlewarePipeline();

        $result = $pipeline
            ->pipe(fn($req, $next) => $next($req))
            ->pipe(fn($req, $next) => $next($req))
            ->pipe(fn($req, $next) => $next($req));

        expect($result)->toBe($pipeline);
    });

    it('middleware can access and modify request attributes', function () {
        $pipeline = new MiddlewarePipeline();

        $middleware1 = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response {
                $request->setAttribute('user_id', 123);
                return $next($request);
            }
        };

        $middleware2 = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response {
                $request->setAttribute('role', 'admin');
                return $next($request);
            }
        };

        $pipeline->pipe($middleware1)->pipe($middleware2);

        $capturedRequest = null;
        $destination = function ($request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response('OK');
        };

        $request = new Request('/test', 'GET');
        $pipeline->process($request, $destination);

        expect($capturedRequest->getAttribute('user_id'))->toBe(123);
        expect($capturedRequest->getAttribute('role'))->toBe('admin');
    });

    it('middleware can authenticate and authorize', function () {
        $pipeline = new MiddlewarePipeline();

        // 驗證中介軟體
        $authMiddleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response {
                $token = $request->getHeader('Authorization');

                if ($token !== 'Bearer valid-token') {
                    return new Response('Unauthorized', 401);
                }

                $request->setAttribute('authenticated', true);
                return $next($request);
            }
        };

        $pipeline->pipe($authMiddleware);

        // 測試未授權
        $request1 = new Request('/admin', 'GET');
        $response1 = $pipeline->process($request1, fn($req) => new Response('Admin Area'));

        expect($response1->getStatus())->toBe(401);
        expect($response1->getContent())->toBe('Unauthorized');

        // 測試已授權
        $request2 = new Request('/admin', 'GET', headers: ['Authorization' => 'Bearer valid-token']);
        $response2 = $pipeline->process($request2, fn($req) => new Response('Admin Area'));

        expect($response2->getStatus())->toBe(200);
        expect($response2->getContent())->toBe('Admin Area');
    });
});
