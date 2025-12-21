<?php

use Routini\UrlGenerator;
use Routini\RouteCollection;
use Routini\RouteItem;

describe('UrlGenerator', function () {
    it('generates simple URLs', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/users', fn() => 'users');
        $route->name('users.index');
        $collection->add($route);

        $generator = new UrlGenerator($collection);
        $url = $generator->generate('users.index');

        expect($url)->toBe('/users');
    });

    it('generates URLs with required parameters', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/user/{id}', fn() => 'user');
        $route->name('user.show');
        $collection->add($route);

        $generator = new UrlGenerator($collection);
        $url = $generator->generate('user.show', ['id' => 123]);

        expect($url)->toBe('/user/123');
    });

    it('generates URLs with multiple parameters', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/user/{id}/post/{postId}', fn() => 'post');
        $route->name('user.post');
        $collection->add($route);

        $generator = new UrlGenerator($collection);
        $url = $generator->generate('user.post', ['id' => 123, 'postId' => 456]);

        expect($url)->toBe('/user/123/post/456');
    });

    it('generates URLs with optional parameters when provided', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/user/{id?}', fn() => 'user');
        $route->name('user.optional');
        $collection->add($route);

        $generator = new UrlGenerator($collection);
        $url = $generator->generate('user.optional', ['id' => 123]);

        expect($url)->toBe('/user/123');
    });

    it('generates URLs with optional parameters omitted', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/user/{id?}', fn() => 'user');
        $route->name('user.optional');
        $collection->add($route);

        $generator = new UrlGenerator($collection);
        $url = $generator->generate('user.optional');

        expect($url)->toBe('/user');
    });

    it('generates URLs with query string for extra parameters', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/search', fn() => 'search');
        $route->name('search');
        $collection->add($route);

        $generator = new UrlGenerator($collection);
        $url = $generator->generate('search', ['q' => 'laravel', 'page' => 2]);

        expect($url)->toBe('/search?q=laravel&page=2');
    });

    it('generates URLs mixing route params and query string', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/user/{id}/profile', fn() => 'profile');
        $route->name('user.profile');
        $collection->add($route);

        $generator = new UrlGenerator($collection);
        $url = $generator->generate('user.profile', [
            'id' => 456,
            'tab' => 'settings',
            'section' => 'privacy'
        ]);

        expect($url)->toBe('/user/456/profile?tab=settings&section=privacy');
    });

    it('throws exception for non-existent route', function () {
        $collection = new RouteCollection();
        $generator = new UrlGenerator($collection);

        expect(fn() => $generator->generate('non.existent'))
            ->toThrow(\Exception::class, 'Route [non.existent] not defined.');
    });

    it('can check if route exists', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/users', fn() => 'users');
        $route->name('users.index');
        $collection->add($route);

        $generator = new UrlGenerator($collection);

        expect($generator->hasRoute('users.index'))->toBeTrue();
        expect($generator->hasRoute('posts.index'))->toBeFalse();
    });

    it('handles mixed required and optional parameters', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/category/{category}/post/{id?}', fn() => 'post');
        $route->name('category.post');
        $collection->add($route);

        $generator = new UrlGenerator($collection);

        // 提供所有參數
        $url1 = $generator->generate('category.post', [
            'category' => 'tech',
            'id' => 123
        ]);
        expect($url1)->toBe('/category/tech/post/123');

        // 省略選填參數
        $url2 = $generator->generate('category.post', ['category' => 'tech']);
        expect($url2)->toBe('/category/tech/post');
    });

    it('handles numeric parameter values', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/post/{id}', fn() => 'post');
        $route->name('post.show');
        $collection->add($route);

        $generator = new UrlGenerator($collection);
        $url = $generator->generate('post.show', ['id' => 42]);

        expect($url)->toBe('/post/42');
    });

    it('handles string parameter values', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/tag/{slug}', fn() => 'tag');
        $route->name('tag.show');
        $collection->add($route);

        $generator = new UrlGenerator($collection);
        $url = $generator->generate('tag.show', ['slug' => 'php-tips']);

        expect($url)->toBe('/tag/php-tips');
    });

    it('preserves parameter order in query string', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/search', fn() => 'search');
        $route->name('search');
        $collection->add($route);

        $generator = new UrlGenerator($collection);
        $url = $generator->generate('search', [
            'q' => 'test',
            'sort' => 'date',
            'order' => 'desc'
        ]);

        // http_build_query 會保持陣列順序
        expect($url)->toBe('/search?q=test&sort=date&order=desc');
    });

    it('works with routes added after generator creation', function () {
        $collection = new RouteCollection();
        $generator = new UrlGenerator($collection);

        // 生成器建立後才加入路由
        $route = new RouteItem(['GET'], '/new-route', fn() => 'new');
        $route->name('new.route');
        $collection->add($route);

        // 應該能找到新加入的路由
        $url = $generator->generate('new.route');
        expect($url)->toBe('/new-route');
    });

    it('can access route collection', function () {
        $collection = new RouteCollection();
        $generator = new UrlGenerator($collection);

        expect($generator->getRoutes())->toBe($collection);
    });

    it('handles empty parameter array', function () {
        $collection = new RouteCollection();
        $route = new RouteItem(['GET'], '/about', fn() => 'about');
        $route->name('about');
        $collection->add($route);

        $generator = new UrlGenerator($collection);
        $url = $generator->generate('about', []);

        expect($url)->toBe('/about');
    });
});
