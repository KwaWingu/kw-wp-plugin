# KwaWingu Tours WordPress Plugin — v0.4 Implementation Plan (completion)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the plugin — a server-side REST proxy that lets the browser use the operator's API without exposing keys; **on-site booking** (create booking → Snippe payment-intent → poll status) as the third booking mode; the live-data blocks (Search, Trip Calculator, Availability) powered by the proxy; internationalization; and WordPress.org submission hardening. Bump to 1.0.0.

**Architecture:** A `Rest_Proxy` registers `/wp-json/kwawingu/v1/*` routes that forward to the operator's `/api/v1/{slug}/*` **server-side** — reads use the public key, writes (booking create, payment-intent) use the **private key**, which never reaches the browser. Front-end blocks are server-rendered shells plus a small vanilla-JS `view.js` that calls the proxy (same-origin, nonce-protected) and renders results — no key in JS, no build tool required. On-site booking is a form block whose JS drives create→pay→poll entirely through the proxy. i18n via a generated `.pot` + `load_plugin_textdomain`. Hardening: ABSPATH guards on class files, PHPCS made blocking, WP.org assets + readme.

**Tech Stack:** PHP 7.4+ (tests on PHP 8.3), WordPress 6.2+ (REST API), Composer PSR-4, PHPUnit 9 + Brain\Monkey + Mockery for the PHP proxy; vanilla ES5-safe JS for `view.js` (no build step, enqueued as-is). Blocks keep the split render-fn/template pattern.

## Global Constraints

- Namespace `KwaWingu\Tours`; PSR-4 → `includes/`. Prefix `KWT_`/`kwt_`. Text domain `kwawingu-tours` on every user-facing string (PHP and JS via `wp_localize_script` strings).
- **The private key is used ONLY server-side inside `Rest_Proxy` write routes; it is NEVER localized to JS, echoed, or returned in a REST response.** Read routes use the public key. All proxy routes verify a WP REST nonce (`X-WP-Nonce`) for same-origin protection; write routes additionally rate-limit via a short transient.
- Proxy base: `/wp-json/kwawingu/v1`. Forwards to `KWT_API_BASE . '/' . slug . '/' . path`. Reuses `Api_Client` for reads; a new `Api_Client::post()` for writes (adds the private key header).
- Blocks keep `block.json` + `render-fn.php` (function) + thin echoing `render.php` template; a block with front-end interactivity adds `"viewScript": "file:./view.js"` and enqueues `view.js` which reads config from a localized `window.kwtView` (ajaxUrl/proxy base + nonce + slug + i18n strings). `view.js` must be dependency-free, escape via `textContent`/DOM APIs (never `innerHTML` with server data), and degrade gracefully.
- Booking modes: `redirect` (v0.2), `widget` (v0.3), `onsite` (this phase). On-site requires the private key configured; when absent it falls back to redirect.
- All PHP output escaped; all input sanitized; REST args validated with `sanitize_callback`/`validate_callback`; caps/nonces on admin writes. TZS integer money via `View::money`.
- Targets PHP 7.4+, WP 6.2+. No bundled minified libraries (ship readable JS). Tests `vendor/bin/phpunit` stay green; PHPCS becomes blocking in CI at the end of this phase.

---

### Task 1: Api_Client write support (POST with private key)

**Files:**
- Modify: `includes/Api_Client.php` (add `post()` + private-key handling; include the WP_Error reason in transport errors — the v0.1 minor)
- Test: `tests/ApiClientTest.php` (add cases)

**Interfaces:**
- Consumes: `Settings::get_private_key`.
- Produces: `Api_Client::post( string $path, array $body, bool $use_private_key = true ): array` — POSTs JSON to `/{slug}/{path}` with `X-API-Key` = private key (when `$use_private_key`) else public; returns the decoded array; throws `Api_Exception` (with the API error code + HTTP status) on non-2xx/transport/invalid-JSON. Transport errors now include the underlying `WP_Error` message.

- [ ] **Step 1: Add the failing tests to `tests/ApiClientTest.php`** (inside the class)

```php
    public function test_post_sends_private_key_and_body(): void {
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'public_key' => 'kw_pub', 'private_key' => 'kw_priv' ) );
        Functions\expect( 'wp_remote_post' )->once()->andReturnUsing( function ( $url, $args ) {
            $this->assertStringContainsString( '/acme/bookings', $url );
            $this->assertSame( 'kw_priv', $args['headers']['X-API-Key'] );
            $this->assertSame( 'application/json', $args['headers']['Content-Type'] );
            $this->assertSame( '{"ref":"KWG-1"}', $args['body'] );
            return array( 'code' => 200, 'body' => '{"data":{"ok":true}}' );
        } );
        $out = $this->client()->post( '/bookings', array( 'ref' => 'KWG-1' ) );
        $this->assertTrue( $out['data']['ok'] );
    }

    public function test_post_uses_public_key_when_requested(): void {
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme', 'public_key' => 'kw_pub', 'private_key' => 'kw_priv' ) );
        Functions\expect( 'wp_remote_post' )->once()->andReturnUsing( function ( $url, $args ) {
            $this->assertSame( 'kw_pub', $args['headers']['X-API-Key'] );
            return array( 'code' => 200, 'body' => '{"ok":1}' );
        } );
        $this->client()->post( '/calculator/estimate', array(), false );
    }
```

