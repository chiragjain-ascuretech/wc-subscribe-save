<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Gateway_Manager {
    /**
     * Return adapter instance for a WC_Payment_Token
     * @param WC_Payment_Token $token
     * @return WCSS_Gateway_Interface|null
     */
    public static function get_adapter_for_token( $token ) {
        if ( ! $token || ! method_exists( $token, 'get_gateway_id' ) ) {
            return null;
        }

        $gateway = $token->get_gateway_id();

        switch ( $gateway ) {
            case 'stripe':
            case 'stripe_gateway':
            case 'stripe_wc_gateway':
                require_once WCSS_PLUGIN_DIR . 'includes/gateways/class-wcss-gateway-stripe.php';
                return new WCSS_Gateway_Stripe();

            case 'paypal':
            case 'paypal_express':
            case 'ppcpaypal':
            case 'ppec_paypal':
                require_once WCSS_PLUGIN_DIR . 'includes/gateways/class-wcss-gateway-paypal.php';
                return new WCSS_Gateway_Paypal();

            default:
                return null;
        }
    }
}