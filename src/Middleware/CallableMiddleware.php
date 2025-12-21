<?php

namespace Routini\Middleware;

use Routini\Contracts\MiddlewareInterface;
use Routini\Http\Request;
use Routini\Http\Response;

/**
 * Callable 中介軟體包裝器
 *
 * 將 Callable 包裝成符合 MiddlewareInterface 的物件
 */
class CallableMiddleware implements MiddlewareInterface
{
    /**
     * @param callable $callable Callable 函式
     */
    public function __construct(protected $callable)
    {
    }

    /**
     * 處理請求
     *
     * @param Request $request 請求物件
     * @param callable $next 下一個中介軟體
     * @return Response 回應物件
     */
    public function handle(Request $request, callable $next): Response
    {
        // 執行 callable，傳入 request 和 next
        $result = call_user_func($this->callable, $request, $next);

        // 如果返回 Response，直接使用
        if ($result instanceof Response) {
            return $result;
        }

        // 如果沒有返回值或返回 null，繼續執行下一個中介軟體
        if ($result === null) {
            return $next($request);
        }

        // 其他類型的返回值，包裝成 Response
        return new Response((string) $result);
    }
}
