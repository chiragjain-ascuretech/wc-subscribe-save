<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Utils {
 public static function sanitize_plan( $plan ) {
  $label = sanitize_text_field( $plan['label'] ?? '' );
  $interval_count = max( 1, absint( $plan['interval_count'] ?? 1 ) );
  $interval_unit = in_array( $plan['interval_unit'] ?? 'week', [ 'day', 'week', 'month' ], true ) ? $plan['interval_unit'] : 'week';
  $discount_type = in_array( $plan['discount_type'] ?? 'percent', [ 'percent', 'fixed' ], true ) ? $plan['discount_type'] : 'percent';
  $discount_value = max( 0, floatval( $plan['discount_value'] ?? 0 ) );
  return [
   'label' => $label,
   'interval_count' => $interval_count,
   'interval_unit' => $interval_unit,
   'discount_type' => $discount_type,
   'discount_value' => $discount_value,
  ];
 }

 public static function apply_discount_to_amount( $amount, $plan ) {
  $amount = (float) $amount;
  if ( ( $plan['discount_type'] ?? 'percent' ) === 'percent' ) {
   $amount = max( 0, $amount * ( 1 - ( (float) $plan['discount_value'] / 100 ) ) );
  } else {
   $amount = max( 0, $amount - (float) $plan['discount_value'] );
  }
  return $amount;
 }

 public static function apply_discount_to_amount_static( $amount, $plan ) {
  $amount = (float) $amount;
  if ( ( $plan['discount_type'] ?? 'percent' ) === 'percent' ) {
   $amount = max( 0, $amount * ( 1 - ( (float) $plan['discount_value'] / 100 ) ) );
  } else {
   $amount = max( 0, $amount - (float) $plan['discount_value'] );
  }
  return $amount;
 }

 public static function format_plan_label_for_frontend( $plan, $base_price ) {
  $label = ! empty( $plan['label'] ) ? $plan['label'] : sprintf( 'Every %d %s', (int) ( $plan['interval_count'] ?? 1 ), $plan['interval_unit'] ?? 'week' );
  $price = self::apply_discount_to_amount( $base_price, $plan );
  // Strip HTML from wc_price so it is safe for <option> text
  $price_text = wp_strip_all_tags( wc_price( $price ), true );
  $off_text = ( $plan['discount_type'] ?? 'percent' ) === 'percent'
   ? sprintf( '%s%% off', (float) ( $plan['discount_value'] ?? 0 ) )
   : sprintf( '%s off', wp_strip_all_tags( wc_price( (float) ( $plan['discount_value'] ?? 0 ) ), true ) );
  return sprintf( '%s — %s (%s)', $label, $price_text, $off_text );
 }

 public static function calculate_next_renewal_timestamp( $from_ts, $plan ) {
  $interval = max( 1, (int) ( $plan['interval_count'] ?? 1 ) );
  $unit = $plan['interval_unit'] ?? 'week';
  $dt = new DateTime( '@' . $from_ts );
  $dt->setTimezone( wp_timezone() );
  switch ( $unit ) {
   case 'day':
    $dt->modify( '+' . $interval . ' day' );
    break;
   case 'month':
    $dt->modify( '+' . $interval . ' month' );
    break;
   case 'week':
   default:
    $dt->modify( '+' . $interval . ' week' );
    break;
  }
  return $dt->getTimestamp();
 }
}


