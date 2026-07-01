# KwaWingu Tours WordPress Plugin — v0.1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship v0.1 of the `kwawingu-tours` WordPress plugin — a working data foundation: settings, an API client for the KwaWingu `/api/v1` developer API, native `kwt_tour`/`kwt_destination` custom post types, and a sync engine (scheduled + "Sync now") that imports the operator's catalog into WordPress.

**Architecture:** A classic-structure WordPress plugin (namespaced PHP classes, Composer PSR-4 autoload). `Api_Client` wraps `wp_remote_get` with the `X-API-Key` header and envelope/error handling. `Cpt` registers the post types + taxonomy. `Sync` pulls `GET /site` and upserts posts by `kwt_id`, preserving operator edits and soft-unpublishing removed tours. `Settings` stores credentials + sync config and renders the admin page. WP-Cron + an admin button drive sync.

**Tech Stack:** PHP 7.4+, WordPress 6.2+, Composer (PSR-4), PHPUnit 9 + Brain\Monkey + Mockery (pure-PHP unit tests that mock WP functions — no Docker/WP install), PHPCS + WPCS (lint), GitHub Actions.

## Global Constraints

- Plugin slug / text domain: `kwawingu-tours`. PHP prefix for globals/constants: `KWT_` / `kwt_`. Class namespace: `KwaWingu\Tours`.
- License: GPL-2.0-or-later (every PHP file carries no license header beyond the main file; main file declares it).
- API base default: `https://tours.kwawingu.com/api/v1`. Auth header: `X-API-Key`. List envelope: `{ data, page, size, total, hasMore }`. Error envelope: `{ error: { code, message } }`. A `403` with `code == "api_access_required"` is a distinct, surfaced state.
- Security: all output escaped (`esc_html`/`esc_attr`/`esc_url`), all input sanitized, nonces + `current_user_can('manage_options')` on every admin write. The **private key is never printed to the front end or exposed via REST** — server-side use only. All HTTP is server-side.
- WordPress Coding Standards (WPCS). i18n: every user-facing string wrapped in `__()`/`esc_html__()` with domain `kwawingu-tours`.
- Targets PHP 7.4+, WP 6.2+. No bundled minified libraries.
- **Prerequisite:** PHP 7.4+ and Composer available in the dev environment. Repo root is `/home/collo/org/kw/kw-wp-plugin` (already a git repo, branch `main`, containing the design spec + `.gitignore`).

---

### Task 1: Tooling + plugin bootstrap scaffold

**Files:**
- Create: `composer.json`
- Create: `kwawingu-tours.php` (main plugin file)
- Create: `includes/Plugin.php`
- Create: `tests/bootstrap.php`
- Create: `phpunit.xml.dist`
- Create: `tests/PluginTest.php`

**Interfaces:**
- Produces: `KwaWingu\Tours\Plugin::instance(): Plugin`, `Plugin::VERSION` (string), constant `KWT_API_BASE`, `KWT_PLUGIN_DIR`, `KWT_PLUGIN_FILE`. Later tasks call `Plugin::instance()` and hang subsystems off it.

- [ ] **Step 1: Write `composer.json`**

```json
{
  "name": "kwawingu/kwawingu-tours",
  "description": "Build a tour-operator website fast on your KwaWingu Tours data.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": { "php": ">=7.4" },
  "require-dev": {
    "phpunit/phpunit": "^9.6",
    "brain/monkey": "^2.6",
    "mockery/mockery": "^1.6",
    "php-stubs/wordpress-stubs": "^6.4",
    "squizlabs/php_codesniffer": "^3.9",
    "wp-coding-standards/wpcs": "^3.0",
    "phpcompatibility/phpcompatibility-wp": "^2.1"
  },
  "autoload": { "psr-4": { "KwaWingu\\Tours\\": "includes/" } },
  "autoload-dev": { "psr-4": { "KwaWingu\\Tours\\Tests\\": "tests/" } },
  "config": { "allow-plugins": { "dealerdirect/phpcodesniffer-composer-installer": true } },
  "scripts": {
    "test": "phpunit",
    "lint": "phpcs"
  }
}
```

- [ ] **Step 2: Install dependencies**

Run: `cd /home/collo/org/kw/kw-wp-plugin && composer install`
Expected: `vendor/` created, no errors. (If `dealerdirect/phpcodesniffer-composer-installer` prompts, it is already allow-listed.)

- [ ] **Step 3: Write the main plugin file `kwawingu-tours.php`**

```php
<?php
/**
 * Plugin Name:       KwaWingu Tours
 * Plugin URI:        https://tours.kwawingu.com
 * Description:        Build a tour-operator website fast on your KwaWingu Tours data — sync your catalog into WordPress, add blocks, and go live in minutes.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            KwaWingu Tours
 * Author URI:        https://tours.kwawingu.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kwawingu-tours
 * Domain Path:       /languages
 *
 * @package KwaWingu\Tours
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KWT_VERSION', '0.1.0' );
define( 'KWT_PLUGIN_FILE', __FILE__ );
define( 'KWT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KWT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
if ( ! defined( 'KWT_API_BASE' ) ) {
    define( 'KWT_API_BASE', 'https://tours.kwawingu.com/api/v1' );
}

$kwt_autoload = KWT_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $kwt_autoload ) ) {
    require $kwt_autoload;
}

add_action( 'plugins_loaded', static function () {
    \KwaWingu\Tours\Plugin::instance()->boot();
} );

register_activation_hook( __FILE__, static function () {
    // CPTs must exist before flushing so their rewrite rules register.
    \KwaWingu\Tours\Plugin::instance()->boot();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function () {
    wp_clear_scheduled_hook( 'kwt_sync_cron' );
    flush_rewrite_rules();
} );
```

- [ ] **Step 4: Write `includes/Plugin.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Root container. Instantiates and boots subsystems.
 */
final class Plugin {

    const VERSION = '0.1.0';

    /** @var Plugin|null */
    private static $instance = null;

    /** @var bool */
    private $booted = false;

    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Wire up subsystems. Safe to call more than once. */
    public function boot(): void {
        if ( $this->booted ) {
            return;
        }
        $this->booted = true;
        // Subsystems registered in later tasks:
        // ( new Cpt() )->register();
        // ( new Settings() )->register();
        // ( new Sync( new Api_Client( new Settings() ) ) )->register();
    }
}
```

- [ ] **Step 5: Write `tests/bootstrap.php`**

```php
<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define the minimal constants the classes reference at load time.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
if ( ! defined( 'KWT_API_BASE' ) ) {
    define( 'KWT_API_BASE', 'https://tours.kwawingu.com/api/v1' );
}
```

