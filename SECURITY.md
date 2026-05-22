# Security Policy

## Supported Versions

The following versions of `syriable/laravel-localizer` receive security
updates. We follow [Semantic Versioning](https://semver.org/), and the
current major release line receives all security fixes.

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability, **please do not open a public
GitHub issue**. Instead, report it privately so we can investigate and
ship a fix before disclosure.

**Preferred channel:** [GitHub Security Advisories](https://github.com/syriable/laravel-localizer/security/advisories/new)
— this notifies the maintainers privately and creates a tracked advisory.

**Alternative channel:** email `security@syriable.com` with a description
of the issue, reproduction steps, and the affected version.

You can expect:

- An acknowledgement within **3 business days**.
- A status update within **7 business days** of the acknowledgement.
- A fix released as a patch version, with credit in the changelog if you
  wish (we'll ask before naming you).

## Scope

In scope:

- The package's runtime code under `src/`.
- The shipped configuration file `config/localizer.php`.
- The Artisan command `localizer:scan` and the published facade.

Out of scope (please report to the upstream project instead):

- Vulnerabilities in Laravel itself or its dependencies.
- Vulnerabilities in PHP or its extensions.
- Application-level misuse (e.g. running the scanner against an
  attacker-controlled file path with an overly permissive `paths` config
  — that's a configuration concern, not a package vulnerability).

Thank you for helping keep the ecosystem safe.
