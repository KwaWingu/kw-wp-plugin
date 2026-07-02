# KwaWingu WP Plugin v1.8 — Operator notification + lead capture

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a guest books on-site through the WordPress site, email the **operator** a notice and store the guest details as a **lead** in WordPress. No guest-facing emails (the KwaWingu backend already owns those).

**Architecture:** A `Notifications` class captures a `kwt_lead` post + sends an operator `wp_mail` on a successful create-booking; the `Rest_Proxy` create-booking handler calls it (best-effort). Two settings gate it (notifications on/off + recipient override; lead capture on/off). A private `kwt_lead` CPT gives the operator a follow-up list in wp-admin.

**Tech Stack:** PHP 7.4+ (tests on PHP 8.3), WordPress 6.2+, PHPUnit + Brain\Monkey + Mockery.

## Global Constraints

- Namespace `KwaWingu\Tours`; text domain `kwawingu-tours`.
- **No guest emails from WP** — only the operator (site admin / configured recipient). Guest confirmations/nurture stay in the KwaWingu backend.
- All new input sanitized (recipient via `sanitize_email`, lead fields via `sanitize_text_field`/`sanitize_email`); nonce + `manage_options` on settings; `Notifications` is best-effort (never throws into the REST flow). `Rest_Proxy` constructor stays backward-compatible (`?Notifications = null`) so existing tests pass.
- `vendor/bin/phpunit` + `vendor/bin/phpcs -q` (exit 0) + `npm run build` + `npm run test:js` stay green. Version bumps to 1.8.0.

---

### Task 1: Settings (notify + leads) + kwt_lead CPT

**Files:**
- Modify: `includes/Settings.php` (getters + sanitize), `includes/Admin_Page.php` (fields), `includes/Cpt.php` (`kwt_lead`)
- Test: `tests/SettingsTest.php`, `tests/CptTest.php`

**Interfaces:**
- Produces: `Settings::notifications_enabled(): bool` (default false), `Settings::notification_recipient(): string` (default '' → caller falls back to admin_email), `Settings::lead_capture_enabled(): bool` (default false). `Cpt::LEAD = 'kwt_lead'` — a private (`public=false`, `show_ui=true`) CPT for leads.

- [ ] **Step 1: Add the failing tests**

To `tests/SettingsTest.php` (add a method):

```php
	public function test_notification_and_lead_getters(): void {
		Functions\when( 'get_option' )->justReturn( array(
			'notify_enabled' => '1',
			'notify_email'   => 'ops@example.com',
			'capture_leads'  => '1',
		) );
		$s = new Settings();
		$this->assertTrue( $s->notifications_enabled() );
		$this->assertSame( 'ops@example.com', $s->notification_recipient() );
		$this->assertTrue( $s->lead_capture_enabled() );

		Functions\when( 'get_option' )->justReturn( array() );
		$s2 = new Settings();
		$this->assertFalse( $s2->notifications_enabled() );
		$this->assertSame( '', $s2->notification_recipient() );
		$this->assertFalse( $s2->lead_capture_enabled() );
	}

	public function test_sanitize_notification_fields(): void {
		Functions\when( 'sanitize_email' )->returnArg();
		$out = ( new Settings() )->sanitize( array(
			'notify_enabled' => 'on',
			'notify_email'   => ' ops@example.com ',
			'capture_leads'  => '',
		) );
		$this->assertSame( '1', $out['notify_enabled'] );
		$this->assertSame( 'ops@example.com', $out['notify_email'] );
		$this->assertSame( '', $out['capture_leads'] );
	}
```

To `tests/CptTest.php` `test_init_registers...` (or a new test) — assert `kwt_lead` registered:

