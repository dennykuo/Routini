<?php

namespace Routini\Http;

/**
 * HTTP 請求物件
 *
 * 封裝 HTTP 請求資訊，提供型別安全的 API
 */
class Request
{
    protected string $uri;
    protected string $method;
    protected string $host;
    protected array $headers;
    protected array $query;
    protected array $post;
    protected array $files;
    protected array $server;
    protected array $attributes = []; // 自定義屬性（如路由參數）

    public function __construct(
        string $uri,
        string $method = 'GET',
        array $query = [],
        array $post = [],
        array $files = [],
        array $server = [],
        array $headers = []
    ) {
        $this->uri = $uri;
        $this->method = strtoupper($method);
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->server = $server;
        $this->headers = $headers;
        $this->host = $server['HTTP_HOST'] ?? '';
    }

    /**
     * 從全域變數建立請求
     */
    public static function capture(): self
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        return new self(
            uri: $_SERVER['REQUEST_URI'] ?? '/',
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            query: $_GET,
            post: $_POST,
            files: $_FILES,
            server: $_SERVER,
            headers: is_array($headers) ? $headers : []
        );
    }

    /**
     * 取得完整 URI（包含查詢字串）
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * 取得路徑（不含查詢字串）
     */
    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH) ?: '/';
    }

    /**
     * 取得 HTTP 方法
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * 取得主機名稱
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * 取得指定 Header
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * 檢查是否有指定 Header
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * 取得所有 Headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 取得 Query 參數
     */
    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * 取得所有 Query 參數
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    /**
     * 取得 POST 參數
     */
    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * 取得所有 POST 參數
     */
    public function allPost(): array
    {
        return $this->post;
    }

    /**
     * 取得輸入（POST 優先，否則 Query）
     */
    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * 取得上傳檔案
     */
    public function file(string $key)
    {
        return $this->files[$key] ?? null;
    }

    /**
     * 取得所有上傳檔案
     */
    public function allFiles(): array
    {
        return $this->files;
    }

    /**
     * 取得 Server 參數
     */
    public function server(string $key, $default = null)
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * 設定自定義屬性（通常由路由設定）
     */
    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * 取得自定義屬性
     */
    public function getAttribute(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * 取得所有自定義屬性
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * 批次設定屬性
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * 檢查是否為 AJAX 請求
     */
    public function isAjax(): bool
    {
        return strtolower($this->getHeader('X-Requested-With') ?? '') === 'xmlhttprequest';
    }

    /**
     * 檢查是否為 JSON 請求
     */
    public function isJson(): bool
    {
        $contentType = strtolower($this->getHeader('Content-Type') ?? '');
        return str_contains($contentType, 'application/json');
    }

    /**
     * 檢查 HTTP 方法
     */
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    /**
     * 檢查是否為 GET 請求
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * 檢查是否為 POST 請求
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }
}
