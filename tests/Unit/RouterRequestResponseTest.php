<?php

use Routini\Route;
use Routini\Http\Request;
use Routini\Http\Response;

beforeEach(function () {
    // 每個測試前重置路由
    Route::reset();
});

describe('Router with Request/Response', function () {
    it('can dispatch using Request object', function () {
        Route::get('/test', function () {
            return 'Hello from Request';
        });

        $request = new Request('/test', 'GET');
        $response = Route::getInstance()->dispatch($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe('Hello from Request');
        expect($response->getStatus())->toBe(200);
    });

    it('can return Response object from route action', function () {
        Route::get('/json', function () {
            return Response::json(['message' => 'success']);
        });

        $request = new Request('/json', 'GET');
        $response = Route::getInstance()->dispatch($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe(json_encode(['message' => 'success']));
        expect($response->getHeader('Content-Type'))->toBe('application/json');
    });

    it('automatically converts array to JSON response', function () {
        Route::get('/data', function () {
            return ['name' => 'John', 'age' => 30];
        });

        $request = new Request('/data', 'GET');
        $response = Route::getInstance()->dispatch($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe(json_encode(['name' => 'John', 'age' => 30]));
        expect($response->getHeader('Content-Type'))->toBe('application/json');
    });

    it('can access route parameters from Request attributes', function () {
        Route::get('/user/{id}', function ($id) {
            // 路由參數會作為函式參數傳入
            return "User ID: {$id}";
        });

        $request = new Request('/user/123', 'GET');
        $response = Route::getInstance()->dispatch($request);

        // 驗證參數有被正確傳入並返回
        expect($response->getContent())->toBe('User ID: 123');

        // 驗證 Request 的 attributes 有被設定
        expect($request->getAttribute('id'))->toBe('123');
    });

    it('returns 404 response for non-existent routes', function () {
        $request = new Request('/not-found', 'GET');
        $response = Route::getInstance()->dispatch($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatus())->toBe(404);
        expect($response->isNotFound())->toBeTrue();
    });

    it('handles POST requests with Request object', function () {
        Route::post('/submit', function () {
            return Response::json(['status' => 'created'], 201);
        });

        $request = new Request('/submit', 'POST');
        $response = Route::getInstance()->dispatch($request);

        expect($response->getStatus())->toBe(201);
        expect($response->getContent())->toBe(json_encode(['status' => 'created']));
    });

    it('can use convenience methods to create responses', function () {
        Route::get('/html', function () {
            return Response::html('<h1>Hello</h1>');
        });

        Route::get('/text', function () {
            return Response::text('Plain text');
        });

        Route::get('/redirect', function () {
            return Response::redirect('/new-location');
        });

        $htmlResponse = Route::getInstance()->dispatch(new Request('/html', 'GET'));
        expect($htmlResponse->getHeader('Content-Type'))->toBe('text/html; charset=UTF-8');

        $textResponse = Route::getInstance()->dispatch(new Request('/text', 'GET'));
        expect($textResponse->getHeader('Content-Type'))->toBe('text/plain; charset=UTF-8');

        $redirectResponse = Route::getInstance()->dispatch(new Request('/redirect', 'GET'));
        expect($redirectResponse->getStatus())->toBe(302);
        expect($redirectResponse->getHeader('Location'))->toBe('/new-location');
    });

    it('converts string results to Response objects', function () {
        Route::get('/string', function () {
            return 'Simple string';
        });

        $request = new Request('/string', 'GET');
        $response = Route::getInstance()->dispatch($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe('Simple string');
        expect($response->getStatus())->toBe(200);
    });

    it('converts numeric results to Response objects', function () {
        Route::get('/number', function () {
            return 42;
        });

        $request = new Request('/number', 'GET');
        $response = Route::getInstance()->dispatch($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe('42');
    });

    it('handles route with domain using Request object', function () {
        Route::domain('api.example.com')->group(function () {
            Route::get('/endpoint', function () {
                return Response::json(['domain' => 'api']);
            });
        });

        $request = new Request(
            '/endpoint',
            'GET',
            server: ['HTTP_HOST' => 'api.example.com']
        );

        $response = Route::getInstance()->dispatch($request);
        expect($response->getStatus())->toBe(200);
        expect($response->getContent())->toBe(json_encode(['domain' => 'api']));
    });

    it('respects HTTP method matching with Request object', function () {
        Route::post('/only-post', function () {
            return 'POST only';
        });

        // GET 請求應該 404
        $getRequest = new Request('/only-post', 'GET');
        $getResponse = Route::getInstance()->dispatch($getRequest);
        expect($getResponse->getStatus())->toBe(404);

        // POST 請求應該成功
        $postRequest = new Request('/only-post', 'POST');
        $postResponse = Route::getInstance()->dispatch($postRequest);
        expect($postResponse->getStatus())->toBe(200);
        expect($postResponse->getContent())->toBe('POST only');
    });

    it('maintains backward compatibility with old dispatch signature', function () {
        Route::get('/compat', function () {
            echo 'Backward Compatible';
        });

        ob_start();
        Route::getInstance()->dispatch('/compat', 'GET');
        $output = ob_get_clean();

        // 舊版用法會直接輸出
        expect($output)->toBe('Backward Compatible');
    });
});
