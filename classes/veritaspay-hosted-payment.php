<?php

namespace VeritasPay\HostedCheckout;

if (!defined('ABSPATH')) {
    exit;
}

use WC_Payment_Gateway;
use WC_Logger;

/**
 * PHP version 8
 *
 * @category Plugin
 * @package  VeritasPay
 * @author   VeritasPay
 * @license  n/a (http://127.0.0.0)
 * @link     n/a
 */
class VeritasPay_Hosted_Payment extends WC_Payment_Gateway
{
    /**
     * Singleton
     *
     * @var Singleton
     */
    private static $_instance;

    /** @var string */
    private $ecomURL;

    /** @var string */
    private $secretKey;

    /** @var string */
    private $merchantId;

    /** @var boolean */
    private $testMode;
    
    /** @var boolean */
    private $completeStatVirtualItem;

    /** Define the Logger Property */
    private $logger;

    // Prefix
    private const PAYMENT_TRANSACTION_ID_PREFIX = "DEV-";

    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * VeritasPay_Hosted_Payment constructor.
     */
    public function __construct()
    {
        $this->id = 'veritaspay-hosted-payment';
        $this->has_fields = false;
        $this->method_title = 'VeritasPay Hosted Checkout';
        $this->method_description = 'Simple and easy payments with credit card,
                                        GCash, GrabPay, BPI, ecPAY, Alipay, and UnionPay';
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Global configuration
        $test = get_option('veritaspay_hosted_test_mode');
        $this->testMode = (!empty($test) && $test === 'yes') ? true : false;

        $ecom = $this->testMode ? 'veritaspay_hosted_test_api_url' : 'veritaspay_hosted_live_api_url';
        $this->ecomURL = get_option($ecom);

        $secretK = $this->testMode ? 'veritaspay_hosted_test_secret_key' : 'veritaspay_hosted_live_secret_key';
        $this->secretKey = get_option($secretK);

        $merch = $this->testMode ? 'veritaspay_hosted_test_merchant_id' : 'veritaspay_hosted_live_merchant_id';
        $this->merchantId = get_option($merch);
        
        // Other Options
        $virtualItem = get_option('veritaspay_autocomplete_virtual_item');
        $this->completeStatVirtualItem = (!empty($virtualItem) && $virtualItem === 'yes') ? true : false;

        // Initialize Logger
        $this->logger = new WC_Logger();

        add_action('wp_enqueue_scripts', [$this, 'paymentScripts']);

        add_action('woocommerce_api_' . $this->id, array($this, 'check_veritas_response'));
	
		// Hook for updating the form fields
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Payment Gateway Settings Page Fields
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'label' => 'Enable Veritaspay Gateway',
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no'
            ),
            'title' => array(
                'type' => 'text',
                'title' => 'Title',
                'description' => 'This controls the title that ' .
                    'the user sees during checkout.',
                'default' => 'Hosted Checkout via Veritaspay Gateway',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'This controls the description that ' .
                    'the user sees during checkout.',
                'default' => 'Simple and easy payments.',
            )
        );
    }

    /**
     * Payment Scripts
     *
     * @return void
     */
    public function paymentScripts()
    {
        $isCheckout = is_checkout() && !is_checkout_pay_page();
        $isOrderPay = is_checkout_pay_page();

        // we need JavaScript to process a token only on cart/checkout pages, right?
        if (!$isCheckout && !$isOrderPay) {
            return;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled) {
            return;
        }

        $unsecure = !$this->testMode && !is_ssl();

        // if unsecure and in live mode, show warning
        if ($unsecure) {
            wc_add_notice('WARNING: This website is not secured to transact using Veritaspay Gateway.', 'error');
        }
    }

    /**
     * Process Payment
     *
     * @param $orderId
     * @return array
     */
    public function process_payment($orderId)
    {
        global $wpdb;

        $tableName = "veritaspay_payment_transaction";

        // Get the order details by orderId
        $order = wc_get_order($orderId);
        
        $isVirtualItem = 0;

        // Get item quantity
        if ($this->completeStatVirtualItem) {
            
            // Get items inside the order 
            $order_items = $order->get_items();
            
            // Check if there is virtual items
            foreach ( $order_items as $item ) {
                $product = $item->get_product();
                $productArray[] = $product->is_virtual();
            }
            
            // Check if there is virtual items
            $allElements = true;
            $isVirtual = in_array(true, $productArray);
            
            // Confirm if it is only virtual items
            if ($isVirtual) {
                $firstElement = reset($productArray);
                foreach ($productArray as $element) {
                    if ($element !== $firstElement) {
                        $allElements = false;
                    }
                }
            }
            
            if ($allElements && $isVirtual) {
                $isVirtualItem = 1;
            }
        }
        
        // Get item quantity
        $itemQuantity = $this->vpi_hosted_get_item_quantity($order->get_items());

        $customerId = null;
        $customerName = null;
        $email = null;

        // Condition for guest user and users
        if ($order->get_customer_id() === 0) {
            $customerId = $order->get_customer_id();
            $customerName = $order->get_billing_first_name();
            $email = $order->get_billing_email();
        } else {
            $user = wp_get_current_user();
            $customerId = $user->ID;
            $customerName = $user->user_firstname ? $user->user_firstname : "";
            $email = $user->user_email ? $user->user_email : "";
        }

        $orderId = $order->get_order_number();

        // Get the total amount
        $total = $order->get_total();

        // Find the last inserted transaction record
        $currentPayment = $wpdb->get_results(
            "SELECT `transaction_id` FROM {$tableName} ORDER BY transaction_id DESC LIMIT 1"
        );

        // Construct prefix based in the domain name
        $prefix = uniqid(strtoupper(str_replace("_", "-", $wpdb->prefix))) . "-";

        // Create payment transaction id | concat order id 2023 - 10 - 05 Prince
        $paymentTransactionId = $this->vpi_hosted_construct_payment_transaction_id($currentPayment, $prefix) . "|" . $orderId;

        // Merchant Order ID
        $merchantOrderId = self::PAYMENT_TRANSACTION_ID_PREFIX . $orderId;

        // App Reference Number
        $appReferenceNo = $orderId;

        // Return URL
        $returnUrl = $returnUrl = $this->get_return_url($order);

        // Callback URL
        $callbackUrl = $this->vpi_hosted_get_redirect_url($orderId);

        // Initialize an array to store item names
        $item_names = array();
        
        // Get the order items
        $order_items = $order->get_items();
        
        // Loop through the order items and store their names in the array
        foreach ($order_items as $item) {
            $product = $item->get_product();
            $item_names[] = $product->get_name();
        }

        // Product Description - Implode the item names into a comma-separated string
        $productDescription = "Items: " . $this->vpi_hosted_character_limit(implode(', ', $item_names), 20);
        
        // Remarks
        $remarks = "Order ID: " . $orderId;

        // array of request data
        $requestData = $this->vpi_hosted_construct_request_data(
            $this->merchantId,
            $appReferenceNo,
            $paymentTransactionId,
            $total,
            $returnUrl,
            $callbackUrl,
            $itemQuantity,
            $customerName,
            $email,
            $productDescription,
            $remarks
        );

        // Log Request
        $this->logger->log('info', 'Request Log ' . json_encode($requestData));

        // Call API method
        $result = $this->vpi_hosted_call_api($this->ecomURL, $requestData);

        // Log Response
        $this->logger->log('info', 'Response Log ' . $result);

        // Decode response
        $jsonResult = json_decode($result);

        // insert new payment transaction
        $insertQuery = $this->vpi_hosted_insert_payment_transaction(
            $wpdb,
            $customerId,
            $merchantOrderId,
            $requestData,
            $result,
            $isVirtualItem,
            $tableName
        );

        // Validate if query was successfully inserted
        if (!$insertQuery) {
            wc_add_notice('Payment failed. Please update payment gateway.', 'error');
        } elseif (strcasecmp($jsonResult->status, "Successful") === 0) {
            return [
                'result' => 'success',
                'redirect' => $jsonResult->data->redirectUrl
            ];
        } else {
            wc_add_notice('Payment failed or declined. Please try again.', 'error');
        }
    }

    /**
     * This method handles the callback response
     */
    public function check_veritas_response()
    {
        global $wpdb;

        // Construct table name
        $tableName = "veritaspay_payment_transaction";

        // Get file contents
        $data = file_get_contents('php://input');

        // Decode
        $jsonDecode = json_decode($data);

        // Get the transaction id from MerchantOrderId param
        $transactionId = $this->vpi_hosted_split_string($jsonDecode->MerchantOrderID, true);

        // Log Callback
        $this->logger->log('info', 'Callback Log ' . $data);

        // Update transaction
        $this->vpi_hosted_update_transaction($wpdb, $transactionId, $data, $tableName);

        // Fetch transaction
        $transaction = $this->vpi_hosted_fetch_transact($wpdb, $transactionId, 'order_id', $tableName);
        
        // Fetch virtual item
        $isVirtual = $this->vpi_hosted_fetch_transact($wpdb, $transactionId, 'vpi_virtual_item', $tableName);
        
        // Convert array and object to int
        $isVirtualtoInt = intval($isVirtual[0]->vpi_virtual_item);
        
        // Get Order ID
        $order = $this->vpi_hosted_get_order_id($transaction);
        $paymentTransactionId = $this->vpi_hosted_split_string($order, false);

        // Get Order Id prefix
        $orderIdPrefix = substr($paymentTransactionId, 0, 3);

        // Set default value for orderId variable
        $orderId = $paymentTransactionId;

        // Check if order id has prefix of SMJ
        if ($orderIdPrefix === "SMJ") {
            $orderId = (int)substr($orderId, 3);
        }
        
        // Order Status
        $status = $jsonDecode->Status;

        $virtualItem = "";
        if ($isVirtualtoInt === 1) {
            $virtualItem = "completeVirtualItem";
        }
        
        // Update order status
        $this->vpi_hosted_update_order_status($orderId, $status, $virtualItem);

        // EXIT
        exit;
    }

    /**
     * Splits a string
     *
     * @param string $string
     * @param bool $filter
     * @return string
     */
    private function vpi_hosted_split_string(string $string, bool $filter): string
    {
        // Added Explode to Separate Merchant Order ID and WooCommerce Order Id
        $string = explode('|', $string)[0];

        $splittedString = null;
        if ($filter) {
            $splittedString = mb_split('-', $string)[2];
        } else {
            $splittedString = mb_split('-', $string)[1];
        }
        return $splittedString;
    }

    /**
     * Updates a transaction by transactionId
     *
     * @param $wpdb
     * @param string $transactionId
     * @param $response
     * @param string $tableName
     */
    private function vpi_hosted_update_transaction($wpdb, string $transactionId, $response, string $tableName)
    {
        $wpdb->update($tableName, ["vpi_callback_response" => $response], ["transaction_id" => $transactionId]);
    }

    /**
     * Fetches a specific transaction by transction id
     *
     * @param $wpdb
     * @param int $transactionId
     * @param string $columnName
     * @param string $tableName
     * @return mixed
     */
    private function vpi_hosted_fetch_transact($wpdb, int $transactionId, string $columnName, string $tableName)
    {
        $query = "SELECT {$columnName} FROM {$tableName} WHERE transaction_id = " . $transactionId;
        return $wpdb->get_results($query);
    }
    
    /**
     * Gets the Order Id
     *
     * @param array $transaction
     * @return mixed
     */
    private function vpi_hosted_get_order_id(array $transaction)
    {
        foreach ($transaction as $transact) {
            $orderId = $transact->order_id;
        }

        return $orderId;
    }

    /**
     * Updates a certain order status by orderId
     */
    private function vpi_hosted_update_order_status(string $orderId, string $status, string $isVirtualItem)
    {
        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        } elseif ($order->status === "processing" || $order->status === "completed") {
		    return;
	    }

        /** Status Codes: Pending = 1, Paid = 2, Cancelled = 3, Expired = 4, Failed = 5 */
        if ($status === "1") {
            $order->set_status("wc-pending");
        } elseif ($status === "2") {
            /**
             * Temporary commented the update status in completed because
             * it is a good practice to manually complete the order if the item already delivered to the customer
             */
            // $order->update_status("completed");
            if ($isVirtualItem === "completeVirtualItem") {
                $order->set_status("completed");
            } else {
                $order->set_status("processing");
            }
            wc_reduce_stock_levels($orderId);
        } elseif ($status === "3" || $status === "4") {
            $order->set_status("wc-cancelled");
        } elseif ($status === "5") {
            $order->set_status("wc-failed");
        }
        // Log Order Status
        $this->logger->log('info', 'Order Status Log ' . $status . ' Virtual Item: ' . $isVirtualItem);
        $order->save();
        // Log Order Status
        $this->logger->log('info', 'Order Status Log ' . $order->get_status());
    }

    /**
     * Construct payment transaction id
     *
     * @param $currentPayment
     * @param $prefix
     * @return string
     */
    private function vpi_hosted_construct_payment_transaction_id($currentPayment, $prefix)
    {
        $id = $currentPayment[0]->transaction_id + 1;

        if ($currentPayment) {
            $paymentTransactionId = $prefix . $id;
        } else {
            error_log('veritaspay-payment-gateway-default.php: Cannot find newly inserted payment_transaction record');
            $paymentTransactionId = $prefix . '1';
        }

        return $paymentTransactionId;
    }

    /**
     * Merge all request data into an array
     *
     * @param string $merchantId
     * @param string $appReferenceNo
     * @param string $paymentTransactionId
     * @param string $total
     * @param string $returnUrl
     * @param string $callbackUrl
     * @param int $itemQuantity
     * @param string $customerName
     * @param string $emailAddress
     * @param string $productDescription
     * @param string $remarks
     * @return array
     */
    private function vpi_hosted_construct_request_data(
        string $merchantId,
        string $appReferenceNo,
        string $paymentTransactionId,
        string $total,
        string $returnUrl,
        string $callbackUrl,
        int $itemQuantity,
        string $customerName,
        string $emailAddress,
        string $productDescription,
        string $remarks
    ): array {
        // $remarks = 'WP ' . $itemQuantity . ' - Products';
        $totalAmount = str_replace(",", "", number_format($total, 2));
        $signatureDetails = hash('sha256', $merchantId . $paymentTransactionId  . $totalAmount . $this->secretKey);

        return [
            'MerchantId' => $merchantId,
            'MerchantOrderId' => $paymentTransactionId,
            'AppReferenceNo' => $appReferenceNo,
            'Amount' => $totalAmount,
            'Remarks' => $remarks,
            'ProductDescription' => $productDescription,
            'CustomerName' => 'VPPI ' . $customerName,
            'EmailAddress' => $emailAddress,
            'MerchantReturnURL' => $returnUrl,
            'MerchantCallbackURL' => $callbackUrl,
			'DisplaySettings' => [
                'IsVoucherEnabled' => false
            ],
            'Signature' => $signatureDetails
        ];
    }

    /**
     * Insert new payment transaction
     *
     * @param $wpdb
     * @param string $customerId
     * @param string $merchantOrderId
     * @param $request
     * @param $result
     * @param $isVirtualItem
     * @param $tableName
     */
    private function vpi_hosted_insert_payment_transaction(
        $wpdb,
        string $customerId,
        string $merchantOrderId,
        $request,
        $result,
        int $isVirtualItem,
        string $tableName
    ) {
        $req = json_encode($request);

        $row = [
            'customer_id' => $customerId,
            'order_id' => $merchantOrderId,
            'vpi_request' => $req,
            'vpi_response' => $result,
            'vpi_virtual_item' => $isVirtualItem
        ];

        $queryResult = $wpdb->insert($tableName, $row);
        
        if (!$queryResult) {
            error_log(
                'veritaspay-payment-gateway-default.php: Cannot create a new payment_transaction record for order_id=' .
                $merchantOrderId .
                ' customer=' .
                $customerId
            );
            
            return false;
        }
        
        return true;
    }

    /**
     * Gets the total item quantity inside cart
     *
     * @param array $getQuantity
     * @return int
     */
    private function vpi_hosted_get_item_quantity(array $getQuantity): int
    {
        $itemQuantity = 0;
        foreach ($getQuantity as $item_id => $item) {
            $itemQuantity = $item->get_quantity();
        }

        return $itemQuantity;
    }

    /**
     * This method will call the external API
     *
     * @param $url
     * @param $data
     * @return bool|string
     */
    private function vpi_hosted_call_api($url, $data)
    {
        $headers = ['Content-Type' => 'application/json'];
        $fields = [
            'body' => json_encode($data),
            'headers' => $headers,
            'method'      => 'POST',
            'data_format' => 'body'
        ];

        $result = wp_remote_post($url, $fields);

        if (is_wp_error($result)) {
            var_dump($result->get_error_message());
        }

        return wp_remote_retrieve_body($result);
    }

    /**
     * Returns redirect URL post payment processing
     *
     * @return string redirect URL
     */
    private function vpi_hosted_get_redirect_url($orderId)
    {
        $order = wc_get_order($orderId);

        $query = [
            'wc-api' => $this->id,
            'order_key' => $order->get_order_key(),
        ];

        return add_query_arg($query, trailingslashit(get_home_url()));
    }

    /**
     * Limits the character of a variable
     * 
     * @param string $inputText
     * @param string $characterLimit
     * 
     * @return string Limited text
     */
    private function vpi_hosted_character_limit($inputText, $characterLimit) {
        if (strlen($inputText) > $characterLimit) {
            $inputText = substr($inputText, 0, $characterLimit) . "...";
        }

        return $inputText;
    }
}