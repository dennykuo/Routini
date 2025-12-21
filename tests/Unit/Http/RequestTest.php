<?php

use Routini\Http\Request;

describe('Request', function () {
    it('can be created with basic parameters', function () {
        $request = new Request('/test', 'GET');

        expect($request->getUri())->toBe('/test');
        expect($request->getMethod())->toBe('GET');
        expect($request->getPath())->toBe('/test');
    });

    it('normalizes HTTP method to uppercase', function () {
        $request = new Request('/test', 'post');

        expect($request->getMethod())->toBe('POST');
    });

    it('extracts path from URI with query string', function () {
        $request = new Request('/test?foo=bar', 'GET');

        expect($request->getUri())->toBe('/test?foo=bar');
        expect($request->getPath())->toBe('/test');
    });

    it('can access query parameters', function () {
        $request = new Request(
            '/test',
            'GET',
            query: ['name' => 'John', 'age' => '30']
        );

        expect($request->query('name'))->toBe('John');
        expect($request->query('age'))->toBe('30');
        expect($request->query('missing'))->toBeNull();
        expect($request->query('missing', 'default'))->toBe('default');
    });

    it('can access all query parameters', function () {
        $request = new Request(
            '/test',
            'GET',
            query: ['name' => 'John', 'age' => '30']
        );

        expect($request->allQuery())->toBe(['name' => 'John', 'age' => '30']);
    });

    it('can access POST parameters', function () {
        $request = new Request(
            '/test',
            'POST',
            post: ['username' => 'john', 'password' => 'secret']
        );

        expect($request->post('username'))->toBe('john');
        expect($request->post('password'))->toBe('secret');
        expect($request->post('missing'))->toBeNull();
        expect($request->post('missing', 'default'))->toBe('default');
    });

    it('can access all POST parameters', function () {
        $request = new Request(
            '/test',
            'POST',
            post: ['username' => 'john', 'password' => 'secret']
        );

        expect($request->allPost())->toBe(['username' => 'john', 'password' => 'secret']);
    });

    it('can access input with POST priority', function () {
        $request = new Request(
            '/test',
            'POST',
            query: ['name' => 'from-query'],
            post: ['name' => 'from-post']
        );

        expect($request->input('name'))->toBe('from-post');
    });

    it('falls back to query when POST not available', function () {
        $request = new Request(
            '/test',
            'GET',
            query: ['name' => 'from-query']
        );

        expect($request->input('name'))->toBe('from-query');
    });

    it('can access headers', function () {
        $request = new Request(
            '/test',
            'GET',
            headers: ['Authorization' => 'Bearer token123', 'Content-Type' => 'application/json']
        );

        expect($request->getHeader('Authorization'))->toBe('Bearer token123');
        expect($request->getHeader('Content-Type'))->toBe('application/json');
        expect($request->getHeader('Missing'))->toBeNull();
    });

    it('can check if header exists', function () {
        $request = new Request(
            '/test',
            'GET',
            headers: ['Authorization' => 'Bearer token123']
        );

        expect($request->hasHeader('Authorization'))->toBeTrue();
        expect($request->hasHeader('Missing'))->toBeFalse();
    });

    it('can get all headers', function () {
        $headers = ['Authorization' => 'Bearer token123', 'Content-Type' => 'application/json'];
        $request = new Request('/test', 'GET', headers: $headers);

        expect($request->getHeaders())->toBe($headers);
    });

    it('can access host', function () {
        $request = new Request(
            '/test',
            'GET',
            server: ['HTTP_HOST' => 'example.com']
        );

        expect($request->getHost())->toBe('example.com');
    });

    it('can access server parameters', function () {
        $request = new Request(
            '/test',
            'GET',
            server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com']
        );

        expect($request->server('REMOTE_ADDR'))->toBe('127.0.0.1');
        expect($request->server('HTTP_HOST'))->toBe('example.com');
        expect($request->server('MISSING'))->toBeNull();
        expect($request->server('MISSING', 'default'))->toBe('default');
    });

    it('can set and get custom attributes', function () {
        $request = new Request('/test', 'GET');

        $request->setAttribute('user_id', 123);
        $request->setAttribute('role', 'admin');

        expect($request->getAttribute('user_id'))->toBe(123);
        expect($request->getAttribute('role'))->toBe('admin');
        expect($request->getAttribute('missing'))->toBeNull();
        expect($request->getAttribute('missing', 'default'))->toBe('default');
    });

    it('can get all attributes', function () {
        $request = new Request('/test', 'GET');

        $request->setAttribute('user_id', 123);
        $request->setAttribute('role', 'admin');

        expect($request->getAttributes())->toBe([
            'user_id' => 123,
            'role' => 'admin'
        ]);
    });

    it('can set attributes in batch', function () {
        $request = new Request('/test', 'GET');

        $request->setAttributes(['id' => '1', 'name' => 'John']);

        expect($request->getAttribute('id'))->toBe('1');
        expect($request->getAttribute('name'))->toBe('John');
    });

    it('can detect AJAX requests', function () {
        $request = new Request(
            '/test',
            'GET',
            headers: ['X-Requested-With' => 'XMLHttpRequest']
        );

        expect($request->isAjax())->toBeTrue();

        $request2 = new Request('/test', 'GET');
        expect($request2->isAjax())->toBeFalse();
    });

    it('can detect JSON requests', function () {
        $request = new Request(
            '/test',
            'POST',
            headers: ['Content-Type' => 'application/json']
        );

        expect($request->isJson())->toBeTrue();

        $request2 = new Request('/test', 'POST');
        expect($request2->isJson())->toBeFalse();
    });

    it('can check HTTP method', function () {
        $request = new Request('/test', 'POST');

        expect($request->isMethod('POST'))->toBeTrue();
        expect($request->isMethod('post'))->toBeTrue();
        expect($request->isMethod('GET'))->toBeFalse();
    });

    it('has convenience methods for common HTTP methods', function () {
        $getRequest = new Request('/test', 'GET');
        expect($getRequest->isGet())->toBeTrue();
        expect($getRequest->isPost())->toBeFalse();

        $postRequest = new Request('/test', 'POST');
        expect($postRequest->isPost())->toBeTrue();
        expect($postRequest->isGet())->toBeFalse();
    });

    it('can access file uploads', function () {
        $files = [
            'avatar' => [
                'name' => 'photo.jpg',
                'type' => 'image/jpeg',
                'size' => 12345,
                'tmp_name' => '/tmp/phpXXXXXX',
                'error' => 0
            ]
        ];

        $request = new Request('/test', 'POST', files: $files);

        expect($request->file('avatar'))->toBe($files['avatar']);
        expect($request->file('missing'))->toBeNull();
        expect($request->allFiles())->toBe($files);
    });

    it('handles empty host gracefully', function () {
        $request = new Request('/test', 'GET');

        expect($request->getHost())->toBe('');
    });

    it('handles root path correctly', function () {
        $request = new Request('/', 'GET');

        expect($request->getPath())->toBe('/');
    });
});
