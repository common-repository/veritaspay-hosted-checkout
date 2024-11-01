<?php

/**
 * Plugin Name: VeritasPay Hosted Checkout
 * Plugin URI: https://wordpress.org/plugins/veritaspay-hosted-checkout/
 * Description: Take credit card, GCash, GrabPay, BPI, ecPAY, Alipay, and UnionPay
 * Version: 1.0.10
 * Author: VeritasPay Philippines Inc.
 * Author URI: https://veritaspay.com/
 * Text Domain: wc-veritaspay-payment-gateway
 * Domain Path: /i18n/languages/
 * Requires at least: 6.1.1
 * Tested up to: 6.6.2
 * WC Required at least: 5.7.2
 * WC tested up to: 9.3.3
 *
 * @category Plugin
 * @package VeritasPay Philippines Inc.
 * @author VeritasPay Philippines Inc.
 * @license GPLv3 (https://www.gnu.org/licenses/gpl-3.0.html)
 * @link n/a
 */
 
use VeritasPay\HostedCheckout\VeritasPay_Hosted_Payment_Blocks;
// use VeritasPay\HostedCheckout\VeritasPay_Hosted_Payment;

if (!defined('ABSPATH')) {
    exit;
}

const TABLE_NAME = "veritaspay_payment_transaction";

/**
 * WooCommerce notice
 *
 * @return string
 */
function Woocommerce_Missing_notice()
{
    echo '<div class="error"><p><strong>' . sprintf(
        esc_html__(
            'VeritasPay requires WooCommerce to be '
            . 'installed and active. You can download %s here.',
            'woocommerce-gateway-plugin-veritaspay'
        ),
        '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
    ) . '</strong></p></div>';
}

function VeritasPay_Gateway_Plugin_Init_class()
{

    // The plugin won't be activated if Woocommerce plugin is still not yet activated.
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'Woocommerce_Missing_notice');
        return;
    }

    define('VERITASPAY_GATEWAY_PLUGIN_MAIN_FILE', __FILE__);
    define(
        'VERITASPAY_GATEWAY_PLUGIN_URL',
        untrailingslashit(
            plugins_url(
                basename(plugin_dir_path(__FILE__)),
                basename(__FILE__)
            )
        )
    );

    if (!class_exists('VeritasPay_Gateway')) :

        class VeritasPay_Gateway
        {
            private static $_instance;

            public static function getInstance()
            {
                if (null === self::$_instance) {
                    self::$_instance = new self();
                }

                return self::$_instance;
            }

            /**
             * To prevent cloning the method
             *
             * @return void
             */
            private function __clone()
            {
                // empty
            }

            /**
             * To prevent unserializing the Singleton
             *
             * @return void
             */
            public function __wakeup()
            {
                // empty
            }

            private function __construct()
            {
                add_action('admin_init', [$this, 'install']);
                $this->init();
            }

            /**
             * Initialize the plugin
             */
            public function init()
            {
                $fileDir = dirname(__FILE__);
                require_once $fileDir . '/classes/veritaspay-hosted-payment.php';
                require_once $fileDir . '/classes/veritaspay-hosted-payment-block.php';
                require_once $fileDir . '/classes/configuration-settings/veritaspay-api-settings.php';
                require_once $fileDir . '/classes/configuration-settings/veritaspay-hosted-payment-settings.php';

                add_filter(
                    'woocommerce_payment_gateways',
                    array($this, 'addGateways')
                );

                // Hook the custom function to the 'before_woocommerce_init' action
                add_action('before_woocommerce_init', 'veritaspay_declare_cart_checkout_blocks_compatibility');

                /**
                 * Custom function to declare compatibility with cart_checkout_blocks feature 
                */
                function veritaspay_declare_cart_checkout_blocks_compatibility() {
                    // Check if the required class exists
                    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                        // Declare compatibility for 'cart_checkout_blocks'
                        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
                    }
                }

                // Hook the custom function to the 'woocommerce_blocks_loaded' action
                add_action( 'woocommerce_blocks_loaded', 'veritaspay_register_order_approval_payment_method_type' );

                /**
                 * Custom function to register a payment method type
                 */
                function veritaspay_register_order_approval_payment_method_type() {
                    // Check if the required class exists
                    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
                        return;
                    }

                    // Include the custom Blocks Checkout class
                    // require_once plugin_dir_path(__FILE__) . 'classes/veritaspay-hosted-payment-block.php';
                    
                    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
                    add_action(
                        'woocommerce_blocks_payment_method_type_registration',
                        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                            // Register an instance of My_Custom_Gateway_Blocks
                            $payment_method_registry->register( new VeritasPay_Hosted_Payment_Blocks );
                        }
                    );
                }

                if (version_compare(WC_VERSION, '3.4', '<')) {
                    add_filter(
                        'woocommerce_get_sections_checkout',
                        array($this, 'filterGatewayOrderAdmin')
                    );
                }

                add_action('init', 'wpdocs_load_textdomain');

                function wpdocs_load_textdomain()
                {
                    load_plugin_textdomain(
                        'wpdocs_textdomain',
                        false,
                        dirname(plugin_basename(__FILE__)) . '/i18n/languages'
                    );
                }
            }

            /**
             * Registers Payment Gateways
             *
             * @return array
             */
            public function addGateways($methods)
            {
                $methods[] = 'VeritasPay\HostedCheckout\VeritasPay_Hosted_Payment';
                return $methods;
            }

            /**
             * Registers Payment Gateways
             *
             * @return array
             */
            public function filterGatewayOrderAdmin($sections)
            {
                unset($sections['veritaspay_gateway_credit']);

                $gatewayName = 'woocommerce-gateway-plugin-veritaspay';
                $sections['veritaspay-hosted-payment'] = __(
                    'Credit/Debit Card via VeritasPay Gateway',
                    $gatewayName
                );

                return $sections;
            }

            public function install()
            {
                if (!is_plugin_active(plugin_basename(__FILE__))) {
                    return;
                }
            }
        }

        VeritasPay_Gateway::getInstance();
    endif;
}