- [ ] **Step 6: Write `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.6/phpunit.xsd">
  <testsuites>
    <testsuite name="unit">
      <directory>tests</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 7: Write the failing test `tests/PluginTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use KwaWingu\Tours\Plugin;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase {

    public function test_instance_is_singleton(): void {
        $this->assertSame( Plugin::instance(), Plugin::instance() );
    }

    public function test_version_constant_matches(): void {
        $this->assertSame( '0.1.0', Plugin::VERSION );
    }
}
```

- [ ] **Step 8: Run the tests**

Run: `cd /home/collo/org/kw/kw-wp-plugin && vendor/bin/phpunit`
Expected: 2 tests, PASS (green).

- [ ] **Step 9: Commit**

```bash
git add composer.json composer.lock kwawingu-tours.php includes/Plugin.php tests/ phpunit.xml.dist
git commit -m "chore(scaffold): plugin bootstrap + PHPUnit/Brain-Monkey tooling (v0.1 task 1)"
```

---

### Task 2: Settings store + sanitization

**Files:**
- Create: `includes/Settings.php`
- Create: `tests/SettingsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `Settings::get_slug(): string`, `get_public_key(): string`, `get_private_key(): string`, `get_sync_interval(): string` (`hourly|twicedaily|daily`), `get_media_mode(): string` (`sideload|hotlink`), `get_booking_mode(): string` (`redirect|widget|onsite`).
  - `Settings::OPTION` = `'kwt_settings'` (single serialized option array).
  - `Settings::sanitize( array $input ): array` — the `register_setting` callback.
  - `Settings::register(): void` — registers the setting + admin page hooks (page rendering added in Task 3).

- [ ] **Step 1: Write the failing test `tests/SettingsTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        // WP sanitizers used by Settings::sanitize().
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'sanitize_key' )->alias( static function ( $k ) {
            return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $k ) );
        } );
        Functions\when( 'esc_url_raw' )->returnArg();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_sanitize_trims_and_defaults(): void {
        $settings = new Settings();
        $out = $settings->sanitize( array(
            'slug'          => '  Serengeti-Tours  ',
            'public_key'    => ' kw_live_abc ',
            'private_key'   => ' kw_live_secret ',
            'sync_interval' => 'weekly',        // invalid -> falls back
            'media_mode'    => 'bogus',         // invalid -> falls back
            'booking_mode'  => 'onsite',
        ) );

        $this->assertSame( 'serengeti-tours', $out['slug'] );      // slugified
        $this->assertSame( 'kw_live_abc', $out['public_key'] );    // trimmed
        $this->assertSame( 'kw_live_secret', $out['private_key'] );
        $this->assertSame( 'hourly', $out['sync_interval'] );      // invalid -> default
        $this->assertSame( 'sideload', $out['media_mode'] );       // invalid -> default
        $this->assertSame( 'onsite', $out['booking_mode'] );
    }

    public function test_getters_read_option_with_defaults(): void {
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme' ) );
        $settings = new Settings();
        $this->assertSame( 'acme', $settings->get_slug() );
        $this->assertSame( '', $settings->get_public_key() );      // missing -> ''
        $this->assertSame( 'redirect', $settings->get_booking_mode() ); // missing -> default
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter SettingsTest`
Expected: FAIL — `Class "KwaWingu\Tours\Settings" not found`.

- [ ] **Step 3: Write `includes/Settings.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Plugin settings: a single serialized option array + typed getters + sanitizer.
 */
class Settings {

    const OPTION = 'kwt_settings';

    const SYNC_INTERVALS = array( 'hourly', 'twicedaily', 'daily' );
    const MEDIA_MODES    = array( 'sideload', 'hotlink' );
    const BOOKING_MODES  = array( 'redirect', 'widget', 'onsite' );

    /** @return array<string,mixed> */
    private function all(): array {
        $stored = get_option( self::OPTION, array() );
        return is_array( $stored ) ? $stored : array();
    }

    public function get_slug(): string {
        return (string) ( $this->all()['slug'] ?? '' );
    }

    public function get_public_key(): string {
        return (string) ( $this->all()['public_key'] ?? '' );
    }

    public function get_private_key(): string {
        return (string) ( $this->all()['private_key'] ?? '' );
    }

    public function get_sync_interval(): string {
        $v = (string) ( $this->all()['sync_interval'] ?? 'hourly' );
        return in_array( $v, self::SYNC_INTERVALS, true ) ? $v : 'hourly';
    }

    public function get_media_mode(): string {
        $v = (string) ( $this->all()['media_mode'] ?? 'sideload' );
        return in_array( $v, self::MEDIA_MODES, true ) ? $v : 'sideload';
    }

    public function get_booking_mode(): string {
        $v = (string) ( $this->all()['booking_mode'] ?? 'redirect' );
        return in_array( $v, self::BOOKING_MODES, true ) ? $v : 'redirect';
    }

    /**
     * register_setting sanitize callback.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function sanitize( $input ): array {
        $input = is_array( $input ) ? $input : array();

        $slug          = sanitize_key( trim( (string) ( $input['slug'] ?? '' ) ) );
        $public_key    = sanitize_text_field( trim( (string) ( $input['public_key'] ?? '' ) ) );
        $private_key   = sanitize_text_field( trim( (string) ( $input['private_key'] ?? '' ) ) );
        $sync_interval = (string) ( $input['sync_interval'] ?? 'hourly' );
        $media_mode    = (string) ( $input['media_mode'] ?? 'sideload' );
        $booking_mode  = (string) ( $input['booking_mode'] ?? 'redirect' );

        return array(
            'slug'          => $slug,
            'public_key'    => $public_key,
            'private_key'   => $private_key,
            'sync_interval' => in_array( $sync_interval, self::SYNC_INTERVALS, true ) ? $sync_interval : 'hourly',
            'media_mode'    => in_array( $media_mode, self::MEDIA_MODES, true ) ? $media_mode : 'sideload',
            'booking_mode'  => in_array( $booking_mode, self::BOOKING_MODES, true ) ? $booking_mode : 'redirect',
        );
    }

    /** Register the setting (admin page rendering wired in Task 3). */
    public function register(): void {
        add_action( 'admin_init', function () {
            register_setting(
                'kwt_settings_group',
                self::OPTION,
                array(
                    'type'              => 'array',
                    'sanitize_callback' => array( $this, 'sanitize' ),
                    'default'           => array(),
                )
            );
        } );
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter SettingsTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add includes/Settings.php tests/SettingsTest.php
git commit -m "feat(settings): options store + sanitizer with typed getters (v0.1 task 2)"
```

