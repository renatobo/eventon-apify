# PHP Unit Suite

Fast, dependency-free unit tests for the plugin's pure logic. The tests themselves
run with just `php` — no database, no WordPress install, and no Composer runtime. WordPress functions the
code calls are replaced by small in-memory doubles in [`wp-stubs.php`](wp-stubs.php).

## Run

```bash
php tests/php/run.php
# or
npm run test:unit
```

CI runs the same suite plus `php -l` on PHP 8.0, 8.3, and 8.5. A separate
integration job installs WordPress 7.0.2 with MySQL and runs
`tests/integration/wp-rest-smoke.php` through WP-CLI.

## Layout

- `bootstrap.php` — defines plugin constants, loads the WordPress doubles and the
  harness, then `require`s every `includes/*.php` file (so a dropped or
  double-declared function fails fast).
- `wp-stubs.php` — minimal WordPress function/class doubles with resettable
  in-memory state (`eventon_test_reset_wp_state()`, `eventon_test_set_current_user_can()`).
- `harness.php` — `test()`, `eq()`, `ok()`, `throws()`, and the runner.
- `cases/*.php` — test files. State is reset before each case.

## Adding a test

Create or extend a file in `cases/`:

```php
test('describes the behavior', function () {
    eq(eventon_apify_some_function('input'), 'expected');
});
```

If the function under test calls a WordPress function not yet doubled, add a
guarded stub to `wp-stubs.php`.

## Scope

These are unit tests for pure-ish logic (helpers, normalizers, validators,
sanitizers, the MCP visibility gate, the capability map). They are the safety net
for refactors such as decomposing the larger write/validation functions. They do
**not** exercise live WordPress integration. CI covers WordPress core
route/bootstrap integration, while the production smoke suite (`npm run
test:prod`, see [../README.md](../README.md)) covers a configured EventON site.

## Quality tooling

The repository uses Composer for development-only PHPCS, PHPStan, and WordPress
stub dependencies. `composer quality` runs standards, static analysis, this unit
suite, and the RSVP helper performance budget. With Xdebug coverage mode enabled,
`composer coverage` also writes `coverage.json` and enforces the configured line
coverage floor.
