<?php

use Routini\Matching\RegexMatcher;
use Routini\RouteItem;

describe('RegexMatcher', function () {
    beforeEach(function () {
        $this->matcher = new RegexMatcher();
    });

    it('matches exact routes', function () {
        $route = new RouteItem(['GET'], '/users', fn() => 'test');

        expect($this->matcher->match($route, '/users'))->toBeTrue();
        expect($this->matcher->match($route, '/posts'))->toBeFalse();
        expect($this->matcher->match($route, '/users/123'))->toBeFalse();
    });

    it('matches routes with required parameters', function () {
        $route = new RouteItem(['GET'], '/user/{id}', fn() => 'test');

        expect($this->matcher->match($route, '/user/123'))->toBeTrue();
        expect($this->matcher->match($route, '/user/abc'))->toBeTrue();
        expect($this->matcher->match($route, '/user'))->toBeFalse();
        expect($this->matcher->match($route, '/user/'))->toBeFalse();
        expect($this->matcher->match($route, '/user/123/extra'))->toBeFalse();
    });

    it('matches routes with multiple parameters', function () {
        $route = new RouteItem(['GET'], '/user/{id}/post/{slug}', fn() => 'test');

        expect($this->matcher->match($route, '/user/123/post/hello'))->toBeTrue();
        expect($this->matcher->match($route, '/user/abc/post/world'))->toBeTrue();
        expect($this->matcher->match($route, '/user/123/post'))->toBeFalse();
        expect($this->matcher->match($route, '/user/123'))->toBeFalse();
    });

    it('matches routes with optional parameters', function () {
        $route = new RouteItem(['GET'], '/user/{id?}', fn() => 'test');

        expect($this->matcher->match($route, '/user'))->toBeTrue();
        expect($this->matcher->match($route, '/user/123'))->toBeTrue();
        expect($this->matcher->match($route, '/user/abc'))->toBeTrue();
        expect($this->matcher->match($route, '/user/123/extra'))->toBeFalse();
    });

    it('matches routes with mixed required and optional parameters', function () {
        $route = new RouteItem(['GET'], '/user/{id}/posts/{slug?}', fn() => 'test');

        expect($this->matcher->match($route, '/user/123/posts'))->toBeTrue();
        expect($this->matcher->match($route, '/user/123/posts/hello'))->toBeTrue();
        expect($this->matcher->match($route, '/user'))->toBeFalse();
        expect($this->matcher->match($route, '/user/123'))->toBeFalse();
    });

    it('extracts required parameters correctly', function () {
        $route = new RouteItem(['GET'], '/user/{id}', fn() => 'test');
        $params = $this->matcher->extractParameters($route, '/user/123');

        expect($params)->toBe(['id' => '123']);
    });

    it('extracts multiple parameters correctly', function () {
        $route = new RouteItem(['GET'], '/user/{id}/post/{slug}', fn() => 'test');
        $params = $this->matcher->extractParameters($route, '/user/456/post/hello-world');

        expect($params)->toBe([
            'id' => '456',
            'slug' => 'hello-world'
        ]);
    });

    it('extracts optional parameters when present', function () {
        $route = new RouteItem(['GET'], '/user/{id?}', fn() => 'test');

        $params1 = $this->matcher->extractParameters($route, '/user/123');
        expect($params1)->toBe(['id' => '123']);

        $params2 = $this->matcher->extractParameters($route, '/user');
        expect($params2)->toBe([]);
    });

    it('extracts mixed parameters correctly', function () {
        $route = new RouteItem(['GET'], '/user/{id}/posts/{slug?}', fn() => 'test');

        // 有選填參數
        $params1 = $this->matcher->extractParameters($route, '/user/123/posts/my-post');
        expect($params1)->toBe([
            'id' => '123',
            'slug' => 'my-post'
        ]);

        // 無選填參數
        $params2 = $this->matcher->extractParameters($route, '/user/123/posts');
        expect($params2)->toBe(['id' => '123']);
    });

    it('returns empty array when route does not match', function () {
        $route = new RouteItem(['GET'], '/user/{id}', fn() => 'test');
        $params = $this->matcher->extractParameters($route, '/posts/123');

        expect($params)->toBe([]);
    });

    it('handles parameters with special characters', function () {
        $route = new RouteItem(['GET'], '/user/{id}', fn() => 'test');

        expect($this->matcher->match($route, '/user/abc-123'))->toBeTrue();
        expect($this->matcher->match($route, '/user/test_123'))->toBeTrue();

        $params = $this->matcher->extractParameters($route, '/user/test-456_xyz');
        expect($params)->toBe(['id' => 'test-456_xyz']);
    });

    it('handles root path', function () {
        $route = new RouteItem(['GET'], '/', fn() => 'test');

        expect($this->matcher->match($route, '/'))->toBeTrue();
        expect($this->matcher->match($route, '/user'))->toBeFalse();
    });

    it('handles nested paths', function () {
        $route = new RouteItem(['GET'], '/api/v1/users/{id}', fn() => 'test');

        expect($this->matcher->match($route, '/api/v1/users/123'))->toBeTrue();
        expect($this->matcher->match($route, '/api/v2/users/123'))->toBeFalse();
    });

    it('does not match routes with trailing slashes inconsistently', function () {
        $route = new RouteItem(['GET'], '/users', fn() => 'test');

        expect($this->matcher->match($route, '/users'))->toBeTrue();
        expect($this->matcher->match($route, '/users/'))->toBeFalse();
    });

    it('handles consecutive parameters', function () {
        $route = new RouteItem(['GET'], '/{category}/{slug}', fn() => 'test');

        expect($this->matcher->match($route, '/tech/my-article'))->toBeTrue();

        $params = $this->matcher->extractParameters($route, '/tech/my-article');
        expect($params)->toBe([
            'category' => 'tech',
            'slug' => 'my-article'
        ]);
    });

    it('handles parameters with underscores and numbers in names', function () {
        $route = new RouteItem(['GET'], '/item/{item_id123}', fn() => 'test');

        expect($this->matcher->match($route, '/item/999'))->toBeTrue();

        $params = $this->matcher->extractParameters($route, '/item/999');
        expect($params)->toBe(['item_id123' => '999']);
    });
});