---

### Task 3: Settings admin page

**Files:**
- Create: `includes/Admin_Page.php`
- Modify: `includes/Plugin.php` (boot `Settings` + `Admin_Page`)
- Test: `tests/AdminPageTest.php`

**Interfaces:**
- Consumes: `Settings` (getters + `OPTION`).
- Produces: `Admin_Page::register(): void` (adds the menu + renders under Settings → KwaWingu Tours); `Admin_Page::__construct( Settings $settings )`.

- [ ] **Step 1: Write the failing test `tests/AdminPageTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Admin_Page;
use KwaWingu\Tours\Settings;
use PHPUnit\Framework\TestCase;

class AdminPageTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_hooks_admin_menu(): void {
        $page = new Admin_Page( new Settings() );
        $page->register();
        $this->assertNotFalse( has_action( 'admin_menu' ) );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter AdminPageTest`
Expected: FAIL — `Class "KwaWingu\Tours\Admin_Page" not found`.

- [ ] **Step 3: Write `includes/Admin_Page.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Settings → KwaWingu Tours admin screen.
 */
class Admin_Page {

    /** @var Settings */
    private $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    public function register(): void {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
    }

    public function add_menu(): void {
        add_options_page(
            __( 'KwaWingu Tours', 'kwawingu-tours' ),
            __( 'KwaWingu Tours', 'kwawingu-tours' ),
            'manage_options',
            'kwawingu-tours',
            array( $this, 'render' )
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $slug         = $this->settings->get_slug();
        $public_key   = $this->settings->get_public_key();
        $booking_mode = $this->settings->get_booking_mode();
        $opt          = Settings::OPTION;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'KwaWingu Tours', 'kwawingu-tours' ); ?></h1>
            <p><?php echo esc_html__( 'Connect your KwaWingu Tours account. The Developer API is a paid add-on — enable it in your KwaWingu dashboard.', 'kwawingu-tours' ); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields( 'kwt_settings_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="kwt_slug"><?php echo esc_html__( 'Operator slug', 'kwawingu-tours' ); ?></label></th>
                        <td><input name="<?php echo esc_attr( $opt ); ?>[slug]" id="kwt_slug" type="text" class="regular-text" value="<?php echo esc_attr( $slug ); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="kwt_public_key"><?php echo esc_html__( 'Public API key', 'kwawingu-tours' ); ?></label></th>
                        <td><input name="<?php echo esc_attr( $opt ); ?>[public_key]" id="kwt_public_key" type="text" class="regular-text" value="<?php echo esc_attr( $public_key ); ?>" autocomplete="off" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="kwt_booking_mode"><?php echo esc_html__( 'Booking mode', 'kwawingu-tours' ); ?></label></th>
                        <td>
                            <select name="<?php echo esc_attr( $opt ); ?>[booking_mode]" id="kwt_booking_mode">
                                <?php foreach ( array( 'redirect', 'widget', 'onsite' ) as $mode ) : ?>
                                    <option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $booking_mode, $mode ); ?>><?php echo esc_html( $mode ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
```

Note: the private-key field and "Sync now" button are added in Task 7 (they depend on the sync subsystem). Keep this task focused on the connection fields.

- [ ] **Step 4: Wire boot in `includes/Plugin.php`** — replace the `boot()` body:

```php
    public function boot(): void {
        if ( $this->booted ) {
            return;
        }
        $this->booted = true;

        $settings = new Settings();
        $settings->register();
        ( new Admin_Page( $settings ) )->register();
        // Cpt + Sync registered in later tasks.
    }
```

- [ ] **Step 5: Run to verify it passes**

Run: `vendor/bin/phpunit --filter AdminPageTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/Admin_Page.php includes/Plugin.php tests/AdminPageTest.php
git commit -m "feat(settings): admin settings page (connection fields) (v0.1 task 3)"
```

---

### Task 4: Api_Client

**Files:**
- Create: `includes/Api_Client.php`
- Test: `tests/ApiClientTest.php`

**Interfaces:**
- Consumes: `Settings` (`get_slug`, `get_public_key`).
- Produces:
  - `Api_Client::__construct( Settings $settings )`.
  - `Api_Client::get( string $path, array $query = array() ): array` — returns the decoded JSON body on success.
  - Throws `Api_Exception` on transport error, non-2xx, or invalid JSON. `Api_Exception::get_code_string(): string` returns the API error `code` (e.g. `api_access_required`) when present, else `''`.
  - `Api_Client::get_site(): array` — convenience for `GET /{slug}/site`.

- [ ] **Step 1: Write the failing test `tests/ApiClientTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Api_Client;
use KwaWingu\Tours\Api_Exception;
use KwaWingu\Tours\Settings;
use PHPUnit\Framework\TestCase;

class ApiClientTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'public_key' => 'kw_live_x' ) );
        Functions\when( 'is_wp_error' )->alias( static function ( $t ) { return $t instanceof \WP_Error_Stub; } );
        Functions\when( 'wp_remote_retrieve_response_code' )->alias( static function ( $r ) { return $r['code']; } );
        Functions\when( 'wp_remote_retrieve_body' )->alias( static function ( $r ) { return $r['body']; } );
        Functions\when( 'add_query_arg' )->alias( static function ( $args, $url ) {
            return $url . '?' . http_build_query( $args );
        } );
        Functions\when( 'esc_url_raw' )->returnArg();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function client(): Api_Client {
        return new Api_Client( new Settings() );
    }

    public function test_get_sends_api_key_header_and_returns_decoded_body(): void {
        Functions\expect( 'wp_remote_get' )->once()->andReturnUsing( function ( $url, $args ) {
            $this->assertStringContainsString( '/acme/tours', $url );
            $this->assertSame( 'kw_live_x', $args['headers']['X-API-Key'] );
            return array( 'code' => 200, 'body' => wp_json_encode_stub( array( 'data' => array( 1, 2 ) ) ) );
        } );

        $body = $this->client()->get( '/tours', array( 'page' => 0 ) );
        $this->assertSame( array( 1, 2 ), $body['data'] );
    }

    public function test_get_throws_with_error_code_on_403(): void {
        Functions\when( 'wp_remote_get' )->justReturn(
            array( 'code' => 403, 'body' => '{"error":{"code":"api_access_required","message":"Enable API access."}}' )
        );

        try {
            $this->client()->get( '/tours' );
            $this->fail( 'Expected Api_Exception' );
        } catch ( Api_Exception $e ) {
            $this->assertSame( 'api_access_required', $e->get_code_string() );
            $this->assertSame( 403, $e->getCode() );
        }
    }

    public function test_get_throws_on_transport_error(): void {
        Functions\when( 'wp_remote_get' )->justReturn( new \WP_Error_Stub() );
        $this->expectException( Api_Exception::class );
        $this->client()->get( '/tours' );
    }
}

// Minimal stubs used above.
if ( ! function_exists( __NAMESPACE__ . '\\wp_json_encode_stub' ) ) {
    function wp_json_encode_stub( $v ) { return json_encode( $v ); }
}
```

