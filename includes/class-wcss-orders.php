<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Orders {
    public function init() {
        add_action( 'woocommerce_thankyou', [ $this, 'create_subscription_record' ], 20 );
        add_action( 'wcss_run_due_renewals', [ $this, 'run_due_renewals' ] );
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
                'post_type'   => 'wcss_subscription',
                'post_status' => 'publish',
                'post_title'  => sprintf( 'Sub #%d — Order #%d — %s', $product_id, $order_id, $plan['label'] ?? '' ),
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
            'post_type'      => 'wcss_subscription',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'meta_query'     => [
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
        $price = WCSS_Utils::apply_discount_to_amount_static( $base_price, $plan );

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
}


