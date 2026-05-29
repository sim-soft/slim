# Web Server Configuration

Your web server must route all requests to a single `index.php` entry file (
front-controller pattern). Below are configurations for common servers.

## Table of Contents

- [PHP Built-in Server](#php-built-in-server)
- [Apache](#apache)
- [Nginx](#nginx)
- [Laragon](#laragon)

## PHP Built-in Server

The quickest way to test during development:

```bash
# Assuming your index.php is in the public/ directory
cd public/
php -S localhost:8080
```

Visit `http://localhost:8080` in your browser.

> **Warning:** The built-in server is for development only. Do not use it in
> production.

## Apache

Create a `.htaccess` file in the same directory as your `index.php`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

This tells Apache: "If the requested file or directory doesn't exist, send the
request to `index.php`."

**Requirements:**

- `mod_rewrite` enabled: `sudo a2enmod rewrite`
- `AllowOverride All` in your virtual host config

### Running in a Sub-Directory

If your app lives at `https://example.com/myapp/`, add a base path:

```php
Route::make()
    ->withBasePath('/myapp')
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

## Nginx

```nginx
server {
    listen 80;
    server_name example.com;
    index index.php;
    root /var/www/html/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param SCRIPT_NAME $fastcgi_script_name;
        fastcgi_index index.php;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
    }
}
```

## Laragon

Laragon autoconfigures Apache with pretty URLs. No `.htaccess` changes needed.
Create your project in `C:\laragon\www\your-project\` and access it at
`http://your-project.test`.

If using a `public/` subdirectory, set the document root in Laragon's Apache
config or use `withBasePath()`.