Add this stub class at the top of the test file's namespace block (so `is_wp_error` can detect it):

```php
namespace { class WP_Error_Stub {} }
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ApiClientTest`
Expected: FAIL — `Class "KwaWingu\Tours\Api_Client" not found`.

- [ ] **Step 3: Write `includes/Api_Exception.php`**

```php
<?php
namespace KwaWingu\Tours;

class Api_Exception extends \RuntimeException {

    /** @var string */
    private $code_string;

    public function __construct( string $message, int $status = 0, string $code_string = '' ) {
        parent::__construct( $message, $status );
        $this->code_string = $code_string;
    }

    public function get_code_string(): string {
        return $this->code_string;
    }
}
```

- [ ] **Step 4: Write `includes/Api_Client.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Thin client for the KwaWingu per-operator developer API (server-side only).
 */
class Api_Client {

    /** @var Settings */
    private $settings;

    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * GET a path under /api/v1/{slug}. Returns the decoded JSON body.
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     * @throws Api_Exception
     */
    public function get( string $path, array $query = array() ): array {
        $slug = $this->settings->get_slug();
        $key  = $this->settings->get_public_key();
        if ( '' === $slug || '' === $key ) {
            throw new Api_Exception( 'KwaWingu Tours is not configured (slug or public key missing).', 0 );
        }

        $url = KWT_API_BASE . '/' . rawurlencode( $slug ) . '/' . ltrim( $path, '/' );
        if ( ! empty( $query ) ) {
            $url = add_query_arg( $query, $url );
        }

        $response = wp_remote_get(
            esc_url_raw( $url ),
            array(
                'timeout' => 15,
                'headers' => array(
                    'X-API-Key' => $key,
                    'Accept'    => 'application/json',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            throw new Api_Exception( 'Request to KwaWingu API failed.', 0 );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = (string) wp_remote_retrieve_body( $response );
        $json   = json_decode( $body, true );

        if ( $status < 200 || $status >= 300 ) {
            $code_string = '';
            $message     = 'KwaWingu API returned status ' . $status . '.';
            if ( is_array( $json ) && isset( $json['error'] ) && is_array( $json['error'] ) ) {
                $code_string = (string) ( $json['error']['code'] ?? '' );
                $message     = (string) ( $json['error']['message'] ?? $message );
            }
            throw new Api_Exception( $message, $status, $code_string );
        }

        if ( ! is_array( $json ) ) {
            throw new Api_Exception( 'KwaWingu API returned an invalid JSON body.', $status );
        }

        return $json;
    }

    /** @return array<string,mixed> */
    public function get_site(): array {
        return $this->get( '/site' );
    }
}
```

- [ ] **Step 5: Run to verify it passes**

Run: `vendor/bin/phpunit --filter ApiClientTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/Api_Client.php includes/Api_Exception.php tests/ApiClientTest.php
git commit -m "feat(api): Api_Client with X-API-Key + envelope/error handling (v0.1 task 4)"
```

---

### Task 5: Custom post types + taxonomy

**Files:**
- Create: `includes/Cpt.php`
- Modify: `includes/Plugin.php` (boot `Cpt`)
- Test: `tests/CptTest.php`

**Interfaces:**
- Produces:
  - `Cpt::TOUR` = `'kwt_tour'`, `Cpt::DESTINATION` = `'kwt_destination'`, `Cpt::TYPE_TAX` = `'kwt_tour_type'`.
  - `Cpt::register(): void` — hooks `init` to register both post types + the taxonomy.

- [ ] **Step 1: Write the failing test `tests/CptTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Cpt;
use PHPUnit\Framework\TestCase;

class CptTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( '__' )->returnArg();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_hooks_init(): void {
        ( new Cpt() )->register();
        $this->assertNotFalse( has_action( 'init' ) );
    }

    public function test_init_registers_tour_cpt_as_public_with_rewrite(): void {
        $captured = array();
        Functions\when( 'register_post_type' )->alias( static function ( $type, $args ) use ( &$captured ) {
            $captured[ $type ] = $args;
        } );
        Functions\when( 'register_taxonomy' )->justReturn( true );

        ( new Cpt() )->init();

        $this->assertArrayHasKey( 'kwt_tour', $captured );
        $this->assertTrue( $captured['kwt_tour']['public'] );
        $this->assertTrue( $captured['kwt_tour']['has_archive'] );
        $this->assertSame( 'tours', $captured['kwt_tour']['rewrite']['slug'] );
        $this->assertContains( 'title', $captured['kwt_tour']['supports'] );
        $this->assertContains( 'editor', $captured['kwt_tour']['supports'] );
        $this->assertContains( 'thumbnail', $captured['kwt_tour']['supports'] );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter CptTest`
Expected: FAIL — `Class "KwaWingu\Tours\Cpt" not found`.

- [ ] **Step 3: Write `includes/Cpt.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Registers the native content model: tours, destinations, tour-type taxonomy.
 */
class Cpt {

    const TOUR        = 'kwt_tour';
    const DESTINATION = 'kwt_destination';
    const TYPE_TAX    = 'kwt_tour_type';

    public function register(): void {
        add_action( 'init', array( $this, 'init' ) );
    }

    public function init(): void {
        register_post_type(
            self::TOUR,
            array(
                'labels'       => array(
                    'name'          => __( 'Tours', 'kwawingu-tours' ),
                    'singular_name' => __( 'Tour', 'kwawingu-tours' ),
                ),
                'public'       => true,
                'has_archive'  => true,
                'menu_icon'    => 'dashicons-palmtree',
                'rewrite'      => array( 'slug' => 'tours', 'with_front' => false ),
                'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
                'show_in_rest' => true,
            )
        );

        register_post_type(
            self::DESTINATION,
            array(
                'labels'       => array(
                    'name'          => __( 'Destinations', 'kwawingu-tours' ),
                    'singular_name' => __( 'Destination', 'kwawingu-tours' ),
                ),
                'public'       => true,
                'has_archive'  => false,
                'menu_icon'    => 'dashicons-location',
                'rewrite'      => array( 'slug' => 'destinations', 'with_front' => false ),
                'supports'     => array( 'title', 'editor', 'thumbnail' ),
                'show_in_rest' => true,
            )
        );

        register_taxonomy(
            self::TYPE_TAX,
            array( self::TOUR ),
            array(
                'labels'            => array(
                    'name'          => __( 'Tour Types', 'kwawingu-tours' ),
                    'singular_name' => __( 'Tour Type', 'kwawingu-tours' ),
                ),
                'public'            => true,
                'hierarchical'      => false,
                'show_admin_column' => true,
                'rewrite'           => array( 'slug' => 'tour-type', 'with_front' => false ),
                'show_in_rest'      => true,
            )
        );
    }
}
```

