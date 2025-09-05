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

        // Load settings from admin (option: wcss_settings)
        $stored = get_option( 'wcss_settings', [] );
        $settings = wp_parse_args( $stored, [
            'enabled'           => 1,
            'purchase_heading'  => 'Purchase Options',
            'one_time_text'     => 'One-time purchase',
            'subscribe_text'    => 'Subscribe & Save',
            'add_to_cart_text'  => 'Add to cart',
        ] );

        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        $plans = get_post_meta( $product->get_id(), '_wcss_plans', true );
        if ( empty( $plans ) || ! is_array( $plans ) ) {
            return;
        }

        $base_price = (float) wc_get_price_to_display( $product );
        ?>
        <div class="wcss-selector">
            <p><strong><?php echo esc_html( $settings['purchase_heading'] ); ?></strong></p>
            <label style="display:block;margin-bottom:6px;">
                <input type="radio" name="wcss_purchase_type" value="one_time" checked />
                <?php echo esc_html( $settings['one_time_text'] ); ?> — <?php echo wc_price( $base_price ); ?>
            </label>
            <label style="display:block;margin-bottom:6px;">
                <input type="radio" name="wcss_purchase_type" value="subscribe" />
                <?php echo esc_html( $settings['subscribe_text'] ); ?>
            </label>
            <div id="wcss-plans-select" style="display:none;margin-top:8px;">
                <select name="wcss_selected_plan" style="min-width:260px;">
                    <?php foreach ( $plans as $idx => $plan ) :
                        $desc = WCSS_Utils::format_plan_label_for_frontend( $plan, $base_price );
                    ?>
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

            // Update add-to-cart button text if provided in settings
            var newLabel = <?php echo wp_json_encode( $settings['add_to_cart_text'] ); ?>;
            var btn = document.querySelector('.single_add_to_cart_button, button.single_add_to_cart_button');
            if (btn && newLabel) { btn.textContent = newLabel; }
        })();
        </script>
        <?php
    }

    public function render_my_subscriptions_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please log in to view your subscriptions.', 'wcss' ) . '</p>';
        }
        $user_id = get_current_user_id();

        // Process cancellation
        $message = '';
        if ( isset( $_POST['wcss_cancel_sub'] ) && isset( $_POST['wcss_sub_id'] ) ) {
            $sub_id = absint( $_POST['wcss_sub_id'] );
            $nonce = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
            if ( wp_verify_nonce( $nonce, 'wcss_cancel_sub_' . $sub_id ) ) {
                $owner = (int) get_post_meta( $sub_id, '_wcss_customer_id', true );
                $status = get_post_meta( $sub_id, '_wcss_status', true );
                if ( $owner === $user_id && $status === 'active' ) {
                    update_post_meta( $sub_id, '_wcss_status', 'cancelled' );
                    $message = '<div class="wcss-message wcss-message-success">' . esc_html__( 'Subscription cancelled.', 'wcss' ) . '</div>';
                } else {
                    $message = '<div class="wcss-message wcss-message-error">' . esc_html__( 'Unable to cancel subscription.', 'wcss' ) . '</div>';
                }
            } else {
                $message = '<div class="wcss-message wcss-message-error">' . esc_html__( 'Invalid request.', 'wcss' ) . '</div>';
            }
        }

        // Fetch subscriptions for the user (newest first)
        $q = new WP_Query( [
            'post_type'      => 'wcss_subscription',
            'post_status'    => 'publish',
            'meta_query'     => [ [ 'key' => '_wcss_customer_id', 'value' => $user_id ] ],
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        if ( ! $q->have_posts() ) {
            return $message . '<p>' . esc_html__( 'You have no subscriptions.', 'wcss' ) . '</p>';
        }

        // Determine latest active subscription ID (first in date-desc list with status 'active')
        $latest_active = null;
        foreach ( $q->posts as $p ) {
            $pid = (int) $p->ID;
            $p_status = get_post_meta( $pid, '_wcss_status', true );
            if ( 'active' === $p_status ) {
                $latest_active = $pid;
                break;
            }
        }

        ob_start();
        echo $message;
        ?>
        <table class="wcss-subscriptions-table-front" style="width:100%;border-collapse:collapse;margin-bottom:18px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'wcss' ); ?></th>
                    <th><?php esc_html_e( 'Product', 'wcss' ); ?></th>
                    <th><?php esc_html_e( 'Plan', 'wcss' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'wcss' ); ?></th>
                    <th><?php esc_html_e( 'Next Renewal', 'wcss' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'wcss' ); ?></th>
                </tr>
            </thead>
            <tbody>
        <?php
        foreach ( $q->posts as $p ) {
            $sid = (int) $p->ID;
            $product_id = (int) get_post_meta( $sid, '_wcss_product_id', true );
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
            ?>
            <tr style="border-top:1px solid #e5e5e5;">
                <td>#<?php echo esc_html( $sid ); ?></td>
                <td><?php echo esc_html( $product ? $product->get_name() : '' ); ?></td>
                <td><?php echo esc_html( $plan_label ); ?><?php echo $plan_price ? ' <small>(' . wp_kses_post( $plan_price ) . ')</small>' : ''; ?></td>
                <td><?php echo esc_html( ucfirst( $status ) ); ?></td>
                <td><?php echo $next ? esc_html( date_i18n( 'Y-m-d', (int) $next ) ) : ''; ?></td>
                <td>
                    <?php if ( 'active' === $status ) : 
                        echo '<form method="post" style="display:inline;">';
                        echo wp_nonce_field( 'wcss_cancel_sub_' . $sid, '_wpnonce', true, false );
                        echo '<input type="hidden" name="wcss_sub_id" value="' . esc_attr( $sid ) . '" />';
                        echo '<button type="submit" name="wcss_cancel_sub" class="button">' . esc_html__( 'Cancel', 'wcss' ) . '</button>';
                        echo '</form>';
                    else :
                        echo '&nbsp;';
                    endif; ?>
                </td>
            </tr>
            <?php
        }
        ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
}