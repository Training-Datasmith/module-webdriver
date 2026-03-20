# Architecture: module-webdriver (Codeception)

## Purpose

The Codeception WebDriver module. Enables full browser-based acceptance testing via the WebDriver protocol (Selenium, Chrome DevTools Protocol via ChromeDriver, Firefox GeckoDriver, etc.).

## Directory Structure

```
src/Codeception/
  Module/
    Web_Driver.php                — Main Codeception module: amOnPage, click, fillField, waitForElement, etc.
  Constraint/
    Web_Driver.php                — PHPUnit constraint for WebDriver element assertions
    Web_Driver_Not.php            — Negated WebDriver constraint
  Exception/
    Connection_Exception.php      — Thrown when WebDriver session cannot be established
```

## Key Design Decisions

- **Facebook WebDriver**: Uses `php-webdriver/webdriver` as the underlying WebDriver client; `WebDriver.php` is a thin Codeception adapter over it
- **Explicit waits**: Methods like `waitForElement($selector, $timeout)` poll the DOM; there are no implicit waits — tests must explicitly wait for async content
- **Session management**: The WebDriver session is created in `_initialize()` and closed in `_after()` (or kept across tests with `restart: false`)

## Extension Points

- Use `$I->executeJS($script)` to run arbitrary JavaScript in the browser
- Configure `capabilities` in `codeception.yml` to pass browser-specific WebDriver capabilities (headless mode, window size, etc.)
