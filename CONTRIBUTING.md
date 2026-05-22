# Contributing

Contributions are **welcome** and will be fully **credited**.

We accept contributions via Pull Requests on [GitHub](https://github.com/syriable/laravel-localizer).

## Pull Requests

- **[PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)** - Enforced via Laravel Pint. Run `composer format` before pushing.

- **Add tests!** - Your patch will not be accepted if it doesn't include tests. Pest is used throughout — match the existing style.

- **Document any change in behaviour** - Make sure the `README.md` and any other relevant documentation are kept up-to-date.

- **Consider our release cycle** - We try to follow [SemVer v2.0.0](https://semver.org/). Randomly breaking public APIs is not an option.

- **Create feature branches** - Don't ask us to pull from your master branch.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please [squash them](https://www.git-scm.com/book/en/v2/Git-Tools-Rewriting-History#Changing-Multiple-Commit-Messages) before submitting.

## Setting up the package locally

```bash
git clone https://github.com/syriable/laravel-localizer.git
cd laravel-localizer
composer install
composer check
```

## Running the test suite

```bash
composer test               # Pest
composer test-coverage      # Pest with coverage (min 95%)
composer analyse            # Larastan at level max
composer format             # Apply Pint
composer format-check       # Verify formatting
composer check              # All of the above (sans format)
```

## Static analysis

PHPStan runs at `level: max` with [`larastan`](https://github.com/larastan/larastan) and strict rules. The bar is HIGH — even one new complaint will fail CI.

Before pushing a PR, always run:

```bash
composer analyse
```

**If your change introduces a legitimate complaint** (e.g. PHPStan can't reason about a Laravel internal that's correctly used), regenerate the baseline:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

This rewrites `phpstan-baseline.neon` with the new ignored errors. Include the baseline file in your PR and explain in the description WHY each new ignore is necessary. Reviewers will check that the ignore is genuinely warranted, not a workaround for a real bug.

**If your change introduces a false-positive class we'd hit everywhere** (e.g. a new defensive-coding pattern), prefer adding an `identifier:`-based ignore to `phpstan.neon.dist` rather than baseline-ignoring each occurrence. See the comments at the top of `phpstan.neon.dist` for the existing identifier-based ignores and the rationale.

## Beta period (0.9.x)

This package is in active beta. While the engine itself is stable, the public API may shift between minor releases (`0.9.x` → `0.10.x`) based on real-world feedback. If you're contributing during the beta:

- Breaking-change PRs are welcome — we'd rather fix the API now than after `1.0.0`.
- New features should be argued for in an Issue first; we're keeping scope tight pre-1.0.
- Add `BC BREAK` to the PR title if your change is breaking. We'll batch breaking changes into the next minor.

## What we look for

The engine has a single responsibility: extraction. Pull requests are evaluated against that scope.

**Welcome:** new source-language extractors, performance improvements, additional normalizers, better source-location accuracy, broader regex coverage for edge cases (escaped quotes, multi-line calls, namespace-style names).

**Out of scope:** anything that writes language files, talks to translation vendors, manages locales, or adds UI. Those concerns belong to downstream packages built on top of this engine — see the README's *Extending the engine* section for the contract surface they should target.

**Happy coding!**
