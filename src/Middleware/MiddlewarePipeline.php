<?php

namespace Routini\Middleware;

use Routini\Contracts\MiddlewareInterface;
use Routini\Http\Request;
use Routini\Http\Response;

/**
 * 中介軟體管道
 *
 * 實作洋蔥模型（Onion Model），讓中介軟體可以在請求前後執行
 */
class MiddlewarePipeline
{
    /**
     * 中介軟體堆疊
     *
     * @var array
     */
    protected array $middlewares = [];

    /**
     * 加入中介軟體到管道
     *
     * @param string|MiddlewareInterface|callable $middleware 中介軟體
     * @return self
     */
    public function pipe($middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * 執行中介軟體管道（洋蔥模型）
     *
     * @param Request $request 請求物件
     * @param callable $destination 最終目的地（路由處理器）
     * @return Response 回應物件
     */
    public function process(Request $request, callable $destination): Response
    {
        // 使用 array_reduce 建立洋蔥層
        // 從最後一個中介軟體開始，層層包裹
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            function ($next, $middleware) {
                return function ($request) use ($middleware, $next) {
                    // 解析中介軟體實例
                    $instance = $this->resolveMiddleware($middleware);

                    // 執行中介軟體的 handle 方法
                    return $instance->handle($request, $next);
                };
            },
            $destination // 最內層是路由動作
        );

        // 開始執行管道
        return $pipeline($request);
    }

    /**
     * 解析中介軟體為可執行的實例
     *
     * @param mixed $middleware 中介軟體
     * @return MiddlewareInterface
     */
    protected function resolveMiddleware($middleware): MiddlewareInterface
    {
        // 已經是 MiddlewareInterface 實例
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        // 是類別名稱，實例化它
        if (is_string($middleware) && class_exists($middleware)) {
            $instance = new $middleware();

            // 檢查是否已經實作 MiddlewareInterface
            if ($instance instanceof MiddlewareInterface) {
                return $instance;
            }

            // 向後相容：舊版中介軟體（有 handle() 方法但不接受參數，返回 boolean）
            if (method_exists($instance, 'handle')) {
                return $this->wrapLegacyMiddleware($instance);
            }

            throw new \InvalidArgumentException(
                'Middleware class must implement MiddlewareInterface or have a handle() method'
            );
        }

        // 是 Callable，包裝成 MiddlewareInterface
        if (is_callable($middleware)) {
            return new CallableMiddleware($middleware);
        }

        throw new \InvalidArgumentException(
            'Middleware must be an instance of MiddlewareInterface, a class name, or a callable'
        );
    }

    /**
     * 包裝舊版中介軟體為新版介面（向後相容）
     *
     * 舊版中介軟體特徵：
     * - handle() 方法不接受參數
     * - 返回 boolean（true 繼續，false 中斷）
     *
     * @param object $legacyMiddleware 舊版中介軟體實例
     * @return MiddlewareInterface
     */
    protected function wrapLegacyMiddleware(object $legacyMiddleware): MiddlewareInterface
    {
        return new class($legacyMiddleware) implements MiddlewareInterface {
            public function __construct(private object $legacy) {}

            public function handle(Request $request, callable $next): Response
            {
                // 執行舊版中介軟體
                $result = $this->legacy->handle();

                // 如果返回 false，中斷並返回 403
                if ($result === false) {
                    return new Response('', 403);
                }

                // 否則繼續執行下一個中介軟體
                return $next($request);
            }
        };
    }

    /**
     * 批次加入中介軟體
     *
     * @param array $middlewares 中介軟體陣列
     * @return self
     */
    public function through(array $middlewares): self
    {
        foreach ($middlewares as $middleware) {
            $this->pipe($middleware);
        }
        return $this;
    }

    /**
     * 取得所有中介軟體
     *
     * @return array
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}
