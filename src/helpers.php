<?php

use Routini\Route;

if (!function_exists('route')) {
    /**
     * 產生路由網址
     * @param string $name
     * @param array $parameters
     * @return string
     */
    function route($name, $parameters = [])
    {
        // 透過 Facade 取得 Router 實例並呼叫 url 方法
        return Route::getInstance()->url($name, $parameters);
    }
}