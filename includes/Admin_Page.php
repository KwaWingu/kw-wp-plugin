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
