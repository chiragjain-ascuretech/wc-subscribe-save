<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_CPT {
 public function init() {
  add_action( 'init', [ $this, 'register_subscription_cpt' ] );
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
   'show_ui' => true,
   'show_in_menu' => true,
   'supports' => [ 'title' ],
   'capability_type' => 'post',
   'menu_position' => 56,
   'menu_icon' => 'dashicons-update',
  ] );
 }
}


