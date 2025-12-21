<?php

use Routini\RouteCollection;
use Routini\RouteItem;

describe('RouteCollection', function () {
    it('starts empty', function () {
        $collection = new RouteCollection();

        expect($collection->isEmpty())->toBeTrue();
        expect($collection->count())->toBe(0);
        expect($collection->all())->toBe([]);
    });

    it('can add routes', function () {
        $collection = new RouteCollection();

        $route1 = new RouteItem(['GET'], '/users', fn() => 'users');
        $route2 = new RouteItem(['POST'], '/posts', fn() => 'posts');

        $collection->add($route1);
        $collection->add($route2);

        expect($collection->count())->toBe(2);
        expect($collection->isEmpty())->toBeFalse();
        expect($collection->all())->toBe([$route1, $route2]);
    });

    it('can find routes by name', function () {
        $collection = new RouteCollection();

        $route1 = new RouteItem(['GET'], '/users', fn() => 'users');
        $route1->name('users.index');

        $route2 = new RouteItem(['GET'], '/posts', fn() => 'posts');
        $route2->name('posts.index');

        $collection->add($route1);
        $collection->add($route2);

        $found = $collection->findByName('users.index');
        expect($found)->toBe($route1);

        $found2 = $collection->findByName('posts.index');
        expect($found2)->toBe($route2);
    });

    it('returns null for non-existent route name', function () {
        $collection = new RouteCollection();

        $route = new RouteItem(['GET'], '/users', fn() => 'users');
        $route->name('users.index');
        $collection->add($route);

        $found = $collection->findByName('non.existent');
        expect($found)->toBeNull();
    });

    it('can check if route name exists', function () {
        $collection = new RouteCollection();

        $route = new RouteItem(['GET'], '/users', fn() => 'users');
        $route->name('users.index');
        $collection->add($route);

        expect($collection->hasRoute('users.index'))->toBeTrue();
        expect($collection->hasRoute('posts.index'))->toBeFalse();
    });

    it('can get all named routes', function () {
        $collection = new RouteCollection();

        $route1 = new RouteItem(['GET'], '/users', fn() => 'users');
        $route1->name('users.index');

        $route2 = new RouteItem(['GET'], '/posts', fn() => 'posts');
        $route2->name('posts.index');

        $route3 = new RouteItem(['GET'], '/about', fn() => 'about');
        // route3 沒有名稱

        $collection->add($route1);
        $collection->add($route2);
        $collection->add($route3);

        $namedRoutes = $collection->getNamedRoutes();

        expect($namedRoutes)->toHaveKey('users.index');
        expect($namedRoutes)->toHaveKey('posts.index');
        expect($namedRoutes)->not->toHaveKey('about');
        expect(count($namedRoutes))->toBe(2);
    });

    it('can clear all routes', function () {
        $collection = new RouteCollection();

        $route1 = new RouteItem(['GET'], '/users', fn() => 'users');
        $route1->name('users.index');

        $route2 = new RouteItem(['POST'], '/posts', fn() => 'posts');

        $collection->add($route1);
        $collection->add($route2);

        expect($collection->count())->toBe(2);

        $collection->clear();

        expect($collection->count())->toBe(0);
        expect($collection->isEmpty())->toBeTrue();
        expect($collection->all())->toBe([]);
        expect($collection->getNamedRoutes())->toBe([]);
    });

    it('can find routes even when name is set after adding', function () {
        $collection = new RouteCollection();

        $route = new RouteItem(['GET'], '/users', fn() => 'users');
        $collection->add($route);

        // 路由加入後才設定名稱
        $route->name('users.index');

        // RouteCollection 會透過遍歷查找（向後相容）
        $found = $collection->findByName('users.index');
        expect($found)->toBe($route);

        // 第二次查找會使用快取的名稱對應表
        $found2 = $collection->findByName('users.index');
        expect($found2)->toBe($route);
    });

    it('handles multiple routes without names', function () {
        $collection = new RouteCollection();

        $route1 = new RouteItem(['GET'], '/page1', fn() => 'page1');
        $route2 = new RouteItem(['GET'], '/page2', fn() => 'page2');
        $route3 = new RouteItem(['GET'], '/page3', fn() => 'page3');

        $collection->add($route1);
        $collection->add($route2);
        $collection->add($route3);

        expect($collection->count())->toBe(3);
        expect($collection->getNamedRoutes())->toBe([]);
    });

    it('maintains route order', function () {
        $collection = new RouteCollection();

        $routes = [];
        for ($i = 1; $i <= 5; $i++) {
            $route = new RouteItem(['GET'], "/route{$i}", fn() => "route{$i}");
            $routes[] = $route;
            $collection->add($route);
        }

        $all = $collection->all();

        expect($all)->toBe($routes);
        expect($all[0])->toBe($routes[0]);
        expect($all[4])->toBe($routes[4]);
    });

    it('can handle route with same URI but different methods', function () {
        $collection = new RouteCollection();

        $getRoute = new RouteItem(['GET'], '/users', fn() => 'index');
        $getRoute->name('users.index');

        $postRoute = new RouteItem(['POST'], '/users', fn() => 'create');
        $postRoute->name('users.create');

        $collection->add($getRoute);
        $collection->add($postRoute);

        expect($collection->count())->toBe(2);
        expect($collection->findByName('users.index'))->toBe($getRoute);
        expect($collection->findByName('users.create'))->toBe($postRoute);
    });

    it('last route wins when duplicate names', function () {
        $collection = new RouteCollection();

        $route1 = new RouteItem(['GET'], '/first', fn() => 'first');
        $route1->name('duplicate');

        $route2 = new RouteItem(['GET'], '/second', fn() => 'second');
        $route2->name('duplicate');

        $collection->add($route1);
        $collection->add($route2);

        // 相同名稱，後加入的會覆蓋
        $found = $collection->findByName('duplicate');
        expect($found)->toBe($route2);
    });
});
