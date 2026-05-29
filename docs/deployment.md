# Deployment

Production checklist for deploying your Simsoft Slim application.

## Table of Contents

- [Checklist](#checklist)
- [Error Handling](#error-handling)
- [Route Caching](#route-caching)
- [Composer Optimization](#composer-optimization)
- [PHP Configuration](#php-configuration)
- [Directory Structure](#directory-structure)

## Checklist

| Step                                  | Command / Action                                  |
|---------------------------------------|---------------------------------------------------|
| Disable error display                 | `displayError: false` in `withErrorHandler()`     |
| Enable route caching                  | Pass `cachePath` to `withRouting()`               |
| Optimize autoloader                   | `composer install --no-dev --optimize-autoloader` |
| Set PHP `display_errors` off          | `display_errors = Off` in `php.ini`               |
| Set PHP `opcache` on                  | `opcache.enable = 1` in `php.ini`                 |
| Ensure `public/` is the document root | Web server points to `public/`, not project root  |

## Error Handling

Never show error details in production — they expose file paths, database
credentials, and stack traces:

```php
Route::make()
    ->withErrorHandler(
        displayError: false,    // NEVER true in production
        logError: true,         // Log errors to file/service
        logErrorDetails: true,  // Include stack traces in logs (not shown to users)
    )
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

## Route Caching

Cache routes to skip parsing on every request:

```php
Route::make()
    ->withRouting(
        routes: function(App $app) { /* ... */ },
        cachePath: __DIR__ . '/routes.cache',
    )
    ->run();
```

- Generate the cache file during deployment
- Delete and regenerate when routes change

## Composer Optimization

```bash
# Install without dev dependencies and optimize autoloader
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

## PHP Configuration

Recommended `php.ini` settings for production:

```ini
display_errors = Off
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php/error.log

opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0

expose_php = Off
```

> **Note:** `opcache.validate_timestamps = 0` means PHP won't check if files
> changed. You must restart PHP-FPM after deploying new code.

## Directory Structure

Keep your entry point in a `public/` directory. Only this directory should be
accessible by the web server:

```
your-project/
├── public/           ← Document root (web server points here)
│   ├── index.php     ← Front controller
│   └── .htaccess     ← Apache rewrite rules
├── src/              ← Your application code
├── vendor/           ← Composer dependencies
├── routes.cache      ← Route cache (generated)
└── composer.json
```

This prevents direct access to `vendor/`, `src/`, config files, and `.env`
files.
