# Security Policy

## Reporting a Vulnerability

**Please do not open a public issue for a security vulnerability.** A public
issue is visible to everyone, including people who would use it before there is
a fix.

Report it privately through GitHub instead:

1. Go to the [Security tab](https://github.com/sim-soft/slim/security) of this
   repository.
2. Click **Report a vulnerability**.

This opens a private advisory visible only to the maintainers. If that option is
not available, open a regular issue saying only that you have a security report
and asking for a private contact — no details — and a maintainer will follow up.

### What to include

The more of this you can provide, the faster it can be confirmed:

- The affected version, and the PHP version you are running.
- What an attacker can do with it. A crash and an authentication bypass need
  very different responses.
- The smallest code sample or request that reproduces it.
- Whether it needs a particular configuration — a non-default middleware
  ordering, a specific storage backend, a proxy in front.

### What to expect

This is a small project without a dedicated security team, so please treat these
as intentions rather than guarantees:

- An acknowledgement that the report was received, normally within a few days.
- An assessment of whether it is exploitable and how serious it looks.
- A fix released as promptly as the severity warrants, with an advisory
  published once it is available.

You are welcome to be credited in the advisory, or to stay anonymous — just say
which you prefer.

Please give a reasonable window for a fix before disclosing publicly. If you do
not hear back at all, that is a failure on this end, not a reason to sit on the
report indefinitely.

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 2.x     | Yes       |
| 1.x     | No        |

Fixes land on the latest 2.x release. If you are on 1.x, upgrading is the
remedy.

Only PHP versions that are themselves
[actively supported](https://www.php.net/supported-versions.php) are covered.
This package requires PHP 8.2 or later.

## Scope

This package is a routing and middleware layer around
[Slim Framework 4](https://www.slimframework.com/). Reports about Slim itself,
PSR-7 implementations, or other dependencies should go to those projects — but
if you are unsure where a problem originates, report it here and it will be
routed onward.

### Worth reporting

The security-relevant surface of this package is mostly its middleware, where a
defect means a control silently stops protecting:

- Authentication and CSRF middleware failing to reject what it should reject.
- CORS granting an origin it was not configured to allow.
- Rate limiting or quota enforcement being bypassable — including by forging
  the headers used for client identification.
- `IpFilter` allowing an address that should be blocked.
- Sensitive data escaping into a response or a log: internal paths, tokens,
  stack traces.

### Configuration, not vulnerabilities

Some behaviour looks like a flaw but is a documented choice:

- **Rate limit storage fails open by default.** If the store is unreachable, the
  limiter allows requests through rather than rejecting them. This is
  deliberate — most applications prefer to stay up — and is configurable with
  `failOpen: false`. See
  [Built-in Middleware](docs/builtin-middleware.md#ratelimit).
- **`X-Forwarded-For` is trusted only from configured proxies.** If you pass
  `trustedProxies`, you are asserting those hosts are trustworthy. Trusting a
  proxy you do not control lets clients spoof their identity.
- **Middleware you do not register does nothing.** This package ships security
  middleware; it does not enable it for you.

A misconfiguration that is easy to make and dangerous when made is still worth
reporting — as a documentation or API design problem, which it is.
