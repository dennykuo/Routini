<?php

namespace Routini\Http;

/**
 * HTTP 回應物件
 *
 * 封裝 HTTP 回應資訊，提供型別安全的 API
 */
class Response
{
    protected $content;
    protected int $status;
    protected array $headers;

    public function __construct(
        $content = '',
        int $status = 200,
        array $headers = []
    ) {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    /**
     * 取得回應內容
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * 設定回應內容
     */
    public function setContent($content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * 取得狀態碼
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * 設定狀態碼
     */
    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * 取得所有 Headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 取得指定 Header
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * 新增 Header
     */
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * 建立帶有新 Header 的回應副本（不可變方法）
     *
     * 這對於中介軟體鏈很重要，因為每層都可以安全地修改回應
     * 而不影響其他層
     */
    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    /**
     * 批次設定 Headers
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * 檢查是否有指定 Header
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * 移除指定 Header
     */
    public function removeHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * 發送回應到客戶端
     */
    public function send(): void
    {
        // 設定 HTTP 狀態碼
        http_response_code($this->status);

        // 設定 Headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // 輸出內容
        echo $this->content;
    }

    /**
     * 便捷方法：建立 JSON 回應
     */
    public static function json($data, int $status = 200): self
    {
        return new self(
            json_encode($data),
            $status,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * 便捷方法：建立重導向回應
     *
     * 注意：此方法不驗證 URL，可能導致開放重定向漏洞
     * 如需安全重定向，請使用 safeRedirect()
     */
    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    /**
     * 便捷方法：建立安全的重導向回應（防止開放重定向攻擊）
     *
     * @param string $url 重定向目標 URL
     * @param array $allowedDomains 允許的域名清單（空陣列表示只允許相對 URL）
     * @param int $status HTTP 狀態碼
     * @return self
     * @throws \InvalidArgumentException 當 URL 不安全時
     */
    public static function safeRedirect(
        string $url,
        array $allowedDomains = [],
        int $status = 302
    ): self {
        if (!self::isValidRedirectUrl($url, $allowedDomains)) {
            throw new \InvalidArgumentException(
                "Unsafe redirect URL detected: {$url}. " .
                "Only relative URLs or whitelisted domains are allowed."
            );
        }

        return self::redirect($url, $status);
    }

    /**
     * 驗證重定向 URL 是否安全
     *
     * @param string $url 要驗證的 URL
     * @param array $allowedDomains 允許的域名清單
     * @return bool
     */
    private static function isValidRedirectUrl(string $url, array $allowedDomains): bool
    {
        // 空 URL 不允許
        if (empty($url)) {
            return false;
        }

        // 允許相對 URL（以 / 開頭但不是 //）
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return true;
        }

        // 解析 URL
        $parsed = parse_url($url);

        // URL 解析失敗
        if ($parsed === false) {
            return false;
        }

        // 沒有 host（相對 URL）
        if (!isset($parsed['host'])) {
            return true;
        }

        // 如果有 host，檢查是否在白名單中
        if (!empty($allowedDomains)) {
            $host = strtolower($parsed['host']);
            $allowedDomains = array_map('strtolower', $allowedDomains);
            return in_array($host, $allowedDomains, true);
        }

        // 有 host 但沒有白名單，不允許
        return false;
    }

    /**
     * 便捷方法：建立 HTML 回應
     */
    public static function html(string $html, int $status = 200): self
    {
        return new self(
            $html,
            $status,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    /**
     * 便捷方法：建立純文字回應
     */
    public static function text(string $text, int $status = 200): self
    {
        return new self(
            $text,
            $status,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    /**
     * 便捷方法：建立 404 回應
     */
    public static function notFound(string $message = '404 - Not Found'): self
    {
        return new self($message, 404);
    }

    /**
     * 便捷方法：建立 500 回應
     */
    public static function error(string $message = '500 - Internal Server Error', int $status = 500): self
    {
        return new self($message, $status);
    }

    /**
     * 便捷方法：建立無內容回應（204）
     */
    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * 檢查是否為成功回應（2xx）
     */
    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * 檢查是否為重導向回應（3xx）
     */
    public function isRedirect(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }

    /**
     * 檢查是否為客戶端錯誤（4xx）
     */
    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    /**
     * 檢查是否為伺服器錯誤（5xx）
     */
    public function isServerError(): bool
    {
        return $this->status >= 500 && $this->status < 600;
    }

    /**
     * 檢查是否為錯誤回應（4xx 或 5xx）
     */
    public function isError(): bool
    {
        return $this->status >= 400;
    }

    /**
     * 檢查是否為 OK（200）
     */
    public function isOk(): bool
    {
        return $this->status === 200;
    }

    /**
     * 檢查是否為 Not Found（404）
     */
    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    /**
     * 檢查是否為 Forbidden（403）
     */
    public function isForbidden(): bool
    {
        return $this->status === 403;
    }

    /**
     * 取得狀態碼文字
     */
    public function getStatusText(): string
    {
        $statusTexts = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
        ];

        return $statusTexts[$this->status] ?? 'Unknown';
    }

    /**
     * 轉換為字串（回傳內容）
     */
    public function __toString(): string
    {
        return (string) $this->content;
    }
}
