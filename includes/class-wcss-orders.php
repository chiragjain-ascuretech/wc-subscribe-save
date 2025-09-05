<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Orders {
 public function init() {
  add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_item_meta' ], 10, 4 );
  add_action( 'woocommerce_thankyou', [ $this, 'create_subscription_record' ], 10, 1 );
  add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hide_item_meta' ] );
  // Ensure readable display in admin for legacy orders too
  if ( is_admin() ) {
   add_action( 'woocommerce_after_order_itemmeta', [ $this, 'render_admin_subscription_row' ], 10, 3 );
  }
 }

 public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
  if ( ! empty( $values['wcss_is_subscription'] ) && ! empty( $values['wcss_plan'] ) ) {
   $item->add_meta_data( '_wcss_is_subscription', 'yes', true );
   $item->add_meta_data( '_wcss_plan', wp_json_encode( $values['wcss_plan'] ), true );

   // Add human-readable meta for admin views and emails
   $plan = $values['wcss_plan'];
   $label = ! empty( $plan['label'] ) ? $plan['label'] : sprintf( 'Every %d %s', (int) $plan['interval_count'], $plan['interval_unit'] );
   $off = ( $plan['discount_type'] ?? 'percent' ) === 'percent'
    ? sprintf( '%s%% off', (float) ( $plan['discount_value'] ?? 0 ) )
    : sprintf( '%s off', wc_price( (float) ( $plan['discount_value'] ?? 0 ) ) );
   $display = sprintf( '%s (%s)', $label, $off );
   $item->add_meta_data( 'Subscription', $display, true );
  }
 }

 public function hide_item_meta( $hidden ) {
  $hidden[] = '_wcss_plan';
  $hidden[] = '_wcss_is_subscription';
  return $hidden;
 }

 public function render_admin_subscription_row( $item_id, $item, $product ) {
  if ( $item->is_type( 'line_item' ) ) {
   $plan_json = $item->get_meta( '_wcss_plan', true );
   $already = $item->get_meta( 'Subscription', true );
   if ( empty( $already ) && ! empty( $plan_json ) ) {
    $plan = json_decode( (string) $plan_json, true );
    if ( is_array( $plan ) ) {
     $label = ! empty( $plan['label'] ) ? $plan['label'] : sprintf( 'Every %d %s', (int) ( $plan['interval_count'] ?? 1 ), $plan['interval_unit'] ?? 'week' );
     $off = ( $plan['discount_type'] ?? 'percent' ) === 'percent'
      ? sprintf( '%s%% off', (float) ( $plan['discount_value'] ?? 0 ) )
      : sprintf( '%s off', wc_price( (float) ( $plan['discount_value'] ?? 0 ) ) );
     echo '<div class="wc-order-item-meta"><p><strong>Subscription:</strong> ' . esc_html( sprintf( '%s (%s)', $label, $off ) ) . '</p></div>';
    }
   }
  }
 }

 public function create_subscription_record( $order_id ) {
  $order = wc_get_order( $order_id );
  if ( ! $order ) { return; }
  foreach ( $order->get_items() as $item_id => $item ) {
   $flag = $item->get_meta( '_wcss_is_subscription', true );
   $plan_json = $item->get_meta( '_wcss_plan', true );
   if ( 'yes' !== $flag || empty( $plan_json ) ) { continue; }
   $plan = json_decode( (string) $plan_json, true );
   if ( empty( $plan ) ) { continue; }

   $product_id = $item->get_product_id();
   $customer_id = $order->get_user_id();
   $next = WCSS_Utils::calculate_next_renewal_timestamp( time(), $plan );

   $sub_id = wp_insert_post( [
    'post_type' => 'wcss_subscription',
    'post_status' => 'publish',
    'post_title' => sprintf( 'Sub #%d — Order #%d — %s', $product_id, $order_id, $plan['label'] ?? '' ),
   ] );
   if ( $sub_id && ! is_wp_error( $sub_id ) ) {
    update_post_meta( $sub_id, '_wcss_customer_id', (int) $customer_id );
    update_post_meta( $sub_id, '_wcss_product_id', (int) $product_id );
    update_post_meta( $sub_id, '_wcss_plan', $plan );
    update_post_meta( $sub_id, '_wcss_last_order_id', (int) $order_id );
    update_post_meta( $sub_id, '_wcss_next_renewal', (int) $next );
    update_post_meta( $sub_id, '_wcss_status', 'active' );
   }
  }
 }
}


