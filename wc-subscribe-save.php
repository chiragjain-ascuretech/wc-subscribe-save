<?php
/**
 * Plugin Name: WooCommerce Subscribe & Save (Lite)
 * Description: Add per-product Subscribe & Save plans (multiple intervals with discounts), adjust cart pricing, and create renewal orders via WP-Cron.
 * Author: Your Team
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WCSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCSS_VERSION', '0.1.0' );

// Core utilities
require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-utils.php';

// Module files (will be created/overwritten by the following patches)
require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cpt.php';
require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-admin-product.php';
require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-frontend.php';
require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cart.php';
require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-orders.php';
require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cron.php';
require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-admin-subscriptions.php';
require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-admin-settings.php';

// Instantiate modules
$cpt = new WCSS_CPT();
$cpt->init();

$admin_product = new WCSS_Admin_Product();
$admin_product->init();

$frontend = new WCSS_Frontend();
$frontend->init();

$cart = new WCSS_Cart();
$cart->init();

$orders = new WCSS_Orders();
$orders->init();

$cron = new WCSS_Cron();
$cron->init();

$admin_subs = new WCSS_Admin_Subscriptions();
$admin_subs->init();

$admin_settings = new WCSS_Admin_Settings();
$admin_settings->init();

// Activation / Deactivation hooks
register_activation_hook( __FILE__, function() {
    // Ensure CPT and endpoint exist for rewrite rules
    require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cpt.php';
    $cpt = new WCSS_CPT();
    $cpt->register_subscription_cpt();

    require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-frontend.php';
    $f = new WCSS_Frontend();
    if ( method_exists( $f, 'add_my_subscriptions_endpoint' ) ) {
        $f->add_my_subscriptions_endpoint();
    }

    flush_rewrite_rules();

    require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cron.php';
    ( new WCSS_Cron() )->activate();
} );

register_deactivation_hook( __FILE__, function() {
    require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cron.php';
    ( new WCSS_Cron() )->deactivate();
    flush_rewrite_rules();
} );


