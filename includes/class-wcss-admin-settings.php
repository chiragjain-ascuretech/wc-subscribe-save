<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Admin_Settings {

    private $option_name = 'wcss_settings';

    public function init() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_menu() {
        // parent slug should match the subscriptions page slug
        add_submenu_page(
            'wcss_subscriptions',
            __( 'General Settings', 'wcss' ),
            __( 'General Settings', 'wcss' ),
            'manage_options',
            'wcss_settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'wcss_settings_group', $this->option_name, [ $this, 'sanitize_settings' ] );

        add_settings_section(
            'wcss_settings_main',
            __( 'S&S General Settings', 'wcss' ),
            function() { echo '<p>' . esc_html__( 'General options for Subscribe & Save.', 'wcss' ) . '</p>'; },
            'wcss_settings'
        );

        add_settings_field(
            'enabled',
            __( 'Enable subscriptions', 'wcss' ),
            [ $this, 'field_enabled_cb' ],
            'wcss_settings',
            'wcss_settings_main'
        );

        add_settings_field(
            'purchase_heading',
            __( 'Product page heading', 'wcss' ),
            [ $this, 'field_purchase_heading_cb' ],
            'wcss_settings',
            'wcss_settings_main'
        );

        add_settings_field(
            'one_time_text',
            __( 'One-time label (prefix)', 'wcss' ),
            [ $this, 'field_one_time_text_cb' ],
            'wcss_settings',
            'wcss_settings_main'
        );

        add_settings_field(
            'subscribe_text',
            __( 'Subscribe label', 'wcss' ),
            [ $this, 'field_subscribe_text_cb' ],
            'wcss_settings',
            'wcss_settings_main'
        );

        add_settings_field(
            'add_to_cart_text',
            __( 'Add to cart button text', 'wcss' ),
            [ $this, 'field_add_to_cart_text_cb' ],
            'wcss_settings',
            'wcss_settings_main'
        );
    }

    public function sanitize_settings( $input ) {
        $out = [];
        $out['enabled'] = isset( $input['enabled'] ) && $input['enabled'] ? 1 : 0;
        $out['purchase_heading'] = isset( $input['purchase_heading'] ) ? sanitize_text_field( $input['purchase_heading'] ) : 'Purchase Options';
        $out['one_time_text'] = isset( $input['one_time_text'] ) ? sanitize_text_field( $input['one_time_text'] ) : 'One-time purchase —';
        $out['subscribe_text'] = isset( $input['subscribe_text'] ) ? sanitize_text_field( $input['subscribe_text'] ) : 'Subscribe & Save';
        $out['add_to_cart_text'] = isset( $input['add_to_cart_text'] ) ? sanitize_text_field( $input['add_to_cart_text'] ) : 'Add to cart';
        return $out;
    }

    public function get_settings() {
        $defaults = [
            'enabled' => 1,
            'purchase_heading' => 'Purchase Options',
            'one_time_text' => 'One-time purchase —',
            'subscribe_text' => 'Subscribe & Save',
            'add_to_cart_text' => 'Add to cart',
        ];
        $stored = get_option( $this->option_name, [] );
        return wp_parse_args( $stored, $defaults );
    }

    public function field_enabled_cb() {
        $s = $this->get_settings();
        printf(
            '<label><input type="checkbox" name="%1$s[enabled]" value="1" %2$s /> %3$s</label>',
            esc_attr( $this->option_name ),
            checked( 1, $s['enabled'], false ),
            esc_html__( 'Enable Subscribe & Save features', 'wcss' )
        );
    }

    public function field_purchase_heading_cb() {
        $s = $this->get_settings();
        printf(
            '<input type="text" name="%1$s[purchase_heading]" value="%2$s" class="regular-text" />',
            esc_attr( $this->option_name ),
            esc_attr( $s['purchase_heading'] )
        );
    }

    public function field_one_time_text_cb() {
        $s = $this->get_settings();
        printf(
            '<input type="text" name="%1$s[one_time_text]" value="%2$s" class="regular-text" />',
            esc_attr( $this->option_name ),
            esc_attr( $s['one_time_text'] )
        );
    }

    public function field_subscribe_text_cb() {
        $s = $this->get_settings();
        printf(
            '<input type="text" name="%1$s[subscribe_text]" value="%2$s" class="regular-text" />',
            esc_attr( $this->option_name ),
            esc_attr( $s['subscribe_text'] )
        );
    }

    public function field_add_to_cart_text_cb() {
        $s = $this->get_settings();
        printf(
            '<input type="text" name="%1$s[add_to_cart_text]" value="%2$s" class="regular-text" />',
            esc_attr( $this->option_name ),
            esc_attr( $s['add_to_cart_text'] )
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'wcss' ) );
        }
        ?>
        <div class="wrap wcss-settings-wrap">
            <h1><?php esc_html_e( 'S&S General Settings', 'wcss' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wcss_settings_group' );
                do_settings_sections( 'wcss_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}