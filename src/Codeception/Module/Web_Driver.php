<?php

//phpcs:disable Generic.Files.LineLength.TooLong
declare (strict_types=1);
namespace Codeception\Module;

use Closure;
use Codeception\Constraint\Page as PageConstraint;
use Codeception\Constraint\Web_Driver as WebDriverConstraint;
use Codeception\Constraint\Web_Driver_Not as WebDriverConstraintNot;
use Codeception\Coverage\Subscriber\Local_Server;
use Codeception\Exception\Connection_Exception;
use Codeception\Exception\Element_Not_Found;
use Codeception\Exception\Malformed_Locator_Exception;
use Codeception\Exception\Module_Config_Exception;
use Codeception\Exception\Module_Exception;
use Codeception\Exception\Test_Runtime_Exception;
use Codeception\Lib\Interfaces\Conflicts_With_Module;
use Codeception\Lib\Interfaces\Element_Locator;
use Codeception\Lib\Interfaces\Multi_Session as MultiSessionInterface;
use Codeception\Lib\Interfaces\Page_Source_Saver;
use Codeception\Lib\Interfaces\Remote as RemoteInterface;
use Codeception\Lib\Interfaces\Requires_Package;
use Codeception\Lib\Interfaces\Screenshot_Saver;
use Codeception\Lib\Interfaces\Session_Snapshot;
use Codeception\Lib\Interfaces\Web as WebInterface;
use Codeception\Module as CodeceptionModule;
use Codeception\Test\Descriptor;
use Codeception\Test\Interfaces\Scenario_Driven;
use Codeception\Test_Interface;
use Codeception\Util\Action_Sequence;
use Codeception\Util\Locator;
use Codeception\Util\Uri;
use Exception;
use Facebook\Web_Driver\Cookie;
use Facebook\Web_Driver\Cookie as WebDriverCookie;
use Facebook\Web_Driver\Exception\Internal\Unexpected_Response_Exception;
use Facebook\Web_Driver\Exception\Invalid_Element_State_Exception;
use Facebook\Web_Driver\Exception\Invalid_Selector_Exception;
use Facebook\Web_Driver\Exception\No_Such_Element_Exception;
use Facebook\Web_Driver\Exception\Php_Web_Driver_Exception_Interface;
use Facebook\Web_Driver\Interactions\Web_Driver_Actions;
use Facebook\Web_Driver\Remote\Local_File_Detector;
use Facebook\Web_Driver\Remote\Remote_Web_Driver;
use Facebook\Web_Driver\Remote\Remote_Web_Element;
use Facebook\Web_Driver\Remote\Useless_File_Detector;
use Facebook\Web_Driver\Remote\Web_Driver_Capability_Type;
use Facebook\Web_Driver\Web_Driver as WebDriverInterface;
use Facebook\Web_Driver\Web_Driver_By;
use Facebook\Web_Driver\Web_Driver_Dimension;
use Facebook\Web_Driver\Web_Driver_Element;
use Facebook\Web_Driver\Web_Driver_Expected_Condition;
use Facebook\Web_Driver\Web_Driver_Keys;
use Facebook\Web_Driver\Web_Driver_Search_Context;
use Facebook\Web_Driver\Web_Driver_Select;
use InvalidArgumentException;
use Php_Unit\Framework\Assertion_Failed_Error as PHPUnitAssertionFailedError;
use Php_Unit\Framework\Self_Describing;
/**
* Run tests in real browsers using the W3C [WebDriver protocol](https://www.w3.org/TR/webdriver/).
* There are multiple ways of running browser tests using WebDriver:
*
* ## Selenium (Recommended)
*
* * Java is required
* * NodeJS is required
*
* The fastest way to get started is to [Install and launch Selenium using selenium-standalone NodeJS package](https://www.npmjs.com/package/selenium-standalone).
*
* Launch selenium standalone in separate console window:
*
* ```
* selenium-standalone start
* ```
*
* Update configuration in `Acceptance.suite.yml`:
*
* ```yaml
* modules:
*    enabled:
*       - WebDriver:
*          url: 'http://localhost/'
*          browser: chrome # 'chrome' or 'firefox'
* ```
*
* ## Headless Chrome Browser
*
* To enable headless mode (launch tests without showing a window) for Chrome browser using Selenium use this config in `Acceptance.suite.yml`:
*
* ```yaml
* modules:
*    enabled:
*       - WebDriver:
*          url: 'http://localhost/'
*          browser: chrome
*          capabilities:
*             goog:chromeOptions:
*                args: ["--headless"]
* ```
*
* ## Headless Selenium in Docker
*
* Docker can ship Selenium Server with all its dependencies and browsers inside a single container.
* Running tests inside Docker is as easy as pulling [official selenium image](https://github.com/SeleniumHQ/docker-selenium) and starting a container with Chrome:
*
* ```
* docker run --net=host --shm-size 2g selenium/standalone-chrome
* ```
*
* By using `--net=host` allow Selenium to access local websites.
*
* ## Local Chrome and/or Firefox
*
* Tests can be executed directly through ChromeDriver or GeckoDriver (for Firefox). Consider using this option if you don't plan to use Selenium.
*
* ### ChromeDriver
*
* * Download and install [ChromeDriver](https://sites.google.com/chromium.org/driver/downloads)
* * Launch ChromeDriver in a separate console window: `chromedriver --url-base=/wd/hub`.
*
* Configuration in `Acceptance.suite.yml`:
*
* ```yaml
* modules:
*    enabled:
*       - WebDriver:
*          browser: chrome
*          url: 'http://localhost/'
*          window_size: 2000x1000
*          port: 9515
*          capabilities:
*              goog:chromeOptions:
*                  args: ["--headless"] # Run Chrome in headless mode
*                  prefs:
*                      download.default_directory: "..."
* ```
* See here for additional [Chrome options](https://sites.google.com/chromium.org/driver/capabilities)
*
*
* ### GeckoDriver
*
* * [GeckoDriver](https://github.com/mozilla/geckodriver/releases) must be installed
* * Start GeckoDriver in a separate console window: `geckodriver`.
*
* Configuration in `Acceptance.suite.yml`:
*
* ```yaml
* modules:
*    enabled:
*       - WebDriver:
*          browser: firefox
*          url: 'http://localhost/'
*          window_size: 2000x1000
*          path: ''
*          capabilities:
*              acceptInsecureCerts: true # allow self-signed certificates
*              moz:firefoxOptions:
*                  args: ["-headless"] # Run Firefox in headless mode
*                  prefs:
*                      intl.accept_languages: "de-AT" # Set HTTP-Header `Accept-Language: de-AT` for requests
* ```
* See here for [Firefox capabilities](https://developer.mozilla.org/en-US/docs/Web/WebDriver/Capabilities#List_of_capabilities)
*
* ## Cloud Testing
*
* Cloud Testing services can run your WebDriver tests in the cloud.
* In case you want to test a local site or site behind a firewall
* you should use a tunnel application provided by a service.
*
* ### SauceLabs
*
* 1. Create an account at [SauceLabs.com](https://saucelabs.com/) to get your username and access key
* 2. In the module configuration use the format `username`:`access_key`@ondemand.saucelabs.com' for `host`
* 3. Configure `platformName` under `capabilities` to define the [Operating System](https://docs.saucelabs.com/basics/platform-configurator/)
* 4. run a tunnel app if your site can't be accessed from Internet
*
* ```yaml
*     modules:
*        enabled:
*           - WebDriver:
*              url: http://mysite.com
*              host: '<username>:<access key>@ondemand.saucelabs.com'
*              port: 80
*              browser: chrome
*              capabilities:
*                  platformName: 'Windows 10'
* ```
*
* ### BrowserStack
*
* 1. Create an account at [BrowserStack](https://www.browserstack.com/) to get your username and access key
* 2. In the module configuration use the format `username`:`access_key`@hub.browserstack.com' for `host`
* 3. Configure `os` and `os_version` under `capabilities` to define the operating System
* 4. If your site is available only locally or via VPN you should use a tunnel app. In this case add `browserstack.local` capability and set it to true.
*
* ```yaml
*  modules:
*      enabled:
*          - WebDriver:
*              url: http://mysite.com
*              host: '<username>:<access key>@hub.browserstack.com'
*              port: 80
*              browser: chrome
*              capabilities:
*                  bstack:options:
*                      os: Windows
*                      osVersion: 10
*                      local: true # for local testing
* ```
*
* ### LambdaTest
*
* 1. Create an account at [LambdaTest](https://www.lambdatest.com) to get your username and access key
* 2. In the module configuration use the format `username`:`access key`@hub.lambdatest.com' for `host`
* 3. Configure `platformName`, 'browserVersion', and 'browserName' under `LT:Options` to define test environments.
* 4. If your website is available only locally or via VPN you should use LambdaTest tunnel. In this case, you can add capability "tunnel":true;.
*
* ```yaml
*  modules:
*      enabled:
*            - WebDriver:
                 url: "https://openclassrooms.com"
                 host: 'hub.lambdatest.com'
                 port: 80
                 browser: 'Chrome'
                 capabilities:
                     LT:Options:
                     platformName: 'Windows 10'
                     browserVersion: 'latest-5'
                     browserName: 'Chrome'
                     tunnel: true #for Local testing
* ```
*
* ### TestingBot
*
* 1. Create an account at [TestingBot](https://testingbot.com/) to get your key and secret
* 2. In the module configuration use the format `key`:`secret`@hub.testingbot.com' for `host`
* 3. Configure `platformName` under `capabilities` to define the [Operating System](https://testingbot.com/support/getting-started/browsers.html)
* 4. Run [TestingBot Tunnel](https://testingbot.com/support/other/tunnel) if your site can't be accessed from Internet
*
* ```yaml
* modules:
*    enabled:
*       - WebDriver:
*          url: http://mysite.com
*          host: '<key>:<secret>@hub.testingbot.com'
*          port: 80
*          browser: chrome
*          capabilities:
*              platformName: Windows 10
* ```
*
* ## Configuration
*
* * `url` *required* - Base URL for your app (amOnPage opens URLs relative to this setting).
* * `browser` *required* - Browser to launch.
* * `host` - Selenium server host (127.0.0.1 by default).
* * `port` - Selenium server port (4444 by default).
* * `restart` - Set to `false` (default) to use the same browser window for all tests, or set to `true` to create a new window for each test. In any case, when all tests are finished the browser window is closed.
* * `start` - Autostart a browser for tests. Can be disabled if browser session is started with `_initializeSession` inside a Helper.
* * `window_size` - Initial window size. Set to `maximize` or a dimension in the format `640x480`.
* * `clear_cookies` - Set to false to keep cookies, or set to true (default) to delete all cookies between tests.
* * `wait` (default: 0 seconds) - Whenever element is required and is not on page, wait for n seconds to find it before fail.
* * `capabilities` - Sets Selenium [desired capabilities](https://github.com/SeleniumHQ/selenium/wiki/DesiredCapabilities). Should be a key-value array.
* * `connection_timeout` - timeout for opening a connection to remote selenium server (30 seconds by default).
* * `request_timeout` - timeout for a request to return something from remote selenium server (30 seconds by default).
* * `pageload_timeout` - amount of time to wait for a page load to complete before throwing an error (default 0 seconds).
* * `http_proxy` - sets http proxy server url for testing a remote server.
* * `http_proxy_port` - sets http proxy server port
* * `ssl_proxy` - sets ssl(https) proxy server url for testing a remote server.
* * `ssl_proxy_port` - sets ssl(https) proxy server port
* * `debug_log_entries` - how many selenium entries to print with `debugWebDriverLogs` or on fail (0 by default).
* * `log_js_errors` - Set to true to include possible JavaScript to HTML report, or set to false (default) to deactivate. This will only work if `debug_log_entries` is set and its value is > 0. Also this will display JS errors as comments only if test fails.
* * `webdriver_proxy` - sets http proxy to tunnel requests to the remote Selenium WebDriver through
* * `webdriver_proxy_port` - sets http proxy server port to tunnel requests to the remote Selenium WebDriver through
*
* Example (`Acceptance.suite.yml`)
*
* ```yaml
* modules:
*    enabled:
*       - WebDriver:
*          url: 'http://localhost/'
*          browser: firefox
*          window_size: 1024x768
*          capabilities:
*              unhandledPromptBehaviour: 'accept'
*              moz:firefoxOptions:
*                  profile: '~/firefox-profiles/codeception-profile.zip.b64'
* ```
*
* ## Loading Parts from other Modules
*
* While all Codeception modules are designed to work stand-alone, it's still possible to load *several* modules at once. To use e.g. the [Asserts module](https://codeception.com/docs/modules/Asserts) in your acceptance tests, just load it like this in your `acceptance.suite.yml`:
*
* ```yaml
* modules:
*     enabled:
*         - WebDriver
*         - Asserts
* ```
*
* However, when loading a framework module (e.g. [Symfony](https://codeception.com/docs/modules/Symfony)) like this, it would lead to a conflict: When you call `$I->amOnPage()`, Codeception wouldn't know if you want to access the page using WebDriver's `amOnPage()`, or Symfony's `amOnPage()`. That's why possibly conflicting modules are separated into "parts". Here's how to load just the "services" part from e.g. Symfony:
* ```yaml
* modules:
*     enabled:
*         - WebDriver
*         - Symfony:
*             part: services
* ```
* To find out which parts each module has, look at the "Parts" header on the module's page.
*
* ## Usage
*
* ### Locating Elements
*
* Most methods in this module that operate on a DOM element (e.g. `click`) accept a locator as the first argument,
* which can be either a string or an array.
*
* If the locator is an array, it should have a single element,
* with the key signifying the locator type (`id`, `name`, `css`, `xpath`, `link`, or `class`)
* and the value being the locator itself.
* This is called a "strict" locator.
* Examples:
*
* * `['id' => 'foo']` matches `<div id="foo">`
* * `['name' => 'foo']` matches `<div name="foo">`
* * `['css' => 'input[type=input][value=foo]']` matches `<input type="input" value="foo">`
* * `['xpath' => "//input[@type='submit'][contains(@value, 'foo')]"]` matches `<input type="submit" value="foobar">`
* * `['link' => 'Click here']` matches `<a href="google.com">Click here</a>`
* * `['class' => 'foo']` matches `<div class="foo">`
*
* Writing good locators can be tricky.
* The Mozilla team has written an excellent guide titled [Writing reliable locators for Selenium and WebDriver tests](https://blog.mozilla.org/webqa/2013/09/26/writing-reliable-locators-for-selenium-and-webdriver-tests/).
*
* If you prefer, you may also pass a string for the locator. This is called a "fuzzy" locator.
* In this case, Codeception uses a a variety of heuristics (depending on the exact method called) to determine what element you're referring to.
* For example, here's the heuristic used for the `submitForm` method:
*
* 1. Does the locator look like an ID selector (e.g. "#foo")? If so, try to find a form matching that ID.
* 2. If nothing found, check if locator looks like a CSS selector. If so, run it.
* 3. If nothing found, check if locator looks like an XPath expression. If so, run it.
* 4. Throw an `ElementNotFound` exception.
*
* Be warned that fuzzy locators can be significantly slower than strict locators.
* Especially if you use Selenium WebDriver with `wait` (aka implicit wait) option.
* In the example above if you set `wait` to 5 seconds and use XPath string as fuzzy locator,
* `submitForm` method will wait for 5 seconds at each step.
* That means 5 seconds finding the form by ID, another 5 seconds finding by CSS
* until it finally tries to find the form by XPath).
* If speed is a concern, it's recommended you stick with explicitly specifying the locator type via the array syntax.
*
* ### Get Scenario Metadata
*
* You can inject `\Codeception\Scenario` into your test to get information about the current configuration:
* ```php
* use Codeception\Scenario;
*
* public function myTest(AcceptanceTester $I, Scenario $scenario)
* {
*     if ('firefox' === $scenario->current('browser')) {
*         // ...
*     }
* }
* ```
* See [Get Scenario Metadata](https://codeception.com/docs/07-AdvancedUsage#Get-Scenario-Metadata) for more information on `$scenario`.
*
* ## Public Properties
*
* * `webDriver` - instance of `\Facebook\WebDriver\Remote\RemoteWebDriver`. Can be accessed from Helper classes for complex WebDriver interactions.
*
* ```php
* // inside Helper class
* $this->getModule('WebDriver')->webDriver->getKeyboard()->sendKeys('hello, webdriver');
* ```
*
*/
class Web_Driver extends Codeception_Module implements Web_Interface, Remote_Interface, Multi_Session_Interface, Session_Snapshot, Screenshot_Saver, Page_Source_Saver, Element_Locator, Conflicts_With_Module, Requires_Package
{
    /**
     * @var string[]
     */
    protected array $required_fields = ['browser', 'url'];
    protected array $config = ['protocol' => 'http', 'host' => '127.0.0.1', 'port' => '4444', 'path' => '/wd/hub', 'start' => true, 'restart' => false, 'wait' => 0, 'clear_cookies' => true, 'window_size' => false, 'capabilities' => [], 'connection_timeout' => null, 'request_timeout' => null, 'pageload_timeout' => null, 'http_proxy' => null, 'http_proxy_port' => null, 'ssl_proxy' => null, 'ssl_proxy_port' => null, 'debug_log_entries' => 0, 'log_js_errors' => false, 'webdriver_proxy' => null, 'webdriver_proxy_port' => null];
    protected ?string $wd_host = null;
    /**
     * @var mixed
     */
    protected $capabilities;
    /**
     * @var float|int|null
     */
    protected $connection_timeout_in_ms;
    /**
     * @var float|int|null
     */
    protected $request_timeout_in_ms;
    protected array $sessions = [];
    protected array $session_snapshots = [];
    /**
     * @var mixed
     */
    protected $webdriver_proxy;
    /**
     * @var mixed
     */
    protected $webdriver_proxy_port;
    public ?Remote_Web_Driver $web_driver = null;
    protected ?Web_Driver_Search_Context $base_element = null;
    public function _requires(): array
    {
        return [Remote_Web_Driver::class => '"php-webdriver/webdriver": "^1.0.1"'];
    }
    /**
     * @throws ModuleException
     */
    protected function get_base_element(): Web_Driver_Search_Context
    {
        if (!$this->base_element) {
            throw new Module_Exception($this, 'Page not loaded. Use `$I->amOnPage` (or hidden API methods `_request` and `_loadPage`) to open it');
        }
        return $this->base_element;
    }
    public function _initialize(): void
    {
        $this->wd_host = sprintf('%s://%s:%s%s', $this->config['protocol'], $this->config['host'], $this->config['port'], $this->config['path']);
        $this->capabilities = $this->config['capabilities'];
        $this->capabilities[Web_Driver_Capability_Type::BROWSER_NAME] = $this->config['browser'];
        if ($proxy = $this->get_proxy()) {
            $this->capabilities[Web_Driver_Capability_Type::PROXY] = $proxy;
        }
        $this->connection_timeout_in_ms = $this->config['connection_timeout'] * 1000;
        $this->request_timeout_in_ms = $this->config['request_timeout'] * 1000;
        $this->webdriver_proxy = $this->config['webdriver_proxy'];
        $this->webdriver_proxy_port = $this->config['webdriver_proxy_port'];
        $this->load_firefox_profile();
    }
    /**
     * Change capabilities of WebDriver. Should be executed before starting a new browser session.
     * This method expects a function to be passed which returns array or [WebDriver Desired Capabilities](https://github.com/php-webdriver/php-webdriver/blob/main/lib/Remote/DesiredCapabilities.php) object.
     * Additional [Chrome options](https://github.com/php-webdriver/php-webdriver/wiki/ChromeOptions) (like adding extensions) can be passed as well.
     *
     * ```php
     * <?php // in helper
     * public function _before(TestInterface $test)
     * {
     *     $this->getModule('WebDriver')->_capabilities(function($currentCapabilities) {
     *         // or new \Facebook\WebDriver\Remote\DesiredCapabilities();
     *         return \Facebook\WebDriver\Remote\DesiredCapabilities::firefox();
     *     });
     * }
     * ```
     *
     * to make this work load `\Helper\Acceptance` before `WebDriver` in `acceptance.suite.yml`:
     *
     * ```yaml
     * modules:
     *     enabled:
     *         - \Helper\Acceptance
     *         - WebDriver
     * ```
     *
     * For instance, [**BrowserStack** cloud service](https://www.browserstack.com/automate/capabilities) may require a test name to be set in capabilities.
     * This is how it can be done via `_capabilities` method from `Helper\Acceptance`:
     *
     * ```php
     * <?php
     * // inside Helper\Acceptance
     * public function _before(TestInterface $test)
     * {
     *      $name = $test->getMetadata()->getName();
     *      $this->getModule('WebDriver')->_capabilities(function($currentCapabilities) use ($name) {
     *          $currentCapabilities['name'] = $name;
     *          return $currentCapabilities;
     *      });
     * }
     * ```
     * In this case, please ensure that `\Helper\Acceptance` is loaded before WebDriver so new capabilities could be applied.
     *
     * @api
     */
    public function _capabilities(Closure $capability_function): void
    {
        $this->capabilities = $capability_function($this->capabilities);
    }
    public function _conflicts(): string
    {
        return Web_Interface::class;
    }
    public function _before(Test_Interface $test): void
    {
        if ($this->web_driver === null && $this->config['start']) {
            $this->_initialize_session();
        }
        $this->set_base_element();
        $test->get_metadata()->set_current(['browser' => $this->web_driver->get_capabilities()->get_browser_name(), 'capabilities' => $this->web_driver->get_capabilities()->to_array()]);
    }
    /**
     * Restarts a web browser.
     * Can be used with `_reconfigure` to open browser with different configuration
     *
     * ```php
     * <?php
     * // inside a Helper
     * $this->getModule('WebDriver')->_restart(); // just restart
     * $this->getModule('WebDriver')->_restart(['browser' => $browser]); // reconfigure + restart
     * ```
     *
     * @api
     */
    public function _restart(array $config = []): void
    {
        $this->web_driver->quit();
        if (!empty($config)) {
            $this->_reconfigure($config);
        }
        $this->_initialize_session();
    }
    protected function on_reconfigure()
    {
        $this->_initialize();
    }
    protected function load_firefox_profile(): void
    {
        if (!array_key_exists('firefox_profile', $this->config['capabilities'])) {
            return;
        }
        $firefox_profile = $this->config['capabilities']['firefox_profile'];
        if (!file_exists($firefox_profile)) {
            throw new Module_Config_Exception(self::class, 'Firefox profile does not exist under given path ' . $firefox_profile);
        }
        // Set firefox profile as capability
        $this->capabilities['firefox_profile'] = file_get_contents($firefox_profile);
    }
    protected function initial_window_size(): void
    {
        if ($this->config['window_size'] == 'maximize') {
            $this->maximize_window();
            return;
        }
        $size = explode('x', (string) $this->config['window_size']);
        if (count($size) == 2) {
            $this->resize_window((int) $size[0], (int) $size[1]);
        }
    }
    public function _after(Test_Interface $test): void
    {
        if ($this->config['restart']) {
            $this->stop_all_sessions();
            return;
        }
        if ($this->config['clear_cookies'] && $this->web_driver !== null) {
            try {
                $this->web_driver->manage()->delete_all_cookies();
            } catch (Exception $exception) {
                // may cause fatal errors when not handled
                $this->debug("Error, can't clean cookies after a test: " . $exception->get_message());
            }
        }
    }
    public function _failed(Test_Interface $test, $fail): void
    {
        if (!$test instanceof Self_Describing) {
            // this exception should never been throw because all existing test types implement SelfDescribing
            throw new InvalidArgumentException('Test class does not implement SelfDescribing interface');
        }
        $this->debug_web_driver_logs($test);
        $filename = preg_replace('#[^a-zA-Z0-9\x80-\xff]#', '.', Descriptor::get_test_signature_unique($test));
        $output_dir = codecept_output_dir();
        $this->_save_screenshot($report = $output_dir . mb_strcut($filename, 0, 245, 'utf-8') . '.fail.png');
        $test->get_metadata()->add_report('png', $report);
        $this->_save_page_source($report = $output_dir . mb_strcut($filename, 0, 244, 'utf-8') . '.fail.html');
        $test->get_metadata()->add_report('html', $report);
        $this->debug("Screenshot and page source were saved into '{$output_dir}' dir");
    }
    /**
     * Print out latest Selenium Logs in debug mode
     */
    public function debug_web_driver_logs(?Test_Interface $test = null): void
    {
        if ($this->web_driver === null) {
            $this->debug('WebDriver::debugWebDriverLogs method has been called when webDriver is not set');
            return;
        }
        // don't show logs if log entries not set
        if (!$this->config['debug_log_entries']) {
            return;
        }
        try {
            // Dump out latest Selenium logs
            $logs = $this->web_driver->manage()->get_available_log_types();
            foreach ($logs as $log_type) {
                $log_entries = array_slice($this->web_driver->manage()->get_log($log_type), -$this->config['debug_log_entries']);
                if (empty($log_entries)) {
                    $this->debug_section("Selenium {$log_type} Logs", ' EMPTY ');
                    continue;
                }
                $this->debug_section("Selenium {$log_type} Logs", "\n" . $this->format_log_entries($log_entries));
                if ($log_type === 'browser' && $this->config['log_js_errors'] && $test instanceof Scenario_Driven) {
                    $this->log_js_errors($test, $log_entries);
                }
            }
        } catch (Exception $e) {
            $this->debug('Unable to retrieve Selenium logs : ' . $e->get_message());
        }
    }
    /**
     * Turns an array of log entries into a human-readable string.
     * Each log entry is an array with the keys "timestamp", "level", and "message".
     * See https://code.google.com/p/selenium/wiki/JsonWireProtocol#Log_Entry_JSON_Object
     */
    protected function format_log_entries(array $log_entries): string
    {
        $formatted_logs = '';
        foreach ($log_entries as $log_entry) {
            // Timestamp is in milliseconds, but date() requires seconds.
            $time = date('H:i:s', intval($log_entry['timestamp'] / 1000)) . '.' . $log_entry['timestamp'] % 1000;
            $formatted_logs .= "{$time} {$log_entry['level']} - {$log_entry['message']}\n";
        }
        return $formatted_logs;
    }
    /**
     * Logs JavaScript errors as comments.
     */
    protected function log_js_errors(Scenario_Driven $test, array $browser_log_entries): void
    {
        foreach ($browser_log_entries as $log_entry) {
            if (isset($log_entry['level']) && isset($log_entry['message']) && $this->is_js_error($log_entry['level'], $log_entry['message'])) {
                // Timestamp is in milliseconds, but date() requires seconds.
                $time = date('H:i:s', intval($log_entry['timestamp'] / 1000)) . '.' . $log_entry['timestamp'] % 1000;
                $test->get_scenario()->comment("{$time} {$log_entry['level']} - {$log_entry['message']}");
            }
        }
    }
    /**
     * Determines if the log entry is an error.
     * The decision is made depending on browser and log-level.
     */
    protected function is_js_error(string $log_entry_level, string $message): bool
    {
        return ($this->is_phantom() && $log_entry_level != 'INFO' || $log_entry_level === 'SEVERE') && !str_contains($message, 'ERR_PROXY_CONNECTION_FAILED');
        // ignore blackhole proxy
    }
    public function _after_suite(): void
    {
        // this is just to make sure webDriver is cleared after suite
        $this->stop_all_sessions();
    }
    protected function stop_all_sessions(): void
    {
        foreach ($this->sessions as $session) {
            $this->_close_session($session);
        }
        $this->web_driver = null;
        $this->base_element = null;
    }
    public function am_on_subdomain(string $subdomain): void
    {
        $url = $this->config['url'];
        $url = preg_replace('#(https?://)(.*\.)(.*\.)#', '$1$3', (string) $url);
        // removing current subdomain
        $url = preg_replace('#(https?://)(.*)#', sprintf('$1%s.$2', $subdomain), $url);
        // inserting new
        $this->_reconfigure(['url' => $url]);
    }
    /**
     * Returns URL of a host.
     *
     * @api
     * @return mixed
     * @throws ModuleConfigException
     */
    public function _get_url()
    {
        if (!isset($this->config['url'])) {
            throw new Module_Config_Exception(self::class, "Module connection failure. The URL for client can't bre retrieved");
        }
        return $this->config['url'];
    }
    protected function get_proxy(): ?array
    {
        $proxy_config = [];
        if ($this->config['http_proxy']) {
            $proxy_config['httpProxy'] = $this->config['http_proxy'];
            if ($this->config['http_proxy_port']) {
                $proxy_config['httpProxy'] .= ':' . $this->config['http_proxy_port'];
            }
        }
        if ($this->config['ssl_proxy']) {
            $proxy_config['sslProxy'] = $this->config['ssl_proxy'];
            if ($this->config['ssl_proxy_port']) {
                $proxy_config['sslProxy'] .= ':' . $this->config['ssl_proxy_port'];
            }
        }
        if (!empty($proxy_config)) {
            $proxy_config['proxyType'] = 'manual';
            return $proxy_config;
        }
        return null;
    }
    /**
     * Uri of currently opened page.
     * @api
     * @throws ModuleException
     */
    public function _get_current_uri(): string
    {
        $url = $this->web_driver->get_current_url();
        if ($url == 'about:blank' || str_starts_with($url, 'data:')) {
            throw new Module_Exception($this, 'Current url is blank, no page was opened');
        }
        return Uri::retrieve_uri($url);
    }
    public function _save_screenshot(string $filename): void
    {
        if ($this->web_driver === null) {
            $this->debug('WebDriver::_saveScreenshot method has been called when webDriver is not set');
            return;
        }
        try {
            $this->web_driver->take_screenshot($filename);
        } catch (Exception $e) {
            $this->debug('Unable to retrieve screenshot from Selenium : ' . $e->get_message());
            return;
        }
    }
    /**
     * @param string|array|WebDriverBy $selector
     */
    public function _save_element_screenshot($selector, string $filename): void
    {
        if ($this->web_driver === null) {
            $this->debug('WebDriver::_saveElementScreenshot method has been called when webDriver is not set');
            return;
        }
        try {
            $this->match_first_or_fail($this->web_driver, $selector)->take_element_screenshot($filename);
        } catch (Exception $e) {
            $this->debug('Unable to retrieve element screenshot from Selenium : ' . $e->get_message());
            return;
        }
    }
    public function _find_elements($locator): array
    {
        return $this->match($this->web_driver, $locator);
    }
    /**
     * Saves HTML source of a page to a file
     */
    public function _save_page_source(string $filename): void
    {
        if ($this->web_driver === null) {
            $this->debug('WebDriver::_savePageSource method has been called when webDriver is not set');
            return;
        }
        try {
            file_put_contents($filename, $this->web_driver->get_page_source());
        } catch (Exception $e) {
            $this->debug('Unable to retrieve source page from Selenium : ' . $e->get_message());
        }
    }
    /**
     * Takes a screenshot of the current window and saves it to `tests/_output/debug`.
     *
     * ``` php
     * <?php
     * $I->amOnPage('/user/edit');
     * $I->makeScreenshot('edit_page');
     * // saved to: tests/_output/debug/edit_page.png
     * $I->makeScreenshot();
     * // saved to: tests/_output/debug/2017-05-26_14-24-11_4b3403665fea6.png
     * ```
     */
    public function make_screenshot(?string $name = null): void
    {
        if (empty($name)) {
            $name = uniqid(date('Y-m-d_H-i-s_'));
        }
        $debug_dir = codecept_log_dir() . 'debug';
        if (!is_dir($debug_dir)) {
            mkdir($debug_dir);
        }
        $screen_name = $debug_dir . DIRECTORY_SEPARATOR . $name . '.png';
        $this->_save_screenshot($screen_name);
        $this->debug_section('Screenshot Saved', "file://{$screen_name}");
    }
    /**
     * Takes a screenshot of an element of the current window and saves it to `tests/_output/debug`.
     *
     * ``` php
     * <?php
     * $I->amOnPage('/user/edit');
     * $I->makeElementScreenshot('#dialog', 'edit_page');
     * // saved to: tests/_output/debug/edit_page.png
     * $I->makeElementScreenshot('#dialog');
     * // saved to: tests/_output/debug/2017-05-26_14-24-11_4b3403665fea6.png
     * ```
     *
     * @param WebDriverBy|array $selector
     */
    public function make_element_screenshot($selector, ?string $name = null): void
    {
        if (empty($name)) {
            $name = uniqid(date('Y-m-d_H-i-s_'));
        }
        $debug_dir = codecept_log_dir() . 'debug';
        if (!is_dir($debug_dir)) {
            mkdir($debug_dir);
        }
        $screen_name = $debug_dir . DIRECTORY_SEPARATOR . $name . '.png';
        $this->_save_element_screenshot($selector, $screen_name);
        $this->debug_section('Screenshot Saved', "file://{$screen_name}");
    }
    public function make_html_snapshot(?string $name = null): void
    {
        if (empty($name)) {
            $name = uniqid(date('Y-m-d_H-i-s_'));
        }
        $debug_dir = codecept_output_dir() . 'debug';
        if (!is_dir($debug_dir)) {
            mkdir($debug_dir);
        }
        $file_name = $debug_dir . DIRECTORY_SEPARATOR . $name . '.html';
        $this->_save_page_source($file_name);
        $this->debug_section('Snapshot Saved', "file://{$file_name}");
    }
    /**
     * Resize the current window.
     *
     * ``` php
     * <?php
     * $I->resizeWindow(800, 600);
     *
     * ```
     */
    public function resize_window(int $width, int $height): void
    {
        $this->web_driver->manage()->window()->set_size(new Web_Driver_Dimension($width, $height));
    }
    private function debug_cookies(): void
    {
        $result = [];
        $cookies = $this->web_driver->manage()->get_cookies();
        foreach ($cookies as $cookie) {
            $result[] = $cookie->to_array();
        }
        $this->debug_section('Cookies', json_encode($result, JSON_THROW_ON_ERROR));
    }
    public function see_cookie($cookie, array $params = [], bool $show_debug = true): void
    {
        $cookies = $this->filter_cookies($this->web_driver->manage()->get_cookies(), $params);
        $cookies = array_map(fn(\Facebook\Web_Driver\Cookie $c) => $c['name'], $cookies);
        if ($show_debug) {
            $this->debug_cookies();
        }
        $this->assert_contains($cookie, $cookies);
    }
    public function dont_see_cookie($cookie, array $params = [], bool $show_debug = true): void
    {
        $cookies = $this->filter_cookies($this->web_driver->manage()->get_cookies(), $params);
        $cookies = array_map(fn(\Facebook\Web_Driver\Cookie $c) => $c['name'], $cookies);
        if ($show_debug) {
            $this->debug_cookies();
        }
        $this->assert_not_contains($cookie, $cookies);
    }
    public function set_cookie($name, $value, array $params = [], $show_debug = true): void
    {
        $params['name'] = $name;
        $params['value'] = $value;
        if (isset($params['expires'])) {
            // PhpBrowser compatibility
            $params['expiry'] = $params['expires'];
        }
        // #5401 Supply defaults, otherwise chromedriver 2.46 complains.
        $defaults = ['path' => '/', 'expiry' => time() + 86400, 'secure' => false, 'httpOnly' => false];
        foreach ($defaults as $key => $default) {
            if (empty($params[$key])) {
                $params[$key] = $default;
            }
        }
        $this->web_driver->manage()->add_cookie($params);
        if ($show_debug) {
            $this->debug_cookies();
        }
    }
    public function reset_cookie($cookie, array $params = [], bool $show_debug = true): void
    {
        $this->web_driver->manage()->delete_cookie_named($cookie);
        if ($show_debug) {
            $this->debug_cookies();
        }
    }
    public function grab_cookie($cookie, array $params = []): mixed
    {
        $params['name'] = $cookie;
        $cookies = $this->filter_cookies($this->web_driver->manage()->get_cookies(), $params);
        if (empty($cookies)) {
            return null;
        }
        $cookie = reset($cookies);
        return $cookie['value'];
    }
    /**
     * Grabs current page source code.
     *
     * @throws ModuleException if no page was opened.
     * @return string Current page source code.
     */
    public function grab_page_source(): string
    {
        // Make sure that some page was opened.
        $this->_get_current_uri();
        return $this->web_driver->get_page_source();
    }
    /**
     * @param Cookie[] $cookies
     * @param array<string, string> $params
     * @return Cookie[]
     */
    protected function filter_cookies(array $cookies, array $params = []): array
    {
        foreach (['domain', 'path', 'name'] as $filter) {
            if (!isset($params[$filter])) {
                continue;
            }
            $cookies = array_filter($cookies, fn(\Facebook\Web_Driver\Cookie $item): bool => $item[$filter] == $params[$filter]);
        }
        return $cookies;
    }
    public function am_on_url($url): void
    {
        $host = Uri::retrieve_host($url);
        $this->_reconfigure(['url' => $host]);
        $this->debug_section('Host', $host);
        $this->web_driver->get($url);
    }
    public function am_on_page($page): void
    {
        $url = Uri::append_path($this->config['url'], $page);
        $this->debug_section('GET', $url);
        $this->web_driver->get($url);
    }
    public function see($text, $selector = null): void
    {
        if (!$selector) {
            $this->assert_page_contains($text);
            return;
        }
        $this->enable_implicit_wait();
        $nodes = $this->match_visible($selector);
        $this->disable_implicit_wait();
        $this->assert_nodes_contain($text, $nodes, $selector);
    }
    public function dont_see($text, $selector = null): void
    {
        if (!$selector) {
            $this->assert_page_not_contains($text);
        } else {
            $nodes = $this->match_visible($selector);
            $this->assert_nodes_not_contain($text, $nodes, $selector);
        }
    }
    public function see_in_source($raw): void
    {
        $this->assert_page_source_contains($raw);
    }
    public function dont_see_in_source($raw): void
    {
        $this->assert_page_source_not_contains($raw);
    }
    /**
     * Checks that the page source contains the given string.
     *
     * ```php
     * <?php
     * $I->seeInPageSource('<link rel="apple-touch-icon"');
     * ```
     */
    public function see_in_page_source(string $text): void
    {
        $this->assert_that($this->web_driver->get_page_source(), new Page_Constraint($text, $this->_get_current_uri()));
    }
    /**
     * Checks that the page source doesn't contain the given string.
     */
    public function dont_see_in_page_source(string $text): void
    {
        $this->assert_that_its_not($this->web_driver->get_page_source(), new Page_Constraint($text, $this->_get_current_uri()));
    }
    public function click($link, $context = null): void
    {
        $page = $this->web_driver;
        if ($context) {
            $page = $this->match_first_or_fail($this->web_driver, $context);
        }
        $el = $this->_find_clickable($page, $link);
        if ($el === null) {
            // check one more time if this was a CSS selector we didn't match
            try {
                $els = $this->match($page, $link);
            } catch (Malformed_Locator_Exception) {
                throw new Element_Not_Found("name={$link}", "'{$link}' is invalid CSS and XPath selector and Link or Button");
            }
            $el = reset($els);
        }
        if (!$el) {
            throw new Element_Not_Found($link, 'Link or Button or CSS or XPath');
        }
        $el->click();
    }
    /**
     * Locates a clickable element.
     *
     * Use it in Helpers or GroupObject or Extension classes:
     *
     * ```php
     * <?php
     * $module = $this->getModule('WebDriver');
     * $page = $module->webDriver;
     *
     * // search a link or button on a page
     * $el = $module->_findClickable($page, 'Click Me');
     *
     * // search a link or button within an element
     * $topBar = $module->_findElements('.top-bar')[0];
     * $el = $module->_findClickable($topBar, 'Click Me');
     *
     * ```
     * @param WebDriverSearchContext $page WebDriver instance or an element to search within
     * @param string|array|WebDriverBy $link A link text or locator to click
     * @api
     */
    public function _find_clickable(Web_Driver_Search_Context $page, $link): ?Web_Driver_Element
    {
        if (is_array($link) || $link instanceof Web_Driver_By) {
            return $this->match_first_or_fail($page, $link);
        }
        // try to match by strict locators, CSS Ids or XPath
        if (Locator::is_precise($link)) {
            return $this->match_first_or_fail($page, $link);
        }
        $locator = self::x_path_literal(trim((string) $link));
        // narrow
        $xpath = Locator::combine(".//a[normalize-space(.)={$locator}]", ".//button[normalize-space(.)={$locator}]", ".//a/img[normalize-space(@alt)={$locator}]/ancestor::a", ".//input[./@type = 'submit' or ./@type = 'image' or ./@type = 'button'][normalize-space(@value)={$locator}]");
        $els = $page->find_elements(Web_Driver_By::xpath($xpath));
        if (count($els) > 0) {
            return reset($els);
        }
        // wide
        $xpath = Locator::combine(".//a[./@href][((contains(normalize-space(string(.)), {$locator})) or contains(./@title, {$locator}) or .//img[contains(./@alt, {$locator})])]", ".//input[./@type = 'submit' or ./@type = 'image' or ./@type = 'button'][contains(./@value, {$locator})]", ".//input[./@type = 'image'][contains(./@alt, {$locator})]", ".//button[contains(normalize-space(string(.)), {$locator})]", ".//input[./@type = 'submit' or ./@type = 'image' or ./@type = 'button'][./@name = {$locator} or ./@title = {$locator}]", ".//button[./@name = {$locator} or ./@title = {$locator}]");
        $els = $page->find_elements(Web_Driver_By::xpath($xpath));
        if (count($els) > 0) {
            return reset($els);
        }
        return null;
    }
    /**
     * @param WebDriverElement|WebDriverBy|array|string $selector
     * @return WebDriverElement[]
     * @throws ElementNotFound
     */
    protected function find_fields($selector): array
    {
        if ($selector instanceof Web_Driver_Element) {
            return [$selector];
        }
        if (is_array($selector) || $selector instanceof Web_Driver_By) {
            $fields = $this->match($this->get_base_element(), $selector);
            if (empty($fields)) {
                throw new Element_Not_Found($selector);
            }
            return $fields;
        }
        $locator = self::x_path_literal(trim((string) $selector));
        // by text or label
        $xpath = Locator::combine(".//*[self::input | self::textarea | self::select][not(./@type = 'submit' or ./@type = 'image' or ./@type = 'hidden')][(((./@name = {$locator}) or ./@id = //label[contains(normalize-space(string(.)), {$locator})]/@for) or ./@placeholder = {$locator})]", ".//label[contains(normalize-space(string(.)), {$locator})]//.//*[self::input | self::textarea | self::select][not(./@type = 'submit' or ./@type = 'image' or ./@type = 'hidden')]");
        $fields = $this->get_base_element()->find_elements(Web_Driver_By::xpath($xpath));
        if (!empty($fields)) {
            return $fields;
        }
        // by name
        $xpath = ".//*[self::input | self::textarea | self::select][@name = {$locator}]";
        $fields = $this->get_base_element()->find_elements(Web_Driver_By::xpath($xpath));
        if (!empty($fields)) {
            return $fields;
        }
        // try to match by CSS or XPath
        $fields = $this->match($this->get_base_element(), $selector, false);
        if (!empty($fields)) {
            return $fields;
        }
        throw new Element_Not_Found($selector, 'Field by name, label, CSS or XPath');
    }
    /**
     * @param string|array|WebDriverBy|WebDriverElement $selector
     * @throws ElementNotFound
     */
    protected function find_field($selector): Web_Driver_Element
    {
        $arr = $this->find_fields($selector);
        return reset($arr);
    }
    public function see_link(string $text, ?string $url = null): void
    {
        $this->enable_implicit_wait();
        $nodes = $this->get_base_element()->find_elements(Web_Driver_By::partial_link_text($text));
        $this->disable_implicit_wait();
        $current_uri = $this->_get_current_uri();
        if (empty($nodes)) {
            $this->fail("No links containing text '{$text}' were found in page {$current_uri}");
        }
        if ($url) {
            $nodes = $this->filter_nodes_by_href($url, $nodes);
        }
        $this->assert_not_empty($nodes, "No links containing text '{$text}' and URL '{$url}' were found in page {$current_uri}");
    }
    public function dont_see_link(string $text, string $url = ''): void
    {
        $nodes = $this->get_base_element()->find_elements(Web_Driver_By::partial_link_text($text));
        $current_uri = $this->_get_current_uri();
        if (!$url) {
            $this->assert_empty($nodes, "Link containing text '{$text}' was found in page {$current_uri}");
        } else {
            $nodes = $this->filter_nodes_by_href($url, $nodes);
            $this->assert_empty($nodes, "Link containing text '{$text}' and URL '{$url}' was found in page {$current_uri}");
        }
    }
    private function filter_nodes_by_href(string $url, array $nodes): array
    {
        //current uri can be relative, merging it with configured base url gives absolute url
        $absolute_current_url = Uri::merge_urls($this->_get_url(), $this->_get_current_uri());
        $expected_url = Uri::merge_urls($absolute_current_url, $url);
        return array_filter($nodes, function (Web_Driver_Element $e) use ($expected_url, $absolute_current_url): bool {
            $element_href = Uri::merge_urls($absolute_current_url, $e->get_attribute('href') ?? '');
            return $element_href === $expected_url;
        });
    }
    public function see_in_current_url(string $uri): void
    {
        $this->assert_string_contains_string($uri, $this->_get_current_uri());
    }
    public function see_current_url_equals(string $uri): void
    {
        $this->assert_equals($uri, $this->_get_current_uri());
    }
    public function see_current_url_matches(string $uri): void
    {
        $this->assert_reg_exp($uri, $this->_get_current_uri());
    }
    public function dont_see_in_current_url(string $uri): void
    {
        $this->assert_string_not_contains_string($uri, $this->_get_current_uri());
    }
    public function dont_see_current_url_equals(string $uri): void
    {
        $this->assert_not_equals($uri, $this->_get_current_uri());
    }
    public function dont_see_current_url_matches(string $uri): void
    {
        $this->assert_not_reg_exp($uri, $this->_get_current_uri());
    }
    public function grab_from_current_url($uri = null): mixed
    {
        if (!$uri) {
            return $this->_get_current_uri();
        }
        $matches = [];
        $res = preg_match($uri, $this->_get_current_uri(), $matches);
        if (!$res) {
            $this->fail("Couldn't match {$uri} in " . $this->_get_current_uri());
        }
        if (!isset($matches[1])) {
            $this->fail("Nothing to grab. A regex parameter required. Ex: '/user/(\\d+)'");
        }
        return $matches[1];
    }
    public function see_checkbox_is_checked($checkbox): void
    {
        $this->assert_true($this->find_field($checkbox)->is_selected());
    }
    public function dont_see_checkbox_is_checked($checkbox): void
    {
        $this->assert_false($this->find_field($checkbox)->is_selected());
    }
    public function see_in_field($field, $value): void
    {
        $els = $this->find_fields($field);
        $this->assert($this->proceed_see_in_field($els, $value));
    }
    public function dont_see_in_field($field, $value): void
    {
        $els = $this->find_fields($field);
        $this->assert_not($this->proceed_see_in_field($els, $value));
    }
    public function see_in_form_fields($form_selector, array $params): void
    {
        $this->proceed_see_in_form_fields($form_selector, $params, false);
    }
    public function dont_see_in_form_fields($form_selector, array $params): void
    {
        $this->proceed_see_in_form_fields($form_selector, $params, true);
    }
    /**
     * @param string|array|WebDriverBy $formSelector
     * @throws ModuleException
     */
    protected function proceed_see_in_form_fields($form_selector, array $params, bool $assert_not)
    {
        $form = $this->match($this->get_base_element(), $form_selector);
        if (empty($form)) {
            throw new Element_Not_Found($form_selector, 'Form via CSS or XPath');
        }
        $form = reset($form);
        $els = [];
        foreach ($params as $name => $values) {
            $this->push_form_field($els, $form, $name, $values);
        }
        foreach ($els as $array_element) {
            [$el, $values] = $array_element;
            if (!is_array($values)) {
                $values = [$values];
            }
            foreach ($values as $value) {
                $ret = $this->proceed_see_in_field($el, $value);
                if ($assert_not) {
                    $this->assert_not($ret);
                } else {
                    $this->assert($ret);
                }
            }
        }
    }
    /**
     * Map an array element passed to seeInFormFields to its corresponding WebDriver element,
     * recursing through array values if the field is not found.
     *
     * @param array $els The previously found elements.
     * @param WebDriverElement $form The form in which to search for fields.
     * @param string $name The field's name.
     * @param mixed $values
     */
    protected function push_form_field(array &$els, Web_Driver_Element $form, string $name, $values): void
    {
        $el = $form->find_elements(Web_Driver_By::name($name));
        if ($el !== []) {
            $els[] = [$el, $values];
        } elseif (is_array($values)) {
            foreach ($values as $key => $value) {
                $this->push_form_field($els, $form, "{$name}[{$key}]", $value);
            }
        } else {
            throw new Element_Not_Found($name);
        }
    }
    /**
     * @param WebDriverElement[] $elements
     * @param mixed $value
     */
    protected function proceed_see_in_field(array $elements, $value): array
    {
        $str_field = reset($elements)->get_attribute('name');
        if (reset($elements)->get_tag_name() === 'select') {
            $el = reset($elements);
            $elements = $el->find_elements(Web_Driver_By::xpath('.//option'));
            if (empty($value) && empty($elements)) {
                return ['True', true];
            }
        }
        $current_values = [];
        if (is_bool($value)) {
            $current_values = [false];
        }
        foreach ($elements as $el) {
            switch ($el->get_tag_name()) {
                case 'input':
                    if ($el->get_attribute('type') === 'radio' || $el->get_attribute('type') === 'checkbox') {
                        if ($el->get_attribute('checked')) {
                            if (is_bool($value)) {
                                $current_values = [true];
                                break;
                            } else {
                                $current_values[] = $el->get_attribute('value');
                            }
                        }
                    } else {
                        $current_values[] = $el->get_attribute('value');
                    }
                    break;
                case 'option':
                    if (!$el->is_selected()) {
                        break;
                    }
                    $current_values[] = $el->get_text();
                // no break we need the trim text and the value also
                case 'textarea':
                    $current_values[] = trim((string) $el->get_text());
                // we include trimmed and real value of textarea for check
                // no break
                default:
                    $current_values[] = $el->get_attribute('value');
                    // raw value
                    break;
            }
        }
        return ['Contains', $value, $current_values, "Failed testing for '{$value}' in {$str_field}'s value: '" . implode("', '", $current_values) . "'"];
    }
    public function select_option($select, $option): void
    {
        $el = $this->find_field($select);
        if ($el->get_tag_name() != 'select') {
            $els = $this->match_checkables($select);
            $radio = null;
            foreach ($els as $el) {
                $radio = $this->find_checkable($el, $option, true);
                if ($radio) {
                    break;
                }
            }
            if (!$radio) {
                throw new Element_Not_Found($select, "Radiobutton with value or name '{$option} in");
            }
            $radio->click();
            return;
        }
        $wd_select = new Web_Driver_Select($el);
        if ($wd_select->is_multiple()) {
            $wd_select->deselect_all();
        }
        if (!is_array($option)) {
            $option = [$option];
        }
        $matched = false;
        if (key($option) !== 'value') {
            foreach ($option as $opt) {
                try {
                    $wd_select->select_by_visible_text($opt);
                    $matched = true;
                } catch (No_Such_Element_Exception) {
                }
            }
        }
        if ($matched) {
            return;
        }
        if (key($option) !== 'text') {
            foreach ($option as $opt) {
                try {
                    $wd_select->select_by_value($opt);
                    $matched = true;
                } catch (No_Such_Element_Exception) {
                }
            }
        }
        if ($matched) {
            return;
        }
        // partially matching
        foreach ($option as $opt) {
            try {
                $opt_element = $el->find_element(Web_Driver_By::xpath('.//option [contains (., "' . $opt . '")]'));
                $matched = true;
                if (!$opt_element->is_selected()) {
                    $opt_element->click();
                }
            } catch (No_Such_Element_Exception) {
                // exception treated at the end
            }
        }
        if ($matched) {
            return;
        }
        throw new Element_Not_Found(json_encode($option, JSON_THROW_ON_ERROR), "Option inside {$select} matched by name or value");
    }
    /**
     * Manually starts a new browser session.
     *
     * ```php
     * <?php
     * $this->getModule('WebDriver')->_initializeSession();
     * ```
     *
     * @api
     */
    public function _initialize_session(): void
    {
        try {
            $this->sessions[] = $this->web_driver;
            $this->web_driver = Remote_Web_Driver::create($this->wd_host, $this->capabilities, $this->connection_timeout_in_ms, $this->request_timeout_in_ms, $this->webdriver_proxy, $this->webdriver_proxy_port);
            if (!is_null($this->config['pageload_timeout'])) {
                $this->web_driver->manage()->timeouts()->page_load_timeout($this->config['pageload_timeout']);
            }
            $this->set_base_element();
            $this->initial_window_size();
        } catch (Unexpected_Response_Exception $exception) {
            codecept_debug('Curl error: ' . $exception->get_message());
            throw new Connection_Exception("Can't connect to WebDriver at {$this->wd_host}." . ' Make sure that ChromeDriver, GeckoDriver or Selenium Server is running.');
        }
    }
    /**
     * Loads current RemoteWebDriver instance as a session
     *
     * @param RemoteWebDriver $session
     * @api
     */
    public function _load_session($session): void
    {
        $this->web_driver = $session;
        $this->set_base_element();
    }
    /**
     * Returns current WebDriver session for saving
     *
     * @api
     */
    public function _backup_session(): Web_Driver_Interface
    {
        return $this->web_driver;
    }
    /**
     * Manually closes current WebDriver session.
     *
     * ```php
     * <?php
     * $this->getModule('WebDriver')->_closeSession();
     *
     * // close a specific session
     * $webDriver = $this->getModule('WebDriver')->webDriver;
     * $this->getModule('WebDriver')->_closeSession($webDriver);
     * ```
     *
     * @api
     * @param RemoteWebDriver|null $webDriver a specific webdriver session instance
     */
    public function _close_session($web_driver = null): void
    {
        if (!$web_driver && $this->web_driver) {
            $web_driver = $this->web_driver;
        }
        if (!$web_driver) {
            return;
        }
        try {
            $web_driver->quit();
            unset($web_driver);
        } catch (Php_Web_Driver_Exception_Interface) {
            // Session already closed so nothing to do
        }
    }
    /**
     * Unselect an option in the given select box.
     *
     * @param string|array|WebDriverBy $select
     * @param string|array|WebDriverBy $option
     */
    public function unselect_option($select, $option): void
    {
        $el = $this->find_field($select);
        $wd_select = new Web_Driver_Select($el);
        if (!is_array($option)) {
            $option = [$option];
        }
        $matched = false;
        foreach ($option as $opt) {
            try {
                $wd_select->deselect_by_visible_text($opt);
                $matched = true;
            } catch (No_Such_Element_Exception $e) {
                // exception treated at the end
            }
            try {
                $wd_select->deselect_by_value($opt);
                $matched = true;
            } catch (No_Such_Element_Exception) {
                // exception treated at the end
            }
        }
        if ($matched) {
            return;
        }
        throw new Element_Not_Found(json_encode($option), "Option inside {$select} matched by name or value");
    }
    /**
     * @param string|array|WebDriverBy|WebDriverElement $radioOrCheckbox
     */
    protected function find_checkable(Web_Driver_Search_Context $context, $radio_or_checkbox, bool $by_value = false): ?Web_Driver_Element
    {
        if ($radio_or_checkbox instanceof Web_Driver_Element) {
            return $radio_or_checkbox;
        }
        if (is_array($radio_or_checkbox) || $radio_or_checkbox instanceof Web_Driver_By) {
            return $this->match_first_or_fail($this->get_base_element(), $radio_or_checkbox);
        }
        $locator = self::x_path_literal($radio_or_checkbox);
        if ($context instanceof Web_Driver_Element && $context->get_tag_name() === 'input') {
            $context_type = $context->get_attribute('type');
            if (!in_array($context_type, ['checkbox', 'radio'], true)) {
                return null;
            }
            $name_literal = self::x_path_literal($context->get_attribute('name'));
            $type_literal = self::x_path_literal($context_type);
            $input_locator_fragment = "input[@type = {$type_literal}][@name = {$name_literal}]";
            $xpath = Locator::combine("ancestor::form//{$input_locator_fragment}[(@id = ancestor::form//label[contains(normalize-space(string(.)), {$locator})]/@for) or @placeholder = {$locator}]", "ancestor::form//label[contains(normalize-space(string(.)), {$locator})]//{$input_locator_fragment}");
            if ($by_value) {
                $xpath = Locator::combine($xpath, "ancestor::form//{$input_locator_fragment}[@value = {$locator}]");
            }
        } else {
            $xpath = Locator::combine("//input[@type = 'checkbox' or @type = 'radio'][(@id = //label[contains(normalize-space(string(.)), {$locator})]/@for) or @placeholder = {$locator} or @name = {$locator}]", "//label[contains(normalize-space(string(.)), {$locator})]//input[@type = 'radio' or @type = 'checkbox']");
            if ($by_value) {
                $xpath = Locator::combine($xpath, sprintf("//input[@type = 'checkbox' or @type = 'radio'][@value = %s]", $locator));
            }
        }
        $els = $context->find_elements(Web_Driver_By::xpath($xpath));
        if (count($els) > 0) {
            return reset($els);
        }
        $els = $context->find_elements(Web_Driver_By::xpath(str_replace('ancestor::form', '', $xpath)));
        if (count($els) > 0) {
            return reset($els);
        }
        $els = $this->match($context, $radio_or_checkbox);
        if (count($els) > 0) {
            return reset($els);
        }
        return null;
    }
    /**
     * @param string|array|WebDriverBy $selector
     * @return WebDriverElement[]
     */
    protected function match_checkables($selector): array
    {
        $els = $this->match($this->web_driver, $selector);
        if ($els === []) {
            throw new Element_Not_Found($selector, 'Element containing radio by CSS or XPath');
        }
        return $els;
    }
    public function check_option($option): void
    {
        $field = $this->find_checkable($this->web_driver, $option);
        if (!$field) {
            throw new Element_Not_Found($option, 'Checkbox or Radio by Label or CSS or XPath');
        }
        if ($field->is_selected()) {
            return;
        }
        $field->click();
    }
    public function uncheck_option($option): void
    {
        $field = $this->find_checkable($this->get_base_element(), $option);
        if (!$field) {
            throw new Element_Not_Found($option, 'Checkbox by Label or CSS or XPath');
        }
        if (!$field->is_selected()) {
            return;
        }
        $field->click();
    }
    public function fill_field($field, $value): void
    {
        $el = $this->find_field($field);
        $el->clear();
        $el->send_keys((string) $value);
    }
    /**
     * Clears given field which isn't empty.
     *
     * ``` php
     * <?php
     * $I->clearField('#username');
     * ```
     *
     * @param string|array|WebDriverBy $field
     */
    public function clear_field($field): void
    {
        $el = $this->find_field($field);
        $el->clear();
    }
    /**
     * Type in characters on active element.
     * With a second parameter you can specify delay between key presses.
     *
     * ```php
     * <?php
     * // activate input element
     * $I->click('#input');
     *
     * // type text in active element
     * $I->type('Hello world');
     *
     * // type text with a 1sec delay between chars
     * $I->type('Hello World', 1);
     * ```
     *
     * This might be useful when you an input reacts to typing and you need to slow it down to emulate human behavior.
     * For instance, this is how Credit Card fields can be filled in.
     *
     * @param int $delay [sec]
     */
    public function type(string $text, int $delay = 0): void
    {
        $keys = str_split($text);
        foreach ($keys as $key) {
            sleep($delay);
            $this->web_driver->get_keyboard()->press_key($key);
        }
        sleep($delay);
    }
    public function attach_file($field, string $filename): void
    {
        $el = $this->find_field($field);
        // in order to be compatible on different OS
        $file_path = codecept_data_dir() . $filename;
        if (!file_exists($file_path)) {
            throw new InvalidArgumentException("File does not exist: {$file_path}");
        }
        if (!is_readable($file_path)) {
            throw new InvalidArgumentException("File is not readable: {$file_path}");
        }
        // in order for remote upload to be enabled
        $el->set_file_detector(new Local_File_Detector());
        // skip file detector for phantomjs
        if ($this->is_phantom()) {
            $el->set_file_detector(new Useless_File_Detector());
        }
        $el->send_keys(realpath($file_path));
    }
    /**
     * Grabs all visible text from the current page.
     */
    protected function get_visible_text(): ?string
    {
        if ($this->get_base_element() instanceof Remote_Web_Element) {
            return $this->get_base_element()->get_text();
        }
        $els = $this->get_base_element()->find_elements(Web_Driver_By::css_selector('body'));
        if (isset($els[0])) {
            return $els[0]->get_text();
        }
        return '';
    }
    public function grab_text_from($css_or_x_path_or_regex): mixed
    {
        $els = $this->match($this->get_base_element(), $css_or_x_path_or_regex, false);
        if ($els !== []) {
            return $els[0]->get_text();
        }
        if (is_string($css_or_x_path_or_regex) && @preg_match($css_or_x_path_or_regex, $this->web_driver->get_page_source(), $matches)) {
            return $matches[1];
        }
        throw new Element_Not_Found($css_or_x_path_or_regex, 'CSS or XPath or Regex');
    }
    public function grab_attribute_from($css_or_xpath, $attribute): ?string
    {
        $el = $this->match_first_or_fail($this->get_base_element(), $css_or_xpath);
        return $el->get_attribute($attribute);
    }
    public function grab_value_from($field): ?string
    {
        $el = $this->find_field($field);
        // value of multiple select is the value of the first selected option
        if ($el->get_tag_name() == 'select') {
            $select = new Web_Driver_Select($el);
            return $select->get_first_selected_option()->get_attribute('value');
        }
        return $el->get_attribute('value');
    }
    public function grab_multiple($css_or_xpath, $attribute = null): array
    {
        $els = $this->match($this->get_base_element(), $css_or_xpath);
        return array_map(function (Web_Driver_Element $e) use ($attribute): ?string {
            if ($attribute) {
                return $e->get_attribute($attribute);
            }
            return $e->get_text();
        }, $els);
    }
    protected function filter_by_attributes($els, array $attributes)
    {
        foreach ($attributes as $attr => $value) {
            $els = array_filter($els, fn(Web_Driver_Element $el): bool => $el->get_attribute($attr) == $value);
        }
        return $els;
    }
    public function see_element($selector, array $attributes = []): void
    {
        $this->enable_implicit_wait();
        $els = $this->match_visible($selector);
        $this->disable_implicit_wait();
        $els = $this->filter_by_attributes($els, $attributes);
        $this->assert_not_empty($els);
    }
    public function dont_see_element($selector, array $attributes = []): void
    {
        $els = $this->match_visible($selector);
        $els = $this->filter_by_attributes($els, $attributes);
        $this->assert_empty($els);
    }
    /**
     * Checks that the given element exists on the page, even it is invisible.
     *
     * ``` php
     * <?php
     * $I->seeElementInDOM('//form/input[type=hidden]');
     * ```
     *
     * @param string|array|WebDriverBy $selector
     */
    public function see_element_in_dom($selector, array $attributes = []): void
    {
        $this->enable_implicit_wait();
        $els = $this->match($this->get_base_element(), $selector);
        $els = $this->filter_by_attributes($els, $attributes);
        $this->disable_implicit_wait();
        $this->assert_not_empty($els);
    }
    /**
     * Opposite of `seeElementInDOM`.
     *
     * @param string|array|WebDriverBy $selector
     */
    public function dont_see_element_in_dom($selector, array $attributes = []): void
    {
        $els = $this->match($this->get_base_element(), $selector);
        $els = $this->filter_by_attributes($els, $attributes);
        $this->assert_empty($els);
    }
    public function see_number_of_elements($selector, $expected): void
    {
        $counted = count($this->match_visible($selector));
        if (is_array($expected)) {
            [$floor, $ceil] = $expected;
            $this->assert_true($floor <= $counted && $ceil >= $counted, 'Number of elements counted differs from expected range');
        } else {
            $this->assert_same($expected, $counted, 'Number of elements counted differs from expected number');
        }
    }
    /**
     * @param string|array|WebDriverBy $selector
     * @param int|array $expected
     * @throws ModuleException
     */
    public function see_number_of_elements_in_dom($selector, $expected): void
    {
        $counted = count($this->match($this->get_base_element(), $selector));
        if (is_array($expected)) {
            [$floor, $ceil] = $expected;
            $this->assert_true($floor <= $counted && $ceil >= $counted, 'Number of elements counted differs from expected range');
        } else {
            $this->assert_same($expected, $counted, 'Number of elements counted differs from expected number');
        }
    }
    public function see_option_is_selected($selector, $option_text): void
    {
        $el = $this->find_field($selector);
        if ($el->get_tag_name() !== 'select') {
            $els = $this->match_checkables($selector);
            foreach ($els as $k => $el) {
                $els[$k] = $this->find_checkable($el, $option_text, true);
            }
            $this->assert_not_empty(array_filter($els, fn(?\Facebook\Web_Driver\Web_Driver_Element $e): bool => $e && $e->is_selected()));
        } else {
            $select = new Web_Driver_Select($el);
            $this->assert_nodes_contain($option_text, $select->get_all_selected_options(), 'option');
        }
    }
    public function dont_see_option_is_selected($selector, $option_text): void
    {
        $el = $this->find_field($selector);
        if ($el->get_tag_name() !== 'select') {
            $els = $this->match_checkables($selector);
            foreach ($els as $k => $el) {
                $els[$k] = $this->find_checkable($el, $option_text, true);
            }
            $this->assert_empty(array_filter($els, fn(?\Facebook\Web_Driver\Web_Driver_Element $e): bool => $e && $e->is_selected()));
        } else {
            $select = new Web_Driver_Select($el);
            $this->assert_nodes_not_contain($option_text, $select->get_all_selected_options(), 'option');
        }
    }
    public function see_in_title($title): void
    {
        $this->assert_string_contains_string($title, $this->web_driver->get_title());
    }
    public function dont_see_in_title($title): void
    {
        $this->assert_string_not_contains_string($title, $this->web_driver->get_title());
    }
    /**
     * Accepts the active JavaScript native popup window, as created by `window.alert`|`window.confirm`|`window.prompt`.
     * Don't confuse popups with modal windows,
     * as created by [various libraries](https://jster.net/category/windows-modals-popups).
     */
    public function accept_popup(): void
    {
        if ($this->is_phantom()) {
            throw new Module_Exception($this, 'PhantomJS does not support working with popups');
        }
        $this->web_driver->switch_to()->alert()->accept();
    }
    /**
     * Dismisses the active JavaScript popup, as created by `window.alert`, `window.confirm`, or `window.prompt`.
     */
    public function cancel_popup(): void
    {
        if ($this->is_phantom()) {
            throw new Module_Exception($this, 'PhantomJS does not support working with popups');
        }
        $this->web_driver->switch_to()->alert()->dismiss();
    }
    /**
     * Checks that the active JavaScript popup,
     * as created by `window.alert`|`window.confirm`|`window.prompt`, contains the given string.
     *
     * @throws ModuleException
     */
    public function see_in_popup(string $text): void
    {
        if ($this->is_phantom()) {
            throw new Module_Exception($this, 'PhantomJS does not support working with popups');
        }
        $alert = $this->web_driver->switch_to()->alert();
        try {
            $this->assert_string_contains_string($text, $alert->get_text());
        } catch (Php_Unit_Assertion_Failed_Error $failed_error) {
            $alert->dismiss();
            throw $failed_error;
        }
    }
    /**
     * Checks that the active JavaScript popup,
     * as created by `window.alert`|`window.confirm`|`window.prompt`, does NOT contain the given string.
     *
     * @throws ModuleException
     */
    public function dont_see_in_popup(string $text): void
    {
        if ($this->is_phantom()) {
            throw new Module_Exception($this, 'PhantomJS does not support working with popups');
        }
        $alert = $this->web_driver->switch_to()->alert();
        try {
            $this->assert_string_not_contains_string($text, $alert->get_text());
        } catch (Php_Unit_Assertion_Failed_Error $e) {
            $alert->dismiss();
            throw $e;
        }
    }
    /**
     * Enters text into a native JavaScript prompt popup, as created by `window.prompt`.
     *
     * @throws ModuleException
     */
    public function type_in_popup(string $keys): void
    {
        if ($this->is_phantom()) {
            throw new Module_Exception($this, 'PhantomJS does not support working with popups');
        }
        $this->web_driver->switch_to()->alert()->send_keys($keys);
    }
    /**
     * Reloads the current page. All forms will be reset, so the outcome is as if the user would press <kbd>Ctrl</kbd>+<kbd>F5</kbd>.
     */
    public function reload_page(): void
    {
        $this->web_driver->navigate()->refresh();
    }
    /**
     * Moves back in history.
     */
    public function move_back(): void
    {
        $this->web_driver->navigate()->back();
        $this->debug($this->_get_current_uri());
    }
    /**
     * Moves forward in history.
     */
    public function move_forward(): void
    {
        $this->web_driver->navigate()->forward();
        $this->debug($this->_get_current_uri());
    }
    protected function get_submission_form_field_name(string $name): string
    {
        if (str_ends_with($name, '[]')) {
            return substr($name, 0, -2);
        }
        return $name;
    }
    /**
     * Submits the given form on the page, optionally with the given form
     * values.  Give the form fields values as an array. Note that hidden fields
     * can't be accessed.
     *
     * Skipped fields will be filled by their values from the page.
     * You don't need to click the 'Submit' button afterwards.
     * This command itself triggers the request to form's action.
     *
     * You can optionally specify what button's value to include
     * in the request with the last parameter as an alternative to
     * explicitly setting its value in the second parameter, as
     * button values are not otherwise included in the request.
     *
     * Examples:
     *
     * ``` php
     * <?php
     * $I->submitForm('#login', [
     *     'login' => 'davert',
     *     'password' => '123456'
     * ]);
     * // or
     * $I->submitForm('#login', [
     *     'login' => 'davert',
     *     'password' => '123456'
     * ], 'submitButtonName');
     *
     * ```
     *
     * For example, given this sample "Sign Up" form:
     *
     * ``` html
     * <form action="/sign_up">
     *     Login:
     *     <input type="text" name="user[login]"><br>
     *     Password:
     *     <input type="password" name="user[password]"><br>
     *     Do you agree to our terms?
     *     <input type="checkbox" name="user[agree]"><br>
     *     Select pricing plan:
     *     <select name="plan">
     *         <option value="1">Free</option>
     *         <option value="2" selected="selected">Paid</option>
     *     </select>
     *     <input type="submit" name="submitButton" value="Submit">
     * </form>
     * ```
     *
     * You could write the following to submit it:
     *
     * ``` php
     * <?php
     * $I->submitForm(
     *     '#userForm',
     *     [
     *         'user[login]' => 'Davert',
     *         'user[password]' => '123456',
     *         'user[agree]' => true
     *     ],
     *     'submitButton'
     * );
     * ```
     * Note that "2" will be the submitted value for the "plan" field, as it is
     * the selected option.
     *
     * Also note that this differs from PhpBrowser, in that
     * ```'user' => [ 'login' => 'Davert' ]``` is not supported at the moment.
     * Named array keys *must* be included in the name as above.
     *
     * Pair this with seeInFormFields for quick testing magic.
     *
     * ``` php
     * <?php
     * $form = [
     *      'field1' => 'value',
     *      'field2' => 'another value',
     *      'checkbox1' => true,
     *      // ...
     * ];
     * $I->submitForm('//form[@id=my-form]', $form, 'submitButton');
     * // $I->amOnPage('/path/to/form-page') may be needed
     * $I->seeInFormFields('//form[@id=my-form]', $form);
     * ```
     *
     * Parameter values must be set to arrays for multiple input fields
     * of the same name, or multi-select combo boxes.  For checkboxes,
     * either the string value can be used, or boolean values which will
     * be replaced by the checkbox's value in the DOM.
     *
     * ``` php
     * <?php
     * $I->submitForm('#my-form', [
     *      'field1' => 'value',
     *      'checkbox' => [
     *          'value of first checkbox',
     *          'value of second checkbox',
     *      ],
     *      'otherCheckboxes' => [
     *          true,
     *          false,
     *          false,
     *      ],
     *      'multiselect' => [
     *          'first option value',
     *          'second option value',
     *      ]
     * ]);
     * ```
     *
     * Mixing string and boolean values for a checkbox's value is not supported
     * and may produce unexpected results.
     *
     * Field names ending in "[]" must be passed without the trailing square
     * bracket characters, and must contain an array for its value.  This allows
     * submitting multiple values with the same name, consider:
     *
     * ```php
     * $I->submitForm('#my-form', [
     *     'field[]' => 'value',
     *     'field[]' => 'another value', // 'field[]' is already a defined key
     * ]);
     * ```
     *
     * The solution is to pass an array value:
     *
     * ```php
     * // this way both values are submitted
     * $I->submitForm('#my-form', [
     *     'field' => [
     *         'value',
     *         'another value',
     *     ]
     * ]);
     * ```
     *
     * The `$button` parameter can be either a string, an array or an instance
     * of Facebook\WebDriver\WebDriverBy. When it is a string, the
     * button will be found by its "name" attribute. If $button is an
     * array then it will be treated as a strict selector and a WebDriverBy
     * will be used verbatim.
     *
     * For example, given the following HTML:
     *
     * ``` html
     * <input type="submit" name="submitButton" value="Submit">
     * ```
     *
     * `$button` could be any one of the following:
     *   - 'submitButton'
     *   - ['name' => 'submitButton']
     *   - WebDriverBy::name('submitButton')
     *
     * @param string|array|WebDriverBy $selector
     * @param string|array|WebDriverBy|null $button
     */
    public function submit_form($selector, array $params, $button = null): void
    {
        $form = $this->match_first_or_fail($this->get_base_element(), $selector);
        $fields = $form->find_elements(Web_Driver_By::css_selector('input:enabled[name],textarea:enabled[name],select:enabled[name],input[type=hidden][name]'));
        foreach ($fields as $field) {
            $field_name = $this->get_submission_form_field_name($field->get_attribute('name') ?? '');
            if (!isset($params[$field_name])) {
                continue;
            }
            $value = $params[$field_name];
            if (is_array($value) && $field->get_tag_name() !== 'select') {
                if ($field->get_attribute('type') === 'checkbox' || $field->get_attribute('type') === 'radio') {
                    $found = false;
                    foreach ($value as $index => $val) {
                        if (!is_bool($val) && $val === $field->get_attribute('value')) {
                            array_splice($params[$field_name], $index, 1);
                            $value = $val;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found && !empty($value) && is_bool(reset($value))) {
                        $value = array_pop($params[$field_name]);
                    }
                } else {
                    $value = array_pop($params[$field_name]);
                }
            }
            if ($field->get_attribute('type') === 'checkbox' || $field->get_attribute('type') === 'radio') {
                if ($value === true || $value === $field->get_attribute('value')) {
                    $this->check_option($field);
                } else {
                    $this->uncheck_option($field);
                }
            } elseif ($field->get_attribute('type') === 'button' || $field->get_attribute('type') === 'submit') {
                continue;
            } elseif ($field->get_tag_name() === 'select') {
                $this->select_option($field, $value);
            } else {
                $this->fill_field($field, $value);
            }
        }
        $this->debug_section('Uri', $form->get_attribute('action') ?: $this->_get_current_uri());
        $this->debug_section('Method', $form->get_attribute('method') ?: 'GET');
        $this->debug_section('Parameters', json_encode($params, JSON_THROW_ON_ERROR));
        $submitted = false;
        if (!empty($button)) {
            if (is_array($button)) {
                $button_selector = $this->get_strict_locator($button);
            } elseif ($button instanceof Web_Driver_By) {
                $button_selector = $button;
            } else {
                $button_selector = Web_Driver_By::name($button);
            }
            $els = $form->find_elements($button_selector);
            if (!empty($els)) {
                $el = reset($els);
                $el->click();
                $submitted = true;
            }
        }
        if (!$submitted) {
            $form->submit();
        }
        $this->debug_section('Page', $this->_get_current_uri());
    }
    /**
     * Waits up to `$timeout` seconds for the given element to change.
     * Element "change" is determined by a callback function which is called repeatedly
     * until the return value evaluates to true.
     *
     * ``` php
     * <?php
     * use Facebook\WebDriver\WebDriverElement;
     *
     * $I->waitForElementChange('#menu', function(WebDriverElement $element) {
     *     return $element->isDisplayed();
     * }, 5);
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @throws ElementNotFound
     */
    public function wait_for_element_change($element, Closure $callback, int $timeout = 30): void
    {
        $el = $this->match_first_or_fail($this->get_base_element(), $element);
        $checker = fn() => $callback($el);
        $this->web_driver->wait($timeout)->until($checker);
    }
    /**
     * Waits up to $timeout seconds for an element to appear on the page.
     * If the element doesn't appear, a timeout exception is thrown.
     *
     * ``` php
     * <?php
     * $I->waitForElement('#agree_button', 30); // secs
     * $I->click('#agree_button');
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @param int $timeout seconds
     * @throws Exception
     */
    public function wait_for_element($element, int $timeout = 10): void
    {
        $condition = Web_Driver_Expected_Condition::presence_of_element_located($this->get_locator($element));
        $this->web_driver->wait($timeout)->until($condition);
    }
    /**
     * Waits up to $timeout seconds for the given element to be visible on the page.
     * If element doesn't appear, a timeout exception is thrown.
     *
     * ``` php
     * <?php
     * $I->waitForElementVisible('#agree_button', 30); // secs
     * $I->click('#agree_button');
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @param int $timeout seconds
     * @throws Exception
     */
    public function wait_for_element_visible($element, int $timeout = 10): void
    {
        $condition = Web_Driver_Expected_Condition::visibility_of_element_located($this->get_locator($element));
        $this->web_driver->wait($timeout)->until($condition);
    }
    /**
     * Waits up to $timeout seconds for the given element to become invisible.
     * If element stays visible, a timeout exception is thrown.
     *
     * ``` php
     * <?php
     * $I->waitForElementNotVisible('#agree_button', 30); // secs
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @param int $timeout seconds
     * @throws Exception
     */
    public function wait_for_element_not_visible($element, int $timeout = 10): void
    {
        $condition = Web_Driver_Expected_Condition::invisibility_of_element_located($this->get_locator($element));
        $this->web_driver->wait($timeout)->until($condition);
    }
    /**
     * Waits up to $timeout seconds for the given element to be clickable.
     * If element doesn't become clickable, a timeout exception is thrown.
     *
     * ``` php
     * <?php
     * $I->waitForElementClickable('#agree_button', 30); // secs
     * $I->click('#agree_button');
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @param int $timeout seconds
     * @throws Exception
     */
    public function wait_for_element_clickable($element, int $timeout = 10): void
    {
        $condition = Web_Driver_Expected_Condition::element_to_be_clickable($this->get_locator($element));
        $this->web_driver->wait($timeout)->until($condition);
    }
    /**
     * Waits up to $timeout seconds for the given string to appear on the page.
     *
     * Can also be passed a selector to search in, be as specific as possible when using selectors.
     * waitForText() will only watch the first instance of the matching selector / text provided.
     * If the given text doesn't appear, a timeout exception is thrown.
     *
     * ``` php
     * <?php
     * $I->waitForText('foo', 30); // secs
     * $I->waitForText('foo', 30, '.title'); // secs
     * ```
     *
     * @param int $timeout seconds
     * @param null|string|array|WebDriverBy $selector
     * @throws Exception
     */
    public function wait_for_text(string $text, int $timeout = 10, $selector = null): void
    {
        $message = sprintf('Waited for %d secs but text %s still not found', $timeout, Locator::human_readable_string($text));
        if (!$selector) {
            $condition = Web_Driver_Expected_Condition::element_text_contains(Web_Driver_By::xpath('//body'), $text);
            $this->web_driver->wait($timeout)->until($condition, $message);
            return;
        }
        $condition = Web_Driver_Expected_Condition::element_text_contains($this->get_locator($selector), $text);
        $this->web_driver->wait($timeout)->until($condition, $message);
    }
    /**
     * Wait for $timeout seconds.
     *
     * @param int|float $timeout secs
     * @throws TestRuntimeException
     */
    public function wait($timeout): void
    {
        if ($timeout >= 1000) {
            throw new Test_Runtime_Exception("\n                Waiting for more then 1000 seconds: 16.6667 mins\n\n                Please note that wait method accepts number of seconds as parameter.");
        }
        usleep((int) ($timeout * 1000000));
    }
    /**
     * Low-level API method.
     * If Codeception commands are not enough, this allows you to use Selenium WebDriver methods directly:
     *
     * ``` php
     * $I->executeInSelenium(function(\Facebook\WebDriver\Remote\RemoteWebDriver $webdriver) {
     *   $webdriver->get('https://google.com');
     * });
     * ```
     *
     * This runs in the context of the
     * [RemoteWebDriver class](https://github.com/php-webdriver/php-webdriver/blob/master/lib/remote/RemoteWebDriver.php).
     * Try not to use this command on a regular basis.
     * If Codeception lacks a feature you need, please implement it and submit a patch.
     *
     * @return mixed
     */
    public function execute_in_selenium(Closure $function)
    {
        return $function($this->web_driver);
    }
    /**
     * Switch to another window identified by name.
     *
     * The window can only be identified by name. If the $name parameter is blank, the parent window will be used.
     *
     * Example:
     * ``` html
     * <input type="button" value="Open window" onclick="window.open('https://example.com', 'another_window')">
     * ```
     *
     * ``` php
     * <?php
     * $I->click("Open window");
     * # switch to another window
     * $I->switchToWindow("another_window");
     * # switch to parent window
     * $I->switchToWindow();
     * ```
     *
     * If the window has no name, match it by switching to next active tab using `switchToNextTab` method.
     *
     * Or use native Selenium functions to get access to all opened windows:
     *
     * ``` php
     * <?php
     * $I->executeInSelenium(function (\Facebook\WebDriver\Remote\RemoteWebDriver $webdriver) {
     *      $handles=$webdriver->getWindowHandles();
     *      $last_window = end($handles);
     *      $webdriver->switchTo()->window($last_window);
     * });
     * ```
     */
    public function switch_to_window(?string $name = null): void
    {
        $this->web_driver->switch_to()->window($name);
    }
    /**
     * Switch to another iframe on the page.
     *
     * Example:
     * ``` html
     * <iframe name="another_frame" id="fr1" src="https://example.com">
     *
     * ```
     *
     * ``` php
     * <?php
     * # switch to iframe by name
     * $I->switchToIFrame("another_frame");
     * # switch to iframe by CSS or XPath
     * $I->switchToIFrame("#fr1");
     * # switch to parent page
     * $I->switchToIFrame();
     *
     * ```
     *
     * @param string|null $locator (name, CSS or XPath)
     */
    public function switch_to_i_frame(?string $locator = null): void
    {
        $this->find_and_switch_to_frame($locator, 'iframe');
    }
    /**
     * Switch to another frame on the page.
     *
     * Example:
     * ``` html
     * <frame name="another_frame" id="fr1" src="https://example.com">
     *
     * ```
     *
     * ``` php
     * <?php
     * # switch to frame by name
     * $I->switchToFrame("another_frame");
     * # switch to frame by CSS or XPath
     * $I->switchToFrame("#fr1");
     * # switch to parent page
     * $I->switchToFrame();
     *
     * ```
     *
     * @param string|null $locator (name, CSS or XPath)
     */
    public function switch_to_frame(?string $locator = null): void
    {
        $this->find_and_switch_to_frame($locator);
    }
    private function find_and_switch_to_frame(?string $locator = null, string $tag = 'frame'): void
    {
        if ($locator === null) {
            $this->web_driver->switch_to()->default_content();
            return;
        }
        $els = null;
        try {
            $els = $this->_find_elements("{$tag}[name='{$locator}']");
        } catch (Exception $e) {
            $this->debug('Failed to find locator by name: ' . $e->get_message());
        }
        if (!isset($els) || !is_array($els) || $els === []) {
            $this->debug(ucfirst($tag) . ' was not found by name, locating ' . $tag . ' by CSS or XPath');
            $els = $this->_find_elements($locator);
        }
        if ($els === []) {
            throw new Element_Not_Found($locator, ucfirst($tag));
        }
        $this->web_driver->switch_to()->frame($els[0]);
    }
    /**
     * Executes JavaScript and waits up to $timeout seconds for it to return true.
     *
     * In this example we will wait up to 60 seconds for all jQuery AJAX requests to finish.
     *
     * ``` php
     * <?php
     * $I->waitForJS("return $.active == 0;", 60);
     * ```
     *
     * @param int $timeout seconds
     */
    public function wait_for_js(string $script, int $timeout = 5): void
    {
        $condition = fn($wd) => $wd->execute_script($script);
        $message = sprintf("Waited for %d secs but script %s still doesn't evaluate to true", $timeout, Locator::human_readable_string($script));
        $this->web_driver->wait($timeout)->until($condition, $message);
    }
    /**
     * Executes JavaScript commands.
     *
     * ```php
     * <?php
     * $myVar = $I->executeJS('return document.getElementById("myField").value');
     *
     * // Additional arguments can be passed as array. E.g. this will alert `Hello World`:
     * $I->executeJS("window.alert(arguments[0])", ['Hello world']);
     * ```
     *
     * @return mixed
     */
    public function execute_js(string $script, array $arguments = [])
    {
        return $this->web_driver->execute_script($script, $arguments);
    }
    /**
     * Executes asynchronous JavaScript.
     * A callback should be executed by JavaScript to exit from a script.
     * Callback is passed as a last element in `arguments` array.
     * Additional arguments can be passed as array in second parameter.
     *
     * ```js
     * // wait for 1200 milliseconds my running `setTimeout`
     * * $I->executeAsyncJS('setTimeout(arguments[0], 1200)');
     *
     * $seconds = 1200; // or seconds are passed as argument
     * $I->executeAsyncJS('setTimeout(arguments[1], arguments[0])', [$seconds]);
     * ```
     *
     * @return mixed
     */
    public function execute_async_js(string $script, array $arguments = [])
    {
        return $this->web_driver->execute_async_script($script, $arguments);
    }
    /**
     * Maximizes the current window.
     */
    public function maximize_window(): void
    {
        $this->web_driver->manage()->window()->maximize();
    }
    /**
     * Performs a simple mouse drag-and-drop operation.
     *
     * ``` php
     * <?php
     * $I->dragAndDrop('#drag', '#drop');
     * ```
     *
     * @param string|array|WebDriverBy $source (CSS ID or XPath)
     * @param string|array|WebDriverBy $target (CSS ID or XPath)
     */
    public function drag_and_drop($source, $target): void
    {
        $source_nodes = $this->match_first_or_fail($this->get_base_element(), $source);
        $target_nodes = $this->match_first_or_fail($this->get_base_element(), $target);
        $action = new Web_Driver_Actions($this->web_driver);
        $action->drag_and_drop($source_nodes, $target_nodes)->perform();
    }
    /**
     * Move mouse over the first element matched by the given locator.
     * If the first parameter null then the page is used.
     * If the second and third parameters are given,
     * then the mouse is moved to an offset of the element's top-left corner.
     * Otherwise, the mouse is moved to the center of the element.
     *
     * ``` php
     * <?php
     * $I->moveMouseOver(['css' => '.checkout']);
     * $I->moveMouseOver(null, 20, 50);
     * $I->moveMouseOver(['css' => '.checkout'], 20, 50);
     * ```
     *
     * @param null|string|array|WebDriverBy $cssOrXPath css or xpath of the web element
     * @throws ElementNotFound
     */
    public function move_mouse_over($css_or_x_path = null, ?int $offset_x = null, ?int $offset_y = null): void
    {
        $where = null;
        if (null !== $css_or_x_path) {
            $el = $this->match_first_or_fail($this->get_base_element(), $css_or_x_path);
            $where = $el->get_coordinates();
        }
        $this->web_driver->get_mouse()->mouse_move($where, $offset_x, $offset_y);
    }
    /**
     * Performs click with the left mouse button on an element.
     * If the first parameter `null` then the offset is relative to the actual mouse position.
     * If the second and third parameters are given,
     * then the mouse is moved to an offset of the element's top-left corner.
     * Otherwise, the mouse is moved to the center of the element.
     *
     * ``` php
     * <?php
     * $I->clickWithLeftButton(['css' => '.checkout']);
     * $I->clickWithLeftButton(null, 20, 50);
     * $I->clickWithLeftButton(['css' => '.checkout'], 20, 50);
     * ```
     *
     * @param null|string|array|WebDriverBy $cssOrXPath css or xpath of the web element (body by default).
     *
     * @throws ElementNotFound
     */
    public function click_with_left_button($css_or_x_path = null, ?int $offset_x = null, ?int $offset_y = null): void
    {
        $this->move_mouse_over($css_or_x_path, $offset_x, $offset_y);
        $this->web_driver->get_mouse()->click();
    }
    /**
     * Performs contextual click with the right mouse button on an element.
     * If the first parameter `null` then the offset is relative to the actual mouse position.
     * If the second and third parameters are given,
     * then the mouse is moved to an offset of the element's top-left corner.
     * Otherwise, the mouse is moved to the center of the element.
     *
     * ``` php
     * <?php
     * $I->clickWithRightButton(['css' => '.checkout']);
     * $I->clickWithRightButton(null, 20, 50);
     * $I->clickWithRightButton(['css' => '.checkout'], 20, 50);
     * ```
     *
     * @param null|string|array|WebDriverBy $cssOrXPath css or xpath of the web element (body by default).
     * @throws ElementNotFound
     */
    public function click_with_right_button($css_or_x_path = null, ?int $offset_x = null, ?int $offset_y = null): void
    {
        $this->move_mouse_over($css_or_x_path, $offset_x, $offset_y);
        $this->web_driver->get_mouse()->context_click();
    }
    /**
     * Performs a double click on an element matched by CSS or XPath.
     *
     * @param string|array|WebDriverBy $cssOrXPath
     * @throws ElementNotFound
     */
    public function double_click($css_or_x_path): void
    {
        $el = $this->match_first_or_fail($this->get_base_element(), $css_or_x_path);
        $this->web_driver->get_mouse()->double_click($el->get_coordinates());
    }
    /**
     * @param string|array|WebDriverBy $selector
     * @return WebDriverElement[]
     */
    protected function match(Web_Driver_Search_Context $page, $selector, bool $throw_malformed = true): array
    {
        if (is_array($selector)) {
            try {
                return $page->find_elements($this->get_strict_locator($selector));
            } catch (Invalid_Selector_Exception) {
                throw new Malformed_Locator_Exception(key($selector) . ' => ' . reset($selector), 'Strict locator');
            } catch (Invalid_Element_State_Exception $exception) {
                if ($this->is_phantom() && $exception->get_results()['status'] == 12) {
                    throw new Malformed_Locator_Exception(key($selector) . ' => ' . reset($selector), 'Strict locator ' . $exception->get_code());
                }
            }
        }
        if ($selector instanceof Web_Driver_By) {
            try {
                return $page->find_elements($selector);
            } catch (Invalid_Selector_Exception) {
                throw new Malformed_Locator_Exception(sprintf("WebDriverBy::%s('%s')", $selector->get_mechanism(), $selector->get_value()), 'WebDriver');
            }
        }
        $is_valid_locator = false;
        $nodes = [];
        try {
            if (Locator::is_id($selector)) {
                $is_valid_locator = true;
                $nodes = $page->find_elements(Web_Driver_By::id(substr($selector, 1)));
            }
            if (Locator::is_class($selector)) {
                $is_valid_locator = true;
                $nodes = $page->find_elements(Web_Driver_By::class_name(substr($selector, 1)));
            }
            if (empty($nodes) && Locator::is_css($selector)) {
                $is_valid_locator = true;
                try {
                    $nodes = $page->find_elements(Web_Driver_By::css_selector($selector));
                } catch (Invalid_Element_State_Exception) {
                    $nodes = $page->find_elements(Web_Driver_By::link_text($selector));
                }
            }
            if (empty($nodes) && Locator::is_x_path($selector)) {
                $is_valid_locator = true;
                $nodes = $page->find_elements(Web_Driver_By::xpath($selector));
            }
        } catch (Invalid_Selector_Exception) {
            throw new Malformed_Locator_Exception($selector);
        }
        if (!$is_valid_locator && $throw_malformed) {
            throw new Malformed_Locator_Exception($selector);
        }
        return $nodes;
    }
    protected function get_strict_locator(array $by): Web_Driver_By
    {
        $type = key($by);
        $locator = $by[$type];
        return match ($type) {
            'id' => Web_Driver_By::id($locator),
            'name' => Web_Driver_By::name($locator),
            'css' => Web_Driver_By::css_selector($locator),
            'xpath' => Web_Driver_By::xpath($locator),
            'link' => Web_Driver_By::link_text($locator),
            'class' => Web_Driver_By::class_name($locator),
            default => throw new Malformed_Locator_Exception("{$type} => {$locator}", 'Strict locator can be either xpath, css, id, link, class, name: '),
        };
    }
    /**
     * @param string|array|WebDriverBy $selector
     * @throws ElementNotFound
     */
    protected function match_first_or_fail(Web_Driver_Search_Context $page, $selector): Web_Driver_Element
    {
        $this->enable_implicit_wait();
        $els = $this->match($page, $selector);
        $this->disable_implicit_wait();
        if ($els === []) {
            throw new Element_Not_Found($selector, 'CSS or XPath');
        }
        return reset($els);
    }
    /**
     * Presses the given key on the given element.
     * To specify a character and modifier (e.g. <kbd>Ctrl</kbd>, <kbd>Alt</kbd>, <kbd>Shift</kbd>, <kbd>Meta</kbd>), pass an array for `$char` with
     * the modifier as the first element and the character as the second.
     * For special keys, use the constants from [Facebook\WebDriver\WebDriverKeys](https://github.com/php-webdriver/php-webdriver/blob/main/lib/WebDriverKeys.php).
     *
     * ``` php
     * <?php
     * // <input id="page" value="old">
     * $I->pressKey('#page', 'a'); // => olda
     * $I->pressKey('#page', ['ctrl', 'a'],'new'); // => new
     * $I->pressKey('#page', ['shift', '111'], '1', 'x'); // => old!!!1x
     * $I->pressKey('descendant-or-self::*[@id='page']', 'u'); // => oldu
     * $I->pressKey('#name', ['ctrl', 'a'], \Facebook\WebDriver\WebDriverKeys::DELETE); // =>''
     * ```
     *
     * @param string|array|WebDriverBy $element
     * @param string|list<string> $chars Can be char or array with modifier. You can provide several chars.
     * @throws ElementNotFound
     */
    public function press_key($element, ...$chars): void
    {
        $el = $this->match_first_or_fail($this->get_base_element(), $element);
        $keys = [];
        foreach ($chars as $char) {
            $keys[] = $this->convert_key_modifier($char);
        }
        $el->send_keys($keys);
    }
    /**
     * @param string|string[] $char
     * @return string|string[]
     */
    protected function convert_key_modifier($char)
    {
        if (is_string($char)) {
            return $char;
        }
        if (!isset($char[1])) {
            return $char;
        }
        [$modifier, $key] = $char;
        return match ($modifier) {
            'ctrl', 'control' => [Web_Driver_Keys::CONTROL, $key],
            'alt' => [Web_Driver_Keys::ALT, $key],
            'shift' => [Web_Driver_Keys::SHIFT, $key],
            'meta' => [Web_Driver_Keys::META, $key],
            default => $char,
        };
    }
    protected function assert_nodes_contain($text, $nodes, $selector = null): void
    {
        $this->assert_node_constraint($nodes, new Web_Driver_Constraint($text, $this->_get_current_uri()), $selector);
    }
    protected function assert_nodes_not_contain($text, $nodes, $selector = null): void
    {
        $this->assert_node_constraint($nodes, new Web_Driver_Constraint_Not($text, $this->_get_current_uri()), $selector);
    }
    protected function assert_node_constraint($nodes, Web_Driver_Constraint $constraint, $selector = null): void
    {
        $message = $selector;
        if (is_array($selector)) {
            $type = key($selector);
            $locator = $selector[$type];
            $message = $type . ':' . $locator;
        }
        $this->assert_that($nodes, $constraint, $message);
    }
    protected function assert_page_contains($needle, string $message = ''): void
    {
        $this->assert_that(htmlspecialchars_decode((string) $this->get_visible_text()), new Page_Constraint($needle, $this->_get_current_uri()), $message);
    }
    protected function assert_page_not_contains($needle, string $message = ''): void
    {
        $this->assert_that_its_not(htmlspecialchars_decode((string) $this->get_visible_text()), new Page_Constraint($needle, $this->_get_current_uri()), $message);
    }
    protected function assert_page_source_contains($needle, string $message = ''): void
    {
        $this->assert_that($this->web_driver->get_page_source(), new Page_Constraint($needle, $this->_get_current_uri()), $message);
    }
    protected function assert_page_source_not_contains($needle, string $message = ''): void
    {
        $this->assert_that_its_not($this->web_driver->get_page_source(), new Page_Constraint($needle, $this->_get_current_uri()), $message);
    }
    /**
     * Append the given text to the given element.
     * Can also add a selection to a select box.
     *
     * ``` php
     * <?php
     * $I->appendField('#mySelectbox', 'SelectValue');
     * $I->appendField('#myTextField', 'appended');
     * ```
     *
     * @param string|array|WebDriverBy $field
     * @throws ElementNotFound
     */
    public function append_field($field, string $value): void
    {
        $el = $this->find_field($field);
        switch ($el->get_tag_name()) {
            //Multiple select
            case 'select':
                $matched = false;
                $wd_select = new Web_Driver_Select($el);
                try {
                    $wd_select->select_by_visible_text($value);
                    $matched = true;
                } catch (No_Such_Element_Exception $e) {
                    // exception treated at the end
                }
                try {
                    $wd_select->select_by_value($value);
                    $matched = true;
                } catch (No_Such_Element_Exception) {
                    // exception treated at the end
                }
                if ($matched) {
                    return;
                }
                throw new Element_Not_Found(json_encode($value, JSON_THROW_ON_ERROR), "Option inside {$field} matched by name or value");
            case 'textarea':
                $el->send_keys($value);
                return;
            case 'div':
                //allows for content editable divs
                $el->send_keys(Web_Driver_Keys::END);
                $el->send_keys($value);
                return;
            //Text, Checkbox, Radio
            case 'input':
                $type = $el->get_attribute('type');
                if ($type == 'checkbox') {
                    //Find by value or css,id,xpath
                    $field = $this->find_checkable($this->get_base_element(), $value, true);
                    if (!$field) {
                        throw new Element_Not_Found($value, 'Checkbox or Radio by Label or CSS or XPath');
                    }
                    if ($field->is_selected()) {
                        return;
                    }
                    $field->click();
                    return;
                }
                if ($type == 'radio') {
                    $this->select_option($field, $value);
                    return;
                }
                $el->send_keys($value);
                return;
        }
        throw new Element_Not_Found($field, 'Field by name, label, CSS or XPath');
    }
    /**
     * @param string|array|WebDriverBy $selector
     */
    protected function match_visible($selector): array
    {
        $els = $this->match($this->get_base_element(), $selector);
        return array_filter($els, fn(Web_Driver_Element $el): bool => $el->is_displayed());
    }
    /**
     * @param string|array|WebDriverBy $selector
     * @throws InvalidArgumentException
     */
    protected function get_locator($selector): Web_Driver_By
    {
        if ($selector instanceof Web_Driver_By) {
            return $selector;
        }
        if (is_array($selector)) {
            return $this->get_strict_locator($selector);
        }
        if (Locator::is_id($selector)) {
            return Web_Driver_By::id(substr($selector, 1));
        }
        if (Locator::is_css($selector)) {
            return Web_Driver_By::css_selector($selector);
        }
        if (Locator::is_x_path($selector)) {
            return Web_Driver_By::xpath($selector);
        }
        throw new InvalidArgumentException('Only CSS or XPath allowed');
    }
    public function save_session_snapshot($name): void
    {
        $this->session_snapshots[$name] = [];
        foreach ($this->web_driver->manage()->get_cookies() as $cookie) {
            if (in_array(trim((string) $cookie['name']), [Local_Server::COVERAGE_COOKIE, Local_Server::COVERAGE_COOKIE_ERROR])) {
                continue;
            }
            if ($this->cookie_domain_matches_config_url($cookie)) {
                $this->session_snapshots[$name][] = $cookie;
            }
        }
        $this->debug_section('Snapshot', sprintf('Saved "%s" session snapshot', $name));
    }
    public function load_session_snapshot($name, bool $show_debug = true): bool
    {
        if (!isset($this->session_snapshots[$name])) {
            return false;
        }
        foreach ($this->web_driver->manage()->get_cookies() as $cookie) {
            if (in_array(trim((string) $cookie['name']), [Local_Server::COVERAGE_COOKIE, Local_Server::COVERAGE_COOKIE_ERROR])) {
                continue;
            }
            $this->web_driver->manage()->delete_cookie_named($cookie['name']);
        }
        foreach ($this->session_snapshots[$name] as $cookie) {
            $this->set_cookie($cookie['name'], $cookie['value'], (array) $cookie, false);
        }
        if ($show_debug) {
            $this->debug_cookies();
        }
        $this->debug_section('Snapshot', sprintf('Restored "%s" session snapshot', $name));
        return true;
    }
    public function delete_session_snapshot($name): void
    {
        if (isset($this->session_snapshots[$name])) {
            unset($this->session_snapshots[$name]);
        }
        $this->debug_section('Snapshot', sprintf('Deleted "%s" session snapshot', $name));
    }
    /**
     * Check if the cookie domain matches the config URL.
     *
     * Taken from Guzzle\Cookie\SetCookie
     */
    private function cookie_domain_matches_config_url(array|Web_Driver_Cookie $cookie): bool
    {
        if (!isset($cookie['domain'])) {
            return true;
        }
        $domain = parse_url((string) $this->config['url'], PHP_URL_HOST);
        // Remove the leading '.' as per spec in RFC 6265.
        // https://tools.ietf.org/html/rfc6265#section-5.2.3
        $cookie_domain = ltrim($cookie['domain'], '.');
        // Domain not set or exact match.
        if (!$cookie_domain || !strcasecmp($domain, $cookie_domain)) {
            return true;
        }
        // Matching the subdomain according to RFC 6265.
        // https://tools.ietf.org/html/rfc6265#section-5.1.3
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return false;
        }
        return (bool) preg_match('/\.' . preg_quote($cookie_domain, '/') . '$/', $domain);
    }
    protected function is_phantom(): bool
    {
        return str_starts_with((string) $this->config['browser'], 'phantom');
    }
    /**
     * Move to the middle of the given element matched by the given locator.
     * Extra shift, calculated from the top-left corner of the element,
     * can be set by passing $offsetX and $offsetY parameters.
     *
     * ``` php
     * <?php
     * $I->scrollTo(['css' => '.checkout'], 20, 50);
     * ```
     *
     * @param string|array|WebDriverBy $selector
     */
    public function scroll_to($selector, ?int $offset_x = null, ?int $offset_y = null): void
    {
        $el = $this->match_first_or_fail($this->get_base_element(), $selector);
        $x = $el->get_location()->get_x() + $offset_x;
        $y = $el->get_location()->get_y() + $offset_y;
        $this->web_driver->execute_script(sprintf('window.scrollTo(%d, %d)', $x, $y));
    }
    /**
     * Opens a new browser tab and switches to it.
     *
     * ```php
     * <?php
     * $I->openNewTab();
     * ```
     * The tab is opened with JavaScript's `window.open()`, which means:
     * * Some ad-blockers might restrict it.
     * * The sessionStorage is copied to the new tab (contrary to a tab that was manually opened by the user)
     */
    public function open_new_tab(): void
    {
        $this->execute_js("window.open('about:blank','_blank');");
        $this->switch_to_next_tab();
    }
    /**
     * Checks current number of opened tabs
     *
     * ```php
     * <?php
     * $I->seeNumberOfTabs(2);
     * ```
     */
    public function see_number_of_tabs(int $number): void
    {
        $this->assert_count($number, $this->web_driver->get_window_handles());
    }
    /**
     * Closes current browser tab and switches to previous active tab.
     *
     * ```php
     * <?php
     * $I->closeTab();
     * ```
     */
    public function close_tab(): void
    {
        $current_tab = $this->web_driver->get_window_handle();
        $prev_tab = $this->get_relative_tab_handle(-1);
        if ($prev_tab === $current_tab) {
            throw new Module_Exception($this, 'Will not close the last open tab');
        }
        $this->web_driver->close();
        $this->web_driver->switch_to()->window($prev_tab);
    }
    /**
     * Switches to next browser tab.
     * An offset can be specified.
     *
     * ```php
     * <?php
     * // switch to next tab
     * $I->switchToNextTab();
     * // switch to 2nd next tab
     * $I->switchToNextTab(2);
     * ```
     */
    public function switch_to_next_tab(int $offset = 1): void
    {
        $tab = $this->get_relative_tab_handle($offset);
        $this->web_driver->switch_to()->window($tab);
    }
    /**
     * Switches to previous browser tab.
     * An offset can be specified.
     *
     * ```php
     * <?php
     * // switch to previous tab
     * $I->switchToPreviousTab();
     * // switch to 2nd previous tab
     * $I->switchToPreviousTab(2);
     * ```
     */
    public function switch_to_previous_tab(int $offset = 1): void
    {
        $this->switch_to_next_tab(-$offset);
    }
    protected function get_relative_tab_handle($offset)
    {
        if ($this->is_phantom()) {
            throw new Module_Exception($this, "PhantomJS doesn't support tab actions");
        }
        $handle = $this->web_driver->get_window_handle();
        $handles = $this->web_driver->get_window_handles();
        $current_handle_idx = array_search($handle, $handles);
        $new_handle_idx = ($current_handle_idx + $offset) % count($handles);
        if ($new_handle_idx < 0) {
            $new_handle_idx = count($handles) + $new_handle_idx;
        }
        return $handles[$new_handle_idx];
    }
    /**
     * Waits for element and runs a sequence of actions inside its context.
     * Actions can be defined with array, callback, or `Codeception\Util\ActionSequence` instance.
     *
     * Actions as array are recommended for simple to combine "waitForElement" with assertions;
     * `waitForElement($el)` and `see('text', $el)` can be simplified to:
     *
     * ```php
     * <?php
     * $I->performOn($el, ['see' => 'text']);
     * ```
     *
     * List of actions can be pragmatically build using `Codeception\Util\ActionSequence`:
     *
     * ```php
     * <?php
     * $I->performOn('.model', ActionSequence::build()
     *     ->see('Warning')
     *     ->see('Are you sure you want to delete this?')
     *     ->click('Yes')
     * );
     * ```
     *
     * Actions executed from array or ActionSequence will print debug output for actions, and adds an action name to
     * exception on failure.
     *
     * Whenever you need to define more actions a callback can be used. A WebDriver module is passed for argument:
     *
     * ```php
     * <?php
     * $I->performOn('.rememberMe', function (WebDriver $I) {
     *      $I->see('Remember me next time');
     *      $I->seeElement('#LoginForm_rememberMe');
     *      $I->dontSee('Login');
     * });
     * ```
     *
     * In 3rd argument you can set number a seconds to wait for element to appear
     *
     * @param string|array|WebDriverBy $element
     * @param callable|array|\Codeception\Util\ActionSequence $actions
     */
    public function perform_on($element, $actions, int $timeout = 10): void
    {
        $this->wait_for_element($element, $timeout);
        $this->set_base_element($element);
        $this->debug_section('InnerText', $this->get_base_element()->get_text());
        if (is_callable($actions)) {
            $actions($this);
            $this->set_base_element();
            return;
        }
        if (is_array($actions)) {
            $actions = Action_Sequence::build()->from_array($actions);
        }
        if (!$actions instanceof Action_Sequence) {
            throw new InvalidArgumentException('2nd parameter, actions should be callback, ActionSequence or array');
        }
        $actions->run($this);
        $this->set_base_element();
    }
    /**
     * @param string|array|WebDriverBy $element
     */
    protected function set_base_element($element = null): void
    {
        if ($element === null) {
            $this->base_element = $this->web_driver;
            return;
        }
        $this->base_element = $this->match_first_or_fail($this->web_driver, $element);
    }
    protected function enable_implicit_wait(): void
    {
        if (!$this->config['wait']) {
            return;
        }
        $this->web_driver->manage()->timeouts()->implicitly_wait($this->config['wait']);
    }
    protected function disable_implicit_wait(): void
    {
        if (!$this->config['wait']) {
            return;
        }
        $this->web_driver->manage()->timeouts()->implicitly_wait(0);
    }
    /**
     * From symfony/dom-crawler
     *
     * Converts string for XPath expressions.
     *
     * Escaped characters are: quotes (") and apostrophe (').
     *
     *  Examples:
     *
     *     echo self::xPathLiteral('foo " bar');
     *     //prints 'foo " bar'
     *
     *     echo self::xPathLiteral("foo ' bar");
     *     //prints "foo ' bar"
     *
     *     echo self::xPathLiteral('a\'b"c');
     *     //prints concat('a', "'", 'b"c')
     *
     * @return string Converted string
     */
    private static function x_path_literal($s): string
    {
        if (!str_contains((string) $s, "'")) {
            return sprintf("'%s'", $s);
        }
        if (!str_contains((string) $s, '"')) {
            return sprintf('"%s"', $s);
        }
        $string = $s;
        $parts = [];
        while (true) {
            if (false !== $pos = strpos((string) $string, "'")) {
                $parts[] = sprintf("'%s'", substr((string) $string, 0, $pos));
                $parts[] = "\"'\"";
                $string = substr((string) $string, $pos + 1);
            } else {
                $parts[] = "'{$string}'";
                break;
            }
        }
        return sprintf('concat(%s)', implode(', ', $parts));
    }
}