# Browser Tests

Pest 4 browser tests (Playwright-based) live in `tests/Browser/`.

## Run all browser tests

```
php artisan test tests/Browser
```

## Run a specific test file

```
php artisan test tests/Browser/CouponPurchaseFlowTest.php
```

## Run the full suite (Unit + Feature + Browser)

```
php artisan test --compact
```

## Debugging

Run with a visible browser window:

```
php artisan test tests/Browser/CouponPurchaseFlowTest.php --headed
```

Open the browser and pause on failure for inspection:

```
php artisan test tests/Browser/CouponPurchaseFlowTest.php --debug
```

## Requirements

- `pestphp/pest-plugin-browser` (composer) and `playwright` (npm) must be installed, with the Chromium binary downloaded (`npx playwright install chromium`).
- The PHP binary running the tests needs the `sockets`, `pdo_sqlite`, and `sqlite3` extensions enabled (used to find a free port for the test server and to run the in-memory sqlite test database). Check with `php -m`.
