# AGENTS.md

PHP library `gemorroj/process-pool` — process pool on top of Symfony Process.
Namespace `ProcessPool\`, sources in `src/`, tests in `tests/`.

## Environment

- PHP >= 8.4 with `proc_open` enabled.
- Install dependencies with `composer install`.

## Code Style

- PSR-12, `declare(strict_types=1);`, typed code.
- Keep classes final where possible.

## Commands

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix
```

## Rules

- Put tests in `tests/`, cover new behavior.
- Update `README.md` if public API changes.