- [ ] **Step 4: Wire boot in `includes/Plugin.php`** — add after the `Admin_Page` line inside `boot()`:

```php
        ( new Cpt() )->register();
```

- [ ] **Step 5: Run to verify it passes**

Run: `vendor/bin/phpunit --filter CptTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add includes/Cpt.php includes/Plugin.php tests/CptTest.php
git commit -m "feat(cpt): register kwt_tour/kwt_destination + tour-type taxonomy (v0.1 task 5)"
```

---

### Task 6: Sync engine (upsert / content-lock / soft-unpublish)

**Files:**
- Create: `includes/Sync.php`
- Test: `tests/SyncTest.php`

**Interfaces:**
- Consumes: `Api_Client::get_site()`, `Cpt::TOUR`, `Cpt::TYPE_TAX`.
- Produces:
  - `Sync::__construct( Api_Client $api )`.
  - `Sync::run(): array` — returns `array( 'created' => int, 'updated' => int, 'unpublished' => int, 'errors' => string[] )`. Fetches `/site`, upserts each tour by the `kwt_id` meta, sets structured meta always, sets `post_title`/`post_content` only when the post is new OR `kwt_content_locked` meta is not `'1'`, and drafts any existing `kwt_tour` whose `kwt_id` is absent from the response.
  - `Sync::META_ID` = `'kwt_id'`, `Sync::META_LOCK` = `'kwt_content_locked'`.

Note: media sideloading is deferred to v0.3 — v0.1 stores the cover URL in the `kwt_cover_url` meta only.

- [ ] **Step 1: Write the failing test `tests/SyncTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Api_Client;
use KwaWingu\Tours\Sync;
use Mockery;
use PHPUnit\Framework\TestCase;

class SyncTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_strip_all_tags' )->returnArg();
        Functions\when( 'sanitize_title' )->returnArg();
        Functions\when( 'update_post_meta' )->justReturn( true );
        Functions\when( 'wp_update_post' )->justReturn( 1 );
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    private function api_returning( array $tours ): Api_Client {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get_site' )->once()->andReturn(
            array( 'tours' => $tours )
        );
        return $api;
    }

    public function test_creates_new_tour_when_no_existing_post(): void {
        // No existing kwt_tour posts at all.
        Functions\when( 'get_posts' )->justReturn( array() );
        // wp_insert_post returns a new ID; capture the args.
        $inserted = array();
        Functions\when( 'wp_insert_post' )->alias( static function ( $args ) use ( &$inserted ) {
            $inserted[] = $args;
            return 101;
        } );

        $api  = $this->api_returning( array(
            array( 'id' => 'T1', 'slug' => 'safari', 'title' => 'Safari', 'descriptionShort' => 'Wild', 'price' => 450000 ),
        ) );
        $out = ( new Sync( $api ) )->run();

        $this->assertSame( 1, $out['created'] );
        $this->assertSame( 0, $out['updated'] );
        $this->assertSame( 'Safari', $inserted[0]['post_title'] );
        $this->assertSame( 'publish', $inserted[0]['post_status'] );
    }

    public function test_updates_existing_but_preserves_locked_content(): void {
        // Existing post 55 for kwt_id T1, content locked.
        Functions\when( 'get_posts' )->alias( static function ( $args ) {
            // First call: lookup by meta kwt_id=T1 -> returns [55]; the "all existing" sweep also returns [55].
            return array( 55 );
        } );
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            if ( 'kwt_id' === $key ) { return 'T1'; }
            if ( 'kwt_content_locked' === $key ) { return '1'; }
            return '';
        } );
        $updates = array();
        Functions\when( 'wp_update_post' )->alias( static function ( $args ) use ( &$updates ) {
            $updates[] = $args;
            return 55;
        } );

        $api = $this->api_returning( array(
            array( 'id' => 'T1', 'slug' => 'safari', 'title' => 'NEW TITLE', 'descriptionShort' => 'x', 'price' => 500000 ),
        ) );
        $out = ( new Sync( $api ) )->run();

        $this->assertSame( 1, $out['updated'] );
        // Locked: title must NOT be overwritten -> no post_title key in the update payload.
        $this->assertArrayNotHasKey( 'post_title', $updates[0] );
    }

    public function test_unpublishes_tour_missing_from_response(): void {
        // Existing post 77 (kwt_id GONE) is absent from the API response.
        Functions\when( 'get_posts' )->alias( static function ( $args ) {
            if ( isset( $args['meta_query'] ) ) { return array(); } // no match for incoming ids
            return array( 77 ); // the "all existing" sweep
        } );
        Functions\when( 'get_post_meta' )->alias( static function ( $id, $key, $single ) {
            return 'kwt_id' === $key ? 'GONE' : '';
        } );
        Functions\when( 'wp_insert_post' )->justReturn( 78 );
        $drafted = array();
        Functions\when( 'wp_update_post' )->alias( static function ( $args ) use ( &$drafted ) {
            $drafted[] = $args;
            return $args['ID'];
        } );

        $api = $this->api_returning( array(
            array( 'id' => 'T1', 'slug' => 'safari', 'title' => 'Safari', 'descriptionShort' => 'x', 'price' => 1 ),
        ) );
        $out = ( new Sync( $api ) )->run();

        $this->assertSame( 1, $out['unpublished'] );
        $this->assertSame( 77, $drafted[0]['ID'] );
        $this->assertSame( 'draft', $drafted[0]['post_status'] );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter SyncTest`
Expected: FAIL — `Class "KwaWingu\Tours\Sync" not found`.

