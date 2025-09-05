<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_CPT {
    public function init() {
        add_action( 'init', [ $this, 'register_subscription_cpt' ], 5 );
    }

    public function register_subscription_cpt() {
        $labels = [
            'name' => 'Subscriptions',
            'singular_name' => 'Subscription',
            'menu_name' => 'S&S Subscriptions',
        ];
        register_post_type( 'wcss_subscription', [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true, // show_ui true so WP_Query can manage posts if needed; we'll hide menu separately
            'show_in_menu' => false,
            'supports' => [ 'title' ],
            'capability_type' => 'post',
            'exclude_from_search' => true,
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'show_in_rest' => false,
            'register_meta_box_cb' => null,
        ] );
    }
}