```php
	public function test_init_registers_lead_cpt_private_with_ui(): void {
		$captured = array();
		Functions\when( 'register_post_type' )->alias( static function ( $type, $args ) use ( &$captured ) {
			$captured[ $type ] = $args;
		} );
		Functions\when( 'register_taxonomy' )->justReturn( true );
		( new Cpt() )->init();
		$this->assertArrayHasKey( 'kwt_lead', $captured );
		$this->assertFalse( $captured['kwt_lead']['public'] );
		$this->assertTrue( $captured['kwt_lead']['show_ui'] );
	}
```

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter SettingsTest` and `--filter CptTest`
Expected: FAIL.

- [ ] **Step 3: Add the getters + sanitize keys to `includes/Settings.php`** — getters:

```php
	/**
	 * Whether to email the operator on a new on-site booking.
	 *
	 * @return bool
	 */
	public function notifications_enabled(): bool {
		return '1' === (string) ( $this->all()['notify_enabled'] ?? '' );
	}

	/**
	 * Operator notification recipient (empty → caller falls back to admin_email).
	 *
	 * @return string
	 */
	public function notification_recipient(): string {
		return (string) ( $this->all()['notify_email'] ?? '' );
	}

	/**
	 * Whether to store on-site booking guest details as leads.
	 *
	 * @return bool
	 */
	public function lead_capture_enabled(): bool {
		return '1' === (string) ( $this->all()['capture_leads'] ?? '' );
	}
```

In `sanitize()`, before the `return array( … )`, compute:

```php
		$notify_enabled = ! empty( $input['notify_enabled'] ) ? '1' : '';
		$notify_email   = sanitize_email( trim( (string) ( $input['notify_email'] ?? '' ) ) );
		$capture_leads  = ! empty( $input['capture_leads'] ) ? '1' : '';
```

and add these three keys to the returned array:

```php
			'notify_enabled' => $notify_enabled,
			'notify_email'   => $notify_email,
			'capture_leads'  => $capture_leads,
```

(Note: `all()` is the existing private option-reader; if `sanitize` reads `$input` directly it already does — mirror the existing style.)

- [ ] **Step 4: Register `kwt_lead` in `includes/Cpt.php`** — add the const near the others:

```php
	const LEAD = 'kwt_lead';
