<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Frontend {
 public function init() {
  add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_product_plan_selector' ] );
  add_shortcode( 'wcss_my_subscriptions', [ $this, 'render_my_subscriptions_shortcode' ] );

  // My Account endpoint/hooks
  add_action( 'init', [ $this, 'add_my_subscriptions_endpoint' ] );
  add_filter( 'query_vars', [ $this, 'add_my_subscriptions_query_var' ] );
  add_filter( 'woocommerce_account_menu_items', [ $this, 'add_my_subscriptions_menu_item' ] );
  add_action( 'woocommerce_account_wcss-subscriptions_endpoint', [ $this, 'output_my_subscriptions_endpoint' ] );
 }

 public function add_my_subscriptions_endpoint() {
  add_rewrite_endpoint( 'wcss-subscriptions', EP_ROOT | EP_PAGES );
 }

 public function add_my_subscriptions_query_var( $vars ) {
  $vars[] = 'wcss-subscriptions';
  return $vars;
 }

 public function add_my_subscriptions_menu_item( $items ) {
  // Insert the menu item after Orders if present, otherwise append
  $new = [];
  foreach ( $items as $key => $label ) {
   $new[ $key ] = $label;
   if ( 'orders' === $key ) {
    $new['wcss-subscriptions'] = __( 'Subscriptions', 'wcss' );
   }
  }
  if ( ! isset( $new['wcss-subscriptions'] ) ) {
   $new['wcss-subscriptions'] = __( 'Subscriptions', 'wcss' );
  }
  return $new;
 }

 public function output_my_subscriptions_endpoint() {
  echo do_shortcode( '[wcss_my_subscriptions]' );
 }

 public function render_product_plan_selector() {
  global $product;
  if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
   return;
  }
  $plans = get_post_meta( $product->get_id(), '_wcss_plans', true );
  if ( empty( $plans ) || ! is_array( $plans ) ) {
   return;
  }
  $base_price = (float) wc_get_price_to_display( $product );
  ?>
  <div class="wcss-selector">
   <p><strong>Purchase Options</strong></p>
   <label style="display:block;margin-bottom:6px;">
    <input type="radio" name="wcss_purchase_type" value="one_time" checked />
    One-time purchase — <?php echo wc_price( $base_price ); ?>
   </label>
   <label style="display:block;margin-bottom:6px;">
    <input type="radio" name="wcss_purchase_type" value="subscribe" />
    Subscribe & Save
   </label>
   <div id="wcss-plans-select" style="display:none;margin-top:8px;">
    <select name="wcss_selected_plan" style="min-width:260px;">
     <?php foreach ( $plans as $idx => $plan ) : ?>
     <?php $desc = WCSS_Utils::format_plan_label_for_frontend( $plan, $base_price ); ?>
     <option value="<?php echo esc_attr( $idx ); ?>"><?php echo esc_html( $desc ); ?></option>
     <?php endforeach; ?>
    </select>
   </div>
  </div>
  <script>
  (function(){
   function toggle(){
    var subscribe = document.querySelector('input[name="wcss_purchase_type"][value="subscribe"]');
    var wrap = document.getElementById('wcss-plans-select');
    if (subscribe && subscribe.checked) { wrap.style.display = 'block'; } else { wrap.style.display = 'none'; }
   }
   document.querySelectorAll('input[name="wcss_purchase_type"]').forEach(function(el){ el.addEventListener('change', toggle); });
   toggle();
  })();
  </script>
  <?php
 }

 public function render_my_subscriptions_shortcode( $atts ) {
  if ( ! is_user_logged_in() ) {
   return '<p>Please log in to view your subscriptions.</p>';
  }
  $user_id = get_current_user_id();

  // Process cancellation
  if ( isset( $_POST['wcss_cancel_sub'] ) && isset( $_POST['wcss_sub_id'] ) ) {
   $sub_id = absint( $_POST['wcss_sub_id'] );
   $nonce = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
   if ( wp_verify_nonce( $nonce, 'wcss_cancel_sub_' . $sub_id ) ) {
    $owner = (int) get_post_meta( $sub_id, '_wcss_customer_id', true );
    $status = get_post_meta( $sub_id, '_wcss_status', true );
    if ( $owner === $user_id && $status === 'active' ) {
     update_post_meta( $sub_id, '_wcss_status', 'cancelled' );
     $message = '<div class="wcss-message">Subscription cancelled.</div>';
    } else {
     $message = '<div class="wcss-message wcss-message-error">Unable to cancel subscription.</div>';
    }
   } else {
    $message = '<div class="wcss-message wcss-message-error">Invalid request.</div>';
   }
  } else { $message = ''; }

  $q = new WP_Query( [
   'post_type' => 'wcss_subscription',
   'post_status' => 'publish',
   'meta_query' => [ [ 'key' => '_wcss_customer_id', 'value' => $user_id ] ],
   'posts_per_page' => -1,
   'orderby' => 'date',
   'order' => 'DESC',
  ] );

  if ( ! $q->have_posts() ) { return $message . '<p>You have no subscriptions.</p>'; }

  $latest_active = null;
  foreach ( $q->posts as $p ) { if ( get_post_meta( $p->ID, '_wcss_status', true ) === 'active' ) { $latest_active = $p->ID; break; } }

  ob_start();
  echo $message;
  echo '<table class="wcss-subscriptions-table-front" style="width:100%;border-collapse:collapse;margin-bottom:18px;">';
  echo '<thead><tr><th>Subscription</th><th>Product</th><th>Plan</th><th>Status</th><th>Next Renewal</th><th>Action</th></tr></thead>';
  echo '<tbody>';
  foreach ( $q->posts as $p ) {
   $sid = $p->ID;
   $product_id = get_post_meta( $sid, '_wcss_product_id', true );
   $plan = get_post_meta( $sid, '_wcss_plan', true );
   $status = get_post_meta( $sid, '_wcss_status', true );
   $next = get_post_meta( $sid, '_wcss_next_renewal', true );
   $product = $product_id ? wc_get_product( $product_id ) : false;
   $plan_label = is_array( $plan ) ? ( $plan['label'] ?? '' ) : '';
   $plan_price = '';
   if ( $product && is_array( $plan ) ) {
    $base = (float) $product->get_price( 'edit' );
    $plan_price = wc_price( WCSS_Utils::apply_discount_to_amount_static( $base, $plan ) );
   }
   echo '<tr style="border-top:1px solid #e5e5e5;">';
   echo '<td>#' . esc_html( $sid ) . '</td>';
   echo '<td>' . esc_html( $product ? $product->get_name() : '' ) . '</td>';
   echo '<td>' . esc_html( $plan_label ) . ( $plan_price ? ' <small>(' . $plan_price . ')</small>' : '' ) . '</td>';
   echo '<td>' . esc_html( ucfirst( $status ) ) . '</td>';
   echo '<td>' . ( $next ? esc_html( date_i18n( 'Y-m-d', $next ) ) : '' ) . '</td>';
   echo '<td>';
   if ( $status === 'active' && $sid === $latest_active ) {
    echo '<form method="post" style="display:inline">' . wp_nonce_field( 'wcss_cancel_sub_' . $sid, '_wpnonce', true, false ) . '<input type="hidden" name="wcss_sub_id" value="' . esc_attr( $sid ) . '" /> <button type="submit" name="wcss_cancel_sub" class="button">Cancel</button></form>';
   } else { echo '&nbsp;'; }
   echo '</td>';
   echo '</tr>';
  }
  echo '</tbody></table>';
  return ob_get_clean();
 }
}


