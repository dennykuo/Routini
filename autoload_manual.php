<?php

// 如果沒有安裝 Composer，請建使用此檔案來手動載入類別

// 1. 載入類別
spl_autoload_register(function ($class) {
    // 設定前綴
    $prefix = 'Routini\\';
    $base_dir = __DIR__ . '/src/';

    // 檢查類別是否使用了這個前綴
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // 取得相對類別名稱
    $relative_class = substr($class, $len);

    // 將 namespace 轉換為檔案路徑
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // 如果檔案存在則引入
    if (file_exists($file)) {
        require $file;
    }
});

// 2. 載入 Helper 函式
require_once __DIR__ . '/src/helpers.php';