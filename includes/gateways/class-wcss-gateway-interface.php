<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Gateway adapter interface
 */
interface WCSS_Gateway_Interface {
    /**
     * Attempt to charge a saved token for a customer.
     * @param WC_Payment_Token $token
     * @param float $amount
     * @param WC_Order $order
     * @return array ['success' => bool, 'transaction_id' => '', 'message' => '']
     */
    public function charge_token( $token, $amount, $order );
}