add_action('plugins_loaded', 'veritaspay_gateway_plugin_init_class');

// Function to add the settings link
function vpi_hosted_checkout_setting($links) {
    // Your custom button URL
    $custom_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=veritaspay-hosted-payment'); // Replace with the URL you want the button to link to

    // Your custom button label
    $custom_button = '<a href="' . esc_url($custom_url) . '">Settings</a>';

    // Add the custom button to the action links array
    array_unshift($links, $custom_button);

    return $links;
}

// Add settings link
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'vpi_hosted_checkout_setting');

// Activation and Deactivation hook
function vpi_hosted_create_plugin_table_install()
{
    global $charset_collate;
    $table_name = TABLE_NAME;
    $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
      `transaction_id` bigint(20) NOT NULL AUTO_INCREMENT,
      `customer_id` int(12) NOT NULL,
      `order_id` varchar(32) NOT NULL,
      `vpi_request` longtext NULL,
      `vpi_response` longtext NULL,
      `vpi_callback_response` text NULL,
      `vpi_virtual_item` TINYINT(1) NOT NULL DEFAULT 0,
       PRIMARY KEY (`transaction_id`)
    )$charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'vpi_hosted_create_plugin_table_install');

function vpi_hosted_delete_plugin_database_table()
{
    global $wpdb;
    $table_name = TABLE_NAME;
    $sql = "DROP TABLE IF EXISTS `$table_name`";
    $wpdb->query($sql);
}

register_uninstall_hook(__FILE__, 'vpi_hosted_delete_plugin_database_table');

function vpi_hosted_display_database_update_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Check if the database update is required
    if (vpi_hosted_needs_database_update()) {
        // Display the update notice
        ?>
        <div class="notice notice-info" style="padding-left: 15px;">
            <p>
                <strong>VeritasPay Hosted Checkout</strong> requires a database update. Click the button below to update the database:
            </p>
            <form method="post">
                <?php wp_nonce_field('vpi_hosted_update_db_nonce', 'vpi_hosted_update_db_nonce'); ?>
                <input type="hidden" name="vpi_hosted_db_update" value="1">
                <input type="submit" class="button button-primary" value="Update Database">
            </form>
            <p>
                
            </p>
        </div>
        <?php
    }
}

add_action('admin_notices', 'vpi_hosted_display_database_update_notice');

function vpi_hosted_needs_database_update()
{
    global $wpdb;
    $table_name = TABLE_NAME;

    // Define the expected columns and their data types
    $expected_columns = array(
        'transaction_id',
        'customer_id',
        'order_id',
        'vpi_request',
        'vpi_response',
        'vpi_callback_response',
        'vpi_virtual_item'
        // Add more columns here as needed
    );

    // Get the current columns from the database
    $current_columns = $wpdb->get_results("DESCRIBE $table_name", ARRAY_A);
    
        // Compare the current columns with the expected columns
    foreach ($current_columns as $column_name) {
        $columnsArray[] = $column_name['Field'];
    }
    
    $difference = array_diff($expected_columns, $columnsArray);

    if (empty($difference)) {
        return false;
    }

    return true;
}

function vpi_hosted_handle_database_update()
{
    if (isset($_POST['vpi_hosted_db_update']) && check_admin_referer('vpi_hosted_update_db_nonce', 'vpi_hosted_update_db_nonce')) {
        
        // Handle the database update
        vpi_hosted_alter_plugin_table();

        // Display a success message
        add_action('admin_notices', 'vpi_hosted_display_database_update_success_notice');

        // Remove the transient when the database update is completed
        delete_transient('vpi_hosted_display_database_update_notice');
    }
}

// Hook into the plugin loaded action to handle the database update
add_action('plugins_loaded', 'vpi_hosted_handle_database_update');

function vpi_hosted_alter_plugin_table()
{
    global $wpdb;
    $table_name = TABLE_NAME;
    $alter_query = "ALTER TABLE `$table_name` ADD COLUMN `vpi_virtual_item` TINYINT(1) NOT NULL DEFAULT 0 AFTER `vpi_callback_response`";
    $wpdb->query($alter_query);
}

function vpi_hosted_display_database_update_success_notice()
{
    ?>
    <div class="notice notice-success is-dismissible">
        <p>The database has been updated successfully!</p>
    </div>
    <?php
}