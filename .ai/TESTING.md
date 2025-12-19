# Testing Guide (測試指南)

## 框架 (Framework)
使用 **Pest** (基於 PHPUnit) 進行測試。

- **Config**: `phpunit.xml`
- **Test Directory**: `tests/`
    - `Feature/`: 功能測試 (RouteTest, DomainRoutingTest)。
    - `Unit/`: (目前為空，保留給隔離的 Unit Tests)。

## 執行測試 (Running Tests)
執行所有測試：
```bash
vendor/bin/pest
```

執行特定測試檔案：
```bash
vendor/bin/pest tests/Feature/DomainRoutingTest.php
```

## 撰寫測試 (Writing Tests)

### Singleton Resetting (重置單例)
因為 `Route` 使用 Singleton `Router` 實例，狀態會在測試之間保留。你 **務必** 在每個測試前重置該實例。請使用 PHP Reflection 來達成：

```php
beforeEach(function () {
    $reflection = new ReflectionClass(\MiniLaravel\Route::class);
    $property = $reflection->getProperty('instance');
    $property->setAccessible(true);
    $property->setValue(null, null);

    // reset Globals if needed
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['REQUEST_METHOD'] = 'GET';
});
```

### Mocking Requests (模擬請求)
Router 通常依賴全域 `$_SERVER` 變數。請在你的 Test Case 或 `beforeEach` 區塊中 Mock 它們：

```php
$_SERVER['REQUEST_URI'] = '/user/1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'example.com';
```

### Output Buffering (輸出緩衝)
由於 Router 會直接 `echo` 輸出，請使用 Output Buffering 來擷取並斷言 (Assert) 回應：

```php
ob_start();
Route::dispatch();
$output = ob_get_clean();
expect($output)->toBe('Expected Output');
```
