<?php
/**
 * One-click setup wizard: connects, brands, scaffolds pages, and runs first sync.
 *
 * @package KwaWingu\Tours
 */

namespace KwaWingu\Tours;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * One-click setup: connect (in Settings), then auto-brand + scaffold pages + first sync.
 */
class Setup_Wizard {

	const ACTION = 'kwt_setup_scaffold';

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Branding instance.
	 *
	 * @var Branding
	 */
	private $branding;

	/**
	 * Importer instance.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * Sync instance.
	 *
	 * @var Sync
	 */
	private $sync;

	/**
	 * Stores all dependencies.
	 *
	 * @param Settings $settings Plugin settings.
	 * @param Branding $branding Branding handler.
	 * @param Importer $importer Page importer.
	 * @param Sync     $sync     Sync service.
	 */
	public function __construct( Settings $settings, Branding $branding, Importer $importer, Sync $sync ) {
		$this->settings = $settings;
		$this->branding = $branding;
		$this->importer = $importer;
		$this->sync     = $sync;
	}

	/**
	 * Hooks the wizard into the admin menu and form submission actions.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_scaffold' ) );
	}

	/**
	 * Adds the setup wizard submenu page under Settings.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'options-general.php',
			__( 'Set up your tour site', 'kwawingu-tours' ),
			__( 'KwaWingu Setup', 'kwawingu-tours' ),
			'manage_options',
			'kwawingu-setup',
			array( $this, 'render' )
		);
	}

	/**
	 * Renders the setup wizard admin page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$connected = '' !== $this->settings->get_slug() && '' !== $this->settings->get_public_key();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Set up your tour site', 'kwawingu-tours' ); ?></h1>
			<?php if ( ! $connected ) : ?>
				<p><?php echo esc_html__( 'First connect your KwaWingu account under Settings → KwaWingu Tours (operator slug + public API key). API access is a paid add-on on your KwaWingu dashboard.', 'kwawingu-tours' ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html__( 'This will pull your branding, create your starter pages (Home, Tours, About, Contact), set your home page, and import your tours. You can edit everything afterwards.', 'kwawingu-tours' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<?php wp_nonce_field( self::ACTION ); ?>
					<?php submit_button( __( 'Build my site', 'kwawingu-tours' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handles the scaffold form submission: applies branding, imports pages, and syncs tours.
	 */
	public function handle_scaffold(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'kwawingu-tours' ) );
		}
		check_admin_referer( self::ACTION );

		$this->branding->apply();
		$this->importer->run();
		$this->sync->run();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'kwawingu-setup',
					'kwt_done' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		$this->terminate();
	}

	/** Seam so tests can intercept the terminal exit. */
	protected function terminate(): void {
		exit;
	}
}