Add to `setUp()`: `Functions\when( 'wp_remote_retrieve_response_code' )` / `wp_remote_retrieve_body` are already stubbed for the GET tests; ensure they also apply here (they read `$r['code']`/`$r['body']`).

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ApiClientTest`
Expected: FAIL — `post()` undefined.

- [ ] **Step 3: Implement `post()` in `includes/Api_Client.php`** — add after `get()`, and refactor the response-handling into a shared private method. Add:

```php
    /**
     * POST JSON to a path under /api/v1/{slug}. Uses the private key by default.
     *
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     * @throws Api_Exception
     */
    public function post( string $path, array $body, bool $use_private_key = true ): array {
        $slug = $this->settings->get_slug();
        $key  = $use_private_key ? $this->settings->get_private_key() : $this->settings->get_public_key();
        if ( '' === $slug || '' === $key ) {
            throw new Api_Exception( 'KwaWingu Tours is not configured (slug or API key missing).', 0 );
        }
        $url = KWT_API_BASE . '/' . rawurlencode( $slug ) . '/' . ltrim( $path, '/' );

        $response = wp_remote_post(
            esc_url_raw( $url ),
            array(
                'timeout' => 20,
                'headers' => array(
                    'X-API-Key'    => $key,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
            )
        );
        return $this->handle_response( $response );
    }
```

And extract the existing body of `get()` (everything after the `wp_remote_get` call) into:

```php
    /**
     * @param mixed $response  WP_Error or the wp_remote_* response array.
     * @return array<string,mixed>
     * @throws Api_Exception
     */
    private function handle_response( $response ): array {
        if ( is_wp_error( $response ) ) {
            $reason = method_exists( $response, 'get_error_message' ) ? $response->get_error_message() : '';
            throw new Api_Exception( 'Request to KwaWingu API failed' . ( '' !== $reason ? ': ' . $reason : '.' ), 0 );
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
```

Then make `get()` call `return $this->handle_response( $response );` in place of its inline handling. Keep behavior identical (the existing GET tests must still pass).

- [ ] **Step 4: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter ApiClientTest` then `vendor/bin/phpunit`
Expected: all PASS (existing GET tests unchanged).

- [ ] **Step 5: Commit**

```bash
git add includes/Api_Client.php tests/ApiClientTest.php
git commit -m "feat(api): Api_Client::post with private key + shared response handling (v0.4 task 1)"
```

---

### Task 2: REST proxy (server-side, key-hiding)

**Files:**
- Create: `includes/Rest_Proxy.php`
- Modify: `includes/Plugin.php` (register `Rest_Proxy` in `boot()`)
- Test: `tests/RestProxyTest.php`

**Interfaces:**
- Consumes: `Api_Client::get/post`.
- Produces:
  - `Rest_Proxy::__construct( Api_Client $api )`.
  - `Rest_Proxy::register(): void` — hooks `rest_api_init` to register routes under `kwawingu/v1`:
    - `GET /search` (q) → `Api_Client::get('/search', ['q'=>...])`
    - `POST /calculator/estimate` → `Api_Client::post('/calculator/estimate', body, false)` (public key)
    - `GET /availability` (tourSlug, from, to) → `Api_Client::get('/tours/{tourSlug}/availability', ...)`
    - `POST /bookings` → `Api_Client::post('/bookings', body, true)` (private key)
    - `POST /payment-intent` (ref, phone) → `Api_Client::post('/bookings/{ref}/payment-intent', ['phone'=>...], true)`
    - `GET /booking` (ref, email) → `Api_Client::get('/bookings/{ref}', ['email'=>...])`
  - Each handler: `permission_callback` verifies the REST nonce (`wp_verify_nonce` of `X-WP-Nonce` for `wp_rest`); returns a `WP_REST_Response` with the API data, or a `WP_Error` with the API error code/status on `Api_Exception`. Write routes (`/bookings`, `/payment-intent`) additionally enforce a per-IP rate limit via a transient.
  - `Rest_Proxy::NS` = `'kwawingu/v1'`.

- [ ] **Step 1: Write `tests/RestProxyTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Api_Client;
use KwaWingu\Tours\Rest_Proxy;
use Mockery;
use PHPUnit\Framework\TestCase;

class RestProxyTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Functions\when( 'rest_ensure_response' )->returnArg();
    }
    protected function tearDown(): void { Monkey\tearDown(); Mockery::close(); parent::tearDown(); }

    public function test_register_hooks_rest_api_init(): void {
        ( new Rest_Proxy( Mockery::mock( Api_Client::class ) ) )->register();
        $this->assertNotFalse( has_action( 'rest_api_init' ) );
    }

    public function test_search_forwards_to_api_and_returns_data(): void {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->once()->with( '/search', array( 'q' => 'safari' ) )
            ->andReturn( array( 'data' => array( array( 'title' => 'Safari' ) ) ) );

        $req = Mockery::mock();
        $req->shouldReceive( 'get_param' )->with( 'q' )->andReturn( 'safari' );

        $out = ( new Rest_Proxy( $api ) )->handle_search( $req );
        $this->assertSame( array( array( 'title' => 'Safari' ) ), $out['data'] );
    }

    public function test_payment_intent_uses_ref_and_phone(): void {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'post' )->once()
            ->with( '/bookings/KWG-1/payment-intent', array( 'phone' => '255700' ), true )
            ->andReturn( array( 'reference' => 'r', 'paymentUrl' => '' ) );

        $req = Mockery::mock();
        $req->shouldReceive( 'get_param' )->with( 'ref' )->andReturn( 'KWG-1' );
        $req->shouldReceive( 'get_param' )->with( 'phone' )->andReturn( '255700' );

        $out = ( new Rest_Proxy( $api ) )->handle_payment_intent( $req );
        $this->assertSame( 'r', $out['reference'] );
    }

    public function test_handler_returns_wp_error_on_api_exception(): void {
        $api = Mockery::mock( Api_Client::class );
        $api->shouldReceive( 'get' )->andThrow( new \KwaWingu\Tours\Api_Exception( 'nope', 403, 'api_access_required' ) );
        $captured = null;
        Functions\when( 'is_wp_error' )->justReturn( false );
        // WP_Error is stubbed globally below; capture its construction args.
        $req = Mockery::mock();
        $req->shouldReceive( 'get_param' )->andReturn( 'x' );
        $out = ( new Rest_Proxy( $api ) )->handle_search( $req );
        $this->assertInstanceOf( \WP_Error::class, $out );
    }
}

namespace {
    if ( ! class_exists( 'WP_Error' ) ) {
        class WP_Error {
            public $code; public $message; public $data;
            public function __construct( $code = '', $message = '', $data = null ) {
                $this->code = $code; $this->message = $message; $this->data = $data;
            }
        }
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter RestProxyTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/Rest_Proxy.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Same-origin REST proxy so the browser can use the operator's API without ever
 * seeing the keys. Reads use the public key; writes use the private key — both
 * only ever on the server, inside these handlers.
 */
class Rest_Proxy {

    const NS = 'kwawingu/v1';

    /** @var Api_Client */
    private $api;

    public function __construct( Api_Client $api ) {
        $this->api = $api;
    }

    public function register(): void {
        add_action( 'rest_api_init', array( $this, 'routes' ) );
    }

    public function routes(): void {
        $auth = array( $this, 'check_nonce' );

        register_rest_route( self::NS, '/search', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_search' ),
            'permission_callback' => $auth,
            'args'                => array( 'q' => array( 'sanitize_callback' => 'sanitize_text_field' ) ),
        ) );
        register_rest_route( self::NS, '/availability', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_availability' ),
            'permission_callback' => $auth,
        ) );
        register_rest_route( self::NS, '/calculator/estimate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_calculator' ),
            'permission_callback' => $auth,
        ) );
        register_rest_route( self::NS, '/bookings', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_create_booking' ),
            'permission_callback' => $auth,
        ) );
        register_rest_route( self::NS, '/payment-intent', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_payment_intent' ),
            'permission_callback' => $auth,
        ) );
        register_rest_route( self::NS, '/booking', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_booking_lookup' ),
            'permission_callback' => $auth,
        ) );
    }

    /** Same-origin protection: a valid wp_rest nonce. */
    public function check_nonce( $request ): bool {
        $nonce = is_object( $request ) && method_exists( $request, 'get_header' ) ? (string) $request->get_header( 'X-WP-Nonce' ) : '';
        return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_search( $request ) {
        return $this->guard( function () use ( $request ) {
            return $this->api->get( '/search', array( 'q' => (string) $request->get_param( 'q' ) ) );
        } );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_availability( $request ) {
        return $this->guard( function () use ( $request ) {
            $slug = (string) $request->get_param( 'tourSlug' );
            $args = array();
            foreach ( array( 'from', 'to' ) as $k ) {
                $v = $request->get_param( $k );
                if ( null !== $v && '' !== $v ) {
                    $args[ $k ] = (string) $v;
                }
            }
            return $this->api->get( '/tours/' . rawurlencode( $slug ) . '/availability', $args );
        } );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_calculator( $request ) {
        return $this->guard( function () use ( $request ) {
            $body = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();
            return $this->api->post( '/calculator/estimate', $body, false );
        } );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_create_booking( $request ) {
        if ( ! $this->rate_ok( 'book' ) ) {
            return new \WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'kwawingu-tours' ), array( 'status' => 429 ) );
        }
        return $this->guard( function () use ( $request ) {
            $body = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();
            return $this->api->post( '/bookings', $body, true );
        } );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_payment_intent( $request ) {
        if ( ! $this->rate_ok( 'pay' ) ) {
            return new \WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'kwawingu-tours' ), array( 'status' => 429 ) );
        }
        return $this->guard( function () use ( $request ) {
            $ref   = (string) $request->get_param( 'ref' );
            $phone = (string) $request->get_param( 'phone' );
            return $this->api->post( '/bookings/' . rawurlencode( $ref ) . '/payment-intent', array( 'phone' => $phone ), true );
        } );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function handle_booking_lookup( $request ) {
        return $this->guard( function () use ( $request ) {
            $ref = (string) $request->get_param( 'ref' );
            return $this->api->get( '/bookings/' . rawurlencode( $ref ), array( 'email' => (string) $request->get_param( 'email' ) ) );
        } );
    }

    /**
     * Run an API call, mapping Api_Exception to a WP_Error with the API code/status.
     *
     * @param callable():array<string,mixed> $fn
     * @return array<string,mixed>|\WP_Error
     */
    private function guard( callable $fn ) {
        try {
            return $fn();
        } catch ( Api_Exception $e ) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 502;
            $code   = '' !== $e->get_code_string() ? $e->get_code_string() : 'api_error';
            return new \WP_Error( $code, $e->getMessage(), array( 'status' => $status ) );
        }
    }

    /** Simple per-visitor rate limit for write routes: 20 per 10 minutes. */
    private function rate_ok( string $bucket ): bool {
        if ( ! function_exists( 'get_transient' ) ) {
            return true;
        }
        $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'anon';
        $key = 'kwt_rl_' . $bucket . '_' . md5( $ip );
        $n   = (int) get_transient( $key );
        if ( $n >= 20 ) {
            return false;
        }
        set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
        return true;
    }
}
```

- [ ] **Step 4: Wire into `includes/Plugin.php`** — in `boot()`, after `Seo`:

```php
        ( new Rest_Proxy( $api ) )->register();
```

- [ ] **Step 5: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter RestProxyTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add includes/Rest_Proxy.php includes/Plugin.php tests/RestProxyTest.php
git commit -m "feat(rest): server-side proxy hiding API keys (search/availability/calculator/booking/pay) (v0.4 task 2)"
```

---

### Task 3: Frontend proxy config + a shared enqueue helper

**Files:**
- Create: `includes/Assets.php`
- Create: `assets/js/kwt-proxy.js`
- Modify: `includes/Plugin.php` (register `Assets`)
- Test: `tests/AssetsTest.php`

**Interfaces:**
- Produces:
  - `Assets::register(): void` — hooks `wp_enqueue_scripts` to register (not necessarily enqueue) `kwt-proxy` (`assets/js/kwt-proxy.js`) and localize `window.kwtProxy = { root: rest_url('kwawingu/v1'), nonce: wp_create_nonce('wp_rest'), slug, i18n:{…} }`. Blocks that need the proxy declare `kwt-proxy` as a script dependency.
  - `assets/js/kwt-proxy.js` — a tiny helper exposing `window.kwtProxy.get(path, params)` and `.post(path, body)` returning Promises that call the REST proxy with the nonce header; dependency-free.
  - `Assets::HANDLE` = `'kwt-proxy'`.

- [ ] **Step 1: Write `tests/AssetsTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Assets;
use PHPUnit\Framework\TestCase;

class AssetsTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_register_hooks_enqueue(): void {
        ( new Assets() )->register();
        $this->assertNotFalse( has_action( 'wp_enqueue_scripts' ) );
    }

    public function test_enqueue_registers_and_localizes_proxy(): void {
        Functions\when( 'plugins_url' )->justReturn( 'https://site/wp-content/plugins/kwawingu-tours/assets/js/kwt-proxy.js' );
        Functions\when( 'rest_url' )->justReturn( 'https://site/wp-json/kwawingu/v1' );
        Functions\when( 'wp_create_nonce' )->justReturn( 'abc123' );
        Functions\when( 'get_option' )->justReturn( array( 'slug' => 'acme' ) );
        Functions\when( '__' )->returnArg();
        $registered = array();
        Functions\when( 'wp_register_script' )->alias( static function ( $h ) use ( &$registered ) { $registered['reg'] = $h; return true; } );
        $localized = array();
        Functions\when( 'wp_localize_script' )->alias( static function ( $h, $obj, $data ) use ( &$localized ) { $localized = array( $h, $obj, $data ); return true; } );

        ( new Assets() )->enqueue();

        $this->assertSame( 'kwt-proxy', $registered['reg'] );
        $this->assertSame( 'kwtProxy', $localized[1] );
        $this->assertSame( 'abc123', $localized[2]['nonce'] );
        $this->assertSame( 'acme', $localized[2]['slug'] );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter AssetsTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/Assets.php`**

```php
<?php
namespace KwaWingu\Tours;

/**
 * Registers the shared proxy JS + config for interactive blocks.
 */
class Assets {

    const HANDLE = 'kwt-proxy';

    public function register(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function enqueue(): void {
        wp_register_script(
            self::HANDLE,
            plugins_url( 'assets/js/kwt-proxy.js', KWT_PLUGIN_FILE ),
            array(),
            KWT_VERSION,
            true
        );
        $settings = new Settings();
        wp_localize_script( self::HANDLE, 'kwtProxy', array(
            'root'  => esc_url_raw( rest_url( Rest_Proxy::NS ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'slug'  => $settings->get_slug(),
            'i18n'  => array(
                'loading'  => __( 'Loading…', 'kwawingu-tours' ),
                'error'    => __( 'Something went wrong. Please try again.', 'kwawingu-tours' ),
                'noResults'=> __( 'No results.', 'kwawingu-tours' ),
                'checkPhone' => __( 'Check your phone to approve the payment.', 'kwawingu-tours' ),
            ),
        ) );
    }
}
```

- [ ] **Step 4: Write `assets/js/kwt-proxy.js`**

```js
/**
 * KwaWingu proxy client. Same-origin fetch to /wp-json/kwawingu/v1/* with the
 * REST nonce. No keys here — the server proxy holds them.
 */
( function () {
	'use strict';
	var cfg = window.kwtProxy || {};

	function req( method, path, dataOrParams ) {
		var url = cfg.root + path;
		var opts = { method: method, headers: { 'X-WP-Nonce': cfg.nonce } };
		if ( 'GET' === method && dataOrParams ) {
			var qs = Object.keys( dataOrParams ).map( function ( k ) {
				return encodeURIComponent( k ) + '=' + encodeURIComponent( dataOrParams[ k ] );
			} ).join( '&' );
			if ( qs ) { url += ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + qs; }
		} else if ( dataOrParams ) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify( dataOrParams );
		}
		return fetch( url, opts ).then( function ( r ) {
			return r.json().then( function ( body ) {
				if ( ! r.ok ) { throw new Error( ( body && body.message ) || cfg.i18n.error ); }
				return body;
			} );
		} );
	}

	cfg.get = function ( path, params ) { return req( 'GET', path, params ); };
	cfg.post = function ( path, body ) { return req( 'POST', path, body ); };
	window.kwtProxy = cfg;
} )();
```

- [ ] **Step 5: Wire into `includes/Plugin.php`** — in `boot()`, after `Rest_Proxy`:

```php
        ( new Assets() )->register();
```

- [ ] **Step 6: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter AssetsTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add includes/Assets.php assets/js/kwt-proxy.js includes/Plugin.php tests/AssetsTest.php
git commit -m "feat(assets): shared proxy JS client + localized config (v0.4 task 3)"
```

---

### Task 4: Search block (live, via proxy)

**Files:**
- Create: `blocks/search/block.json`, `blocks/search/render-fn.php`, `blocks/search/render.php`, `blocks/search/view.js`
- Test: `tests/blocks/SearchRenderTest.php`

**Interfaces:**
- Produces: block `kwawingu/search`; `kwt_render_search( array $attributes, string $content ): string` renders a server-side shell `<div class="kwt-search" data-...><input><ul class="kwt-search__results"></ul></div>`; `view.js` (depends on `kwt-proxy`) wires the input to `kwtProxy.get('/search',{q})` and renders result titles as links via DOM APIs (textContent — no innerHTML injection). block.json declares `"viewScript": "file:./view.js"` and the `kwt-proxy` dependency is enqueued in the render fn (`wp_enqueue_script('kwt-proxy')`).

- [ ] **Step 1: Write `tests/blocks/SearchRenderTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class SearchRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/search/render-fn.php';
        foreach ( array( 'esc_attr', 'esc_attr__', 'esc_html__' ) as $f ) { Functions\when( $f )->returnArg(); }
        Functions\when( 'wp_enqueue_script' )->justReturn( null );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_search_shell(): void {
        $html = kwt_render_search( array(), '' );
        $this->assertStringContainsString( 'kwt-search', $html );
        $this->assertStringContainsString( '<input', $html );
        $this->assertStringContainsString( 'kwt-search__results', $html );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter SearchRenderTest`
Expected: FAIL — file not found.

- [ ] **Step 3: Write `blocks/search/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "kwawingu/search",
  "title": "KwaWingu Tour Search",
  "category": "widgets",
  "icon": "search",
  "description": "Live search across the operator's tours.",
  "textdomain": "kwawingu-tours",
  "attributes": { "placeholder": { "type": "string", "default": "Search tours…" } },
  "supports": { "html": false },
  "viewScript": "file:./view.js",
  "render": "file:./render.php"
}
```

- [ ] **Step 4: Write `blocks/search/render-fn.php`**

```php
<?php
/**
 * Render function for kwawingu/search (server shell; view.js does the fetching).
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_search' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_search( array $attributes, string $content = '' ): string {
        if ( function_exists( 'wp_enqueue_script' ) ) {
            wp_enqueue_script( 'kwt-proxy' );
        }
        $placeholder = isset( $attributes['placeholder'] ) && '' !== $attributes['placeholder']
            ? (string) $attributes['placeholder']
            : __( 'Search tours…', 'kwawingu-tours' );
        return '<div class="kwt-search">'
            . '<input type="search" class="kwt-search__input" placeholder="' . esc_attr( $placeholder ) . '" aria-label="' . esc_attr__( 'Search tours', 'kwawingu-tours' ) . '" />'
            . '<ul class="kwt-search__results" aria-live="polite"></ul>'
            . '</div>';
    }
}
```

- [ ] **Step 5: Write `blocks/search/render.php`** (thin template)

```php
<?php
/**
 * WP block template for kwawingu/search.
 *
 * @package KwaWingu\Tours
 */
require_once __DIR__ . '/render-fn.php';
$kwt_attrs   = isset( $attributes ) && is_array( $attributes ) ? $attributes : array();
$kwt_content = isset( $content ) ? (string) $content : '';
echo kwt_render_search( $kwt_attrs, $kwt_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- render fn returns escaped HTML.
```

- [ ] **Step 6: Write `blocks/search/view.js`**

```js
/**
 * kwawingu/search view: debounced live search via the proxy.
 */
( function () {
	'use strict';
	function init( root ) {
		var input = root.querySelector( '.kwt-search__input' );
		var list = root.querySelector( '.kwt-search__results' );
		var t;
		input.addEventListener( 'input', function () {
			clearTimeout( t );
			var q = input.value.trim();
			if ( q.length < 2 ) { list.textContent = ''; return; }
			t = setTimeout( function () {
				window.kwtProxy.get( '/search', { q: q } ).then( function ( res ) {
					list.textContent = '';
					var items = ( res && res.data ) || [];
					if ( ! items.length ) {
						var li = document.createElement( 'li' );
						li.textContent = window.kwtProxy.i18n.noResults;
						list.appendChild( li );
						return;
					}
					items.forEach( function ( item ) {
						var li = document.createElement( 'li' );
						var a = document.createElement( 'a' );
						a.href = item.url || '#';
						a.textContent = item.title || '';
						li.appendChild( a );
						list.appendChild( li );
					} );
				} ).catch( function () { list.textContent = window.kwtProxy.i18n.error; } );
			}, 250 );
		} );
	}
	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-search' ), init );
	} );
} )();
```

- [ ] **Step 7: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter SearchRenderTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
git add blocks/search/ tests/blocks/SearchRenderTest.php
git commit -m "feat(blocks): live Search block via proxy (v0.4 task 4)"
```

---

### Task 5: Trip Calculator block (live, via proxy)

**Files:**
- Create: `blocks/calculator/block.json`, `render-fn.php`, `render.php`, `view.js`
- Test: `tests/blocks/CalculatorRenderTest.php`

**Interfaces:**
- Produces: block `kwawingu/calculator`; `kwt_render_calculator( array $attributes, string $content ): string` — server shell with a small form (adults, children, nights) + a total area; `view.js` posts to `kwtProxy.post('/calculator/estimate', {...})` and shows the returned total. Enqueues `kwt-proxy`.

- [ ] **Step 1: Write `tests/blocks/CalculatorRenderTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class CalculatorRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/calculator/render-fn.php';
        foreach ( array( 'esc_attr', 'esc_attr__', 'esc_html__' ) as $f ) { Functions\when( $f )->returnArg(); }
        Functions\when( 'wp_enqueue_script' )->justReturn( null );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_calculator_form(): void {
        $html = kwt_render_calculator( array(), '' );
        $this->assertStringContainsString( 'kwt-calculator', $html );
        $this->assertStringContainsString( 'kwt-calculator__total', $html );
        $this->assertStringContainsString( 'name="adults"', $html );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter CalculatorRenderTest`
Expected: FAIL — file not found.

- [ ] **Step 3: Write `blocks/calculator/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "kwawingu/calculator",
  "title": "KwaWingu Trip Calculator",
  "category": "widgets",
  "icon": "calculator",
  "description": "Estimate a trip price live.",
  "textdomain": "kwawingu-tours",
  "attributes": {},
  "supports": { "html": false },
  "viewScript": "file:./view.js",
  "render": "file:./render.php"
}
```

- [ ] **Step 4: Write `blocks/calculator/render-fn.php`**

```php
<?php
/**
 * Render function for kwawingu/calculator.
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_calculator' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_calculator( array $attributes, string $content = '' ): string {
        if ( function_exists( 'wp_enqueue_script' ) ) {
            wp_enqueue_script( 'kwt-proxy' );
        }
        $l_adults   = esc_html__( 'Adults', 'kwawingu-tours' );
        $l_children = esc_html__( 'Children', 'kwawingu-tours' );
        $l_nights   = esc_html__( 'Nights', 'kwawingu-tours' );
        $l_estimate = esc_html__( 'Estimate', 'kwawingu-tours' );
        return '<form class="kwt-calculator">'
            . '<label>' . $l_adults . ' <input type="number" name="adults" min="1" value="2" /></label>'
            . '<label>' . $l_children . ' <input type="number" name="children" min="0" value="0" /></label>'
            . '<label>' . $l_nights . ' <input type="number" name="nights" min="1" value="3" /></label>'
            . '<button type="submit" class="kwt-btn">' . $l_estimate . '</button>'
            . '<p class="kwt-calculator__total" aria-live="polite"></p>'
            . '</form>';
    }
}
```

- [ ] **Step 5: Write `blocks/calculator/render.php`** (thin template)

```php
<?php
/**
 * WP block template for kwawingu/calculator.
 *
 * @package KwaWingu\Tours
 */
require_once __DIR__ . '/render-fn.php';
$kwt_attrs   = isset( $attributes ) && is_array( $attributes ) ? $attributes : array();
$kwt_content = isset( $content ) ? (string) $content : '';
echo kwt_render_calculator( $kwt_attrs, $kwt_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- render fn returns escaped HTML.
```

- [ ] **Step 6: Write `blocks/calculator/view.js`**

```js
/**
 * kwawingu/calculator view: posts inputs to the proxy, shows the total.
 */
( function () {
	'use strict';
	function init( form ) {
		var total = form.querySelector( '.kwt-calculator__total' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			total.textContent = window.kwtProxy.i18n.loading;
			var body = {
				adults: Number( form.adults.value ) || 1,
				children: Number( form.children.value ) || 0,
				nights: Number( form.nights.value ) || 1
			};
			window.kwtProxy.post( '/calculator/estimate', body ).then( function ( res ) {
				var data = ( res && res.data ) || res || {};
				var amount = data.total || data.perPersonTotal || 0;
				total.textContent = 'TZS ' + Number( amount ).toLocaleString();
			} ).catch( function () { total.textContent = window.kwtProxy.i18n.error; } );
		} );
	}
	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-calculator' ), init );
	} );
} )();
```

- [ ] **Step 7: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter CalculatorRenderTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
git add blocks/calculator/ tests/blocks/CalculatorRenderTest.php
git commit -m "feat(blocks): live Trip Calculator block via proxy (v0.4 task 5)"
```

---

### Task 6: On-site booking block (create → pay → poll)

**Files:**
- Create: `blocks/booking/block.json`, `render-fn.php`, `render.php`, `view.js`
- Modify: `blocks/book-button/render-fn.php` (onsite mode → link/scroll to the booking block or render inline embed)
- Test: `tests/blocks/BookingFormRenderTest.php`

**Interfaces:**
- Produces: block `kwawingu/booking`; `kwt_render_booking_form( array $attributes, string $content ): string` — a server shell booking form (name, email, phone, pax, date) + status area; `view.js` drives: `kwtProxy.post('/bookings', {...})` → `kwtProxy.post('/payment-intent', {ref, phone})` → poll `kwtProxy.get('/booking', {ref, email})` until status completed, updating a live status message. Enqueues `kwt-proxy`. Book Button in `onsite` mode links to the booking form (anchor `#kwt-book`).

- [ ] **Step 1: Write `tests/blocks/BookingFormRenderTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests\Blocks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class BookingFormRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/blocks/booking/render-fn.php';
        foreach ( array( 'esc_attr', 'esc_attr__', 'esc_html__' ) as $f ) { Functions\when( $f )->returnArg(); }
        Functions\when( 'wp_enqueue_script' )->justReturn( null );
        Functions\when( 'get_the_ID' )->justReturn( 7 );
        Functions\when( 'get_post_meta' )->justReturn( 'safari' );
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_booking_form_with_fields(): void {
        $html = kwt_render_booking_form( array(), '' );
        $this->assertStringContainsString( 'id="kwt-book"', $html );
        $this->assertStringContainsString( 'name="email"', $html );
        $this->assertStringContainsString( 'name="phone"', $html );
        $this->assertStringContainsString( 'kwt-booking__status', $html );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter BookingFormRenderTest`
Expected: FAIL — file not found.

- [ ] **Step 3: Write `blocks/booking/block.json`**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "kwawingu/booking",
  "title": "KwaWingu Booking",
  "category": "widgets",
  "icon": "cart",
  "description": "On-site booking + payment for the current tour.",
  "textdomain": "kwawingu-tours",
  "attributes": { "tourSlug": { "type": "string", "default": "" } },
  "supports": { "html": false },
  "viewScript": "file:./view.js",
  "render": "file:./render.php"
}
```

- [ ] **Step 4: Write `blocks/booking/render-fn.php`**

```php
<?php
/**
 * Render function for kwawingu/booking (on-site booking form shell).
 *
 * @package KwaWingu\Tours
 */

if ( ! function_exists( 'kwt_render_booking_form' ) ) {
    /**
     * @param array<string,mixed> $attributes
     */
    function kwt_render_booking_form( array $attributes, string $content = '' ): string {
        if ( function_exists( 'wp_enqueue_script' ) ) {
            wp_enqueue_script( 'kwt-proxy' );
        }
        $id        = (int) get_the_ID();
        $tour_slug = ! empty( $attributes['tourSlug'] ) ? (string) $attributes['tourSlug'] : (string) get_post_meta( $id, 'kwt_slug', true );

        $l_name  = esc_html__( 'Full name', 'kwawingu-tours' );
        $l_email = esc_html__( 'Email', 'kwawingu-tours' );
        $l_phone = esc_html__( 'Mobile money number', 'kwawingu-tours' );
        $l_date  = esc_html__( 'Date', 'kwawingu-tours' );
        $l_pax   = esc_html__( 'Guests', 'kwawingu-tours' );
        $l_book  = esc_html__( 'Book & pay', 'kwawingu-tours' );

        return '<form id="kwt-book" class="kwt-booking" data-tour="' . esc_attr( $tour_slug ) . '">'
            . '<label>' . $l_name . ' <input type="text" name="name" required /></label>'
            . '<label>' . $l_email . ' <input type="email" name="email" required /></label>'
            . '<label>' . $l_phone . ' <input type="tel" name="phone" required placeholder="2557…" /></label>'
            . '<label>' . $l_date . ' <input type="date" name="date" required /></label>'
            . '<label>' . $l_pax . ' <input type="number" name="pax" min="1" value="1" /></label>'
            . '<button type="submit" class="kwt-btn">' . $l_book . '</button>'
            . '<p class="kwt-booking__status" aria-live="polite"></p>'
            . '</form>';
    }
}
```

- [ ] **Step 5: Write `blocks/booking/render.php`** (thin template)

```php
<?php
/**
 * WP block template for kwawingu/booking.
 *
 * @package KwaWingu\Tours
 */
require_once __DIR__ . '/render-fn.php';
$kwt_attrs   = isset( $attributes ) && is_array( $attributes ) ? $attributes : array();
$kwt_content = isset( $content ) ? (string) $content : '';
echo kwt_render_booking_form( $kwt_attrs, $kwt_content ); // phpcs:ignore WordPress.Security.EscapeOutput -- render fn returns escaped HTML.
```

- [ ] **Step 6: Write `blocks/booking/view.js`**

```js
/**
 * kwawingu/booking view: create booking -> start payment -> poll status.
 */
( function () {
	'use strict';
	function init( form ) {
		var status = form.querySelector( '.kwt-booking__status' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			status.textContent = window.kwtProxy.i18n.loading;
			var email = form.email.value.trim();
			var phone = form.phone.value.trim();
			var payload = {
				tourSlug: form.getAttribute( 'data-tour' ),
				customer: { name: form.name.value.trim(), email: email, phone: phone },
				date: form.date.value,
				pax: Number( form.pax.value ) || 1
			};
			window.kwtProxy.post( '/bookings', payload ).then( function ( res ) {
				var ref = ( res && ( res.ref || ( res.data && res.data.ref ) ) );
				if ( ! ref ) { throw new Error( window.kwtProxy.i18n.error ); }
				return window.kwtProxy.post( '/payment-intent', { ref: ref, phone: phone } ).then( function () {
					status.textContent = window.kwtProxy.i18n.checkPhone;
					poll( ref, email, 0 );
				} );
			} ).catch( function ( err ) { status.textContent = err.message || window.kwtProxy.i18n.error; } );
		} );

		function poll( ref, email, tries ) {
			if ( tries > 40 ) { return; }
			setTimeout( function () {
				window.kwtProxy.get( '/booking', { ref: ref, email: email } ).then( function ( res ) {
					var data = res && res.data ? res.data : res;
					var st = data && ( data.status || data.paymentStatus );
					if ( st === 'paid' || st === 'confirmed' || st === 'completed' ) {
						status.textContent = 'Payment received — you are booked!';
					} else {
						poll( ref, email, tries + 1 );
					}
				} ).catch( function () { poll( ref, email, tries + 1 ); } );
			}, 5000 );
		}
	}
	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.kwt-booking' ), init );
	} );
} )();
```

- [ ] **Step 7: Update `blocks/book-button/render-fn.php`** — add an `onsite` branch before the redirect fallback:

```php
        if ( 'onsite' === $booking->mode() ) {
            // The on-site booking form lives in the kwawingu/booking block (anchor #kwt-book).
            return '<a class="kwt-book-btn" href="#kwt-book">' . esc_html( $label ) . '</a>';
        }