- [ ] **Step 3: Write `includes/Sync.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Imports the operator's KwaWingu catalog into kwt_tour posts.
 *
 * Upserts by the kwt_id meta. Structured meta is always refreshed; title/body
 * are written only for new posts or posts the operator has not locked by
 * editing. Tours that vanish from the API are drafted (never hard-deleted).
 */
class Sync {

    const META_ID   = 'kwt_id';
    const META_LOCK = 'kwt_content_locked';

    /** @var Api_Client */
    private $api;

    public function __construct( Api_Client $api ) {
        $this->api = $api;
    }

    /**
     * @return array{created:int,updated:int,unpublished:int,errors:array<int,string>}
     */
    public function run(): array {
        $result = array( 'created' => 0, 'updated' => 0, 'unpublished' => 0, 'errors' => array() );

        try {
            $site  = $this->api->get_site();
            $tours = isset( $site['tours'] ) && is_array( $site['tours'] ) ? $site['tours'] : array();
        } catch ( Api_Exception $e ) {
            $result['errors'][] = $e->getMessage();
            return $result;
        }

        $seen_ids = array();

        foreach ( $tours as $tour ) {
            if ( ! is_array( $tour ) ) {
                continue;
            }
            $kwt_id = (string) ( $tour['id'] ?? '' );
            if ( '' === $kwt_id ) {
                $result['errors'][] = 'Skipped a tour with no id.';
                continue;
            }
            $seen_ids[] = $kwt_id;

            $existing = $this->find_post_by_kwt_id( $kwt_id );
            if ( 0 === $existing ) {
                $this->insert_tour( $tour, $kwt_id );
                $result['created']++;
            } else {
                $this->update_tour( $existing, $tour );
                $result['updated']++;
            }
        }

        $result['unpublished'] = $this->unpublish_missing( $seen_ids );

        return $result;
    }

    private function find_post_by_kwt_id( string $kwt_id ): int {
        $ids = get_posts( array(
            'post_type'      => Cpt::TOUR,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array( 'key' => self::META_ID, 'value' => $kwt_id ),
            ),
        ) );
        return ! empty( $ids ) ? (int) $ids[0] : 0;
    }

    /** @param array<string,mixed> $tour */
    private function insert_tour( array $tour, string $kwt_id ): void {
        $id = wp_insert_post( array(
            'post_type'    => Cpt::TOUR,
            'post_status'  => 'publish',
            'post_title'   => sanitize_text_field( (string) ( $tour['title'] ?? '' ) ),
            'post_excerpt' => sanitize_text_field( (string) ( $tour['descriptionShort'] ?? '' ) ),
            'post_content' => wp_strip_all_tags( (string) ( $tour['description'] ?? $tour['descriptionShort'] ?? '' ) ),
        ) );
        if ( is_int( $id ) && $id > 0 ) {
            $this->write_meta( $id, $tour, $kwt_id );
        }
    }

    /** @param array<string,mixed> $tour */
    private function update_tour( int $post_id, array $tour ): void {
        $locked = '1' === (string) get_post_meta( $post_id, self::META_LOCK, true );

        $payload = array( 'ID' => $post_id );
        if ( ! $locked ) {
            $payload['post_title']   = sanitize_text_field( (string) ( $tour['title'] ?? '' ) );
            $payload['post_excerpt'] = sanitize_text_field( (string) ( $tour['descriptionShort'] ?? '' ) );
            $payload['post_content'] = wp_strip_all_tags( (string) ( $tour['description'] ?? $tour['descriptionShort'] ?? '' ) );
        }
        wp_update_post( $payload );
        $this->write_meta( $post_id, $tour, (string) get_post_meta( $post_id, self::META_ID, true ) ?: (string) ( $tour['id'] ?? '' ) );
    }

    /** @param array<string,mixed> $tour */
    private function write_meta( int $post_id, array $tour, string $kwt_id ): void {
        update_post_meta( $post_id, self::META_ID, $kwt_id );
        update_post_meta( $post_id, 'kwt_slug', sanitize_title( (string) ( $tour['slug'] ?? '' ) ) );
        update_post_meta( $post_id, 'kwt_price', (int) ( $tour['price'] ?? 0 ) );
        update_post_meta( $post_id, 'kwt_duration_days', (int) ( $tour['durationDays'] ?? 0 ) );
        update_post_meta( $post_id, 'kwt_difficulty', sanitize_text_field( (string) ( $tour['difficulty'] ?? '' ) ) );
        update_post_meta( $post_id, 'kwt_type', sanitize_text_field( (string) ( $tour['type'] ?? '' ) ) );
        update_post_meta( $post_id, 'kwt_cover_url', esc_url_raw_or_empty( $tour['coverImageUrl'] ?? '' ) );
        update_post_meta( $post_id, 'kwt_synced_at', time() );
    }

    /**
     * @param array<int,string> $seen_ids
     * @return int number of posts drafted
     */
    private function unpublish_missing( array $seen_ids ): int {
        $all = get_posts( array(
            'post_type'      => Cpt::TOUR,
            'post_status'    => array( 'publish' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        $count = 0;
        foreach ( (array) $all as $post_id ) {
            $kwt_id = (string) get_post_meta( (int) $post_id, self::META_ID, true );
            if ( '' !== $kwt_id && ! in_array( $kwt_id, $seen_ids, true ) ) {
                wp_update_post( array( 'ID' => (int) $post_id, 'post_status' => 'draft' ) );
                $count++;
            }
        }
        return $count;
    }
}

/** esc_url_raw that tolerates empty/non-string input without a WP dependency in unit tests. */
function esc_url_raw_or_empty( $url ): string {
    $url = is_string( $url ) ? $url : '';
    return '' === $url ? '' : ( function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url );
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter SyncTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/Sync.php tests/SyncTest.php
git commit -m "feat(sync): catalog upsert with content-lock + soft-unpublish (v0.1 task 6)"
```

---

### Task 7: Sync triggers — WP-Cron, "Sync now", private-key field, status

**Files:**
- Create: `includes/Sync_Controller.php`
- Modify: `includes/Plugin.php` (boot the sync subsystem)
- Modify: `includes/Admin_Page.php` (add private-key field + "Sync now" button + last-run status)
- Test: `tests/SyncControllerTest.php`

**Interfaces:**
- Consumes: `Sync::run()`, `Settings::get_sync_interval()`, `Settings::OPTION`.
- Produces:
  - `Sync_Controller::__construct( Sync $sync, Settings $settings )`.
  - `Sync_Controller::register(): void` — schedules `kwt_sync_cron` on the configured interval, hooks the cron to `run_and_store()`, and registers `admin_post_kwt_sync_now`.
  - `Sync_Controller::run_and_store(): array` — runs the sync and saves the summary to option `kwt_sync_status` (`array{ran_at:int, created:int, updated:int, unpublished:int, errors:string[]}`).
  - `Sync_Controller::handle_sync_now(): void` — nonce + capability check, runs, redirects back with a notice.

