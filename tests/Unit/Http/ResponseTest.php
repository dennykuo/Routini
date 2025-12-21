<?php

use Routini\Http\Response;

describe('Response', function () {
    it('can be created with default values', function () {
        $response = new Response();

        expect($response->getContent())->toBe('');
        expect($response->getStatus())->toBe(200);
        expect($response->getHeaders())->toBe([]);
    });

    it('can be created with custom values', function () {
        $response = new Response('Hello World', 201, ['X-Custom' => 'Value']);

        expect($response->getContent())->toBe('Hello World');
        expect($response->getStatus())->toBe(201);
        expect($response->getHeader('X-Custom'))->toBe('Value');
    });

    it('can set and get content', function () {
        $response = new Response();
        $response->setContent('New Content');

        expect($response->getContent())->toBe('New Content');
    });

    it('can chain setContent', function () {
        $response = (new Response())->setContent('Test');

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getContent())->toBe('Test');
    });

    it('can set and get status', function () {
        $response = new Response();
        $response->setStatus(404);

        expect($response->getStatus())->toBe(404);
    });

    it('can chain setStatus', function () {
        $response = (new Response())->setStatus(500);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatus())->toBe(500);
    });

    it('can add headers', function () {
        $response = new Response();
        $response->addHeader('Content-Type', 'application/json');
        $response->addHeader('X-Custom', 'Value');

        expect($response->getHeader('Content-Type'))->toBe('application/json');
        expect($response->getHeader('X-Custom'))->toBe('Value');
    });

    it('can chain addHeader', function () {
        $response = (new Response())
            ->addHeader('X-First', 'First')
            ->addHeader('X-Second', 'Second');

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getHeader('X-First'))->toBe('First');
        expect($response->getHeader('X-Second'))->toBe('Second');
    });

    it('can set headers in batch', function () {
        $response = new Response();
        $response->setHeaders([
            'Content-Type' => 'text/html',
            'X-Custom' => 'Value'
        ]);

        expect($response->getHeaders())->toBe([
            'Content-Type' => 'text/html',
            'X-Custom' => 'Value'
        ]);
    });

    it('can check if header exists', function () {
        $response = new Response('', 200, ['Authorization' => 'Bearer token']);

        expect($response->hasHeader('Authorization'))->toBeTrue();
        expect($response->hasHeader('Missing'))->toBeFalse();
    });

    it('can remove headers', function () {
        $response = new Response('', 200, ['X-Remove' => 'Value', 'X-Keep' => 'Value']);
        $response->removeHeader('X-Remove');

        expect($response->hasHeader('X-Remove'))->toBeFalse();
        expect($response->hasHeader('X-Keep'))->toBeTrue();
    });

    it('can create JSON response', function () {
        $data = ['name' => 'John', 'age' => 30];
        $response = Response::json($data);

        expect($response->getContent())->toBe(json_encode($data));
        expect($response->getStatus())->toBe(200);
        expect($response->getHeader('Content-Type'))->toBe('application/json');
    });

    it('can create JSON response with custom status', function () {
        $response = Response::json(['error' => 'Not Found'], 404);

        expect($response->getStatus())->toBe(404);
    });

    it('can create redirect response', function () {
        $response = Response::redirect('/new-location');

        expect($response->getStatus())->toBe(302);
        expect($response->getHeader('Location'))->toBe('/new-location');
        expect($response->getContent())->toBe('');
    });

    it('can create redirect response with custom status', function () {
        $response = Response::redirect('/permanent', 301);

        expect($response->getStatus())->toBe(301);
    });

    it('can create HTML response', function () {
        $response = Response::html('<h1>Hello</h1>');

        expect($response->getContent())->toBe('<h1>Hello</h1>');
        expect($response->getStatus())->toBe(200);
        expect($response->getHeader('Content-Type'))->toBe('text/html; charset=UTF-8');
    });

    it('can create text response', function () {
        $response = Response::text('Plain text');

        expect($response->getContent())->toBe('Plain text');
        expect($response->getStatus())->toBe(200);
        expect($response->getHeader('Content-Type'))->toBe('text/plain; charset=UTF-8');
    });

    it('can create not found response', function () {
        $response = Response::notFound();

        expect($response->getStatus())->toBe(404);
        expect($response->getContent())->toBe('404 - Not Found');
    });

    it('can create not found response with custom message', function () {
        $response = Response::notFound('Page not found');

        expect($response->getStatus())->toBe(404);
        expect($response->getContent())->toBe('Page not found');
    });

    it('can create error response', function () {
        $response = Response::error();

        expect($response->getStatus())->toBe(500);
        expect($response->getContent())->toBe('500 - Internal Server Error');
    });

    it('can create error response with custom status', function () {
        $response = Response::error('Bad Gateway', 502);

        expect($response->getStatus())->toBe(502);
        expect($response->getContent())->toBe('Bad Gateway');
    });

    it('can create no content response', function () {
        $response = Response::noContent();

        expect($response->getStatus())->toBe(204);
        expect($response->getContent())->toBe('');
    });

    it('can detect successful responses', function () {
        expect((new Response('', 200))->isSuccessful())->toBeTrue();
        expect((new Response('', 201))->isSuccessful())->toBeTrue();
        expect((new Response('', 299))->isSuccessful())->toBeTrue();
        expect((new Response('', 300))->isSuccessful())->toBeFalse();
        expect((new Response('', 400))->isSuccessful())->toBeFalse();
    });

    it('can detect redirect responses', function () {
        expect((new Response('', 301))->isRedirect())->toBeTrue();
        expect((new Response('', 302))->isRedirect())->toBeTrue();
        expect((new Response('', 200))->isRedirect())->toBeFalse();
        expect((new Response('', 400))->isRedirect())->toBeFalse();
    });

    it('can detect client error responses', function () {
        expect((new Response('', 400))->isClientError())->toBeTrue();
        expect((new Response('', 404))->isClientError())->toBeTrue();
        expect((new Response('', 499))->isClientError())->toBeTrue();
        expect((new Response('', 500))->isClientError())->toBeFalse();
        expect((new Response('', 200))->isClientError())->toBeFalse();
    });

    it('can detect server error responses', function () {
        expect((new Response('', 500))->isServerError())->toBeTrue();
        expect((new Response('', 502))->isServerError())->toBeTrue();
        expect((new Response('', 404))->isServerError())->toBeFalse();
        expect((new Response('', 200))->isServerError())->toBeFalse();
    });

    it('can detect error responses', function () {
        expect((new Response('', 400))->isError())->toBeTrue();
        expect((new Response('', 404))->isError())->toBeTrue();
        expect((new Response('', 500))->isError())->toBeTrue();
        expect((new Response('', 200))->isError())->toBeFalse();
        expect((new Response('', 302))->isError())->toBeFalse();
    });

    it('can check specific status codes', function () {
        expect((new Response('', 200))->isOk())->toBeTrue();
        expect((new Response('', 201))->isOk())->toBeFalse();

        expect((new Response('', 404))->isNotFound())->toBeTrue();
        expect((new Response('', 403))->isNotFound())->toBeFalse();

        expect((new Response('', 403))->isForbidden())->toBeTrue();
        expect((new Response('', 404))->isForbidden())->toBeFalse();
    });

    it('can get status text', function () {
        expect((new Response('', 200))->getStatusText())->toBe('OK');
        expect((new Response('', 404))->getStatusText())->toBe('Not Found');
        expect((new Response('', 500))->getStatusText())->toBe('Internal Server Error');
        expect((new Response('', 999))->getStatusText())->toBe('Unknown');
    });

    it('can be converted to string', function () {
        $response = new Response('Hello World');

        expect((string) $response)->toBe('Hello World');
    });

    it('handles various content types when converting to string', function () {
        $response1 = new Response('String content');
        expect((string) $response1)->toBe('String content');

        $response2 = new Response(123);
        expect((string) $response2)->toBe('123');

        $response3 = new Response(null);
        expect((string) $response3)->toBe('');
    });

    it('returns null for non-existent header', function () {
        $response = new Response();

        expect($response->getHeader('Non-Existent'))->toBeNull();
    });

    it('can fluently build a complex response', function () {
        $response = (new Response())
            ->setContent('Complex response')
            ->setStatus(201)
            ->addHeader('X-Custom-1', 'Value 1')
            ->addHeader('X-Custom-2', 'Value 2');

        expect($response->getContent())->toBe('Complex response');
        expect($response->getStatus())->toBe(201);
        expect($response->getHeader('X-Custom-1'))->toBe('Value 1');
        expect($response->getHeader('X-Custom-2'))->toBe('Value 2');
    });
});
