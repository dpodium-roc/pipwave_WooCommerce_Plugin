<?php
/**
 * pipwave WooCommerce Shopping Cart Plugin
 * 
 * @author pipwave <support@pipwave.com>
 * @version 1.0.0
 */

/**
 * Plugin Name: WooCommerce pipwave
 * Plugin URI: https://www.pipwave.com/
 * Description: WooCommerce pipwave | Simple, reliable and cost-effective that helps WooCommerce merchants sell online. It's FREE!
 * Author: pipwave
 * Author URI: https://www.pipwave.com/
 * Version: 1.0.0
 * License: GPLv3
 */
function pipwave_wc_require_woocommerce() {
    $message = '<div class="error">';
    $message .= '<p>' . __('WooCommerce pipwave requires <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>', 'wc_pipwave') . '</p>';
    $message .= '</div>';
    echo $message;
}

add_action('plugins_loaded', 'woocommerce_pipwave', 0);

// Load pipwave plugin function
function woocommerce_pipwave() {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'pipwave_wc_require_woocommerce');
        return;
    }

    // Load language
    load_plugin_textdomain('wc_pipwave', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    // Add pipwave gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_pipwave');

    function add_pipwave($methods) {
        $methods[] = 'WC_Gateway_Pipwave';
        return $methods;
    }

    // Define pipwave gateway
    class WC_Gateway_Pipwave extends WC_Payment_Gateway {

        public function __construct() {
            global $woocommerce;

            $this->id = 'pipwave';
            $this->icon = plugins_url('images/pipwave.png', __FILE__);
            $this->has_fields = false;
            $this->method_title = __('pipwave', 'wc_pipwave');
            $this->method_description = __('Simple, reliable, and cost-effective way to accept payments online.', 'wc_pipwave');

            // Load the form fields.
            $this->init_form_fields();
            // Load the settings.
            $this->init_settings();

            // Define user setting variables.
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->api_key = $this->settings['api_key'];
            $this->api_secret = $this->settings['api_secret'];
            $this->test_mode = $this->settings['test_mode'];

            // Actions.
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            // Save configuration
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            // Payment listener/API hook
            add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'pipwave_wc_callback_handler'));
            add_action('pipwave_wc_update_payment_method', array(&$this, 'pipwave_wc_update_payment_method'));
            add_action('pipwave_wc_response_callback', array(&$this, 'pipwave_wc_response_callback'));

            // Check if api_key or api_secret is empty
            $this->api_key == '' ? add_action('admin_notices', array(&$this, 'pipwave_wc_error_api_key')) : '';
            $this->api_secret == '' ? add_action('admin_notices', array(&$this, 'pipwave_wc_error_api_secret')) : '';
        }

        // Admin Panel Options
        public function admin_options() {
            ?>
            <h3><?php echo $this->method_title; ?></h3>
            <p><?php echo $this->method_description; ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table><!--/.form-table-->
            <div style="margin-top: 10px">
                <strong><?php echo __('What you should do next:', 'wc_pipwave'); ?></strong>
                <ol>
                    <li style="padding-left: 5px;">
                        <?php echo __('Sign in to <a href="https://merchant.pipwave.com" target="_blank"><strong>pipwave Merchant Center</strong></a>. If you do not have a pipwave account, <a href="https://merchant.pipwave.com/site/signup" target="_blank"><strong>sign up now</strong></a>! It is quick and easy.', 'wc_pipwave'); ?>
                    </li>
                    <li style="padding-left: 5px;">
                        <?php echo __('After sign in, go to <strong>Setup > Payments</strong>.', 'wc_pipwave'); ?>
                    </li>
                    <li style="padding-left: 5px;">
                        <?php echo __("Configure the payment methods which you would like to offer your customers and it's done.", 'wc_pipwave'); ?>
                    </li>
                    <li style="padding-left: 5px;">
                        <?php echo __('To know more about pipwave, click <a href="https://www.pipwave.com" target="_blank"><strong>here</strong></a>.', 'wc_pipwave'); ?>
                    </li>
                </ol>
            </div>
            <div style="margin-top: 20px">
                <strong><?php echo __('Note:', 'wc_pipwave'); ?></strong>
                <ol>
                    <li style="padding-left: 5px;">
                        <?php echo __('To refund an order, please proceed to <a href="https://merchant.pipwave.com" target="_blank"><strong>pipwave Merchant Center</strong></a> and search for you transaction.', 'wc_pipwave'); ?>
                    </li>
                </ol>
            </div>
            <?php
        }

        // Initialize pipwave Settings Form Fields
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'wc_pipwave'),
                    'type' => 'checkbox',
                    'label' => __('Enable pipwave', 'wc_pipwave'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'wc_pipwave'),
                    'type' => 'text',
                    'description' => __('The title of which the payer sees during checkout.', 'wc_pipwave'),
                    'default' => __('pipwave', 'wc_pipwave'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'wc_pipwave'),
                    'type' => 'textarea',
                    'description' => __('The description of which the payer sees during checkout.', 'wc_pipwave'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'api_key' => array(
                    'title' => __('pipwave API Key', 'wc_pipwave'),
                    'type' => 'text',
                    'description' => __('API key provided by pipwave', 'wc_pipwave'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'api_secret' => array(
                    'title' => __('pipwave API Secret', 'wc_pipwave'),
                    'type' => 'password',
                    'description' => __('API secret provided by pipwave', 'wc_pipwave'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'test_mode' => array(
                    'title' => __('Test Mode', 'wc_pipwave'),
                    'type' => 'checkbox',
                    'description' => __('Turn on pipwave test mode for testing purpose', 'wc_pipwave'),
                    'label' => __('Enable Test Mode', 'wc_pipwave'),
                    'default' => '',
                    'desc_tip' => true, 'desc_tip' => true,
                ),
            );
        }

        /**
         * Generate the form.
         *
         * @param mixed $order_id
         * @return string
         */
        public function generate_form($order_id) {
            $order = new WC_Order($order_id);
            $data = array(
                'action' => 'initiate-payment',
                'timestamp' => time(),
                'api_key' => $this->api_key,
                'txn_id' => $order->id . "",
                'amount' => $order->order_total,
                'currency_code' => get_woocommerce_currency(),
                'short_description' => 'Payment for Order#' . $order->id,
                'session_info' => array(
                    'ip_address' => $order->customer_ip_address,
                    'language' => get_bloginfo('language'),
                ),
                'buyer_info' => array(
                    'id' => $order->billing_email,
                    'email' => $order->billing_email,
                    'first_name' => $order->billing_first_name,
                    'last_name' => $order->billing_last_name,
                    'contact_no' => $order->billing_phone,
                    'country_code' => $order->billing_country,
                ),
                'billing_info' => array(
                    'name' => $order->billing_first_name . " " . $order->billing_last_name,
                    'address1' => $order->billing_address_1,
                    'address2' => $order->billing_address_2,
                    'city' => $order->billing_city,
                    'state' => $order->billing_state,
                    'zip' => $order->billing_postcode,
                    'country' => $order->billing_country,
                    'contact_no' => $order->billing_phone,
                    'email' => $order->billing_email,
                ),
                'shipping_info' => array(
                    'name' => $order->shipping_first_name . " " . $order->shipping_last_name,
                    'address1' => $order->shipping_address_1,
                    'address2' => $order->shipping_address_2,
                    'city' => $order->shipping_city,
                    'state' => $order->shipping_state,
                    'zip' => $order->shipping_postcode,
                    'country' => $order->shipping_country,
                    'contact_no' => $order->shipping_phone,
                    'email' => $order->shipping_email,
                ),
                'api_override' => array(
                    'success_url' => $this->get_return_url($order),
                    'fail_url' => $this->get_return_url($order),
                    'notification_url' => str_replace('https:', 'http:', add_query_arg('wc-api', 'WC_Gateway_Pipwave', home_url('/')))
                )
            );
            // Login user
            if ($order->customer_user) {
                $buyer = get_user_by('id', $order->customer_user);
                $data['buyer_info']['id'] = $order->customer_user;
                $data['buyer_info']['email'] = $buyer->user_email;
            }
            $signatureParam = array(
                'api_key' => $this->api_key,
                'api_secret' => $this->api_secret,
                'txn_id' => $data['txn_id'],
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'],
                'action' => $data['action'],
                'timestamp' => $data['timestamp']
            );
            $data['signature'] = $this->_pipwave_wc_generate_signature($signatureParam);

            foreach ($order->get_items() as $item) {
                $product = $order->get_product_from_item($item);
                $data['item_info'][] = array(
                    "name" => $item['name'],
                    "description" => $item['name'] . ' x ' . $item['qty'],
                    "amount" => $product->get_price(),
                    "currency_code" => get_woocommerce_currency(),
                    "quantity" => $item['qty'],
                    "sku" => $product->get_sku()
                );
            }

            $response = $this->_pipwave_wc_send_request($data);
            if ($response['status'] == 200) {
                $api_data = json_encode([
                    'api_key' => $this->api_key,
                    'token' => $response['token'],
                ]);
                $sdk_url = ($this->test_mode == 'yes') ? '//staging-checkout.pipwave.com/sdk/' : '//checkout.pipwave.com/sdk/';
                $result = <<<EOD
                    <div style="margin-bottom: 20px;"><img src="$this->icon"></div>
                    <div id="pwscript" class="text-center"></div>
                    <div id="pwloading" style="text-align: center;">
                        <i class="fa fa-spinner fa-spin fa-fw margin-bottom" style="font-size: 3em; color: #7a7a7a;"></i>
                        <span class="sr-only">Loading...</span>
                    </div>
                    <script type="text/javascript">
                        var pwconfig = $api_data;
                        (function (_, p, w, s, d, k) {
                            var a = _.createElement("script");
                            a.setAttribute('src', w + d);
                            a.setAttribute('id', k);
                            setTimeout(function() {
                                var reqPwInit = (typeof reqPipwave != 'undefined');
                                if (reqPwInit) {
                                    reqPipwave.require(['pw'], function(pw) {
                                        pw.setOpt(pwconfig);
                                        pw.startLoad();
                                    });
                                } else {
                                    _.getElementById(k).parentNode.replaceChild(a, _.getElementById(k));
                                }
                            }, 800);
                        })(document, 'script', "$sdk_url", "pw.sdk.min.js", "pw.sdk.min.js", "pwscript");
                    </script>
EOD;
            } else {
                $result = isset($response['message']) ? (is_array($response['message']) ? implode('; ', $response['message']) : $response['message']) : "Error occured";
            }

            return $result;
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id) {
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        // Render pipwave payment selection page
        public function receipt_page($order) {
            echo $this->generate_form($order);
        }

        /**
         * Update order status by pipwave response
         * transaction status
         */
        function pipwave_wc_callback_handler() {
            @ob_clean();
            header('HTTP/1.1 200 OK');
            $post_content = file_get_contents("php://input");
            $post_data = json_decode($post_content, true);
            do_action("pipwave_wc_response_callback", $post_data);
            exit();
        }

        // pipwave callback function
        function pipwave_wc_response_callback($post_data) {
            global $woocommerce;
            $timestamp = (isset($post_data['timestamp']) && !empty($post_data['timestamp'])) ? $post_data['timestamp'] : time();
            $pw_id = (isset($post_data['pw_id']) && !empty($post_data['pw_id'])) ? $post_data['pw_id'] : '';
            $order_id = (isset($post_data['txn_id']) && !empty($post_data['txn_id'])) ? $post_data['txn_id'] : '';
            $amount = (isset($post_data['amount']) && !empty($post_data['amount'])) ? $post_data['amount'] : '';
            $currency_code = (isset($post_data['currency_code']) && !empty($post_data['currency_code'])) ? $post_data['currency_code'] : '';
            $transaction_status = (isset($post_data['transaction_status']) && !empty($post_data['transaction_status'])) ? $post_data['transaction_status'] : '';
            $payment_method = isset($post_data['payment_method_title']) ? __('pipwave', 'wc_pipwave') . " - " . $post_data['payment_method_title'] : $this->title;
            $signature = (isset($post_data['signature']) && !empty($post_data['signature'])) ? $post_data['signature'] : '';
            $data_for_signature = array(
                'timestamp' => $timestamp,
                'api_key' => $this->api_key,
                'pw_id' => $pw_id,
                'txn_id' => $order_id,
                'amount' => $amount,
                'currency_code' => $currency_code,
                'transaction_status' => $transaction_status,
                'api_secret' => $this->api_secret,
            );
            $generatedSignature = $this->_pipwave_wc_generate_signature($data_for_signature);
            if ($signature != $generatedSignature) {
                $transaction_status = -1;
            }

            $order = new WC_Order($order_id);
            if ($transaction_status == 1) { // failed
                $order->add_order_note('[pipwave] Payment Status: FAILED' . '<br>pipwave Transaction ID: ' . $pw_id);
                $order->update_status('failed', sprintf(__('Payment %s via %s.', 'woocommerce'), $pw_id, $payment_method));
            } else if ($transaction_status == 2) { // cancelled
                $order->add_order_note('[pipwave] Payment Status: CANCELLED' . '<br>pipwave Transaction ID: ' . $pw_id);
                $order->update_status('cancelled', sprintf(__('Payment %s via %s.', 'woocommerce'), $pw_id, $payment_method));
            } else if ($transaction_status == 10) { // complete
                $order->add_order_note('[pipwave] Payment Status: COMPLETE' . '<br>pipwave Transaction ID: ' . $pw_id);
                $order->payment_complete();
            } else if ($transaction_status == 20) { // refunded
                $order->add_order_note('[pipwave] Payment Status: REFUNDED' . '<br>pipwave Transaction ID: ' . $pw_id);
                $order->update_status('refunded', sprintf(__('Payment %s via %s.', 'woocommerce'), $pw_id, $payment_method));
            } else if ($transaction_status == -1) {
                $order->add_order_note('[pipwave] Payment Status: INVALID TRANSACTION' . '<br>pipwave Transaction ID: ' . $pw_id);
                $order->update_status('on-hold', sprintf(__('Payment %s via %s.', 'woocommerce'), $pw_id, $payment_method));
            } else if ($transaction_status == 5) {
                do_action('pipwave_wc_update_payment_method', array('order_id' => $order_id, 'payment_method' => $payment_method));
            }
        }

        function pipwave_wc_update_payment_method($arg) {
            update_post_meta($arg['order_id'], '_payment_method_title', $arg['payment_method']);
        }

        public function pipwave_wc_error_api_key() {
            $message = '<div class="error">';
            $message .= '<p>' . sprintf(__('<strong>pipwave error:</strong> API key cannot be blank. %sConfigure here%s.', 'wc_pipwave'), '<a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=pipwave">', '</a>') . '</p>';
            $message .= '</div>';
            echo $message;
        }

        public function pipwave_wc_error_api_secret() {
            $message = '<div class="error">';
            $message .= '<p>' . sprintf(__('<strong>pipwave error:</strong> API secret cannot be blank. %sConfigure here%s.', 'wc_pipwave'), '<a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=pipwave">', '</a>') . '</p>';
            $message .= '</div>';
            echo $message;
        }

        private function _pipwave_wc_generate_signature($array) {
            ksort($array);
            $signature = "";
            foreach ($array as $key => $value) {
                $signature .= $key . ':' . $value;
            }
            return sha1($signature);
        }

        private function _pipwave_wc_send_request($data) {
            // test mode is on
            if ($this->test_mode == 'yes') {
                $url = "https://staging-api.pipwave.com/payment";
            } else {
                $url = "https://api.pipwave.com/payment";
            }

            $agent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_USERAGENT, $agent);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

            $response = curl_exec($ch);
            if ($response == false) {
                echo "<pre>";
                echo 'CURL ERROR: ' . curl_errno($ch) . '::' . curl_error($ch);
                die;
            }
            curl_close($ch);

            return json_decode($response, true);
        }

    }

}