- [ ] **Step 1: Write the failing test `tests/SyncControllerTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Settings;
use KwaWingu\Tours\Sync;
use KwaWingu\Tours\Sync_Controller;
use Mockery;
use PHPUnit\Framework\TestCase;

class SyncControllerTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function test_run_and_store_persists_summary(): void {
        $sync = Mockery::mock( Sync::class );
        $sync->shouldReceive( 'run' )->once()->andReturn(
            array( 'created' => 2, 'updated' => 1, 'unpublished' => 0, 'errors' => array() )
        );

        $stored = array();
        Functions\when( 'update_option' )->alias( static function ( $k, $v ) use ( &$stored ) {
            $stored[ $k ] = $v;
            return true;
        } );
        Functions\when( 'time' )->justReturn( 1000 );

        $ctrl = new Sync_Controller( $sync, new Settings() );
        $summary = $ctrl->run_and_store();

        $this->assertSame( 2, $summary['created'] );
        $this->assertSame( 2, $stored['kwt_sync_status']['created'] );
        $this->assertSame( 1000, $stored['kwt_sync_status']['ran_at'] );
    }

    public function test_register_schedules_cron_when_missing(): void {
        Functions\when( 'get_option' )->justReturn( array( 'sync_interval' => 'hourly' ) );
        Functions\when( 'wp_next_scheduled' )->justReturn( false );
        $scheduled = array();
        Functions\when( 'wp_schedule_event' )->alias( static function ( $ts, $recur, $hook ) use ( &$scheduled ) {
            $scheduled[] = array( $recur, $hook );
            return true;
        } );
        Functions\when( 'time' )->justReturn( 0 );

        $sync = Mockery::mock( Sync::class );
        ( new Sync_Controller( $sync, new Settings() ) )->register();

        $this->assertSame( array( 'hourly', 'kwt_sync_cron' ), $scheduled[0] );
        $this->assertNotFalse( has_action( 'kwt_sync_cron' ) );
        $this->assertNotFalse( has_action( 'admin_post_kwt_sync_now' ) );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter SyncControllerTest`
Expected: FAIL — `Class "KwaWingu\Tours\Sync_Controller" not found`.

- [ ] **Step 3: Write `includes/Sync_Controller.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Drives Sync via WP-Cron + a manual "Sync now" admin action, and records status.
 */
class Sync_Controller {

    const CRON_HOOK   = 'kwt_sync_cron';
    const STATUS_OPT  = 'kwt_sync_status';
    const ACTION      = 'kwt_sync_now';

    /** @var Sync */
    private $sync;

    /** @var Settings */
    private $settings;

    public function __construct( Sync $sync, Settings $settings ) {
        $this->sync     = $sync;
        $this->settings = $settings;
    }

    public function register(): void {
        add_action( self::CRON_HOOK, array( $this, 'run_and_store' ) );
        add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_sync_now' ) );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 60, $this->settings->get_sync_interval(), self::CRON_HOOK );
        }
    }

    /**
     * @return array{ran_at:int,created:int,updated:int,unpublished:int,errors:array<int,string>}
     */
    public function run_and_store(): array {
        $summary           = $this->sync->run();
        $summary['ran_at'] = time();
        update_option( self::STATUS_OPT, $summary );
        return $summary;
    }

    public function handle_sync_now(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do this.', 'kwawingu-tours' ) );
        }
        check_admin_referer( self::ACTION );
        $this->run_and_store();
        wp_safe_redirect( add_query_arg(
            array( 'page' => 'kwawingu-tours', 'kwt_synced' => '1' ),
            admin_url( 'options-general.php' )
        ) );
        exit;
    }
}
```

- [ ] **Step 4: Wire boot in `includes/Plugin.php`** — replace `boot()` body with the full wiring:

```php
    public function boot(): void {
        if ( $this->booted ) {
            return;
        }
        $this->booted = true;

        $settings = new Settings();
        $settings->register();

        ( new Cpt() )->register();

        $api        = new Api_Client( $settings );
        $sync       = new Sync( $api );
        $controller = new Sync_Controller( $sync, $settings );
        $controller->register();

        ( new Admin_Page( $settings, $controller ) )->register();
    }
```

- [ ] **Step 5: Update `includes/Admin_Page.php`** — accept the controller and render the private-key field, the last-run status, and the "Sync now" form. Change the constructor and add markup:

Constructor:

```php
    /** @var Settings */
    private $settings;

    /** @var Sync_Controller */
    private $controller;

    public function __construct( Settings $settings, Sync_Controller $controller ) {
        $this->settings   = $settings;
        $this->controller = $controller;
    }
```

Inside `render()`, after the booking-mode `<tr>` and before `</table>`, add the private-key row:

```php
                    <tr>
                        <th scope="row"><label for="kwt_private_key"><?php echo esc_html__( 'Private API key (on-site booking only)', 'kwawingu-tours' ); ?></label></th>
                        <td>
                            <input name="<?php echo esc_attr( $opt ); ?>[private_key]" id="kwt_private_key" type="password" class="regular-text" value="<?php echo esc_attr( $this->settings->get_private_key() ); ?>" autocomplete="off" />
                            <p class="description"><?php echo esc_html__( 'Only needed for on-site booking. Stored server-side, never shown on your website.', 'kwawingu-tours' ); ?></p>
                        </td>
                    </tr>
```

After the closing `</form>` add the status + "Sync now" block:

```php
        <?php
        $status = get_option( \KwaWingu\Tours\Sync_Controller::STATUS_OPT, array() );
        ?>
        <hr />
        <h2><?php echo esc_html__( 'Catalog sync', 'kwawingu-tours' ); ?></h2>
        <?php if ( ! empty( $status['ran_at'] ) ) : ?>
            <p>
                <?php
                echo esc_html( sprintf(
                    /* translators: 1: created, 2: updated, 3: unpublished */
                    __( 'Last sync — created %1$d, updated %2$d, unpublished %3$d.', 'kwawingu-tours' ),
                    (int) ( $status['created'] ?? 0 ),
                    (int) ( $status['updated'] ?? 0 ),
                    (int) ( $status['unpublished'] ?? 0 )
                ) );
                ?>
            </p>
            <?php if ( ! empty( $status['errors'] ) ) : ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html( implode( ' | ', array_map( 'strval', $status['errors'] ) ) ); ?></p></div>
            <?php endif; ?>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr( \KwaWingu\Tours\Sync_Controller::ACTION ); ?>" />
            <?php wp_nonce_field( \KwaWingu\Tours\Sync_Controller::ACTION ); ?>
            <?php submit_button( __( 'Sync now', 'kwawingu-tours' ), 'secondary' ); ?>
        </form>
```

