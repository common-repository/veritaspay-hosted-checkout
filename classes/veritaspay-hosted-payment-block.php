<?php

namespace VeritasPay\HostedCheckout;

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * PHP version 8
 *
 * @category Plugin
 * @package  VeritasPay
 * @author   VeritasPay
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
final class VeritasPay_Hosted_Payment_Blocks extends AbstractPaymentMethodType {

    private $gateway;

    /** @var string */
    protected $name = 'veritaspay-hosted-payment';// your payment gateway name

    public function initialize() {
        $this->settings = get_option( 'woocommerce_veritaspay-hosted-payment_settings', [] );
        $this->gateway = new VeritasPay_Hosted_Payment();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {

        wp_register_script(
            'veritaspay-hosted-payment-blocks-integration',
            plugin_dir_url(__DIR__) . '/assets/js/blocks-integration.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if( function_exists( 'wp_set_script_translations' ) ) {            
            wp_set_script_translations( 'veritaspay-hosted-payment-blocks-integration');
            
        }
        return [ 'veritaspay-hosted-payment-blocks-integration' ];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
        ];
    }

}
?>