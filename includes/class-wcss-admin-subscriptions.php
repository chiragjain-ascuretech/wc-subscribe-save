<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Admin_Subscriptions {
    public function init() {
        add_action( 'admin_menu', [ $this, 'add_subscriptions_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_subscriptions_admin_css' ] );
    }

    public function enqueue_subscriptions_admin_css() {
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'wcss_subscriptions' ) {
            wp_enqueue_style( 'wcss-admin-subscriptions', WCSS_PLUGIN_URL . 'assets/admin-subscriptions.css', [], WCSS_VERSION );
        }
    }

    public function add_subscriptions_admin_menu() {
        add_menu_page(
            'S&S Subscriptions',
            'S&S Subscriptions',
            'manage_woocommerce',
            'wcss_subscriptions',
            [ $this, 'render_subscriptions_admin_page' ],
            'dashicons-update',
            56
        );
    }

    public function render_subscriptions_admin_page() {
        // Handle cancel action
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'cancel' && isset( $_GET['sub_id'] ) ) {
            $sub_id = absint( $_GET['sub_id'] );
            if ( $sub_id ) {
                update_post_meta( $sub_id, '_wcss_status', 'cancelled' );
                echo '<div class="notice notice-success"><p>Subscription cancelled.</p></div>';
            }
        }

        $search = isset( $_GET['wcss_search'] ) ? sanitize_text_field( $_GET['wcss_search'] ) : '';
        $paged = isset( $_GET['wcss_page'] ) ? max( 1, absint( $_GET['wcss_page'] ) ) : 1;
        $per_page = 20;
        $args = [
            'post_type' => 'wcss_subscription',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $paged,
        ];
        if ( $search ) { $args['s'] = $search; }
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
                    $discounted = WCSS_Utils::apply_discount_to_amount_static( $base_price, $plan );
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
                echo '<td>' . esc_html( $plan_label ) . ($plan_price ? ' <span class="wcss-plan-price">(' . $plan_price . ')</span>' : '') . '</td>';
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
                    echo '<td><a href="' . esc_url( $cancel_url ) . '" class="wcss-cancel-btn">Cancel</a></td>';
                } else {
                    echo '<td></td>';
                }
                echo '</tr>';
            }
            wp_reset_postdata();
        } else {
            echo '<tr><td colspan="8" class="wcss-no-results">No subscriptions found.</td></tr>';
        }
        echo '</tbody></table>';

        // Pagination
        $total = $q->found_posts;
        $total_pages = ceil( $total / $per_page );
        if ( $total_pages > 1 ) {
            echo '<div class="wcss-pagination">';
            for ( $i = 1; $i <= $total_pages; $i++ ) {
                $url = add_query_arg([
                    'page' => 'wcss_subscriptions',
                    'wcss_search' => $search,
                    'wcss_page' => $i,
                ], admin_url( 'admin.php' ) );
                $active = $i === $paged ? 'active' : '';
                echo '<a href="' . esc_url( $url ) . '" class="page-link ' . $active . '">' . $i . '</a>';
            }
            echo '</div>';
        }
    }
}
