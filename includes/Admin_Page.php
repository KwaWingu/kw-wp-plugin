<?php
/**
 * Settings → KwaWingu Tours admin screen.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Settings → KwaWingu Tours admin screen.
 */
class Admin_Page {

	/**
	 * Plugin settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Sync controller instance.
	 *
	 * @var Sync_Controller
	 */
	private $controller;

	/**
	 * Constructor.
	 *
	 * @param Settings        $settings   Plugin settings instance.
	 * @param Sync_Controller $controller Sync controller instance.
	 */
	public function __construct( Settings $settings, Sync_Controller $controller ) {
		$this->settings   = $settings;
		$this->controller = $controller;
	}

	/**
	 * Registers admin menu hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Adds the plugin options page under the Settings menu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_options_page(
			__( 'KwaWingu Tours', 'kwawingu-tours' ),
			__( 'KwaWingu Tours', 'kwawingu-tours' ),
			'manage_options',
			'kwawingu-tours',
			array( $this, 'render' )
		);
	}

	/**
	 * Renders the admin settings page HTML.
	 *
	 * @return void
	 */
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
					<tr>
						<th scope="row"><label for="kwt_private_key"><?php echo esc_html__( 'Private API key (on-site booking only)', 'kwawingu-tours' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( $opt ); ?>[private_key]" id="kwt_private_key" type="password" class="regular-text" value="<?php echo esc_attr( $this->settings->get_private_key() ); ?>" autocomplete="off" />
							<p class="description"><?php echo esc_html__( 'Only needed for on-site booking. Stored server-side, never shown on your website.', 'kwawingu-tours' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		$status = get_option( \KwaWingu\Tours\Sync_Controller::STATUS_OPT, array() );
		?>
		<hr />
		<h2><?php echo esc_html__( 'Catalog sync', 'kwawingu-tours' ); ?></h2>
		<?php if ( ! empty( $status['ran_at'] ) ) : ?>
			<p>
				<?php
				echo esc_html(
					sprintf(
					/* translators: 1: created, 2: updated, 3: unpublished */
						__( 'Last sync — created %1$d, updated %2$d, unpublished %3$d.', 'kwawingu-tours' ),
						(int) ( $status['created'] ?? 0 ),
						(int) ( $status['updated'] ?? 0 ),
						(int) ( $status['unpublished'] ?? 0 )
					)
				);
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
		<?php
	}
}