```

and in `init()`, after the destination CPT, register it:

```php
		register_post_type(
			self::LEAD,
			array(
				'labels'       => array(
					'name'          => __( 'Leads', 'kwawingu-tours' ),
					'singular_name' => __( 'Lead', 'kwawingu-tours' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-groups',
				'supports'     => array( 'title', 'custom-fields' ),
				'capability_type' => 'post',
			)
		);
```

- [ ] **Step 5: Add the settings fields to `includes/Admin_Page.php`** — inside `render()`, in the settings `<table class="form-table">`, add three rows (after the existing fields), each escaped, using `checked()` / `esc_attr()`:

```php
					<tr>
						<th scope="row"><?php echo esc_html__( 'Email me on new on-site bookings', 'kwawingu-tours' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[notify_enabled]" value="1" <?php checked( $this->settings->notifications_enabled() ); ?> />
								<?php echo esc_html__( 'Send me a notification', 'kwawingu-tours' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Guest booking confirmations are sent by KwaWingu; this only notifies you.', 'kwawingu-tours' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="kwt_notify_email"><?php echo esc_html__( 'Notification email', 'kwawingu-tours' ); ?></label></th>
						<td><input type="email" id="kwt_notify_email" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[notify_email]" value="<?php echo esc_attr( $this->settings->notification_recipient() ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Save booking leads', 'kwawingu-tours' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[capture_leads]" value="1" <?php checked( $this->settings->lead_capture_enabled() ); ?> />
								<?php echo esc_html__( 'Store on-site booking guest details under Leads', 'kwawingu-tours' ); ?>
							</label>
						</td>
					</tr>
```

(`$opt = Settings::OPTION` is already defined in `render()`.)

- [ ] **Step 6: Run to pass + full suite + phpcs**

Run: `vendor/bin/phpunit --filter SettingsTest` then `--filter CptTest` then `vendor/bin/phpunit` then `vendor/bin/phpcs -q`
Expected: PASS; phpcs exit 0.

- [ ] **Step 7: Commit**

```bash
git add includes/Settings.php includes/Admin_Page.php includes/Cpt.php tests/SettingsTest.php tests/CptTest.php
git commit -m "feat(notify): settings (operator notify + lead capture) + kwt_lead CPT (v1.8 task 1)"
```

---

### Task 2: Notifications class + Rest_Proxy wire

**Files:**
- Create: `includes/Notifications.php`
- Modify: `includes/Rest_Proxy.php` (optional dep + call on create-booking success), `includes/Plugin.php` (construct + pass)
- Test: `tests/NotificationsTest.php`

**Interfaces:**
- Produces:
  - `Notifications::__construct( Settings $settings )`.
  - `Notifications::on_booking_created( array $payload, array $result ): void` — best-effort; when lead capture is on, insert a `kwt_lead` post (title = guest name, meta `kwt_lead_email`/`kwt_lead_phone`/`kwt_lead_tour`/`kwt_lead_ref`); when notifications are on, `wp_mail` the recipient (`notification_recipient()` or `get_option('admin_email')`) a plain-text notice. Never throws.
- Consumes (Rest_Proxy): `Rest_Proxy::__construct( Api_Client $api, ?Notifications $notifications = null )` — on a non-`WP_Error` create-booking result, calls `on_booking_created( $body, $result )`.

- [ ] **Step 1: Write `tests/NotificationsTest.php`**

```php
<?php
namespace KwaWingu\Tours\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use KwaWingu\Tours\Notifications;
use KwaWingu\Tours\Settings;
use PHPUnit\Framework\TestCase;

class NotificationsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'wp_strip_all_tags' )->returnArg();
	}
	protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

	private function payload(): array {
		return array( 'guestFirstName' => 'Jane', 'guestLastName' => 'Doe', 'guestEmail' => 'j@x.com', 'guestPhone' => '255700', 'tourSlug' => 'safari' );
	}

	public function test_captures_lead_and_emails_when_enabled(): void {
		Functions\when( 'get_option' )->justReturn( array( 'notify_enabled' => '1', 'notify_email' => 'ops@x.com', 'capture_leads' => '1' ) );
		$inserted = array();
		Functions\when( 'wp_insert_post' )->alias( static function ( $args ) use ( &$inserted ) { $inserted[] = $args; return 99; } );
		Functions\when( 'update_post_meta' )->justReturn( true );
		$mail = array();
		Functions\when( 'wp_mail' )->alias( static function ( $to, $subj, $body ) use ( &$mail ) { $mail = array( $to, $subj, $body ); return true; } );

		( new Notifications( new Settings() ) )->on_booking_created( $this->payload(), array( 'booking' => array( 'ref' => 'KWG-1' ) ) );

		$this->assertNotEmpty( $inserted );
		$this->assertStringContainsString( 'Jane', $inserted[0]['post_title'] );
		$this->assertSame( 'ops@x.com', $mail[0] );
		$this->assertStringContainsString( 'KWG-1', $mail[2] );
	}

	public function test_does_nothing_when_disabled(): void {
		Functions\when( 'get_option' )->justReturn( array() ); // both off
		Functions\expect( 'wp_insert_post' )->never();
		Functions\expect( 'wp_mail' )->never();
		( new Notifications( new Settings() ) )->on_booking_created( $this->payload(), array() );
		$this->assertTrue( true );
	}

	public function test_falls_back_to_admin_email(): void {
		Functions\when( 'get_option' )->alias( static function ( $key ) {
			if ( 'admin_email' === $key ) { return 'admin@site.com'; }
			return array( 'notify_enabled' => '1', 'notify_email' => '', 'capture_leads' => '' );
		} );
		$mail = array();
		Functions\when( 'wp_mail' )->alias( static function ( $to ) use ( &$mail ) { $mail[] = $to; return true; } );
		( new Notifications( new Settings() ) )->on_booking_created( $this->payload(), array() );
		$this->assertSame( array( 'admin@site.com' ), $mail );
	}
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter NotificationsTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `includes/Notifications.php`**

```php
<?php
/**
 * Operator notifications + lead capture for on-site bookings.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emails the operator and stores a lead when a guest books on-site. Never emails
 * the guest — guest-facing mail is handled by the KwaWingu backend.
 */
class Notifications {

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Handle a successful on-site booking: capture a lead + notify the operator.
	 * Best-effort — never throws into the REST flow.
	 *
	 * @param array<string,mixed> $payload The create-booking request body.
	 * @param array<string,mixed> $result  The create-booking API response.
	 * @return void
	 */
	public function on_booking_created( array $payload, array $result ): void {
		try {
			$name  = trim( sanitize_text_field( (string) ( $payload['guestFirstName'] ?? '' ) ) . ' ' . sanitize_text_field( (string) ( $payload['guestLastName'] ?? '' ) ) );
			$email = sanitize_email( (string) ( $payload['guestEmail'] ?? '' ) );
			$phone = sanitize_text_field( (string) ( $payload['guestPhone'] ?? '' ) );
			$tour  = sanitize_text_field( (string) ( $payload['tourSlug'] ?? '' ) );
			$ref   = $this->ref_from_result( $result );

			if ( $this->settings->lead_capture_enabled() ) {
				$this->capture_lead( $name, $email, $phone, $tour, $ref );
			}
			if ( $this->settings->notifications_enabled() ) {
				$this->notify_operator( $name, $email, $phone, $tour, $ref );
			}
		} catch ( \Throwable $e ) {
			// Best-effort — swallow.
		}
	}

	/**
	 * Store a kwt_lead post.
	 *
	 * @param string $name  Guest name.
	 * @param string $email Guest email.
	 * @param string $phone Guest phone.
	 * @param string $tour  Tour slug.
	 * @param string $ref   Booking reference.
	 * @return void
	 */
	private function capture_lead( string $name, string $email, string $phone, string $tour, string $ref ): void {
		$post_id = wp_insert_post( array(
			'post_type'   => Cpt::LEAD,
			'post_status' => 'publish',
			'post_title'  => '' !== $name ? $name : $email,
		) );
		if ( is_int( $post_id ) && $post_id > 0 ) {
			update_post_meta( $post_id, 'kwt_lead_email', $email );
			update_post_meta( $post_id, 'kwt_lead_phone', $phone );
			update_post_meta( $post_id, 'kwt_lead_tour', $tour );
			update_post_meta( $post_id, 'kwt_lead_ref', $ref );
		}
	}

	/**
	 * Email the operator a new-booking notice.
	 *
	 * @param string $name  Guest name.
	 * @param string $email Guest email.
	 * @param string $phone Guest phone.
	 * @param string $tour  Tour slug.
	 * @param string $ref   Booking reference.
	 * @return void
	 */
	private function notify_operator( string $name, string $email, string $phone, string $tour, string $ref ): void {
		$to = $this->settings->notification_recipient();
		if ( '' === $to ) {
			$to = (string) get_option( 'admin_email' );
		}
		if ( '' === $to ) {
			return;
		}
		/* translators: %s: tour slug */
		$subject = sprintf( __( 'New booking via your website — %s', 'kwawingu-tours' ), $tour );
		$body    = wp_strip_all_tags(
			__( 'A guest booked on your website:', 'kwawingu-tours' ) . "\n\n"
			. __( 'Name:', 'kwawingu-tours' ) . ' ' . $name . "\n"
			. __( 'Email:', 'kwawingu-tours' ) . ' ' . $email . "\n"
			. __( 'Phone:', 'kwawingu-tours' ) . ' ' . $phone . "\n"
			. __( 'Tour:', 'kwawingu-tours' ) . ' ' . $tour . "\n"
			. __( 'Reference:', 'kwawingu-tours' ) . ' ' . $ref . "\n"
		);
		wp_mail( $to, $subject, $body );
	}

	/**
	 * Extract a booking ref from the API response (shape varies).
	 *
	 * @param array<string,mixed> $result API response.
	 * @return string
	 */
	private function ref_from_result( array $result ): string {
		$booking = isset( $result['booking'] ) && is_array( $result['booking'] ) ? $result['booking'] : ( isset( $result['data']['booking'] ) && is_array( $result['data']['booking'] ) ? $result['data']['booking'] : array() );
		$ref     = $booking['ref'] ?? ( $booking['bookingReference'] ?? ( $result['ref'] ?? '' ) );
		return sanitize_text_field( (string) $ref );
	}
}
```

- [ ] **Step 4: Wire into `includes/Rest_Proxy.php`** — change the constructor + add the property:

```php
	/**
	 * Optional operator notifier.
	 *
	 * @var Notifications|null
	 */
	private $notifications;
```

```php
	public function __construct( Api_Client $api, ?Notifications $notifications = null ) {
		$this->api           = $api;
		$this->notifications = $notifications;
	}
```

And in `handle_create_booking`, capture the result + notify on success:

```php
	public function handle_create_booking( $request ) {
		if ( ! $this->rate_ok( 'book' ) ) {
			return new \WP_Error( 'rate_limited', __( 'Too many requests. Please wait a moment.', 'kwawingu-tours' ), array( 'status' => 429 ) );
		}
		$body   = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();
		$result = $this->guard(
			function () use ( $body ) {
				return $this->api->post( '/bookings', $body, true );
			}
		);
		if ( ! is_wp_error( $result ) && null !== $this->notifications ) {
			$this->notifications->on_booking_created( $body, is_array( $result ) ? $result : array() );
		}
		return $result;
	}
```

- [ ] **Step 5: Wire into `includes/Plugin.php`** — where the `Rest_Proxy` is constructed in `boot()`, pass a `Notifications`:

```php
		( new Rest_Proxy( $api, new Notifications( $settings ) ) )->register();
```

(`$settings` already exists in `boot()`.)

- [ ] **Step 6: Run to pass + full suite + phpcs**

Run: `vendor/bin/phpunit --filter NotificationsTest` then `vendor/bin/phpunit` then `vendor/bin/phpcs -q`
Expected: PASS (existing RestProxyTest constructs `new Rest_Proxy($api)` — 1 arg, notifications null → unchanged); phpcs 0.

- [ ] **Step 7: Commit**

```bash
git add includes/Notifications.php includes/Rest_Proxy.php includes/Plugin.php tests/NotificationsTest.php
git commit -m "feat(notify): operator email + lead capture on on-site booking (v1.8 task 2)"
```

---

### Task 3: Docs + bump 1.8.0

**Files:**
- Modify: `docs/booking-modes.md` (note the operator notification + leads), `README.md`, `readme.txt` (changelog + Stable tag), `kwawingu-tours.php` + `includes/Plugin.php` (version), `tests/PluginTest.php`, `package.json`

- [ ] **Step 1: Bump to 1.8.0** — `kwawingu-tours.php` (`Version: 1.8.0` + `KWT_VERSION '1.8.0'`), `includes/Plugin.php` (`const VERSION = '1.8.0'`), `tests/PluginTest.php` (expect `'1.8.0'`), `readme.txt` (`Stable tag: 1.8.0`), `package.json`.

- [ ] **Step 2: Docs** — `docs/booking-modes.md` On-site section: note that a successful on-site booking can email the operator + save a lead in WordPress (Settings), and that guest confirmations are still sent by KwaWingu. `README.md`: add "operator notifications + leads" to the feature list. `readme.txt` changelog:

```
= 1.8.0 =
* Operator notifications: get an email when a guest books on-site through your site, and keep their details as a Lead in WordPress. (Guest confirmations are still sent by KwaWingu.)
```

- [ ] **Step 3: Run full suite + phpcs + build + jest**

Run: `vendor/bin/phpunit` then `vendor/bin/phpcs -q` then `npm run build` then `npm run test:js`
Expected: PASS (PluginTest expects 1.8.0); phpcs 0; build 0 (no diffs — no JS changed); jest green.

- [ ] **Step 4: Commit**

```bash
git add docs/ README.md readme.txt kwawingu-tours.php includes/Plugin.php tests/PluginTest.php package.json
git commit -m "chore(release): operator-notification docs; bump 1.8.0 (v1.8 task 3)"
```

---

## Self-Review

**Spec coverage (v1.8):**
- Operator notification (`wp_mail`, recipient override → admin_email fallback) → Task 2. ✓
- Lead capture (`kwt_lead` CPT + post per booking) → Task 1 (CPT) + Task 2 (capture). ✓
- Settings toggles + admin fields → Task 1. ✓
- No guest emails → enforced (Notifications only mails the operator/admin). ✓
- Wire on create-booking success, best-effort → Task 2. ✓

**Placeholder scan:** No TBD/TODO; complete code each step.

**Type consistency:** `Settings::notifications_enabled/notification_recipient/lead_capture_enabled`; `Cpt::LEAD='kwt_lead'`; `Notifications::on_booking_created(array,array): void`; `Rest_Proxy::__construct(Api_Client, ?Notifications=null)` (backward-compatible with existing 1-arg tests) + the create-booking success hook; meta keys `kwt_lead_email/phone/tour/ref`. All consistent.

**Known simplification:** `Notifications` unit-tested with `wp_mail`/`wp_insert_post` stubbed; real delivery is a wp-env/live concern (v1.9). The create-booking→notify path is covered by the Notifications tests directly; a Rest_Proxy integration assertion is deferred to the wp-env suite.
