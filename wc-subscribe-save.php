<?php
/**
 * Plugin Name: WooCommerce Subscribe & Save (Lite)
 * Description: Add per-product Subscribe & Save plans (multiple intervals with discounts), adjust cart pricing, and create renewal orders via WP-Cron.
 * Author: Your Team
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 */

define( 'WCSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCSS_VERSION', '0.1.0' );

class WCSS_Plugin {
    /** @var WCSS_CPT */
    private $cpt;
    /** @var WCSS_Admin_Product */
    private $admin_product;
    /** @var WCSS_Frontend */
    private $frontend;
    /** @var WCSS_Cart */
    private $cart;
    /** @var WCSS_Orders */
    private $orders;
    /** @var WCSS_Cron */
    private $cron;
    public function init() {
        // Defer bootstrap until all plugins are loaded so WooCommerce is available
        add_action( 'plugins_loaded', [ $this, 'bootstrap' ], 20 );
    }

    public function bootstrap() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-error"><p>WooCommerce Subscribe & Save requires WooCommerce to be active.</p></div>';
            } );
            return;
        }

        // Load includes
        require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-utils.php';
        require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cpt.php';
        require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-admin-product.php';
        require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-frontend.php';
        require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cart.php';
        require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-orders.php';
        require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cron.php';
        require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-admin-subscriptions.php';

        // Initialize modules
        $this->cpt = new WCSS_CPT();
        $this->cpt->init();

        $this->admin_product = new WCSS_Admin_Product();
        $this->admin_product->init();

        $this->frontend = new WCSS_Frontend();
        $this->frontend->init();

        $this->cart = new WCSS_Cart();
        $this->cart->init();

        $this->orders = new WCSS_Orders();
        $this->orders->init();

        $this->cron = new WCSS_Cron();
        $this->cron->init();

        // Initialize admin subscriptions handler
        if ( class_exists( 'WCSS_Admin_Subscriptions' ) ) {
            $admin_subs = new WCSS_Admin_Subscriptions();
            $admin_subs->init();
        }
    }

    public function activate() {
        // Initialize CPT for rewrite rules then schedule cron via cron class
        require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cpt.php';
        ( new WCSS_CPT() )->init();
        // Ensure frontend endpoint is registered so rewrite rules include it
        if ( file_exists( WCSS_PLUGIN_DIR . 'includes/class-wcss-frontend.php' ) ) {
            require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-frontend.php';
            $f = new WCSS_Frontend();
            // Register endpoint (matches init hook registration)
            if ( method_exists( $f, 'add_my_subscriptions_endpoint' ) ) {
                $f->add_my_subscriptions_endpoint();
            }
        }
        flush_rewrite_rules();

        require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cron.php';
        ( new WCSS_Cron() )->activate();
    }

    public function deactivate() {
        require_once WCSS_PLUGIN_DIR . 'includes/class-wcss-cron.php';
        ( new WCSS_Cron() )->deactivate();
        flush_rewrite_rules();
    }

    /* ===== Admin: WooCommerce Product Data Tab ===== */

    public function enqueue_admin_assets( $hook ) {
    if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
    return;
    }
    wp_enqueue_style( 'wcss-admin', WCSS_PLUGIN_URL . 'assets/admin.css', [], WCSS_VERSION );
    wp_enqueue_script( 'wcss-admin', WCSS_PLUGIN_URL . 'assets/admin.js', [ 'jquery' ], WCSS_VERSION, true );
    }

    public function add_product_data_tab( $tabs ) {
    $tabs['wcss_subscribe'] = [
    'label' => 'Subscribe & Save',
    'target' => 'wcss_product_data',
    'class' => [ 'show_if_simple' ],
    'priority' => 70,
    ];
    return $tabs;
    }

    public function render_product_data_panel() {
    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product || ! $product instanceof WC_Product_Simple ) {
    return;
    }
    $plans = get_post_meta( $product->get_id(), '_wcss_plans', true );
    if ( ! is_array( $plans ) ) {
    $plans = [];
    }
    ?>
    <div id="wcss_product_data" class="panel woocommerce_options_panel">
    <div class="options_group wcss-plans-wrapper">
        <p>Define one or more subscription options for this product. Customers can choose One-time or Subscribe & Save.</p>
        <table class="widefat wcss-plans-table">
        <thead>
        <tr>
        <th>Label</th>
        <th>Interval Count</th>
        <th>Interval Unit</th>
        <th>Discount Type</th>
        <th>Discount Value</th>
        <th></th>
        </tr>
        </thead>
        <tbody id="wcss-plans-body">
        <?php if ( empty( $plans ) ) : ?>
        <tr class="wcss-plan-row">
        <td><input type="text" name="wcss_plans[0][label]" value="Every 3 weeks"/></td>
        <td><input type="number" min="1" name="wcss_plans[0][interval_count]" value="3"/></td>
        <td>
            <select name="wcss_plans[0][interval_unit]">
            <option value="day">day</option>
            <option value="week" selected>week</option>
            <option value="month">month</option>
            </select>
        </td>
        <td>
            <select name="wcss_plans[0][discount_type]">
            <option value="percent" selected>percent</option>
            <option value="fixed">fixed</option>
            </select>
        </td>
        <td><input type="number" step="0.01" min="0" name="wcss_plans[0][discount_value]" value="10"/></td>
        <td><button type="button" class="button wcss-remove-plan">Remove</button></td>
        </tr>
        <?php else : ?>
        <?php foreach ( $plans as $idx => $plan ) : ?>
        <tr class="wcss-plan-row">
        <td><input type="text" name="wcss_plans[<?php echo esc_attr( $idx ); ?>][label]" value="<?php echo esc_attr( $plan['label'] ?? '' ); ?>"/></td>
        <td><input type="number" min="1" name="wcss_plans[<?php echo esc_attr( $idx ); ?>][interval_count]" value="<?php echo esc_attr( $plan['interval_count'] ?? 1 ); ?>"/></td>
        <td>
            <select name="wcss_plans[<?php echo esc_attr( $idx ); ?>][interval_unit]">
            <?php $unit = $plan['interval_unit'] ?? 'week'; ?>
            <option value="day" <?php selected( $unit, 'day' ); ?>>day</option>
            <option value="week" <?php selected( $unit, 'week' ); ?>>week</option>
            <option value="month" <?php selected( $unit, 'month' ); ?>>month</option>
            </select>
        </td>
        <td>
            <select name="wcss_plans[<?php echo esc_attr( $idx ); ?>][discount_type]">
            <?php $dtype = $plan['discount_type'] ?? 'percent'; ?>
            <option value="percent" <?php selected( $dtype, 'percent' ); ?>>percent</option>
            <option value="fixed" <?php selected( $dtype, 'fixed' ); ?>>fixed</option>
            </select>
        </td>
        <td><input type="number" step="0.01" min="0" name="wcss_plans[<?php echo esc_attr( $idx ); ?>][discount_value]" value="<?php echo esc_attr( $plan['discount_value'] ?? 0 ); ?>"/></td>
        <td><button type="button" class="button wcss-remove-plan">Remove</button></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        </table>
        <p><button type="button" class="button button-primary" id="wcss-add-plan">Add Plan</button></p>
    </div>
    </div>
    <?php
    }

    public function sanitize_plan( $plan ) {
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

    public function save_product_plans_via_wc( $product ) {
    if ( ! $product instanceof WC_Product_Simple ) {
    return;
    }
    $plans_raw = isset( $_POST['wcss_plans'] ) ? (array) $_POST['wcss_plans'] : [];
    $plans = [];
    if ( is_array( $plans_raw ) ) {
    foreach ( $plans_raw as $plan ) {
        if ( empty( $plan['label'] ) ) { continue; }
        $plans[] = $this->sanitize_plan( $plan );
    }
    }
    update_post_meta( $product->get_id(), '_wcss_plans', $plans );
    }

    /* ===== Frontend: Selector & Cart Integration ===== */
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
        <?php $desc = $this->format_plan_label_for_frontend( $plan, $base_price ); ?>
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

    private function format_plan_label_for_frontend( $plan, $base_price ) {
    $label = $plan['label'] ?: sprintf( 'Every %d %s', (int) $plan['interval_count'], $plan['interval_unit'] );
    $price = $this->apply_discount_to_amount( $base_price, $plan );
    $off = $plan['discount_type'] === 'percent' ? (float) $plan['discount_value'] . '% off' : wc_price( (float) $plan['discount_value'] ) . ' off';
    return sprintf( '%s — %s (%s)', $label, wc_price( $price ), $off );
    }

    private function apply_discount_to_amount( $amount, $plan ) {
    $amount = (float) $amount;
    if ( ( $plan['discount_type'] ?? 'percent' ) === 'percent' ) {
    $amount = max( 0, $amount * ( 1 - ( (float) $plan['discount_value'] / 100 ) ) );
    } else {
    $amount = max( 0, $amount - (float) $plan['discount_value'] );
    }
    return $amount;
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
    $plan = $cart_item['wcss_plan'];
    $product = $cart_item['data'];
    if ( $product && $product instanceof WC_Product ) {
        $base_price = (float) $product->get_price( 'edit' );
        $discounted = $this->apply_discount_to_amount( $base_price, $plan );
        $product->set_price( $discounted );
    }
    }
    }

    /* ===== Order & Subscription Records ===== */
    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['wcss_is_subscription'] ) && ! empty( $values['wcss_plan'] ) ) {
            $item->add_meta_data( '_wcss_is_subscription', 'yes', true );
            $item->add_meta_data( '_wcss_plan', wp_json_encode( $values['wcss_plan'] ), true );
        }
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
            'show_ui' => false,
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
    /**
     * Render subscriptions table with search, pagination, price in plan column, and improved design
     */
    public function render_subscriptions_admin_page() {
        // Handle cancel action
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'cancel' && isset( $_GET['sub_id'] ) ) {
            $sub_id = absint( $_GET['sub_id'] );
            if ( $sub_id ) {
                update_post_meta( $sub_id, '_wcss_status', 'cancelled' );
                echo '<div class="notice notice-success"><p>Subscription cancelled.</p></div>';
            }
        }

        // Search
        $search = isset( $_GET['wcss_search'] ) ? sanitize_text_field( $_GET['wcss_search'] ) : '';
        $paged = isset( $_GET['wcss_page'] ) ? max( 1, absint( $_GET['wcss_page'] ) ) : 1;
        $per_page = 20;
        $args = [
            'post_type' => 'wcss_subscription',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $paged,
        ];
        if ( $search ) {
            $args['s'] = $search;
        }
        $q = new WP_Query( $args );

        echo '<div class="wrap wcss-subscriptions-wrap"><h1>S&S Subscriptions</h1>';
        echo '<form method="get" class="wcss-subscriptions-search">';
        echo '<input type="hidden" name="page" value="wcss_subscriptions" />';
        echo '<input type="text" name="wcss_search" value="' . esc_attr( $search ) . '" placeholder="Search by customer, order, product..." />';
        echo '<button class="button" type="submit">Search</button>';
        echo '</form>';

        echo '<table class="widefat fixed striped wcss-subscriptions-table">';
        echo '<thead><tr>';
        echo '<th>Subscription ID</th><th>Order ID</th><th>Customer</th><th>Product</th><th>Plan</th><th>Status</th><th>Next Renewal</th><th>Action</th>';
        echo '</tr></thead><tbody>';
        if ( $q->have_posts() ) {
            while ( $q->have_posts() ) { $q->the_post();
                $sub_id = get_the_ID();
                $customer_id = get_post_meta( $sub_id, '_wcss_customer_id', true );
                $product_id = get_post_meta( $sub_id, '_wcss_product_id', true );
                $plan = get_post_meta( $sub_id, '_wcss_plan', true );
                $status = get_post_meta( $sub_id, '_wcss_status', true );
                $next_renewal = get_post_meta( $sub_id, '_wcss_next_renewal', true );
                $last_order = get_post_meta( $sub_id, '_wcss_last_order_id', true );
                $product = $product_id ? wc_get_product( $product_id ) : false;
                $plan_label = is_array($plan) ? ($plan['label'] ?? '') : '';
                $plan_price = '';
                if ( $product && is_array($plan) ) {
                    $base_price = (float) $product->get_price( 'edit' );
                    $discounted = $this->apply_discount_to_amount( $base_price, $plan );
                    $plan_price = wc_price( $discounted );
                }
                $next_renewal_str = $next_renewal ? date_i18n( 'Y-m-d', $next_renewal ) : '';
                $customer = $customer_id ? get_userdata( $customer_id ) : false;
                $customer_name = $customer ? $customer->user_login : '';
                $order_link = $last_order ? '<a href="' . esc_url( admin_url( 'post.php?post=' . $last_order . '&action=edit' ) ) . '" target="_blank">' . esc_html( $last_order ) . '</a>' : '';
                echo '<tr style="vertical-align:middle;">';
                echo '<td>' . esc_html( $sub_id ) . '</td>';
                echo '<td>' . $order_link . '</td>';
                echo '<td>' . esc_html( $customer_name ) . '</td>';
                echo '<td>' . esc_html( $product ? $product->get_name() : '' ) . '</td>';
                echo '<td>' . esc_html( $plan_label ) . ($plan_price ? ' <span style="color:#555;font-size:13px;">(' . $plan_price . ')</span>' : '') . '</td>';
                echo '<td>' . esc_html( ucfirst( $status ) ) . '</td>';
                echo '<td>' . esc_html( $next_renewal_str ) . '</td>';
                if ( $status === 'active' ) {
                    $cancel_url = add_query_arg([
                        'page' => 'wcss_subscriptions',
                        'action' => 'cancel',
                        'sub_id' => $sub_id,
                        'wcss_search' => $search,
                        'wcss_page' => $paged,
                    ], admin_url( 'admin.php' ) );
                    echo '<td><a href="' . esc_url( $cancel_url ) . '" class="button" style="background:#e74c3c;color:#fff;border:none;">Cancel</a></td>';
                } else {
                    echo '<td></td>';
                }
                echo '</tr>';
            }
            wp_reset_postdata();
        } else {
            echo '<tr><td colspan="8" style="text-align:center;color:#888;">No subscriptions found.</td></tr>';
        }
        echo '</tbody></table>';

        // Pagination
        $total = $q->found_posts;
        $total_pages = ceil( $total / $per_page );
        if ( $total_pages > 1 ) {
            echo '<div style="margin-top:18px;text-align:right;">';
            for ( $i = 1; $i <= $total_pages; $i++ ) {
                $url = add_query_arg([
                    'page' => 'wcss_subscriptions',
                    'wcss_search' => $search,
                    'wcss_page' => $i,
                ], admin_url( 'admin.php' ) );
                $active = $i === $paged ? 'background:#0073aa;color:#fff;' : 'background:#f7f7f7;color:#0073aa;';
                echo '<a href="' . esc_url( $url ) . '" class="button" style="margin-left:2px;' . $active . 'border-radius:3px;min-width:32px;text-align:center;">' . $i . '</a>';
            }
            echo '</div>';
        }
        echo '</div>';
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
        $next = $this->calculate_next_renewal_timestamp( time(), $plan );

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

        private function calculate_next_renewal_timestamp( $from_ts, $plan ) {
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

        // Price at renewal time with discount
        $base_price = (float) $product->get_price( 'edit' );
        $price = $this->apply_discount_to_amount( $base_price, $plan );

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
        $next = $this->calculate_next_renewal_timestamp( time(), $plan );
        update_post_meta( $sub_id, '_wcss_next_renewal', $next );
    }
    public function admin_hooks() {
        // Enqueue admin assets for product edit screens
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Remove CPT menu if it appears due to caching or other plugins
     */
    public function force_remove_cpt_menu() {
        add_action( 'admin_menu', function() {
            remove_menu_page( 'edit.php?post_type=wcss_subscription' );
            remove_submenu_page( 'edit.php?post_type=wcss_subscription', 'edit.php?post_type=wcss_subscription' );
        }, 99 );
    }
}


$__wcss = new WCSS_Plugin();
$__wcss->init();
$__wcss->admin_hooks();
$__wcss->force_remove_cpt_menu();

register_activation_hook( __FILE__, [ $__wcss, 'activate' ] );
register_deactivation_hook( __FILE__, [ $__wcss, 'deactivate' ] );


