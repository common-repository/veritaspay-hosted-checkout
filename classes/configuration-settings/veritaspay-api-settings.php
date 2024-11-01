<?php

namespace VeritasPay\HostedCheckout;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Veritaspay_Configuration_Settings {
     /**
     * @var string|null
     */
    protected $liveEcomAPI;

    /**
     * @var string|null
     */
    protected $liveMerchantId;

    /**
     * @var string|null
     */
    protected $liveSecretKey;

    /**
     * @var string|null
     */
    protected $testEcomAPI;

     /**
     * @var string|null
     */
    protected $testSecretKey;

    /**
     * @var string|null
     */
    protected $testMerchantId;

    /**
     * boolean
     */
    protected $testMode;
    
    /**
     * boolean
     */
    protected $completeStatVirtualItem;

    private function register($function)
    {
        add_filter(
            'woocommerce_get_settings_checkout',
            [$this, $function],
            10,
            2
        );
    }

    abstract protected function veritaspay_settings($settings, $current_section);

    /**
     * Veritaspay_Configuration_Settings constructor.
     *
     * @param $function
     */
    public function __construct($function)
    {
        $this->register($function);
    }
}
