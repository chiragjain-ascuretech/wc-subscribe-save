<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Cron {
 public function init() {
  add_action( 'wcss_run_renewals', [ $this, 'run_due_renewals' ] );
 }

 public function activate() {
  if ( ! wp_next_scheduled( 'wcss_run_renewals' ) ) {
   wp_schedule_event( time() + 300, 'hourly', 'wcss_run_renewals' );
  }
 }

 public function deactivate() {
  $timestamp = wp_next_scheduled( 'wcss_run_renewals' );
  if ( $timestamp ) {
   wp_unschedule_event( $timestamp, 'wcss_run_renewals' );
  }
 }

 public function run_due_renewals() {
  $now = time();
  $q = new WP_Query( [
   'post_type' => 'wcss_subscription',
   'post_status' => 'publish',
   'posts_per_page' => 50,
   'meta_query' => [
    'relation' => 'AND',
    [ 'key' => '_wcss_status', 'value' => 'active' ],
    [ 'key' => '_wcss_next_renewal', 'value' => $now, 'compare' => '<=', 'type' => 'NUMERIC' ],
   ],
  ] );
  if ( $q->have_posts() ) {
   while ( $q->have_posts() ) { $q->the_post();
    $sub_id = get_the_ID();
    $this->create_renewal_order_for_subscription( $sub_id );
   }
   wp_reset_postdata();
  }
 }

 private function create_renewal_order_for_subscription( $sub_id ) {
  $customer_id = (int) get_post_meta( $sub_id, '_wcss_customer_id', true );
  $product_id = (int) get_post_meta( $sub_id, '_wcss_product_id', true );
  $plan = get_post_meta( $sub_id, '_wcss_plan', true );
  if ( empty( $product_id ) || empty( $plan ) ) { return; }

  $product = wc_get_product( $product_id );
  if ( ! $product ) { return; }

  $base_price = (float) $product->get_price( 'edit' );
  $price = WCSS_Utils::apply_discount_to_amount( $base_price, $plan );

  $order = wc_create_order( [ 'customer_id' => $customer_id ] );
  $order->add_product( $product, 1, [
   'totals' => [ 'subtotal' => $price, 'total' => $price ],
   'name' => $product->get_name(),
  ] );

  foreach ( $order->get_items() as $item ) {
   $item->add_meta_data( '_wcss_is_subscription', 'yes', true );
   $item->add_meta_data( '_wcss_plan', wp_json_encode( $plan ), true );
  }
  $order->calculate_totals();
  $order->set_status( 'pending' );
  $order->save();

  update_post_meta( $sub_id, '_wcss_last_order_id', $order->get_id() );
  $next = WCSS_Utils::calculate_next_renewal_timestamp( time(), $plan );
  update_post_meta( $sub_id, '_wcss_next_renewal', $next );
 }
}


