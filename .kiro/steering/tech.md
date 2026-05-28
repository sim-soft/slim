# Tech Stack

## Language & Runtime

- PHP >= 8.2
- Strict types (`declare(strict_types=1)`)

## Dependencies

- `slim/slim ^4` — core routing and middleware
- `slim/psr7 ^1.7` — PSR-7 HTTP message implementation

## Dev Dependencies

- `phpstan/phpstan ^1.11` — static analysis, level 8
- `phpmd/phpmd ^2.15` — mess detection with custom ruleset
- `phpunit/phpunit ^11.0` — unit testing

## Common Commands

```shell
composer test       # PHPUnit
composer qc         # PHPStan + PHPMD
```

## Code Quality Rules

- PHPStan level 8
- PHPMD: clean code, code size, camelCase naming, no unused code
- No `else` expressions (early return)
- No `eval` or `goto`
- Variables minimum 2 characters
- 4 spaces indent for PHP, LF line endings, UTF-8, final newline
