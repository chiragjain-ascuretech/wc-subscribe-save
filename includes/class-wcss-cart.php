<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Cart {
    public function init() {
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_meta' ], 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'adjust_cart_item_price' ], 10 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_item_meta' ], 10, 4 );
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
                $discounted = WCSS_Utils::apply_discount_to_amount_static( $base_price, $plan );
                $product->set_price( $discounted );
            }
        }
    }

    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['wcss_is_subscription'] ) && ! empty( $values['wcss_plan'] ) ) {
            $item->add_meta_data( '_wcss_is_subscription', 'yes', true );
            $item->add_meta_data( '_wcss_plan', wp_json_encode( $values['wcss_plan'] ), true );
        }
    }
}