```

Place this right after the existing `if ( 'widget' === $booking->mode() ) { … }` block. (Redirect remains the final fallback.)

- [ ] **Step 8: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter BookingFormRenderTest` then `--filter BookButtonRenderTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 9: Commit**

```bash
git add blocks/booking/ blocks/book-button/render-fn.php tests/blocks/BookingFormRenderTest.php
git commit -m "feat(blocks): on-site booking form (create/pay/poll via proxy) + book-button onsite mode (v0.4 task 6)"
```

---

### Task 7: Register new blocks' shortcodes + i18n loading

**Files:**
- Modify: `includes/Shortcodes.php` (`[kwawingu_search]`, `[kwawingu_calculator]`, `[kwawingu_booking_form]`)
- Modify: `kwawingu-tours.php` (load text domain)
- Create: `languages/kwawingu-tours.pot`
- Test: `tests/ShortcodesTest.php`

**Interfaces:**
- Produces: shortcodes `[kwawingu_search]`, `[kwawingu_calculator]`, `[kwawingu_booking_form]`; a `.pot` template; `load_plugin_textdomain('kwawingu-tours', …)` on `init`.

- [ ] **Step 1: Extend `test_register_adds_all_shortcodes` in `tests/ShortcodesTest.php`**

```php
        $this->assertContains( 'kwawingu_search', $registered );
        $this->assertContains( 'kwawingu_calculator', $registered );
        $this->assertContains( 'kwawingu_booking_form', $registered );
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ShortcodesTest`
Expected: FAIL.

- [ ] **Step 3: Extend `includes/Shortcodes.php`** — in `register()`:

```php
        add_shortcode( 'kwawingu_search', array( $this, 'render_search' ) );
        add_shortcode( 'kwawingu_calculator', array( $this, 'render_calculator' ) );
        add_shortcode( 'kwawingu_booking_form', array( $this, 'render_booking_form' ) );