- [ ] **Step 6: Update `tests/AdminPageTest.php`** for the new constructor signature — replace the `new Admin_Page( new Settings() )` line:

```php
        $settings   = new Settings();
        $controller = \Mockery::mock( \KwaWingu\Tours\Sync_Controller::class );
        $page       = new Admin_Page( $settings, $controller );
        $page->register();
```

Add `use Mockery;` and a `tearDown` with `Mockery::close();` if not present.

- [ ] **Step 7: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: all tests PASS.

- [ ] **Step 8: Commit**

```bash
git add includes/Sync_Controller.php includes/Plugin.php includes/Admin_Page.php tests/SyncControllerTest.php tests/AdminPageTest.php
git commit -m "feat(sync): WP-Cron schedule + Sync now action + status + private-key field (v0.1 task 7)"
```

---

### Task 8: readme.txt, uninstall cleanup, CI

**Files:**
- Create: `readme.txt`
- Create: `uninstall.php`
- Create: `.github/workflows/ci.yml`
- Create: `phpcs.xml.dist`

**Interfaces:** none (packaging/compliance).

- [ ] **Step 1: Write `readme.txt`** (WordPress.org format with the required external-service disclosure)

```
=== KwaWingu Tours ===
Contributors: kwawingu
Tags: tours, travel, tour operator, booking, safari
Requires at least: 6.2
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build a tour-operator website fast on your KwaWingu Tours data. Sync your catalog into WordPress, add blocks, and go live in minutes.

== Description ==

KwaWingu Tours syncs your tour catalog from your KwaWingu Tours account into native WordPress content (Tours & Destinations), so you get fast, SEO-friendly pages you can extend like any other post.

This plugin connects to the KwaWingu Tours developer API using your operator slug and API key. The Developer API is a paid add-on on your KwaWingu account.

== External services ==

This plugin connects to the KwaWingu Tours API (https://tours.kwawingu.com) to fetch your tour catalog, availability, and related content, using the operator slug and API key you configure. Data sent: your API key (in a request header) and query parameters for the content requested. No visitor personal data is sent by this plugin during catalog sync. See https://tours.kwawingu.com (Terms) and the KwaWingu privacy policy.

== Changelog ==

= 0.1.0 =
* Initial release: settings, API client, Tours/Destinations post types, and scheduled catalog sync.
```

- [ ] **Step 2: Write `uninstall.php`**

```php
<?php
/**
 * Uninstall cleanup. Removes plugin options + the scheduled sync.
 * CPT content (tours) is intentionally left in place so the site does not lose pages.
 *
 * @package KwaWingu\Tours
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'kwt_settings' );
delete_option( 'kwt_sync_status' );
wp_clear_scheduled_hook( 'kwt_sync_cron' );
```

- [ ] **Step 3: Write `phpcs.xml.dist`**

```xml
<?xml version="1.0"?>
<ruleset name="KwaWingu Tours">
    <description>WPCS for the KwaWingu Tours plugin.</description>
    <file>.</file>
    <exclude-pattern>/vendor/*</exclude-pattern>
    <exclude-pattern>/tests/*</exclude-pattern>
    <rule ref="WordPress-Core"/>
    <rule ref="WordPress-Docs"/>
    <config name="testVersion" value="7.4-"/>
    <rule ref="PHPCompatibilityWP"/>
    <config name="text_domain" value="kwawingu-tours"/>
</ruleset>
```

- [ ] **Step 4: Write `.github/workflows/ci.yml`**

```yaml
name: CI
on:
  push:
    branches: [ main ]
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
          coverage: none
      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist
      - name: PHPUnit
        run: vendor/bin/phpunit
      - name: PHPCS
        run: vendor/bin/phpcs -q || true
```

Note: `phpcs` is non-blocking (`|| true`) in v0.1 so a style nit doesn't fail CI while the codebase is young; tighten in a later phase.

- [ ] **Step 5: Run the full suite locally one more time**

Run: `vendor/bin/phpunit`
Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add readme.txt uninstall.php phpcs.xml.dist .github/workflows/ci.yml
git commit -m "chore(release): readme, uninstall cleanup, CI + PHPCS config (v0.1 task 8)"
```

---

## Self-Review

**Spec coverage (v0.1 slice):**
- Repo structure & standards → Task 1, 8. ✓
- Settings (slug/keys/interval/media/booking mode) + admin page → Task 2, 3, 7. ✓
- Api_Client (X-API-Key, envelope, 403 `api_access_required`) → Task 4. ✓
- CPT model (`kwt_tour`, `kwt_destination`, `kwt_tour_type`) → Task 5. ✓
- Sync engine (`/site` source, upsert by id, content-lock, soft-unpublish, availability never synced) → Task 6. ✓
- Sync triggers (WP-Cron + "Sync now" + status) → Task 7. ✓
- WP.org compliance (GPL, external-service disclosure, uninstall, i18n, caps/nonces) → Task 3, 7, 8. ✓
- Deferred to later phases (correctly NOT in this plan): blocks/patterns/importer + wizard (v0.2), SEO/media-sideload/widget mode (v0.3), on-site API booking (v0.4). The private-key field is added now (Task 7) but unused until v0.4 — acceptable, it's just a stored credential.

**Placeholder scan:** No TBD/TODO; every code step contains complete code. ✓

**Type consistency:** `Settings::OPTION`, `Cpt::TOUR`, `Sync::run()` return shape, `Sync_Controller::STATUS_OPT`/`ACTION`/`CRON_HOOK`, and `Admin_Page` constructor `( Settings, Sync_Controller )` are used consistently across Tasks 3/5/6/7. Task 3 introduces `Admin_Page( Settings )` then Task 7 widens it to `( Settings, Sync_Controller )` and updates its test in the same task — consistent within the plan. ✓

**Known simplification:** the `Sync` unit tests mock `get_posts`/`get_post_meta` coarsely (same return regardless of args in a couple of cases). This validates the branching logic (create vs update-locked vs unpublish) without a live DB. Integration coverage against a real WP DB is a v0.2+ concern (wp-env), noted here so it isn't mistaken for full coverage.
