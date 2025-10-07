<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/class-wcss-gateway-interface.php';

class WCSS_Gateway_Paypal implements WCSS_Gateway_Interface {
    /**
     * Attempt to charge a PayPal vaulted agreement / token.
     *
     * NOTE: PayPal integration varies (vaulted Billing Agreements, PayPal Orders API, Braintree, etc).
     * Implement using the PayPal SDK or gateway plugin APIs and saved token format.
     *
     * @param WC_Payment_Token $token
     * @param float $amount
     * @param WC_Order $order
     * @return array
     */
    public function charge_token( $token, $amount, $order ) {
        // TODO: implement PayPal capture using your chosen PayPal integration / SDK
        return [
            'success' => false,
            'message' => 'PayPal charging not implemented. Implement with PayPal SDK or gateway plugin API.',
        ];
    }
}