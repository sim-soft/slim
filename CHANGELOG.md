# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Everything below is a bug fix, but four of them change behaviour you may be
relying on. Read **Changed** before upgrading.

### Changed

- **`MaintenanceMode` returns a 503 instead of throwing `HttpException`.**
  It also now sends the `Retry-After` header that the `retryAfter` constructor
  argument always accepted but never emitted. The header is the reason for the
  change: Slim's `ErrorHandler` builds a fresh response from a thrown exception
  and discards any header attached to it, so the only way to keep `Retry-After`
  is to return the response directly.

  *If you upgrade:* code that catches `HttpException` from this middleware, or
  tests asserting it is thrown, will no longer see it. The 503 also no longer
  passes through a custom error renderer — use the `message` argument for the
  body, or wrap the middleware if you need a full HTML maintenance page.

- **`RateLimit` returns a 429 instead of throwing `HttpException`**, for the
  same reason. The 429 now carries `Retry-After` alongside the `X-RateLimit-*`
  headers, all of which were previously lost when the exception was rendered.

  *If you upgrade:* the same two caveats apply — no `HttpException` to catch,
  and no custom error renderer for this response.

- **`RateLimitFileStorage` and `RateLimitRedisStorage` constructors take two new
  optional arguments**, `logger` and `failOpen`. Both are appended after the
  existing parameters and both default to current behaviour, so positional and
  named construction continue to work unchanged.

- **`response()` no longer discards `'0'` and `''` bodies.** If you were relying
  on a falsy body being dropped — that is, on the bug — you will now get the
  string you passed.

### Fixed

- **`response('0')` returned an empty body.** The helper tested `$content` for
  truthiness, so `'0'` and `''`, both legitimate bodies, were silently
  discarded. It now compares against `null`. `Response::json()` had the same
  defect: it tested the encoded string, rejecting valid output such as `'0'`
  while a genuine `json_encode()` failure fell through to the same throw. Only
  `false` now counts as failure.

- **`MaintenanceMode::$retryAfter` was accepted and never used.** Clients and
  crawlers had nothing telling them the outage was temporary. See **Changed**
  above for how this was fixed and what it costs.

- **Rate limit storage failed open silently.** When the file backend's directory
  was unwritable, or Redis was unreachable, the limiter stopped limiting and
  nothing reported it — a limiter that has quietly stopped working is
  indistinguishable from one that is working. Both backends now accept a PSR-3
  `logger` and a `failOpen` flag.

  Failing open remains the default, because most applications would rather stay
  up than reject traffic during an infrastructure problem. Pass `failOpen: false`
  on endpoints where abuse costs more than downtime — login, password reset,
  payment.

- **`RateLimitRedisStorage` turned a Redis outage into a 500.** phpredis signals
  a dropped connection by throwing `RedisException`, which escaped the class
  entirely; only the `false` return path was handled, and that path is not the
  one phpredis normally takes. Every failure route now converges on the same
  `failOpen` policy, and a rate limiter can no longer be the reason a request
  fails.

- **`mkdir()` and `fopen()` warnings could leak the storage path** into a
  response when the rate limit directory was unusable. Both calls are now
  suppressed and the failure is reported through the logger instead.

### Added

- `ext-redis` is now listed under `suggest`, and `psr/log` is declared as a
  direct dependency. `src/Route.php` has always used PSR-3 but only received it
  transitively via `slim/slim`, so a future Slim release dropping that
  dependency would have broken the package.
- A CI workflow covering PHP 8.2, 8.3, and 8.4 on Linux, PHP 8.4 on Windows,
  and a lowest-dependency build, plus PHPStan level 8, PHPMD, and
  `composer validate`.
- Test coverage for `RateLimitRedisStorage`, which previously had none.
- `SECURITY.md`, with a private disclosure route and the distinction between a
  vulnerability and a documented configuration trade-off.
- This changelog.

### Internal

- `Request::extractData()` split into `sanitizeAll()` and `sanitizeOne()`,
  clearing the last PHPMD violation in `src/` so PHPMD is now a CI gate rather
  than advisory. Behaviour is unchanged: the new tests covering sanitized
  defaults pass against both the old and new implementations.

### Fixed (security-relevant)

- **`ShutdownHandler` rendered an error page for non-fatal errors.**
  `error_get_last()` matches any severity, so a request that merely raised a
  deprecation had a 500 page appended to its already-sent 200 response body. It
  now handles only fatal severities, guards on `headers_sent()`, and discards
  partial output before emitting.

- **`CORS` resolved the allowed origin once per instance rather than per
  request.** The origin was pinned for the lifetime of the object, which is
  wrong under any persistent runtime (RoadRunner, Swoole, FrankenPHP). The old
  code also read `$_SERVER` directly and fell back to `HTTP_REFERER`, which is
  not an origin and is attacker-influenced. The origin is now read from the
  PSR-7 `Origin` header per request, and `Vary: Origin` is sent when the
  response varies by origin.

  *Breaking:* the public `parseOrigins()` method is replaced by
  `resolveOrigin(Request)`.

- **`Request::getBearerToken()` mishandled malformed headers.** An
  `Authorization` header with no scheme raised a warning and then a
  `TypeError`, and a `Basic` header had its base64 credentials returned as
  though they were a bearer token. Malformed and non-Bearer headers now return
  `''`.

- **`XmlSerializer` produced unparseable XML for some keys.** Keys drawn from
  database columns or user input may contain spaces or lead with a digit, which
  are not valid XML element names. Such keys now fall back to
  `<item name="...">`.

## [2.0.0] - 2026-05-30

Major refactoring. Added API resources, the DataTable plugin, the middleware
suite, and the documentation site.

## [1.0.3] and earlier

No changelog was kept for these releases. See the
[commit history](https://github.com/sim-soft/slim/commits/master) for details.

[Unreleased]: https://github.com/sim-soft/slim/compare/2.0.0...HEAD
[2.0.0]: https://github.com/sim-soft/slim/releases/tag/2.0.0
