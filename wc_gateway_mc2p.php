<?php
/**
 * Plugin Name: WooCommerce MyChoice2Pay Payment Gateway
 * Plugin URI: https://github.com/mc2p/mc2p-woocommerce
 * Description: WooCommerce library for the MyChoice2Pay API.
 * Author: MyChoice2Pay
 * Author URI: https://www.mychoice2pay.com/
 * Version: 1.2.9
 * Text Domain: wc_mc2p_payment_gateway
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2017 MyChoice2Pay SL. (hola@mychoice2pay.com) and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-MC2P
 * @author    MyChoice2Pay
 * @category  Admin
 * @copyright Copyright (c) 2017 MyChoice2Pay SL. (hola@mychoice2pay.com) and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;
// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + mc2p gateway
 */
function wc_mc2p_add_to_gateways( $gateways ) {
    $gateways[] = 'WC_Gateway_MC2P';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_mc2p_add_to_gateways' );
/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_mc2p_gateway_plugin_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mc2p_gateway' ) . '">' . __( 'Configure', 'wc-gateway-mc2p' ) . '</a>'
    );
    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_mc2p_gateway_plugin_links' );
/**
 * MC2P Payment Gateway
 *
 * Provides an MC2P Payment Gateway.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_MC2P
 * @extends		WC_Payment_Gateway
 * @version		1.2.9
 * @package		WooCommerce/Classes/Payment
 * @author 		MyChoice2Pay
 */

$autoloader_param = __DIR__ . '/lib/MC2P/MC2PClient.php';
// Load up the MC2P library
try {
    require_once $autoloader_param;
} catch (\Exception $e) {
    throw new \Exception('The MC2P payment plugin was not installed correctly or the files are corrupt. Please reinstall the plugin. If this message persists after a reinstall, contact hola@mychoice2pay.com with this message.');
}

