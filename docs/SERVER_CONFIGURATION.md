# Nginx

```
server {
    listen 80;
    server_name example.com;
    root /var/www/mini-router; # 指向你的專案目錄

    index index.php index.html;

    location / {
        # 這是關鍵：嘗試讀取檔案 -> 嘗試讀取目錄 -> 失敗則轉發給 index.php
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock; # 視你的 PHP 版本而定
    }
}
```

# Apache (.htaccess)

```
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # 1. 如果請求的是真實存在的檔案或目錄，直接讀取該檔案 (不經過 index.php)
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f

    # 2. 其他所有請求，全部轉發給 index.php
    RewriteRule ^ index.php [L]
</IfModule>
```