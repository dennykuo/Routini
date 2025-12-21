<?php

namespace Routini\Contracts;

use Routini\Http\Request;
use Routini\Http\Response;

/**
 * 中介軟體介面
 *
 * 定義標準的中介軟體處理方法，支援洋蔥模型（Onion Model）
 */
interface MiddlewareInterface
{
    /**
     * 處理請求並傳遞給下一個中介軟體
     *
     * @param Request $request 請求物件
     * @param callable $next 下一個中介軟體或路由處理器
     * @return Response 回應物件
     */
    public function handle(Request $request, callable $next): Response;
}
