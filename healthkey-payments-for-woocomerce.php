<?php

/**
 * Plugin Name: HealthKey Payment for Woocommerce
 * Plugin URI: https://health.goodbodyclinic.com
 * Author Name: Fabrice Gagneux
 * Author URI: https://health.goodbodyclinic.com
 * Description: This plugin allows for payments with HealthKey.
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 * License: 0.1.0
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: healthkey-pay-woo
 * WC tested up to: 9.0
*/ 

if (!in_array('woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )))) {
    return;
}

add_action('plugins_loaded', 'healthkey_payment_init', 11);

add_action('rest_api_init', function () {
    register_rest_route('healthkey', '/oauth/redirect', [
      'methods' => 'GET',
      'callback' => 'processTransaction',
      'permission_callback' => '__return_true'
    ]);
} );

add_action('rest_api_init', function () {
    register_rest_route('healthkey', '/orders/payment_status', [
        'methods' => 'post',
        'callback' => 'processPayment',
        'permission_callback' => '__return_true'
      ]);
});

/**
 * wp api function to process the payment from HealthKey
 * 
 * @param WP_REST_Request $request
 * @return wp_redirect
 */
function processPayment(WP_REST_Request $request) 
{
    $request_body = json_decode($request->get_body());

    if (!isset($request_body->id) || !isset($request_body->status)) {
        return new WP_REST_Response(
            NULL,
            401
        );
    }


    $args = [
        'meta_value' => $request_body->id,
        'meta_key' => 'hk_transaction_id',
        'meta_compare'  => '=',
    ];
    $orders = wc_get_orders($args);

    if (count($orders) < 1) {
        return new WP_REST_Response(
            $request_body->id,
            400
        );
    } 

    $order = $orders[0];

    if (strtoupper($request_body->status) == 'COMPLETED') {
        $order->payment_complete();
    } else {
        $order->update_status('cancelled',  __('Awaiting HealthKey Payment', 'healthkey-pay-woo'));
    }
    return new WP_REST_Response(
        ['success' => 1, 'message' => "Order Status Updated"],
        200
    );
    
}

/**
 * wp api function to authorise the payment from HealthKey
 * 
 * @param WP_REST_Request $request
 * @return wp_redirect
 */
function processTransaction(WP_REST_Request $request) 
{
    if (!isset($_SESSION)) {
        session_start(); 
    }

    if (!isset($_SESSION['hk_code_verifier']) || !isset($request['code'])) {
        wp_redirect( wc_get_checkout_url() );
        exit();
    }

    $access_token = getAccessToken($request['code']);
    $order = wc_get_order( $_SESSION['hk_order_id'] );

    $payment_response = requestPayment($access_token, $order);
    
    $transaction_status = $payment_response->status;
    $transaction_id = $payment_response->id;

    if (strtolower($transaction_status) != 'processing') {
        wp_redirect( wc_get_checkout_url() );
        exit();
    } else {
        $order->update_meta_data('hk_transaction_id', $transaction_id);
        $order->save();

        $healthkey_settings = get_option("woocommerce_healthkey_payment_settings");
        $url = $healthkey_settings['SERVER_AUTHORISATION_URL'];
        wp_redirect($url);
        exit();
    }
}


/**
 * return HealthKey access token 
 * 
 * @param string $code
 * @return string $access_token
 */
function getAccessToken($code) 
{
    $healthkey_settings = get_option("woocommerce_healthkey_payment_settings");
    $url = $healthkey_settings['AUTH_HOSTNAME'] . "/o/token/";
    $data = [
        "code"          => $code,
        "grant_type"    => "authorization_code",
        "code_verifier" => $_SESSION['hk_code_verifier'],
        "client_id"     => $healthkey_settings['CLIENT_ID'],
        "client_secret" => $healthkey_settings['CLIENT_SECRET'],
        "redirect_url"  => get_site_url() . "/wp-json/healthkey/oauth/redirect",
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = json_decode(curl_exec($ch));
    curl_close($ch);

    return $response->access_token;
}

/**
 * send payment request to HealthKey
 * 
 * @param string $access_token
 * @param WC_Order $order
 * @return string json response
 */
function requestPayment($access_token, $order) 
{
    $healthkey_settings = get_option("woocommerce_healthkey_payment_settings");
    $url = $healthkey_settings['SERVER_API_HOSTNAME'] . "/api/v1/transactions";
    $authorization = "Authorization: Bearer " . $access_token;

    $products = [];
    foreach ( $order->get_items() as $item_id => $item ) {
        $products[] = [
            "name"          => $item->get_name(),
            "externalId"    => $healthkey_settings['product_prefix'] . "-" . $item->get_product_id(),
            "description"   => $item->get_name(),
            "price"         => $item->get_total() / $item->get_quantity(), //unit price
            "currency"      => "GBP",
            "quantity"      => $item->get_quantity(),
        ];
    }
    if ($order->get_shipping_total() > 0) {
        $products[] = [
            "name"          => $order->get_shipping_method(),
            "externalId"    => $healthkey_settings['product_prefix'] . "-SHIPPING",
            "description"   => $order->get_shipping_method(),
            "price"         => $order->get_shipping_total(),
            "currency"      => "GBP",
            "quantity"      => 1,
        ];
    }

    $successUrl = $order->get_checkout_order_received_url();

    $data = ["products" => $products, "successUrl" => $successUrl];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json' , $authorization]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response);
}

/**
 * intitialise Woocommerce integration
 * 
 * @return void
 */
function healthkey_payment_init() 
{
    if (class_exists( 'WC_Payment_Gateway')) {
        class WC_Healthkey_pay_Gateway extends WC_Payment_Gateway 
        {

            /**
             * 
             * @var string
             */
            public $id;

            /**
             * 
             * @var string
             */
            public $icon;

            /**
             * 
             * @var bool
             */
            public $has_fields;
            
            /**
             * 
             * @var string
             */
            public $method_title;
            
            /**
             * 
             * @var string
             */
            public $method_description;

            /**
             * 
             * @var string
             */
            public $title;
            
            /**
             * 
             * @var string
             */
            public $description;
            
            /**
             * 
             * @var string
             */
            public $instructions;
            
            /**
             * 
             * @var string
             */
            private $hk_client_id;
            
            /**
             * 
             * @var string
             */
            private $hk_client_secret;
            
            /**
             * 
             * @var string
             */
            private $hk_auth_hostname;
            
            /**
             * 
             * @var string
             */
            private $hk_server_api_hostname;
            
            /**
             * 
             * @var string
             */
            private $hk_code_verifier;
            
            /**
             * 
             * @var string
             */
            private $hk_code_challenge;

            /**
             * construct
             * 
             * @return void
             */
            public function __construct()
            {
                $this->id   = 'healthkey_payment';
                $this->icon = apply_filters('woocommerce_healthkey_icon', plugins_url('/assets/icon.png', __FILE__ ));
                $this->has_fields = false;
                $this->method_title = __('HealthKey Payment', 'healthkey-pay-woo');
                $this->method_description = __('Pay with HealthKey membership', 'healthkey-pay-woo');
 
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->instructions = $this->get_option('instructions', $this->description);
                $this->hk_client_id = $this->get_option('CLIENT_ID');
                $this->hk_client_secret = $this->get_option('CLIENT_SECRET');
                $this->hk_auth_hostname = $this->get_option('AUTH_HOSTNAME');
                $this->hk_server_api_hostname = $this->get_option('SERVER_API_HOSTNAME');
                $this->hk_code_verifier = $this->generateCodeVerifier();
                $this->hk_code_challenge = $this->generateCodeChallenge($this->hk_code_verifier);
                

                $this->init_form_fields();
                $this->init_settings();

                add_action('woocommerce_update_options_payment_gateways_' . $this->id,  [$this, 'process_admin_options' ]);
            }

            /**
             * set configuration fields
             * 
             * @return void
             */
            public function init_form_fields() 
            {
                $this->form_fields = apply_filters('woo_healthkey_pay_fields', [
                    'enabled' => [
                        'title' => __('Enable/Disable', 'healthkey-pay-woo'),
                        'type' => 'checkbox',
                        'label' => __('Enable HealthKey', 'healthkey-pay-woo'),
                        'default' => 'no'
                    ],
                    'CLIENT_ID' => [
                        'title' => __('CLIENT_ID', 'healthkey-pay-woo'),
                        'type' => 'text',
                        'default' => __('', 'healthkey-pay-woo'),
                        'desc_tip' => true,
                        'description' => __('HealthKey CLIENT_ID', 'healthkey-pay-woo')
                    ],
                    'CLIENT_SECRET' => [
                        'title' => __('CLIENT_SECRET', 'healthkey-pay-woo'),
                        'type' => 'text',
                        'default' => __('', 'healthkey-pay-woo'),
                        'desc_tip' => true,
                        'description' => __('HealthKey CLIENT_SECRET', 'healthkey-pay-woo')
                    ],
                    'AUTH_HOSTNAME' => [
                        'title' => __('AUTH_HOSTNAME', 'healthkey-pay-woo'),
                        'type' => 'text',
                        'default' => __('https://auth-server.sandbox.healthkey.health', 'healthkey-pay-woo'),
                        'desc_tip' => true,
                        'description' => __('HealthKey AUTH_HOSTNAME', 'healthkey-pay-woo')
                    ],
                    'SERVER_API_HOSTNAME' => [
                        'title' => __('SERVER_API_HOSTNAME', 'healthkey-pay-woo'),
                        'type' => 'text',
                        'default' => __('https://server.sandbox.healthkey.health', 'healthkey-pay-woo'),
                        'desc_tip' => true,
                        'description' => __('HealthKey SERVER_API_HOSTNAME', 'healthkey-pay-woo')
                    ],            
                    'SERVER_AUTHORISATION_URL' => [
                        'title' => __('SERVER_AUTHORISATION_URL', 'healthkey-pay-woo'),
                        'type' => 'text',
                        'default' => __('https://app.sandbox.healthkey.health/', 'healthkey-pay-woo'),
                        'desc_tip' => true,
                        'description' => __('link used for redirection for authorising the payment', 'healthkey-pay-woo')
                    ],    
                    'product_prefix' => [
                        'title' => __('Product Prefix', 'healthkey-pay-woo'),
                        'type' => 'text',
                        'default' => __('HK', 'healthkey-pay-woo'),
                        'desc_tip' => true,
                        'description' => __('prefix used when sending product id to HealthKey', 'healthkey-pay-woo')
                    ],  
                ]);
            }

            /**
             * process payment
             * 
             * @param int $order_id
             * @return array [result, redirect]
             */
            public function process_payment( $order_id ) 
            {
                

                $order = wc_get_order( $order_id );

                if (!isset($_SESSION)) {
                    session_start(); 
                }

                $_SESSION['hk_checkout_url'] = $this->get_return_url( $order );
                $_SESSION['hk_code_verifier'] = $this->hk_code_verifier;
                $_SESSION['hk_code_challenge'] = $this->hk_code_challenge;
                
                $_SESSION['hk_order_id'] = $order_id;
            
                $order->update_status('pending',  __('Awaiting HealthKey Payment', 'healthkey-pay-woo'));
                
                //$order->reduce_order_stock();
                //WC()->cart->empty_cart();

                $url = $this->hk_auth_hostname .
                    "/o/authorize/?response_type=code&code_challenge=" .
                    $this->hk_code_challenge .
                    "&code_challenge_method=S256&client_id=" .
                    $this->hk_client_id .
                    "&redirect_uri=" . get_site_url() . 
                    "/wp-json/healthkey/oauth/redirect&scope=payment";

                return [
                    'result'   => 'success',
                    'redirect' => $url,
                ];
            }

            /**
             * generate a random string 43 tp 128 characters
             * 
             * @return string $code_verifier
             */
            private function generateCodeVerifier() 
            {
                $length = random_int(43, 128);
                $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-._~';
                $charactersLength = strlen($characters);
                $code_verifier = '';
                for ($i = 0; $i < $length; $i++) {
                    $code_verifier .= $characters[random_int(0, $charactersLength - 1)];
                }
                return $code_verifier;
            } 

            /**
             * generate HealthKey CODE_CHALLENGE 
             * 
             * @param string $code_verifier
             * @return string $code_challenge
             */
            private function generateCodeChallenge($code_verifier) 
            {
                $sha256 = hash("sha256", $code_verifier, true);
                return str_replace(['+', '/', '='], ['-', '_', ''] , base64_encode($sha256));
            } 
        }
    }
}

add_filter('woocommerce_payment_gateways', 'add_to_woo_healthkey_payment_gateway');
 
/**
 *  add to HealthKey to $gateways
 * @param array $gateways
 * @return array $gateways
 */
function add_to_woo_healthkey_payment_gateway($gateways) 
{
    $gateways[] = 'WC_Healthkey_pay_Gateway';
    return $gateways;
}

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

add_action( 'woocommerce_blocks_loaded', 'healthkey_woocommerce_blocks_support' );

function healthkey_woocommerce_blocks_support() {
    require_once dirname( __FILE__ ) . '/class-wc-healthkey-blocks-support.php';
    add_action(
      'woocommerce_blocks_payment_method_type_registration',
      function( PaymentMethodRegistry $payment_method_registry ) {
        $payment_method_registry->register( new WC_Healthkey_Blocks_Support );
      }
    );
}

add_action( 'before_woocommerce_init', function() {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
} );
