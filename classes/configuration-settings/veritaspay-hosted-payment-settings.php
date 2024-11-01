<?php

namespace VeritasPay\HostedCheckout;

class VeritasPay_Hosted_Payment_Settings extends Veritaspay_Configuration_Settings
{
    /** @var Singleton */
    private static $instance;

    /**
     * Returns the *Singleton* instance of this class
     *
     * @return Singleton Instance
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        parent::__construct('veritaspay_settings');

        // Initialize
        // Live Environment
        $this->liveEcomAPI = 'veritaspay_hosted_live_api_url';
        $this->liveMerchantId = 'veritaspay_hosted_live_secret_key';
        $this->liveSecretKey = 'veritaspay_hosted_live_merchant_id';

        // Test Mode
        $this->testMode = 'veritaspay_hosted_test_mode';

        // Test Environment
        $this->testEcomAPI = 'veritaspay_hosted_test_api_url';
        $this->testSecretKey = 'veritaspay_hosted_test_secret_key';
        $this->testMerchantId = 'veritaspay_hosted_test_merchant_id';
        
        // Other Options
        $this->completeStatVirtualItem = 'veritaspay_autocomplete_virtual_item';
    }

    public function veritaspay_settings($settings, $current_section)
    {
        if ($current_section === 'veritaspay-hosted-payment') {

            return $this->veritaspayHostedPaymentConfiguration(
                $this->testMode,
                $this->liveEcomAPI,
                $this->liveMerchantId,
                $this->liveSecretKey,
                $this->testEcomAPI,
                $this->testSecretKey,
                $this->testMerchantId,
                $this->completeStatVirtualItem
            );
        } else {
            return $settings;
        }
    }

    /**
     * VeritasPay Hosted Checkout Payment Config
     *
     * @param $testMode
     * @param $liveEcomAPI
     * @param $liveSecretKey
     * @param $liveMerchantId
     * @param $testEcomAPI
     * @param $testSecretKey
     * @param $testMerchantId
     * @param $completeStatVirtualItem
     * @return array
     */
    public function veritaspayHostedPaymentConfiguration(
        $testMode,
        $liveEcomAPI,
        $liveSecretKey,
        $liveMerchantId,
        $testEcomAPI,
        $testSecretKey,
        $testMerchantId,
        $completeStatVirtualItem
    ) {
        return [
            [
                'name' => 'API Settings',
                'id' => 'veritaspay_api_settings_title',
                'type' => 'title',
                'desc' => 'Veritaspay API settings'
            ],
            [
                'id' => $liveEcomAPI,
                'title' => 'Live API URL',
                'type' => 'text'
            ],
            [
                'id' => $liveSecretKey,
                'title' => 'Live Secret Key',
                'type' => 'password'
            ],
            [
                'id' => $liveMerchantId,
                'title' => 'Live Merchant ID',
                'type' => 'text'
            ],
            [
                'id' => 'live_env_end',
                'type' => 'sectionend'
            ],
            [
                'id' => 'test_env',
                'title' => 'Test Environment',
                'type' => 'title',
                'desc' => 'Use the plugin in <b>Test Mode</b><br/>In test mode, 
                            you can transact using the Veritaspay payment methods in checkout without actual payments'
            ],
            [
                'id' => $testMode,
                'title'       => 'Test mode',
                'label'       => 'Enable Test Mode',
                'type'        => 'checkbox',
                'desc' => 'Place the payment gateway in test mode using <b>Test API keys</b>',
                'default'     => 'yes'
            ],
            [
                'id' => $testEcomAPI,
                'title' => 'Test API URL',
                'type' => 'text'
            ],
            [
                'id' => $testSecretKey,
                'title' => 'Test Secret Key',
                'type' => 'password'
            ],
            [
                'id' => $testMerchantId,
                'title' => 'Test Merchant ID',
                'type' => 'text'
            ],
            [
                'id' => 'veritaspay_test_env_end',
                'type' => 'sectionend',
            ],
            [
                'id' => 'other_options',
                'title' => 'Other Options',
                'type' => 'title',
                'desc' => ''
            ],
            [
                'id' => $completeStatVirtualItem,
                'title' => 'Virtual Item',
                'type'        => 'checkbox',
                'desc' => '<b>Auto complete order status after payment</b>',
                'default'     => 'false'
            ],
            [
                'id' => 'veritaspay_additional_options_end',
                'type' => 'sectionend',
            ],
            [
                'id' => 'veritaspay_api_settings',
                'type' => 'sectionend'
            ]
        ];
    }
}

VeritasPay_Hosted_Payment_Settings::getInstance();