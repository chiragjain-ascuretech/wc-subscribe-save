<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Cart {
 public function init() {
  add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );
  add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_meta' ], 10, 2 );
  // Run as late as possible so no other logic overwrites our discounted price during recalculations (payment method changes, Blocks, etc.)
  add_action( 'woocommerce_before_calculate_totals', [ $this, 'adjust_cart_item_price' ], 100000 );
  add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'restore_cart_item_from_session' ], 10, 3 );
  // Also set prices immediately after the cart is loaded from session (prevents gateway JS refresh from changing totals)
  add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'apply_locked_prices_from_session' ], 999 );
 }

 public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
  $purchase_type = isset( $_POST['wcss_purchase_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wcss_purchase_type'] ) ) : 'one_time';
  if ( 'subscribe' !== $purchase_type ) {
   return $cart_item_data;
  }
  $plans = get_post_meta( $product_id, '_wcss_plans', true );
  $selected_idx = isset( $_POST['wcss_selected_plan'] ) ? absint( $_POST['wcss_selected_plan'] ) : 0;
  if ( ! isset( $plans[ $selected_idx ] ) ) {
   return $cart_item_data;
  }
  $plan = $plans[ $selected_idx ];
  $cart_item_data['wcss_plan'] = $plan;
  $cart_item_data['wcss_is_subscription'] = true;

  // Lock unit price at add-to-cart time to avoid later recalculation drift
  $product = wc_get_product( $variation_id ? $variation_id : $product_id );
  if ( $product ) {
   $base_price = (float) $product->get_price( 'edit' );
   $unit = WCSS_Utils::apply_discount_to_amount( $base_price, $plan );
   $cart_item_data['wcss_unit_price'] = wc_format_decimal( $unit, wc_get_price_decimals() );
  }
  return $cart_item_data;
 }

 public function display_cart_item_meta( $item_data, $cart_item ) {
  if ( ! empty( $cart_item['wcss_is_subscription'] ) && ! empty( $cart_item['wcss_plan'] ) ) {
   $plan = $cart_item['wcss_plan'];
   $item_data[] = [
    'key' => 'Subscription',
    'value' => sprintf( 'Every %d %s', (int) $plan['interval_count'], sanitize_text_field( $plan['interval_unit'] ) ),
   ];
  }
  return $item_data;
 }

 public function adjust_cart_item_price( $cart ) {
  if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
   return;
  }
  if ( empty( $cart ) ) {
   return;
  }
  foreach ( $cart->get_cart() as $cart_item ) {
   if ( empty( $cart_item['wcss_is_subscription'] ) || empty( $cart_item['wcss_plan'] ) ) {
    continue;
   }
   $product = $cart_item['data'];
   if ( $product && $product instanceof WC_Product ) {
    if ( isset( $cart_item['wcss_unit_price'] ) ) {
     $product->set_price( (float) $cart_item['wcss_unit_price'] );
    } else {
     $plan = $cart_item['wcss_plan'];
     $base_price = (float) $product->get_price( 'edit' );
     $discounted = WCSS_Utils::apply_discount_to_amount( $base_price, $plan );
     $product->set_price( $discounted );
    }
   }
  }
 }

 public function restore_cart_item_from_session( $cart_item, $values, $key ) {
  if ( isset( $values['wcss_is_subscription'] ) ) {
   $cart_item['wcss_is_subscription'] = $values['wcss_is_subscription'];
  }
  if ( isset( $values['wcss_plan'] ) ) {
   $cart_item['wcss_plan'] = $values['wcss_plan'];
  }
  if ( isset( $values['wcss_unit_price'] ) ) {
   $cart_item['wcss_unit_price'] = $values['wcss_unit_price'];
  }
  return $cart_item;
 }

 public function apply_locked_prices_from_session( $cart ) {
  if ( empty( $cart ) ) { return; }
  foreach ( $cart->get_cart() as $item_key => $cart_item ) {
   if ( empty( $cart_item['wcss_is_subscription'] ) ) { continue; }
   if ( isset( $cart_item['wcss_unit_price'] ) && $cart_item['data'] instanceof WC_Product ) {
    $cart_item['data']->set_price( (float) $cart_item['wcss_unit_price'] );
   }
  }
 }
}