add_action( 'plugins_loaded', 'wc_mc2p_gateway_init', 11 );
function wc_mc2p_gateway_init() {
	if (!class_exists('WC_Payment_Gateway')) {
		return;
    }
    
    class WC_Gateway_MC2P extends WC_Payment_Gateway {

        $WAY_REDIRECT = 'redirect';
        $WAY_IFRAME = 'iframe';

        public $supports = array(
            'subscriptions',
            'refunds'
        );

        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->id                 = 'mc2p_gateway';
            $this->icon               = apply_filters( 'woocommerce_mc2p_icon', plugins_url( 'assets/images/icons/mc2p.png' , __FILE__ ) );
            $this->has_fields         = false;
            $this->method_title       = __( 'MyChoice2Pay', 'wc-gateway-mc2p' );
            $this->method_description = __( 'Allows to receive payments from several payment gateways while offering the possibility of dividing payments between several people.', 'wc-gateway-mc2p' );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option( 'mc2p_title' );
            $this->key          = $this->get_option( 'mc2p_key' );
            $this->secret_key   = $this->get_option( 'mc2p_secret_key' );
            $this->description  = $this->get_option( 'mc2p_description' );
            $this->thank_you_text = $this->get_option( 'mc2p_thank_you_text', $this->description );
            $this->way = $this->get_option( 'mc2p_way', $WAY_REDIRECT );
            $this->set_completed = $this->get_option( 'mc2p_set_completed', 'N' );
            $this->icon = $this->get_option( 'mc2p_icon', $this->icon );

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

            $this->notify_url = add_query_arg( 'wc-api', 'WC_Gateway_MC2P', home_url( '/' ) );
            if ( 'yes' === get_option( 'woocommerce_force_ssl_checkout' ) || is_ssl() ) {
                $this->notify_url   = str_ireplace( 'https:', 'http:',  $this->notify_url );
            } else {
                $this->notify_url   = str_ireplace( 'http:', 'https:', $this->notify_url );
            }

            // Actions
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
                // Check for gateway messages using WC 1.X format
                add_action( 'init', array( $this, 'check_notification' ) );
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            } else {
                // Payment listener/API hook (WC 2.X)
                add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_notification' ) );
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            }

            add_action( 'woocommerce_receipt_mc2p_gateway', array( $this, 'receipt_page' ) );
        }


        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {

            $this->form_fields = apply_filters( 'wc_mc2p_form_fields', array(

                'mc2p_enabled' => array(
                    'title'   => __( 'Enable/Disable', 'wc-gateway-mc2p' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable MyChoice2Pay Gateway', 'wc-gateway-mc2p' ),
                    'default' => 'yes'
                ),

                'mc2p_key' => array(
                    'title'       => __( 'Key', 'wc-gateway-mc2p' ),
                    'type'        => 'text',
                    'description' => __( 'Key obtained in MyChoice2Pay.com', 'wc-gateway-mc2p' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),

                'mc2p_secret_key' => array(
                    'title'       => __( 'Secret Key', 'wc-gateway-mc2p' ),
                    'type'        => 'text',
                    'description' => __( 'Secret Key obtained in MyChoice2Pay.com', 'wc-gateway-mc2p' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),

                'mc2p_title' => array(
                    'title'       => __( 'Title', 'wc-gateway-mc2p' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title for the payment method the customer sees during checkout', 'wc-gateway-mc2p' ),
                    'default'     => __( 'MyChoice2Pay', 'wc-gateway-mc2p' ),
                    'desc_tip'    => true,
                ),

                'mc2p_description' => array(
                    'title'       => __( 'Description', 'wc-gateway-mc2p' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout', 'wc-gateway-mc2p' ),
                    'default'     => __( 'Select among several payment methods the one that works best for you in MyChoice2Pay', 'wc-gateway-mc2p' ),
                    'desc_tip'    => true,
                ),

                'mc2p_thank_you_text' => array(
                    'title'       => __( 'Text in thank you page', 'wc-gateway-mc2p' ),
                    'type'        => 'textarea',
                    'description' => __( 'Text that will be added to the thank you page', 'wc-gateway-mc2p' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'mc2p_way' => array(
                    'title'       => __( 'Integration', 'wc-gateway-mc2p' ),
                    'type'        => 'select',
                    'description' => __( 'Way to integrate MyChoice2Pay.', 'wc-gateway-mc2p' ),
                    'options'     => array(
                        $WAY_REDIRECT => __( 'Redirect', 'wc-gateway-mc2p' ),
                        $WAY_IFRAME => __( 'iFrame', 'wc-gateway-mc2p' )
                    ),
                    'default'     => $WAY_REDIRECT
                ),
                'mc2p_set_completed' => array(
                    'title'       => __( 'Set order as completed after payment?', 'wc-gateway-mc2p' ),
                    'type'        => 'select',
                    'description' => __( 'After payment, should the order be set as completed? Default is "processing".', 'wc-gateway-mc2p' ),
                    'desc_tip'    => false,
                    'options'     => array(
                        'N' => __( 'No', 'wc-gateway-mc2p' ),
                        'Y' => __( 'Yes', 'wc-gateway-mc2p' ),
                    ),
                    'default'     => 'N'
                ),

                'mc2p_icon' => array(
                    'title'   => __( 'Icon', 'wc-gateway-mc2p' ),
                    'type'    => 'text',
                    'label'   => __( 'Icon to show in the order page', 'wc-gateway-mc2p' ),
                    'default' => apply_filters( 'woocommerce_mc2p_icon', plugins_url( 'assets/images/icons/mc2p.png' , __FILE__ ) )
                ),
            ) );
        }


        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
                $woocommerce->cart->empty_cart();
            } else {
                WC()->cart->empty_cart();
            }

            if ( $this->thank_you_text ) {
                echo wpautop( wptexturize( $this->thank_you_text ) );
            }
        }


        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {

            if ( $this->way == $WAY_REDIRECT ) {
                return $this->start_process_payment( $order_id );
            }

            $order = wc_get_order( $order_id );

            if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '<' ) ) {
                $redirect_url = add_query_arg('order', $order->get_id(), add_query_arg('key', $order->get_order_key(), get_permalink(woocommerce_get_page_id('pay'))));
            } else {
                $redirect_url = $order->get_checkout_payment_url( true );
            }

            return array(
                    'result'        => 'success',
                    'redirect'      => $redirect_url
            );
        }

        function receipt_page( $order_id ) {
            return $this->start_process_payment( $order_id );
        }


        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function start_process_payment( $order_id ) {

            $order = wc_get_order( $order_id );

            $mc2p = new MC2P\MC2PClient($this->key, $this->secret_key);

            // Language
            if( $this->language == 'no' ) {
                $language = 'au';
            } else {
                $customer_language = substr( get_bloginfo("language"), 0, 2 );
                switch ( $customer_language ) {
                    case 'es':
                        $language = 'es';
                        break;
                    case 'en':
                        $language = 'en';
                        break;
                    case 'pt':
                        $language = 'pt';
                        break;
                    case 'ca':
                        $language = 'ca';
                        break;
                    case 'de':
                        $language = 'de';
                        break;
                    case 'it':
                        $language = 'it';
                        break;
                    case 'fr':
                        $language = 'fr';
                        break;
                    case 'gl':
                        $language = 'gl';
                        break;
                    case 'eu':
                        $language = 'eu';
                        break;
                }
            }

            if( class_exists( 'WC_Subscriptions_Order' ) && WC_Subscriptions_Order::order_contains_subscription( $order_id ) ) {
                $obj = $this->process_subscription_payment( $mc2p, $order, $language, $order_id );
            } else {
                $obj = $this->process_regular_payment( $mc2p, $order, $language, $order_id );
            }

            if ( $this->way == $WAY_IFRAME ) {
				echo '<iframe src="'.$obj->getIframeUrl().'" frameBorder="0" style="width: 100%; height: 700px"></iframe>';
            } else {
				return array(
					'result' 	=> 'success',
					'redirect'	=> $obj->getPayUrl()
				);
			}
        }


        /**
         * Process regular payment and return the result
         *
         * @param object $mc2p
         * @param object $order
         * @param string $language
         * @return array
         */
        public function process_regular_payment( $mc2p, $order, $language, $order_id ) {

			$country = '';
			$email = '';
            $first_name = '';
            $last_name = '';
            $postal_code = '';
			if ( version_compare( WOOCOMMERCE_VERSION, '3.0', '<' ) ) {
                $country = $order->billing_country;
                $email = $order->billing_email;
                $first_name = $order->billing_first_name;
                $last_name = $order->billing_last_name;
                $postal_code = $order->billing_postcode;
            } else {
                $country = $order->get_billing_country();
				$email = $order->get_billing_email();
                $first_name = $order->get_billing_first_name();
                $last_name = $order->get_billing_last_name();
                $postal_code = $order->get_billing_postcode();
            }

            // Create transaction
            $transaction = $mc2p->Transaction(
                array(
                    "order_id" => $order_id,
                    "currency" => get_woocommerce_currency(),
                    "return_url"  => $this->get_return_url( $order ),
                    "cancel_url" => $order->get_cancel_order_url(),
                    "notify_url" => $this->notify_url,
                    "language" => $language,
                    "products" => array(
                        array(
                            "amount" => 1,
                            "product" => array(
                                "name" => __('Payment of order ', 'wc-gateway-mc2p').$order_id,
                                "price" => $order->get_total()
                            )
                        )
                    ),
					"extra" => array(
						"email" => $email,
						"country" => $country,
						"first_name" => $first_name,
						"last_name" => $last_name,
                        "billing_postal_code" => $postal_code,
                        "mc2p_lib" => array(
                            "name" => "woocommerce",
                            "version" => "1.2.9"
                        )
					)
                )
            );
            $transaction->save();

            return $transaction;
        }


        /**
         * Process subscription payment and return the result
         *
         * @param object $mc2p
         * @param object $order
         * @param string $language
         * @return array
         */
        public function process_subscription_payment( $mc2p, $order, $language, $order_id ) {

            $country = '';
			$email = '';
            $first_name = '';
            $last_name = '';
            $postal_code = '';
			if ( version_compare( WOOCOMMERCE_VERSION, '3.0', '<' ) ) {
                $country = $order->billing_country;
                $email = $order->billing_email;
                $first_name = $order->billing_first_name;
                $last_name = $order->billing_last_name;
                $postal_code = $order->billing_postcode;
            } else {
                $country = $order->get_billing_country();
				$email = $order->get_billing_email();
                $first_name = $order->get_billing_first_name();
                $last_name = $order->get_billing_last_name();
                $postal_code = $order->get_billing_postcode();
            }

            $unconverted_periods = array(
                'period'        => WC_Subscriptions_Order::get_subscription_period( $order ),
                'trial_period'  => WC_Subscriptions_Order::get_subscription_trial_period( $order )
            );

            $converted_periods = array();
            foreach ( $unconverted_periods as $key => $period ) {
                switch( strtolower( $period ) ) {
                    case 'day':
                        $converted_periods[$key] = 'D';
                        break;
                    case 'week':
                        $converted_periods[$key] = 'W';
                        break;
                    case 'year':
                        $converted_periods[$key] = 'Y';
                        break;
                    case 'month':
                    default:
                        $converted_periods[$key] = 'M';
                        break;
                }
            }

            $period = $converted_periods['period'];
            $duration = WC_Subscriptions_Order::get_subscription_interval( $order );
            $price = WC_Subscriptions_Order::get_total_initial_payment( $order );

            // Create transaction
            $subscription = $mc2p->Subscription(
                array(
                    "order_id" => $order_id,
                    "currency" => get_woocommerce_currency(),
                    "return_url"  => $this->get_return_url( $order ),
                    "cancel_url" => $order->get_cancel_order_url(),
                    "notify_url" => $this->notify_url,
                    "language" => $language,
                    "plan" => array(
                        "name" => __('Subscription of order ', 'wc-gateway-mc2p').$order_id,
                        "price" => $price,
                        "duration" => $duration,
                        "unit" => $period,
                        "recurring" => True
                    ),
					"extra" => array(
						"email" => $email,
						"country" => $country,
						"first_name" => $first_name,
						"last_name" => $last_name,
                        "billing_postal_code" => $postal_code,
                        "mc2p_lib" => array(
                            "name" => "woocommerce",
                            "version" => "1.2.9"
                        )
					)
                )
            );
            $subscription->save();

            return $subscription;
        }
        
        /**
         * Process refund
         *
         * Overriding refund method
         *
         * @param       int $order_id
         * @param       float $amount
         * @param       string $reason
         * @return      mixed True or False based on success, or WP_Error
         */
        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $order = wc_get_order( $order_id );

            $sale_id = $order->get_transaction_id();
            if ( !$sale_id ) {
                return new WP_Error( 'mc2p_gateway_wc_refund_error',
                    sprintf(
                        __( 'Refund %s failed because Transaction ID is empty.', 'wc-gateway-mc2p' ),
                        get_class( $this )
                    )
                );
            }

            $error_message = sprintf(
                __( 'Refund %s failed', 'wc-gateway-mc2p' ),
                get_class( $this )
            );

            try {
                $mc2p = new MC2P\MC2PClient($this->key, $this->secret_key);
                $sale = $mc2p->Sale(
                    array(
                        'id' => $sale_id
                    )
                );
                $sale->retrieve();

                $refund_data = array();
                // If the amount is set, refund that amount, otherwise the entire amount is refunded
                if ( $amount ) {
                    $refund_data['amount'] = $amount;
                }
                $response = $sale->refund($refund_data);
                if ( $response->success ) {
                    $order->add_order_note( __( 'MC2P Refund Amount: ', 'wc-gateway-mc2p' ).$refund_data->amount );
                    return true;
                } else {
                    $order->add_order_note($error_message);
                    return new WP_Error( 'mc2p_gateway_wc_refund_error', $error_message );
                }
            } catch ( Exception $e ) {
                $order->add_order_note($error_message);
                // Something failed somewhere, send a message.
                return new WP_Error( 'mc2p_gateway_wc_refund_error', $error_message );
            }
            return false;
        }

        /**
         * Check for MC2P notification
         *
         * @access public
         * @return void
         */
        function check_notification() {
            global $woocommerce;

            if ( !empty( $_REQUEST ) ) {
                $json = (array)json_decode(file_get_contents('php://input'));

                if ( !empty( $json ) ) {

                    $mc2p = new MC2P\MC2PClient($this->key, $this->secret_key);

                    @ob_clean();

                    $notification_data = $mc2p->NotificationData($json, $mc2p);

                    if ( $notification_data->getStatus() == 'D' ) {
                        $order = new WC_Order( $notification_data->getOrderId() );

                        if ( $order->status == 'completed' ) {
                             wp_die();
                        }

                        $sale = $notification_data->getSale();

                        // Payment completed
                        $order->add_order_note( __('MC2P payment completed', 'wc-gateway-mc2p') );
                        $order->add_order_note( __('MC2P Sale ID: ', 'wc-gateway-mc2p').$sale->id );
                        $order->payment_complete( $sale->id );

                        // Set order as completed if user did set up it
                        if ( 'Y' == $this->set_completed ) {
                            $order->update_status( 'completed' );
                        }
                    } else if ( $notification_data->getStatus() == 'C' ) {
                        // Order failed
                        $message = __('MC2P payment cancelled', 'wc-gateway-mc2p');
                        $order->update_status('failed', $message );
                    }
                }
            }
        }

    } // end \WC_Gateway_MC2P class
}