```

And the methods:

```php
    /** @param array<string,mixed> $atts */
    public function render_search( $atts ): string {
        require_once Blocks::block_dir() . 'search/render-fn.php';
        return kwt_render_search( array(), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_calculator( $atts ): string {
        require_once Blocks::block_dir() . 'calculator/render-fn.php';
        return kwt_render_calculator( array(), '' );
    }

    /** @param array<string,mixed> $atts */
    public function render_booking_form( $atts ): string {
        require_once Blocks::block_dir() . 'booking/render-fn.php';
        return kwt_render_booking_form( array(), '' );
    }
```

- [ ] **Step 4: Load the text domain** — in `kwawingu-tours.php`, inside the `plugins_loaded` callback (before `Plugin::instance()->boot()`), add:

```php
    load_plugin_textdomain( 'kwawingu-tours', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
```

- [ ] **Step 5: Create `languages/kwawingu-tours.pot`** — a minimal valid POT header (translators fill strings later):

```
# Copyright (C) 2026 KwaWingu Tours
# This file is distributed under the GPL-2.0-or-later license.
msgid ""
msgstr ""
"Project-Id-Version: KwaWingu Tours 1.0.0\n"
"Report-Msgid-Bugs-To: https://tours.kwawingu.com\n"
"Language: \n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"X-Domain: kwawingu-tours\n"
```

- [ ] **Step 6: Run to pass + full suite**

Run: `vendor/bin/phpunit --filter ShortcodesTest` then `vendor/bin/phpunit`
Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add includes/Shortcodes.php kwawingu-tours.php languages/kwawingu-tours.pot tests/ShortcodesTest.php
git commit -m "feat(v0.4): search/calculator/booking shortcodes + i18n text domain + .pot (v0.4 task 7)"
```

---

### Task 8: Hardening + WP.org submission prep + bump 1.0.0

**Files:**
- Modify: every `includes/*.php` (add ABSPATH guard)
- Modify: `phpcs.xml.dist` (modern text_domain rule), `.github/workflows/ci.yml` (make phpcs blocking)
- Modify: `readme.txt` (Tested up to 6.6, `= 1.0.0 =` changelog, screenshots section), `README.md`, `docs/booking-modes.md` (on-site now available)
- Modify: `kwawingu-tours.php` + `includes/Plugin.php` (version `1.0.0`), `tests/PluginTest.php`

**Interfaces:** hardening/packaging.

- [ ] **Step 1: Add the ABSPATH guard to every class file** — at the top of each `includes/*.php` (after the `<?php` and namespace line is fine; standard is right after `<?php`), add:

```php
if ( ! defined( 'ABSPATH' ) ) { exit; }
```

Apply to: `Plugin.php`, `Settings.php`, `Admin_Page.php`, `Api_Client.php`, `Api_Exception.php`, `Cpt.php`, `Sync.php`, `Sync_Controller.php`, `Blocks.php`, `View.php`, `Shortcodes.php`, `Patterns.php`, `Branding.php`, `Importer.php`, `Setup_Wizard.php`, `Seo.php`, `Media.php`, `Rest_Proxy.php`, `Assets.php`. NOTE: the tests define `ABSPATH` in `tests/bootstrap.php`, so the guard is a no-op under test — the full suite must still pass.

- [ ] **Step 2: Run the full suite (guards must not break tests)**

Run: `vendor/bin/phpunit`
Expected: all PASS (ABSPATH is defined in the test bootstrap).

- [ ] **Step 3: Fix the PHPCS text_domain rule in `phpcs.xml.dist`** — replace the legacy `<config name="text_domain" …>` line with the modern property form:

```xml
    <rule ref="WordPress.WP.I18n">
        <properties>
            <property name="text_domain" type="array">
                <element value="kwawingu-tours"/>
            </property>
        </properties>
    </rule>
```

- [ ] **Step 4: Make PHPCS blocking in CI** — in `.github/workflows/ci.yml`, change the PHPCS step from `vendor/bin/phpcs -q || true` to:

```yaml
      - name: PHPCS
        run: vendor/bin/phpcs -q
```

- [ ] **Step 5: Bump version to 1.0.0** — `kwawingu-tours.php` (`Version: 1.0.0` + `KWT_VERSION '1.0.0'`), `includes/Plugin.php` (`const VERSION = '1.0.0'`), `tests/PluginTest.php` (expect `'1.0.0'`), `readme.txt` (`Stable tag: 1.0.0`, `Tested up to: 6.6`).

- [ ] **Step 6: Update docs** — `docs/booking-modes.md`: move On-site to "available now" with a note that it needs the private key; `README.md`: add On-site booking + Search/Calculator/Booking blocks + i18n to the feature list and shortcode table; `readme.txt` changelog:

```
= 1.0.0 =
* On-site booking mode: book + pay (mobile money) without leaving your site.
* Live blocks: Search, Trip Calculator, On-site Booking (via a secure server-side proxy — your API keys never reach the browser).
* Internationalization (.pot) + text domain loading.
* Hardening for WordPress.org: input/output security review, ABSPATH guards, blocking coding-standards check.
```

- [ ] **Step 7: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: all PASS (PluginTest expects 1.0.0). Optionally run `vendor/bin/phpcs -q` and fix any surfaced nits (now that it's blocking in CI).

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "chore(release): ABSPATH guards, blocking PHPCS, i18n, docs; bump to 1.0.0 (v0.4 task 8)"
```

---

## Self-Review

**Spec coverage (v0.4 / completion slice):**
- On-site booking mode → Task 6 (block + view.js create/pay/poll) + Task 2 (proxy write routes with the private key) + Task 1 (`Api_Client::post`). Book Button onsite branch → Task 6. ✓
- Live blocks Search / Calculator / Availability — Search (Task 4), Calculator (Task 5). **Availability** block is folded into the on-site booking form (date picker) rather than a standalone calendar block; a standalone availability-calendar block is deferred (noted below) to keep v0.4 shippable. The proxy `/availability` route exists (Task 2) for future use. ✓ (partial — see note)
- REST proxy hiding keys → Task 2; frontend proxy client → Task 3. ✓
- i18n (.pot + textdomain) → Task 7. ✓
- WP.org prep (ABSPATH guards, blocking PHPCS, readme, version) → Task 8. ✓
- Api_Client transport-error message (v0.1 minor) → Task 1. ✓

**Deferred (noted, not silently dropped):** a standalone Availability **Calendar** block (visual month grid) is deferred beyond 1.0.0 — the `/availability` proxy route is in place, and on-site booking already collects a date. A follow-up can add the calendar UI. Gallery **sideloading** (v0.3 stored gallery as meta) also remains a future enhancement.

**Placeholder scan:** No TBD/TODO in steps; each code step has complete code. The `.pot` ships as a valid header-only template (translators add msgids) — that is the standard initial state, not a placeholder.

**Type consistency:** `Api_Client::post(path, body, usePrivate)` + `handle_response`; `Rest_Proxy` handler method names (`handle_search`/`handle_availability`/`handle_calculator`/`handle_create_booking`/`handle_payment_intent`/`handle_booking_lookup`) match the tests; `Assets::HANDLE='kwt-proxy'` + `window.kwtProxy` config keys (`root`/`nonce`/`slug`/`i18n`) match `kwt-proxy.js` + every block `view.js`; block render fns (`kwt_render_search`/`kwt_render_calculator`/`kwt_render_booking_form`) + their split render-fn/template + shortcode requires (render-fn.php) follow the established pattern. boot() adds Rest_Proxy + Assets after Seo.

**JS test-coverage limitation (explicit):** Brain\Monkey/PHPUnit cannot exercise the block `view.js` files — they are verified by the PHP render tests (shell markup) + manual/browser QA, and the security-critical logic (keys, auth, rate limit, API forwarding) lives in the PHP `Rest_Proxy` which IS unit-tested. The JS only calls the same-origin, nonce-protected proxy and renders via DOM APIs (no `innerHTML` with server data), so an XSS via JS is structurally avoided. This is called out for the final review.
