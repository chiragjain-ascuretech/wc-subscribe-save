<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/class-wcss-gateway-interface.php';

class WCSS_Gateway_Stripe implements WCSS_Gateway_Interface {

    /**
     * Charge a Stripe saved token (off-session). Requires stripe-php installed and secret key available.
     *
     * @param WC_Payment_Token $token
     * @param float $amount
     * @param WC_Order $order
     * @return array
     */
    public function charge_token( $token, $amount, $order ) {
        // Basic checks
        if ( ! $token || ! is_callable( [ $token, 'get_token' ] ) ) {
            return [ 'success' => false, 'message' => 'Invalid token object' ];
        }

        // Token string (may be a Stripe payment_method id, card id, pm_... etc)
        $token_str = $token->get_token();

        // Find Stripe secret key from common plugin options (attempts multiple keys used by plugins)
        $stripe_options = (array) get_option( 'woocommerce_stripe_settings', [] );
        $secret = $stripe_options['secret_key'] ?? $stripe_options['test_secret_key'] ?? '';

        // Fallback: try WooCommerce Payments option keys
        if ( empty( $secret ) ) {
            $wc_pay_opts = (array) get_option( 'woocommerce_payments_settings', [] );
            $secret = $wc_pay_opts['secret_key'] ?? $wc_pay_opts['test_secret_key'] ?? '';
        }

        if ( empty( $secret ) ) {
            return [ 'success' => false, 'message' => 'Stripe secret key not found in settings. Configure or provide in environment.' ];
        }

        if ( ! class_exists( '\Stripe\StripeClient' ) ) {
            return [ 'success' => false, 'message' => 'Stripe PHP SDK (stripe/stripe-php) not available.' ];
        }

        try {
            $client = new \Stripe\StripeClient( $secret );

            // Create PaymentIntent and confirm immediately off-session using saved payment_method token
            $currency = strtolower( get_woocommerce_currency() );
            $amount_cents = (int) round( $amount * 100 );

            $params = [
                'amount' => $amount_cents,
                'currency' => $currency,
                'payment_method' => $token_str,
                'confirmation_method' => 'automatic',
                'confirm' => true,
                'off_session' => true,
                'metadata' => [
                    'wcss_order_id' => $order ? $order->get_id() : '',
                    'wcss_subscription' => '1',
                ],
            ];

            $pi = $client->paymentIntents->create( $params );

            if ( in_array( $pi->status, [ 'succeeded', 'requires_capture' ], true ) ) {
                $tx = $pi->charges->data[0]->id ?? ( $pi->id ?? '' );
                return [
                    'success' => true,
                    'transaction_id' => $tx,
                    'message' => 'Charge succeeded via Stripe.',
                    'raw' => $pi,
                ];
            }

            // other statuses may require action (3DS) — treat as failure for off-session
            return [
                'success' => false,
                'message' => 'Payment requires additional action or did not succeed: ' . ( $pi->status ?? 'unknown' ),
                'raw' => $pi,
            ];
        } catch ( \Exception $e ) {
            return [ 'success' => false, 'message' => 'Stripe error: ' . $e->getMessage() ];
        }
    }
}