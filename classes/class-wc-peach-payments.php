<?php
/**
 * Peach Payments Gateway
 *
 * Provides an Peach Payments WPF Gateway
 *
 * @class 		WC_Peach_Payments
 * @extends		WC_Payment_Gateway
 * @version		1.6.7
 * @package		WC_Peach_Payments
 * @author 		Nitin Sharma
 */

class WC_Peach_Payments extends WC_Payment_Gateway {

	/**
	 * Holds the current payment
	 */
	public $payment = '';

	/**
	 * Hold the Gateway URLs for Peach
	 */
	protected $gateway_url    = '';
	protected $query_url      = '';
	protected $post_query_url = '';

	/**
	 * Is the gateway set to live or dev.
	 */
	protected $transaction_mode = '';

	/**
	 * Peach Payments Sender ID
	 */
	protected $sender = '';

	

	/**
	 * Peach Payments Channel
	 */
	public $channel = '';

	/**
	 * Store the credit cards
	 */
	public $card_storage = 'no';

	/**
	 * The Credit Cars available
	 */
	public $cards = array();

	public $cardPaymentOption=array('VISA','MASTER','AMEX','DINERS');

	/**
	 * Holds the currencies this gateway can use
	 */
	public $available_currencies = array();

	/**
	 * Hold a the base request before it is sent out.
	 */
	protected $base_request = '';

	/**
	 * Hold a the base request before it is sent out.
	 */
	protected $access_token = '';

	/**
	 * Hold the get_token_status response, used if the subscriptions plugin is active.
	 */
	protected $token_response = false;

	/**
	 * If 3DS is active
	 */
	protected $channel_3ds = false;

	/**
	 * If the Force Completed setting is active
	 */
	protected $force_completed = false;

	/**
	 * If the debug is active and we need to log the info
	 */
	public $debug = false;

	/** @var bool Whether or not logging is enabled */
	public static $log_enabled = false;
	/** @var WC_Logger Logger instance */
	public static $log = false;

	/**
	 * Constructor for the gateway.
	 *
	 * @access public
	 */
	public function __construct() {
		global $woocommerce;		
		$this->id 			= 'peach-payments';
		$this->method_title = __( 'Peach Payments', 'woocommerce-gateway-peach-payments' );
		$this->method_description = __( 'Take payments via card or checkout.', 'woocommerce-gateway-peach-payments' );
		$this->icon 		= '';
		
		$this->has_fields 	= true;
		$this->supports 			= array(
			'subscriptions',
			'products',
			'refunds',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_admin',
			'subscription_payment_method_change_customer',
			'subscription_date_changes',
			'multiple_subscriptions',
			'pre-orders'

		);

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		$this->available_currencies = array( 'ZAR' );
		$this->pp_plugin_version='1.3.2'; //This need to be upto date
		
		// Load the form fields.
		$this->init_form_fields();

		$this->order_button_text = __( 'Proceed to payment', 'woocommerce-gateway-peach-payment' );

		// Load the settings.
		$this->init_settings();
		if ( ! is_admin() ) {
			$this->setup_constants();
		}

		// Get setting values
		foreach ( $this->settings as $key => $val )
		$this->$key = $val;

		// Switch the Gateway to the Live url if it is set to live.
		$this->definePeachPaymentConstants();
		if ( $this->transaction_mode == 'LIVE' ) {			
			$this->gateway_url = PEACHPAYMENT_PAYMENT_GATEWAY_URL.'v1/checkouts';			
			$this->post_query_url = PEACHPAYMENT_PAYMENT_GATEWAY_URL.'v1/payment';	
			$this->registration_url = PEACHPAYMENT_PAYMENT_GATEWAY_URL.'v1/registrations';
			$this->refund_url = PEACHPAYMENT_PAYMENT_GATEWAY_URL.'v1/payments';
			$this->checkout_gateway_url = PEACHPAYMENT_CHECKOUT_LIVE.'checkout';	

			
		} else {			
			$this->gateway_url = PEACHPAYMENT_PAYMENT_GATEWAY_URL.'v1/checkouts';			
			$this->post_query_url = PEACHPAYMENT_PAYMENT_GATEWAY_URL.'v1/payment';
			$this->registration_url = PEACHPAYMENT_PAYMENT_GATEWAY_URL.'v1/registrations';
			$this->refund_url = PEACHPAYMENT_PAYMENT_GATEWAY_URL.'v1/payments';	
			$this->checkout_gateway_url = PEACHPAYMENT_CHECKOUT_TEST.'checkout';
			$this->send_debug_email = true;		
		}

		//set the debug to a boolean
		self::$log_enabled = true;
		$this->base_request = array(	     			      	
		      	
		      	'authentication.entityId'		=> $this->channel    	
		      	
				);

		// Hooks
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );  // WC >= 2.0
		add_action( 'admin_notices', array( $this, 'ecommerce_ssl_check' ) );

		// Add Copy and Pay form to receipt_page
		add_action( 'woocommerce_receipt_peach-payments', array( $this, 'receipt_page' ) );

		// API Handler
		add_action( 'woocommerce_api_wc_peach_payments', array( $this, 'process_payment_status' ) );

		add_action( 'woocommerce_api_wc_switch_peach_payments', array( $this, 'switch_payment_response' ) );
		/*For Webhook, set on the qa console for all MerChant..ie.
		http://52dbb183.ngrok.io/peach-wp-plugins/?wc-api=wc_switch_webhook_peach_payments*/
		add_action( 'woocommerce_api_wc_switch_webhook_peach_payments', array( $this, 'switch_payment_webhook_response' ) );
		/*For Webhook, set on the qa console for all MerChant..ie.
		http://52dbb183.ngrok.io/peach-wp-plugins/?wc-api=wc_payon_webhook_peach_payments*/
		add_action( 'woocommerce_api_wc_payon_webhook_peach_payments', array( $this, 'payon_payment_webhook_response' ) );

		

		//Scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_order_status_refunded',  array( $this, 'process_refund'), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'wpdocs_selectively_enqueue_admin_script' )  );

		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment12' ) );
		}

		//Allow the form tag
		add_filter( 'wp_kses_allowed_html', array(
			$this,
			'wp_kses_allowed_html',
		), 10, 2 );

		//add_action( 'woocommerce_payment_complete', array( $this, 'pp_payment_complete' ) );

		add_action( 'woocommerce_thankyou', array( $this, 'pp_payment_complete' ) );


		add_action( 'woocommerce_checkout_order_processed', array( $this, 'pp_payment_method_session' ) );

		


		
	}

	/**
	 * Define WC Constants.
	 */
	private function definePeachPaymentConstants() {	
		if (!defined('PEACHPAYMENT_PAYMENT_GATEWAY_URL')){	
			if ( $this->transaction_mode == 'LIVE' ) {
				define('PEACHPAYMENT_PAYMENT_GATEWAY_URL','https://oppwa.com/');
				define('PEACHPAYMENT_CHECKOUT_LIVE','https://secure.peachpayments.com/');
				
	     		
			}else{
				define('PEACHPAYMENT_PAYMENT_GATEWAY_URL','https://test.oppwa.com/');
				
				 $GetCurrentSiteUrl=site_url();
				if(($GetCurrentSiteUrl=='http://54.246.255.54') || ($GetCurrentSiteUrl=='http://127.0.0.1/peach-wp-plugins')){
					define('PEACHPAYMENT_CHECKOUT_TEST','https://testsecure.ppay.io/');
				}else{
					define('PEACHPAYMENT_CHECKOUT_TEST','https://testsecure.peachpayments.com/');
				}

				
				//For Wix testing 
				//define('PEACHPAYMENT_CHECKOUT_TEST','https://checkout-dev.ppay.io/');
			}
				define('PEACHPAYMENT_REGISTRATION_NOT_EXISTS','100.150.200');
				define('PEACHPAYMENT_REGISTRATION_NOT_CONFIRMED','100.150.201');
				define('PEACHPAYMENT_REGISTRATION_NOT_VALID','100.150.203');
				define('PEACHPAYMENT_REGISTRATION_DEREGISTERED','100.150.202');
				define('PEACHPAYMENT_REQUEST_SUCCESSFULLY_PROCESSED','/^(000\.400\.0[^3]|000\.400\.100)/');			
				define('PEACHPAYMENT_TRANSACTION_SUCCEEDED','/^(000\.000\.|000\.100\.1|000\.[36])/');
				define('PEACHPAYMENT_NO_PAYMENT_SESSION_FOUND','200.300.404');

		}
			
	}

	/**
	 * Setup constants.
	 *
	 * Setup common values and messages used by the peachpayment gateway.
	 *
	 * @since 1.0.0
	 */
	public function setup_constants() {

		
			// Messages
			// Error
			define( 'PPAYMENT_ERR_AMOUNT_MISMATCH', __( 'Amount mismatch', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_BAD_ACCESS', __( 'Bad access of page', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_BAD_SOURCE_IP', __( 'Bad source IP address', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_CONNECT_FAILED', __( 'Failed to connect to peachpayment', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_INVALID_SIGNATURE', __( 'Security signature mismatch', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_MERCHANT_ID_MISMATCH', __( 'Merchant ID mismatch', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_NO_SESSION', __( 'No saved session found for ITN transaction', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_ORDER_ID_MISSING_URL', __( 'Order ID not present in URL', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_ORDER_ID_MISMATCH', __( 'Order ID mismatch', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_ORDER_INVALID', __( 'This order ID is invalid', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_ORDER_NUMBER_MISMATCH', __( 'Order Number mismatch', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_ORDER_PROCESSED', __( 'This order has already been processed', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_PDT_FAIL', __( 'PDT query failed', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_PDT_TOKEN_MISSING', __( 'PDT token not present in URL', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_SESSIONID_MISMATCH', __( 'Session ID mismatch', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_ERR_UNKNOWN', __( 'Unkown error occurred', 'woocommerce-gateway-peach-payment' ) );

			// General
			define( 'PPAYMENT_MSG_OK', __( 'Payment was successful', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_MSG_FAILED', __( 'Payment has failed', 'woocommerce-gateway-peach-payment' ) );
			define( 'PPAYMENT_MSG_PENDING', __( 'The payment is pending. Please note, you will receive another Instant Transaction Notification when the payment status changes to "Completed", or "Failed"', 'woocommerce-gateway-peach-payment' ) );
			define( 'DEBUG_EMAIL', 'nitin+debug@atlogys.com');
			define('PPAYMENT_CURRENT_VERSION',$this->pp_plugin_version ); 

			do_action( 'woocommerce_gateway_peach_payments_setup_constants' );

	}

	/**
	 * Initialize Gateway Settings form fields
	 *
	 * @access public
	 * @return void
	 */
	function init_form_fields() {
		$this->form_fields = array(
			



			'enabled'          => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-gateway-peach-payments' ),
				'label'       => __( 'Enable Peach Payments', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),

			'transaction_mode' => array(
				'title'       => __( 'Transaction Mode', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'select',
				'description' => __( 'Set your gateway to live when you are ready.', 'woocommerce-gateway-peach-payments' ),
				'default'     => 'INTEGRATOR_TEST',
				'options'     => array(
					'INTEGRATOR_TEST' => 'Integrator Test',
					'CONNECTOR_TEST'  => 'Connector Test',
					'LIVE'            => 'Live',
				),
			),
			'title'            => array(
				'title'       => __( 'Title', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'text',
				'description' => __( 'Title of the payment section customers will see during checkout.', 'woocommerce-gateway-peach-payments' ),
				'default'     => __( 'Peach Payments', 'woocommerce-gateway-peach-payments' ),
			),
			'description'      => array(
				'title'       => __( 'Description', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'textarea',
				'description' => __( 'Helper text to give the customer more information about making payment. (Optional)', 'woocommerce-gateway-peach-payments' ),
				'default'     => 'Please select your preferred payment method',
			),

			
			
			'checkout_methods'            => array(
				'title'       => __( 'Payment Methods', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'multiselect',
				'description' => __( 'Select your payment methods. Please make sure you are activated for these payment methods with Peach Payments', 'woocommerce-gateway-peach-payments' ),
				'options'     => array(
					'VISA'   => 'VISA',
					'MASTER' => 'Master Card',
					'AMEX'   => 'American Express',
					'DINERS' => 'Diners Club',
					'EFTSECURE'   => 'EFT Secure',
					'MOBICRED' => 'Mobicred',
					'MASTERPASS'   => 'Masterpass',
					'OZOW' => 'Ozow',
				),
				'default'     => array('VISA','MASTER','EFTSECURE', 'MOBICRED', 'MASTERPASS', 'OZOW'  ),
				'class'       => 'chosen_select',
				'css'         => 'width: 450px;',
			),


			'card_storage'     => array(
				'title'       => __( 'Card Storage', 'woocommerce-gateway-peach-payments' ),
				'label'       => __( 'Enable Card Storage', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Allow customers to store cards against their account. Required for subscriptions and stored card payments.', 'woocommerce-gateway-peach-payments' ),
				'default'     => 'yes',
				'class'       => 'ppHideMe',
			),		


			'access_token'         => array(
				'title'       => __( 'Access Token', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'text',
				'description' => __( 'This is the key generated within the Peach Payments Console under Development > Access Token.', 'woocommerce-gateway-peach-payments' ),
				'default'     => '',
				'css'         => 'width: 600px;',
			),

		/*	'username'         => array(
				'title'       => __( 'User Login (deprecated)', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'text',
				'description' => __( 'This field will be deprecated on 31 July 2020. Make sure you have entered an Access Token before that time.', 'woocommerce-gateway-peach-payments' ),
				'default'     => '',
			),

			'password'         => array(
				'title'       => __( 'User Password (deprecated)', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'password',
				'description' => __( 'This field will be deprecated on 31 July 2020. Make sure you have entered a Access Token before that time.', 'woocommerce-gateway-peach-payments' ),
				'default'     => '',
			),*/
			'secret'         => array(
				'title'       => __( 'Secret Token', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'text',
				'description' => __( 'This is the key generated within the Peach Payments Console under Checkout > Live Configuration. (Only if EFT Secure, Ozow, Masterpass or Mobicred is enabled)' ),
				'default'     => '',
			),

			

			'channel_3ds'      => array(
				'title'       => __( '3DSecure Channel ID', 'wc-gateway-peach-payments' ),
				'type'        => 'text',
				'description' => __( 'The entityId that you received from Peach Payments.', 'wc-gateway-peach-payments' ),
				'default'     => '',
			),
			'channel'          => array(
				'title'       => __( 'Recurring Channel ID', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'text',
				'description' => __( 'This field is only required if you want to receive recurring payments. You will receive this from Peach Payments.', 'woocommerce-gateway-peach-payments' ),
				'default'     => '',
			),

			'card_webhook_key'          => array(
				'title'       => __( 'Card Webhook Decryption key', 'woocommerce-gateway-peach-payments' ),
				'type'        => 'text',
				'description' => __( 'You’ll receive this key from Peach Payments after your webhook is enabled.<br>To enable the webhook, please email support@peachpayments.com to setup '.site_url().'/ on your account', 'woocommerce-gateway-peach-payments' ),
				'default'     => '',
				'css'         => 'width: 600px;',
			),
			

			

			
			
		);
	}


	/**
	 * Register and enqueue specific JavaScript.
	 *
	 * @access public
	 * @return    void    Return early if no settings page is registered.
	 */
	public function enqueue_scripts() {		

		if ( is_checkout_pay_page() && !isset($_GET['registered_payment']) )  {					
			wp_enqueue_style( 'peach-payments-widget-css', plugins_url( 'assets/css/cc-form.css', dirname(__FILE__) ) );

		}
		wp_enqueue_script('pp_google_anlaytics_external', 'https://www.googletagmanager.com/gtag/js?id=UA-36515646-5');
		wp_enqueue_script('pp_google_anlaytics',plugins_url('assets/js/analytics.js', dirname(__FILE__)));

		if ( is_checkout() && !(is_wc_endpoint_url()))  {
			$analyticsData = array("pp_page_title"=>'CartCheckout',							
								);
						
			wp_enqueue_script('pp_google_anlaytics_page_view',plugins_url('assets/js/analytics_page_view.js', dirname(__FILE__)));
			wp_localize_script( "pp_google_anlaytics_page_view", "merchant", $analyticsData );
				

		}
	

	}

	/**
	 * Enqueue a script in the WordPress admin, excluding edit.php.
	 *
	 * @param int $hook Hook suffix for the current admin page.
	 */
	function wpdocs_selectively_enqueue_admin_script( $hook ) {
	    
	   
	    wp_enqueue_script('pp_google_anlaytics_external', 'https://www.googletagmanager.com/gtag/js?id=UA-36515646-5');
	    wp_enqueue_script('pp_google_anlaytics',plugins_url('assets/js/analytics.js', dirname(__FILE__)));	    
	     wp_enqueue_script( 'my_custom_script',  plugins_url( 'assets/js/pp_admin_setting.js', dirname(__FILE__) ), array(), '1.0' );

	    
	    if( isset($_GET['section']) && (sanitize_text_field($_GET['section'])=='peach-payments' )){
		    $analyticsData = array("pp_page_title"=>'ConfigurationForm');
			wp_enqueue_script('pp_google_anlaytics_page_view',plugins_url('assets/js/analytics_page_view.js', dirname(__FILE__)));
			wp_localize_script( "pp_google_anlaytics_page_view", "merchant", $analyticsData );
		}
	}
	




	/**
	 * Check if SSL is enabled.
	 *
	 * @access public
	 * @return void
	 */
	function ecommerce_ssl_check() {
		if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && $this->enabled == 'yes' ) {
			echo '<div class="error"><p>We have detected that you currently don\'t have SSL enabled. Peach Payments recommends using SSL on your website. Please enable SSL and ensure your server has a valid SSL certificate.</p></div>';
		}
	}

	/**
	 * Logging method.
	 * @param string $message
	 */
	public static function log( $message ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			//echo $message;
			self::$log->add( 'woocommerce-gateway-peach-payments', $message );
		}
	}


	/**
	 * Grab the ID from the WC Order Object, handles 2.5 -> 3.0 compatibility
	 *
	 * @param object $order WC_Order
	 * @return string post_id
	 */
	public function get_order_id( $order ) {
		$return = 0;
		if ( is_object( $order ) ) {
			if ( defined( 'WC_VERSION' ) && WC_VERSION >= 2.6 ) {
				$return = $order->get_id();
			} else {
				$return = $order->id;
			}
		}
		return $return;
	}

	/**
	 * Grab the Customer ID from the WC Order Object, handles 2.5 -> 3.0 compatibility
	 *
	 * @param object $order WC_Order
	 * @return string user_id
	 */
	public function get_customer_id( $order ) {
		$return = 0;
		if ( is_object( $order ) ) {
			if ( defined( 'WC_VERSION' ) && WC_VERSION >= 2.6 ) {
				$return = $order->get_customer_id();
			} else {
				$return = $order->user_id;
			}
		}
		return $return;
	}

	/**
	 * Grab the product from the $item WC Order Object, handles 3.0 compatibility
	 *
	 * @param object $item
	 * @param object $order WC_Order
	 * @return string user_id
	 */
	public function get_item_product( $item = false, $order = false ) {
		$return = 0;
		if ( false !== $item ) {
			if ( defined( 'WC_VERSION' ) && WC_VERSION >= 3.0 ) {
				$return = $item->get_product();
			} else {
				$return = $order->get_product_from_item( $item );
			}
		}
		return $return;
	}

	/**
	 * Adds option for registering or using existing Peach Payments details
	 *
	 * @access public
	 * @return void
	 **/
	function payment_fields() {
                  /*  echo  $fileName= ABSPATH.'response.txt';
                        
                        if (file_exists($fileName)){
                            $fp = fopen($fileName, 'a+') or die("can't open file");
                        }else{
                            $fp= fopen($fileName, 'x+');// or die("can't open file");
                        }

                        fwrite($fp,"----------- Only Test new------------- \n ");
                       // fwrite($fp,print_r($parsed_response, TRUE)."\n");*/
                $description = $this->get_description();
                $hasCardPaymentEnabled=false;
                $hasCardStoragePaymentEnabled=false;
                $hasSwitchPaymentEnabled=false;
                if ( $description ) {
                        echo wpautop( wptexturize( $description ) ); // @codingStandardsIgnoreLine.
                }
                
                if(($this->enabled == 'yes') && (!empty($this->checkout_methods))):
                ?>
                
                <fieldset>
                    <p class="form-row form-row-wide">  
                    	<?php if(!empty($this->checkout_methods)): ?>   
                                    <input type="radio" id="dontsave" name="peach_payment_id" style="width:auto;" value="dontsave" <?php if ( !($hasCardStoragePaymentEnabled  )){ echo "checked";} ?> /> <label style="display:inline;" for="dontsave"><?php esc_html_e( 'Pay with Card', 'woocommerce-gateway-peach-payments' ); ?></label><br />
                                <?php endif; ?>          
                        <?php  if( is_user_logged_in() && $this->card_storage == 'yes' ): 
                                    $hasCardStoragePaymentEnabled=true; ?>
                                    <?php if ( $credit_cards = get_user_meta( get_current_user_id(), '_peach_payment_id', false ) ) : ?>

                                        <?php foreach ( $credit_cards as $i => $credit_card ) : ?>
                                            <input type="radio" id="peach_card_<?php echo esc_attr( $i ); ?>" name="peach_payment_id" style="width:auto;" value="<?php echo esc_attr( $i ); ?>" />
                                            <label style="display:inline;" for="peach_card_<?php echo esc_attr( $i ); ?>"><?php echo wp_kses_post( get_card_brand_image( $credit_card['brand'] ) ); ?> <?php echo '**** **** **** ' . esc_attr( $credit_card['active_card'] ); ?> (<?php echo esc_attr( $credit_card['exp_month']) . '/' . esc_attr( $credit_card['exp_year'] ) ?>)</label><br />
                                        <?php endforeach; ?>

                                        <br /> <a class="button" style="float:right;" href="<?php echo esc_url( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ); ?>#saved-cards"><?php esc_html_e( 'Manage cards', 'woocommerce-gateway-peach-payments' ); ?></a>

                                    <?php endif; ?>

                                    <input type="radio" id="saveinfo" name="peach_payment_id" style="width:auto;" value="saveinfo" <?php if ( $hasCardStoragePaymentEnabled  ){ echo "checked";} ?> /> <label style="display:inline;" for="saveinfo"><?php esc_html_e( 'Pay with Card and store for future use', 'woocommerce-gateway-peach-payments' ); ?></label><br />
                                <?php endif;?>

                                
                                 
                    

                    <?php if(!empty($this->checkout_methods)){ ?>                
                             <?php foreach ( $this->checkout_methods as $payment_options ) : ?>                                 
                                
                                 <?php if($payment_options=='EFTSECURE') :?>
                                     <input type="radio" id="eft_payment" name="peach_payment_id" style="width:auto;" value="EFTSECURE"  <?php if ( !($hasSwitchPaymentEnabled) ){ echo "checked"; $hasSwitchPaymentEnabled=true;} ?>/> <label style="display:inline;" for="eft_payment"><?php esc_html_e( 'EFT Secure', 'woocommerce-gateway-peach-payments' ); ?></label><br />
                                 <?php endif;?>
                                 
                                 <?php if($payment_options=='MOBICRED') :?>
                                     <input type="radio" id="mobicred_payment" name="peach_payment_id" style="width:auto;" value="MOBICRED"<?php if ( !($hasSwitchPaymentEnabled) ){ echo "checked"; $hasSwitchPaymentEnabled=true;} ?> /> <label style="display:inline;" for="mobicred_payment"><?php esc_html_e( 'Mobicred', 'woocommerce-gateway-peach-payments' ); ?></label><br />
                                 <?php endif;?>    
                                 <?php if($payment_options=='MASTERPASS') :?>
                                     <input type="radio" id="masterpass_payment" name="peach_payment_id" style="width:auto;" value="MASTERPASS" <?php if ( !($hasSwitchPaymentEnabled) ){ echo "checked"; $hasSwitchPaymentEnabled=true;} ?> /> <label style="display:inline;" for="masterpass_payment"><?php esc_html_e( 'Masterpass', 'woocommerce-gateway-peach-payments' ); ?></label><br />
                                 <?php endif;?>
                                 <?php if($payment_options=='OZOW') :?>
                                     <input type="radio" id="ozow_payment" name="peach_payment_id" style="width:auto;" value="OZOW" <?php if ( !($hasSwitchPaymentEnabled) ){ echo "checked"; $hasSwitchPaymentEnabled=true;} ?> /> <label style="display:inline;" for="ozow_payment"><?php esc_html_e( 'Ozow', 'woocommerce-gateway-peach-payments' ); ?></label><br />
                                 <?php endif;?>    
                                 <?php endforeach;?>
                                 
                             
                            <div class="clear"></div>  
                        
                    <?php } //If payment Method Empty?>
                    </p>
                </fieldset>

                 <?php endif; ?>

                <?php

            }




	/**
	 * Process the payment and return the result
	 *
	 * @access public
	 * @param int $order_id
	 * @return array
	 */
	function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );
		$switchPaymentOption=array('EFTSECURE','MOBICRED','MASTERPASS','OZOW');
		
		if (   class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order->id ) ) {
     	
				// perform a simple authorization/void (or whatever method your gateway requires)
				// to get the payment token that may be used later to charged the customer's payment method

				// mark order as pre-ordered, this will also save meta that indicates a payment token
				// exists for the pre-order so that it may be charged upon release		          		
			
				$selectedPaymentOption=$_POST['peach_payment_id'];
                if ( isset( $_POST['peach_payment_id'] ) && ( in_array($selectedPaymentOption,$switchPaymentOption  ) ) ){
                    
                	if (WC_Pre_Orders_Order::order_will_be_charged_upon_release($order)) {
                    	throw new Exception( __( 'Please choose the credit card payment method.', 'woocommerce-gateway-peach-payments' ) );
                	}

                	if(  in_array( $selectedPaymentOption ,$switchPaymentOption  ) ){
                		return $this->process_checkout_order( $order_id );
                	}
                }
                update_post_meta($order->id, "_checkout_payment_option", '');
				return $this->process_pre_order( $order_id );
     		
		}
		try {
			if ( isset( $_POST['peach_payment_id'] ) && ( in_array(sanitize_text_field( $_POST['peach_payment_id'] ),$switchPaymentOption  ) ) ){						
                        return $this->process_checkout_order( $order_id );

            }

            update_post_meta($order->id, "_checkout_payment_option", '');

			if ( isset( $_POST['peach_payment_id'] ) && ctype_digit( $_POST['peach_payment_id'] ) ) {


				$payment_ids = get_user_meta( $this->get_customer_id( $order ), '_peach_payment_id', false );
				$payment_id  = sanitize_text_field($payment_ids[ $_POST['peach_payment_id'] ]['payment_id']);
				//throw exception if payment method does not exist
				if ( ! isset( $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'] ) ) {
					//$this->log( '232 Invalid Payment Method ' . $order_id );
					throw new Exception( __( 'Invalid Payment Method', 'woocommerce-gateway-peach-payments' ) );
				}

				$redirect_url = $this->execute_post_payment_request( $order, $order->get_total(), $payment_id );
				//throw exception if payment is not accepted
				if ( is_wp_error( $redirect_url ) ) {
					throw new Exception( $redirect_url->get_error_message() );
				}

				return array(
					'result'   => 'success',
					'redirect' => $redirect_url,
				);
			} else {

				$order_request = array(
						'customParameters[PAYMENT_PLUGIN]'	=> 'WORDPRESS',
			     		'merchantTransactionId'				=> $order->get_order_number(),
			     		'customer.merchantCustomerId'		=> $this->get_customer_id( $order ),
			     		'customer.givenName'				=> $order->billing_first_name." ".$order->billing_last_name,				     	       		
				     	'billing.street1'					=> $order->billing_address_1,        		
				        'billing.postcode'					=> $order->billing_postcode,
				        'billing.city'						=> $order->billing_city,        		
				        'billing.state'						=> $order->billing_state,
				        'billing.country'					=> $order->billing_country,				        
				        'customer.email'					=> $order->billing_email,
				        'customer.ip'						=> $_SERVER['REMOTE_ADDR']
			     		);

				if ( sanitize_text_field( $_POST['peach_payment_id'] ) == 'saveinfo' ) {
					$payment_request = array(
						'paymentType'						=> 'DB',
						'createRegistration'				=> true
				      	);

					if ( $this->transaction_mode == 'CONNECTOR_TEST' || 'LIVE' ) {
						$payment_request['currency'] = get_option( 'woocommerce_currency' );
						$payment_request['amount'] =self::get_order_prop( $order, 'order_total' );
					}

					
				} 
				else {
					$payment_request = array(
						'paymentType'						=> 'DB',
						'merchantInvoiceId'					=> $order->get_order_number(),
			     		'amount'							=> self::get_order_prop( $order, 'order_total' ),
				      	'currency'							=> get_option( 'woocommerce_currency' ) 
				      	);					
				}
				if($this->access_token!=''){
					
					$request = array_merge( $payment_request, $order_request );
					$request['authentication.entityId'] = $this->channel_3ds;
					$json_token_response = $this->generate_token_header( $request );
				}else{
					$order_request = array_merge( $order_request, $this->base_request );
					$request = array_merge( $payment_request, $order_request );
					$request['authentication.entityId'] = $this->channel_3ds;
					$json_token_response = $this->generate_token( $request );
				}
				
				if ( is_wp_error( $json_token_response ) ) {
					throw new Exception( $json_token_response->get_error_message() );
				}

				//token received - offload payment processing to copyandpay form
				return array(
		          'result'   => 'success',
		          'redirect' => $order->get_checkout_payment_url( true )
		        );

			}
		} catch ( Exception $e ) {
			$error_message = __( 'Error:', 'woocommerce-gateway-peach-payments' ) . ' "' . $e->getMessage() . '"';
			wc_add_notice( $error_message, 'error' );
			$this->log( $error_message );
			return;
		}

	}

	/**
	 * Trigger the payment form for the payment page shortcode.
	 *
	 * @access public
	 * @param object $order
	 * @return null
	 */
	function receipt_page( $order_id ) {
		global $woocommerce;
		$order = wc_get_order( $order_id );	
 		$ppPaymentMethod = WC()->session->get( 'ppSessionPaymentMethod' );
 		$payment_token = get_post_meta( $order_id, '_peach_payment_token', true );
		$checkoutPaymentMethod = get_post_meta( $order_id, '_checkout_payment_option', true );
		if($ppPaymentMethod=='saveinfo'){
			$ppGetPaymentMethod="card_with_storage";
		}elseif($ppPaymentMethod=='dontsave'){
			$ppGetPaymentMethod="card_without_storage";
		}else{
			$ppGetPaymentMethod=strtolower($ppPaymentMethod);
		}

		
		$cartCounter= $woocommerce->cart->cart_contents_count;
		$items = $woocommerce->cart->get_cart();		
		$getBasketArray=$this->ppBasketValues($items,$order_id);
		$analyticsData = array("siteurl"=>site_url(),
							   "transaction_id"=>$order_id,
							   "pp_version"=>PPAYMENT_CURRENT_VERSION,
							   "wc_version"=>WC()->version,
							   "wp_version"=>get_bloginfo( 'version' ),
								"amount"=>self::get_order_prop( $order, 'order_total' ),
								"pp_mode"=>$this->transaction_mode,							
								'basket'=>$getBasketArray,
								"payment_method"=>$ppGetPaymentMethod,
								

								);
		wp_enqueue_script('peachpaymentGoogleTagPaymentStartJs',plugins_url('assets/js/pp_invoking_plugin.js', dirname(__FILE__)));
		wp_localize_script( "peachpaymentGoogleTagPaymentStartJs", "merchant", $analyticsData );
		
		if( $checkoutPaymentMethod !=''){			
			echo $this->generate_checkout_form( $order_id );					
		}else{
				//For Event Analytics
				if(is_wc_endpoint_url( 'order-pay' )){
					$analyticsPageViewData = array("pp_page_title"=>'CardPayment');	
					wp_enqueue_script('pp_google_anlaytics_page_view',plugins_url('assets/js/analytics_page_view.js', dirname(__FILE__)));
					wp_localize_script( "pp_google_anlaytics_page_view", "merchant", $analyticsPageViewData );
				}
				$analyticsData = array("siteurl"=>site_url(),
									   "transaction_id"=>$order_id,
									   "amount"=>self::get_order_prop( $order, 'order_total' ));
				wp_enqueue_script('peachpaymentGoogleTagCheckoutPaymentStartJs',plugins_url('assets/js/pp_payon_payment.js', dirname(__FILE__)));
				wp_localize_script( "peachpaymentGoogleTagCheckoutPaymentStartJs", "merchant", $analyticsData );	
				// End Here
				if ( isset( $_GET['registered_payment'] ) && wp_verify_nonce( $_GET['registered_payment'] )  ) {
					$status = sanitize_text_field($_GET['registered_payment']);
					$this->process_registered_payment_status( $order_id, $status );
				} else {
					echo  $this->generate_peach_payments_form( $order_id  );
				}
		}
	}


	public function generate_checkout_form( $order_id ) {
		global $woocommerce;
		$order = wc_get_order( $order_id );
		$checkoutPaymentMethod = get_post_meta( $order_id, '_checkout_payment_option', true );
		$testingwhitelist = array('127.0.0.1', "::1");
		if(in_array($_SERVER['REMOTE_ADDR'], $testingwhitelist)){
		    $merchant_shopperResultUrl = add_query_arg( 'wc-api', 'wc_switch_peach_payments', home_url( '/' ) );	
			$merchant_endpoint = wc_get_checkout_url();
			$merchant_notificationUrl = add_query_arg( 'wc-api', 'wc_switch_peach_payments', home_url( '/' ) );
		}else{
			$merchant_shopperResultUrl=add_query_arg( 'wc-api', 'wc_switch_peach_payments', home_url( '/' ) );			
			$merchant_endpoint = wc_get_checkout_url();
			$merchant_notificationUrl = add_query_arg( 'wc-api', 'wc_switch_peach_payments', home_url( '/' ) );
		}
		
		
		if ( $this->transaction_mode == 'CONNECTOR_TEST' || 'LIVE' ) {
			$payment_request['currency'] = get_option( 'woocommerce_currency' );
			$payment_request['amount'] = self::get_order_prop( $order, 'order_total' );
		}

					
				
		$payment_request = array(
			'paymentType'						=> 'DB',
			
     		'amount'							=> self::get_order_prop( $order, 'order_total' ),
	      	'currency'							=> get_option( 'woocommerce_currency' ) 
	      	);					
				
		$order_request = array(
			'plugin'	=> 'woocommerce',
     		'merchantTransactionId'				=> $order->get_order_number(),
     		'customer.merchantCustomerId'		=> $this->get_customer_id( $order ),
     		'customer.givenName'				=> $order->billing_first_name." ".$order->billing_last_name,
	     	'billing.street1'					=> $order->billing_address_1,        		
	        'billing.postcode'					=> $order->billing_postcode,
	        'billing.city'						=> $order->billing_city,        		
	        'billing.state'						=> $order->billing_state,
	        'billing.country'					=> $order->billing_country,				        
	        'customer.email'					=> $order->billing_email,
	        'customer.ip'						=> $_SERVER['REMOTE_ADDR'],
	        'shopperResultUrl'					=> $merchant_shopperResultUrl,
	        /*'cancelUrl'							=> $merchant_endpoint,*/
			'defaultPaymentMethod'				=> $checkoutPaymentMethod,
			'notificationUrl'					=> $merchant_notificationUrl,
			     		);

				

		

		//$order_request = array_merge( $order_request, $this->base_request );
		$request = array_merge( $payment_request, $order_request );
		$request['authentication.entityId'] = $this->channel_3ds; //Later will be access_token
		$request=$this->peachPaymentsignData($request, $includeNonce = true,$order->id);		
		$checkout_args_array = array();
		foreach ( $request as $key => $value ) {
			$checkout_args_array[] = '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}
		$cartCounter= $woocommerce->cart->cart_contents_count;
		$items = $woocommerce->cart->get_cart();		
		$getBasketArray=$this->ppBasketValues($items,$order_id);		
		$analyticsData = array("siteurl"=>site_url(),
							   "transaction_id"=>$order_id,
							   "pp_version"=>PPAYMENT_CURRENT_VERSION,
							   "wc_version"=>WC()->version,
							   "wp_version"=>get_bloginfo( 'version' ),
							   "amount"=>self::get_order_prop( $order, 'order_total' ),
							   "checkoutPaymentMethod"=>strtolower($checkoutPaymentMethod),
							   "basket"=>$getBasketArray);

		wp_enqueue_script('pp_event_switch_paymentjs',plugins_url('assets/js/pp_event_switch_payment.js', dirname(__FILE__)));
		wp_localize_script( "pp_event_switch_paymentjs", "merchant", $analyticsData );

		wp_enqueue_script('peachpaymentSwitchPaymentJs',plugins_url('assets/js/switch_payment.js', dirname(__FILE__)));	
		
		return   '<form action="' . esc_url( $this->checkout_gateway_url ) . '" method="post" id="checkout_payment_form">
				' . implode( '', $checkout_args_array ) . '
				<input type="submit" class="button-alt" id="submit_checkout_payment_form" value="' . __( 'Pay via Checkout', 'woocommerce-gateway-peach-payments' ) . '" />
							
				</form>';
	}

	/**
	 * Generate the Peach Payments Copy and Pay form
	 *
	 * @access public
	 * @param mixed $order_id
	 * @return string
	 */
	function generate_peach_payments_form( $order_id ) {
		$supported_cards='';
		$order = wc_get_order( $order_id );
		$payment_token = get_post_meta( $order_id, '_peach_payment_token', true );		
		$merchant_endpoint = add_query_arg( 'wc-api', 'WC_Peach_Payments', home_url( '/' ) );
		//print_r($this->cardPaymentOption);
		foreach ( $this->checkout_methods as $payment_options ){
			
			 if(in_array($payment_options, $this->cardPaymentOption)){
			 	//echo $payment_options;
			 	$supported_cards .= $payment_options." ";
			 }
		}
		wp_enqueue_script( 'peachpaymentWidgetJs', PEACHPAYMENT_PAYMENT_GATEWAY_URL . 'v1/paymentWidgets.js?checkoutId='. $payment_token , array(), null,  false );				 
		$checkoutCode ='<form action="' . $merchant_endpoint . '" class="paymentWidgets">' . $supported_cards . '</form>';
		
		
		return $checkoutCode;

	}

	

	/**
	 * WC API endpoint for Copy and Pay response
	 *
	 * @access public
	 * @return void
	 */
	function process_payment_status() {
		$parsed_response='';
		$token = sanitize_text_field($_GET['id']);
		$parsed_response = $this->get_token_status( $token );

		
		if(isset($token) && !empty($token)){
			if ( is_wp_error( $parsed_response ) ) {
				$order->update_status('failed', __('Payment Failed: Couldn\'t connect to gateway server - Peach Payments', 'woocommerce-gateway-peach-payments') );
				wp_safe_redirect( $this->get_return_url( $order ) );
				exit;
			}		
			$order_id        = $this->checkOrderNumberValidate($parsed_response->merchantTransactionId);
			$order           = wc_get_order( $order_id );
			if ( false !== $order ) {
				$current_order_status = $order->get_status();
				$force_complete = false;

				if ( 'complete' !== $current_order_status && 'processing' !== $current_order_status ) {

					switch ($parsed_response->result->code) {
			             case PEACHPAYMENT_NO_PAYMENT_SESSION_FOUND:
			             $order->update_status('failed', __('Payment Failed: Couldn\'t connect to gateway - Peach Payments', 'woocommerce-gateway-peach-payments') );
			                $redirect_url = $this->get_return_url( $order );  
			                wp_safe_redirect( $redirect_url );
			                exit;
			             break;                             
			                                         
			                                
			        }
					$preOrderStatus = get_post_meta( $order_id, '_wc_pre_orders_is_pre_order', true );

					//If you are using a Stored card,  or not storing a card at all this will process the completion of the order. 
					if ( $parsed_response->paymentType  == 'DB' || $parsed_response->paymentType  == 'PA' ) {
							if($parsed_response->registrationId!=''){										
									switch ($parsed_response->result->code) {
										 case PEACHPAYMENT_REGISTRATION_NOT_EXISTS:
			                                    $order->update_status('pending', __('Registration Failed: Card Registration Not Exists - Peach Payments', 'woocommerce-gateway-peach-payments') );
			                                    wp_safe_redirect( $order->get_checkout_payment_url( true ) );
			                                    exit;
			                                 break;
			                              case PEACHPAYMENT_REGISTRATION_NOT_CONFIRMED:
			                                    $order->update_status('pending', __('Registration Failed: Card registration was not Confirmed - Peach Payments', 'woocommerce-gateway-peach-payments') );
			                                    wp_safe_redirect( $order->get_checkout_payment_url( true ) );
			                                    exit;
			                                 break;
										 case PEACHPAYMENT_REGISTRATION_NOT_VALID:
										 	$order->update_status('pending', __('Registration Failed: Card registration was not accpeted - Peach Payments', 'woocommerce-gateway-peach-payments') );
											wp_safe_redirect( $order->get_checkout_payment_url( true ) );
											exit;
										 break;
										 case PEACHPAYMENT_REGISTRATION_DEREGISTERED:
										 	$order->update_status('pending', __('Registration Failed: Card registration is already deregistered - Peach Payments', 'woocommerce-gateway-peach-payments') );
											wp_safe_redirect( $order->get_checkout_payment_url( true ) );								
											exit;
										 break;
										 default:										
			                             break;				
									
								}					
							}

						if ( preg_match(PEACHPAYMENT_REQUEST_SUCCESSFULLY_PROCESSED,$parsed_response->result->code) || preg_match(PEACHPAYMENT_TRANSACTION_SUCCEEDED,$parsed_response->result->code)) {
							if($parsed_response->registrationId!=''){
								$this->add_customer( $parsed_response );
							}
							update_post_meta( $this->get_order_id( $order ), '_subscription_payment_id', $parsed_response->id );
							if($preOrderStatus==1){						
										WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );						
									
							}else{
									$order->payment_complete();
							}
							
							
							$order->add_order_note( sprintf(__('Payment Completed : Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
							wp_safe_redirect( $this->get_return_url( $order ) );
							exit;
						} 
						else {
							$order->update_status('failed');
							wp_safe_redirect( $this->get_return_url( $order ) );
							exit;
						}
						
					}

				}
			}

		}
	}

	/**
	 * Checks the order for virtual or downloadable products
	 * @param $order \WC_Order
	 * @return boolean
	 */
	public function check_orders_products( $order = false ) {
		$force_complete = false;
		$mixed_products = false;

		if ( false !== $order && count( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				$_product = $this->get_item_product( $item, $order );
				if ( $_product ) {
					if ( $_product->is_downloadable() || $_product->is_virtual() ) {
						$force_complete = true;
					} else {
						$mixed_products = true;
					}
				}
			}
		}
		if ( true === $mixed_products ) {
			$force_complete = false;
		}
		return $force_complete;
	}


	/**
	 * Process respnse from registered payment request on POST api
	 *
	 * @access public
	 * @param string $order_id
	 * @param string $status
	 * @return void
	 */
	function process_registered_payment_status( $order_id, $status ) {
		

		$order = wc_get_order( $order_id );

		if ( $status == 'NOK' ) {
			$order->update_status('failed');
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}
		elseif ( $status == 'ACK' ) {
			$order->payment_complete();
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}
	}

	/**
	 * Generate token for Copy and Pay API
	 *
	 * @access public
	 * @param array $request
	 * @return object
	 */
	function generate_token( $request ) {
		global $woocommerce;		
		$response = wp_remote_post( $this->gateway_url, array(
			'method'		=> 'POST', 
			'body' 			=> $request,
			'timeout' 		=> 70,
			'sslverify' 	=> false,
			'user-agent' 	=> 'WooCommerce ' . $woocommerce->version
		));
		//print_r($response);die();

		if ( is_wp_error($response) )
			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

		if( empty($response['body']) )
			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

		$parsed_response = json_decode( $response['body'] );
		//echo "<pre>";print_r($parsed_response);
		//echo "<pre>";echo $parsed_response->id;die();
		// Handle response
		if ( ! empty( $parsed_response->error ) ) {

			return new WP_Error( 'peach_error', $parsed_response->error->message );

		} else {
			update_post_meta( $this->checkOrderNumberValidate($request['merchantTransactionId']), '_peach_payment_token', $parsed_response->id );
		}

		return $parsed_response;
		
	}

	function generate_token_header( $request ) {
		global $woocommerce;		
		$response = wp_remote_post( $this->gateway_url, array(
			'method'		=> 'POST', 
			'headers' 		=> array('Authorization' => 'BEARER '.$this->access_token),
			'body' 			=> $request,
			'timeout' 		=> 70,
			'sslverify' 	=> false,
			'user-agent' 	=> 'WooCommerce ' . $woocommerce->version
		));
		
		if ( is_wp_error($response) )
			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

		if( empty($response['body']) )
			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

		$parsed_response = json_decode( $response['body'] );		
		// Handle response
		if ( ! empty( $parsed_response->error ) ) {

			return new WP_Error( 'peach_error', $parsed_response->error->message );

		} else {
			update_post_meta( $this->checkOrderNumberValidate($request['merchantTransactionId']), '_peach_payment_token', $parsed_response->id );
		}

		return $parsed_response;
		
	}

	/**
	 * Get status of token after Copy and Pay API
	 *
	 * @access public
	 * @param string $token
	 * @return object
	 */
	function get_token_status( $token ) {
		global $woocommerce;
		
		 $url = $this->gateway_url . "/" . $token."/payment";
		
		$response = wp_remote_post( $url, array(
			'method'		=> 'GET', 
			'timeout' 		=> 70,
			'sslverify' 	=> false,
			'user-agent' 	=> 'WooCommerce ' . $woocommerce->version
		));
		
		if ( is_wp_error($response) )
			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

		if( empty($response['body']) )
			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

		$get_response = json_decode( $response['body'] );		
		return $get_response;
	}

	/**
	 * Execute Single payment request through POST endpoint and returns redirect URL
	 *
	 * @access public
	 * @param object $order
	 * @param string $amount
	 * @param string $payment_method_id
	 * @return string
	 */
	function execute_post_payment_request( $order, $amount, $payment_method_id,$payment_type='DB' ) {
		global $woocommerce;		
		$merchant_endpoint = add_query_arg( 'wc-api', 'WC_Peach_Payments', home_url( '/' ) );
		
				

		$payment_request = array(
							      	'customParameters[PAYMENT_PLUGIN]'	=> 'WORDPRESS',
							      	'paymentType'					=> $payment_type,
							      	'merchantTransactionId'			=> $order->get_order_number(),
						     		'customer.merchantCustomerId'	=> $this->get_customer_id( $order ),  
							      	'merchantInvoiceId'				=> $order->get_order_number(),
						     		'amount'						=> $amount,
							      	'currency'						=> get_option( 'woocommerce_currency' ),
							      	'shopperResultUrl'				=> $merchant_endpoint,
							      	'authentication.entityId'		=> $this->channel,
							      	'recurringType'					=> 'REPEATED' 	      	
							      );

		
		
		if($this->access_token!=''){
			$headers = array('Authorization'=> 'BEARER '.$this->access_token);
			$request = $payment_request;

		}
		
		
		$ppUrl= $this->registration_url."/".$payment_method_id."/payments";
		$ppMethod='POST';
		$sslverify='false';
		$response= $this->pp_remote_post_data($ppUrl,$request, $ppMethod, $sslverify, $headers);


		if ( is_wp_error($response) )
			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

		if( empty($response['body']) )
			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

		$parsed_response = json_decode( $response['body'] );		
	    //create redirect link
	    $redirect_url = $this->get_return_url( $order );
	    $order_id = $this->checkOrderNumberValidate($parsed_response->merchantTransactionId);
	    $preOrderStatus = get_post_meta( $order_id, '_wc_pre_orders_is_pre_order', true );

	    if ( preg_match(PEACHPAYMENT_REQUEST_SUCCESSFULLY_PROCESSED,$parsed_response->result->code) || preg_match(PEACHPAYMENT_TRANSACTION_SUCCEEDED,$parsed_response->result->code)) {
	    		update_post_meta( $order_id, '_subscription_payment_id', $parsed_response->id );
	    		if($preOrderStatus=='1'){
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
				$order->add_order_note( sprintf(__('Payment Completed : Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
				}else{
					$order->payment_complete();
					$order->add_order_note( sprintf(__('Payment Completed : Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
				}
				return add_query_arg( 'registered_payment', 'ACK', $redirect_url );
			} 
			else {
				$order->update_status('failed', sprintf(__('Payment Failed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
				return add_query_arg ('registered_payment', 'NOK', $redirect_url );
			}
	}

	

	/**
	 * add_customer function.
	 *
	 * @access public
	 * @param object $response
	 * @return void
	 */
	function add_customer( $response ) {

		$user_id = $response->customer->merchantCustomerId;

		if ( isset( $response->card->last4Digits ) )
			add_user_meta( $user_id, '_peach_payment_id', array(
				'payment_id' 	=> $response->registrationId,
				'active_card' 	=> $response->card->last4Digits,
				'brand'			=> $response->paymentBrand,
				'exp_year'		=> $response->card->expiryYear,
				'exp_month'		=> $response->card->expiryMonth,
			) );
	}

	/**
	 * Allow the form tag in wp_kses_post()
	 */
	public function wp_kses_allowed_html( $allowedtags, $context ) {
		if ( ! isset( $allowedtags['form'] ) ) {
			$allowedtags['form']['id'] = 1;
			$allowedtags['form']['action'] = 1;
		}
		return $allowedtags;
	}



 /*
    *   Function Name : process_pre_order
    *   Description   : Process the pre orders payment and return the result
    *   Author        : Nitin Sharma
    *   Created On    : 27-Oct-2016
    *   Parameters    : int $order_id 
    *   Return Value  : array
    */

    function process_pre_order( $order_id ) {
     	global $woocommerce;

     	$order = wc_get_order( $order_id );
		$payment_id = get_post_meta( $order_id, '_subscription_payment_id', true );	
		     	
     	try {
     		//If pre order convert to normal
     		if($payment_id!=''){     			
     			$this->process_pre_order_release_payment12( $order );
     			exit;
     		}
     		// if pre-order type will be charged upon release
    			if (WC_Pre_Orders_Order::order_will_be_charged_upon_release($order)) {
    			
        			$PreOrderType='DB';
        			// get pre-order amount
        			$order_items = $order->get_items();
    				$product = $order->get_product_from_item( array_shift( $order_items ) );
					$preOrderAmount = WC_Pre_Orders_Product::get_pre_order_fee( $product );
					if ( $preOrderAmount <= 0 ){
						$preOrderAmount=1;
						$PreOrderType='PA';
					}

    			}else{
    				$PreOrderType='DB';
    				$preOrderAmount=self::get_order_prop( $order, 'order_total' );
    			}
     		if ( isset( $_POST['peach_payment_id'] ) && ctype_digit( $_POST['peach_payment_id'] ) ) {

				
				$payment_ids = get_user_meta( $this->get_customer_id( $order ), '_peach_payment_id', false );
				$payment_id = sanitize_text_field($payment_ids[ $_POST['peach_payment_id'] ]['payment_id']);

				
				//throw exception if payment method does not exist
				if ( ! isset( $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'] ) ) {
					throw new Exception( __( 'Invalid Payment Method', 'woocommerce-gateway-peach-payments' ) );
				}
				
				$redirect_url = $this->execute_post_payment_request( $order, $preOrderAmount, $payment_id,$PreOrderType );				
				//throw exception if payment is not accepted
				if ( is_wp_error( $redirect_url ) ) {
					throw new Exception( $redirect_url->get_error_message() );
				}

				return array(
				          'result'   => 'success',
				          'redirect' => $redirect_url
				        );
			}
			elseif ( (WC_Pre_Orders_Order::order_will_be_charged_upon_release($order)) && (isset( $_POST['peach_payment_id'] ) && ( sanitize_text_field($_POST['peach_payment_id']) == 'dontsave' ) ) ) {
    					throw new Exception( __( 'To purchase this pre-order, you need to use a stored card or store a new card.', 'woocommerce-gateway-peach-payments' ) );    		
			}else {
				

				$order_request = array(
						'customParameters[PAYMENT_PLUGIN]'	=> 'WORDPRESS',
			     		'merchantTransactionId'				=> $order->get_order_number(),
			     		'customer.merchantCustomerId'		=> $this->get_customer_id( $order ),
			     		'customer.givenName'				=> $order->billing_first_name." ".$order->billing_last_name,				     	       		
				     	'billing.street1'					=> $order->billing_address_1,        		
				        'billing.postcode'					=> $order->billing_postcode,
				        'billing.city'						=> $order->billing_city,        		
				        'billing.state'						=> $order->billing_state,
				        'billing.country'					=> $order->billing_country,				        
				        'customer.email'					=> $order->billing_email,
				        'customer.ip'						=> $_SERVER['REMOTE_ADDR']
			     		);

				if ( sanitize_text_field( $_POST['peach_payment_id'] ) == 'saveinfo' ) {
					$payment_request = array(
						'paymentType'						=> $PreOrderType,
						'createRegistration'				=> true
				      	);

					if ( $this->transaction_mode == 'CONNECTOR_TEST' || 'LIVE' ) {
						$payment_request['currency'] = get_option( 'woocommerce_currency' );
						$payment_request['amount'] = $preOrderAmount;
					}

					
				} 
				else {
					$payment_request = array(
						'paymentType'						=> $PreOrderType,
						'merchantInvoiceId'					=> $order->get_order_number(),
			     		'amount'							=> self::get_order_prop( $order, 'order_total' ),
				      	'currency'							=> get_option( 'woocommerce_currency' ) 
				      	);					
				}

				
				if($this->access_token!=''){
					
					$request = array_merge( $payment_request, $order_request );
					$request['authentication.entityId'] = $this->channel_3ds;
					$json_token_response = $this->generate_token_header( $request );
				}else{
					$order_request = array_merge( $order_request, $this->base_request );
					$request = array_merge( $payment_request, $order_request );
					$request['authentication.entityId'] = $this->channel_3ds;
					$json_token_response = $this->generate_token( $request );
				}


				if ( is_wp_error( $json_token_response ) ) {
					throw new Exception( $json_token_response->get_error_message() );
				}

				//token received - offload payment processing to copyandpay form
				return array(
		          'result'   => 'success',
		          'redirect' => $order->get_checkout_payment_url( true )
		        );

			}

     	} catch( Exception $e ) {
				wc_add_notice( __('Error:', 'woocommerce-gateway-peach-payments') . ' "' . $e->getMessage() . '"' , 'error' );
				return;
		}
		
     }
 	/*
    *   Function Name : process_pre_order_release_payment12
    *   Description   : Process the pre orders payment and complete the payment 
    *   Author        : Nitin Sharma
    *   Created On    : 27-Oct-2016
    *   Parameters    : int $order 
    *   Return Value  : array
    */
 		//Need To verify 
    	function process_pre_order_release_payment12( $order_id ) {

    	global $woocommerce;
    	$order = wc_get_order( $order_id );
    	$order_id=$order->id;
		$payment_id = get_post_meta( $order_id, '_subscription_payment_id', true );
				
		//throw exception if payment method does not exist
		/*if ( ! isset( $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'] ) ) {
			throw new Exception( __( 'Invalid Payment Method', 'woocommerce-gateway-peach-payments' ) );
		}
		*/
		// get pre-order fee amount
		if (WC_Pre_Orders_Order::order_will_be_charged_upon_release($order)) {
			$order_items = $order->get_items();
    		$product = $order->get_product_from_item( array_shift( $order_items ) );
			$preOrderAmount = WC_Pre_Orders_Product::get_pre_order_fee( $product );
			$chargeAmount=self::get_order_prop( $order, 'order_total' )-$preOrderAmount;
		}else{
			$chargeAmount=self::get_order_prop( $order, 'order_total' );
		}
        

		$redirect_url = $this->execute_pre_payment_request( $order, $chargeAmount, $payment_id,'DB' );				
		//throw exception if payment is not accepted
		if ( is_wp_error( $redirect_url ) ) {
			throw new Exception( $redirect_url->get_error_message() );
		}

		return array(
		          'result'   => 'success',
		          'redirect' => $redirect_url
		        );

	}


	/*
    *   Function Name : execute_pre_payment_request
    *   Description   : Execute payment request through POST endpoint and returns redirect URL
    *   Author        : Nitin Sharma    
    *   Parameters    : object $order, string $amount ,string $payment_method_id
    *   Return Value  : string
    */
	function execute_pre_payment_request( $order, $amount, $payment_id ,$payment_type='DB') {
		global $woocommerce;
		$merchant_endpoint = add_query_arg( 'wc-api', 'WC_Peach_Payments', home_url( '/' ) );
		$payment_request = array(
							      	'customParameters[PAYMENT_PLUGIN]'	=> 'WORDPRESS',
							      	'paymentType'					=> $payment_type,
							      	'merchantTransactionId'			=> $order->get_order_number(),
						     		
							      	'merchantInvoiceId'				=> 'Order #' . $order->get_order_number(),
						     		'amount'						=> $amount,
							      	'currency'						=> get_option( 'woocommerce_currency' ),
							      	'shopperResultUrl'				=> $merchant_endpoint,
							      	'authentication.entityId'		=> $this->channel 	      	
							      );

		
        

		if($this->access_token!=''){
			$headers = array('Authorization'=> 'BEARER '.$this->access_token);
			$request = $payment_request;

		}
		$ppUrl= $this->refund_url."/".$payment_id;
		$ppMethod='POST';
		$sslverify='false';
		$response= $this->pp_remote_post_data($ppUrl,$request, $ppMethod, $sslverify, $headers);


		if ( is_wp_error($response) )
			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

		if( empty($response['body']) )
			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

		$parsed_response = json_decode( $response['body'] );

	    //create redirect link
	    $redirect_url = $this->get_return_url( $order );
	    $order_id = $this->checkOrderNumberValidate($parsed_response->merchantTransactionId);


	    if ( preg_match(PEACHPAYMENT_REQUEST_SUCCESSFULLY_PROCESSED,$parsed_response->result->code) || preg_match(PEACHPAYMENT_TRANSACTION_SUCCEEDED,$parsed_response->result->code)) {
	    	update_post_meta( $order_id, '_subscription_payment_id', $parsed_response->id );
				$order->payment_complete();
				$order->add_order_note( sprintf(__('Payment Completed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
				return add_query_arg( 'registered_payment', 'ACK', $redirect_url );

			} 
			else {
				$order->update_status('failed', sprintf(__('Payment Failed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description  )  ) );
				return add_query_arg ('registered_payment', 'NOK', $redirect_url );
			}
		
	}

	/*
    *   Function Name : process_refund 
    *   Description   : Process for full and partial refund 
    *   Author        : Nitin Sharma    
    *   Parameters    : order_id
    *   Return Value  : void
    */
	
	public function process_refund( $order_id,$amount = NULL, $reason = '' ) {
		global $woocommerce;
		$totalRefundAmount=0;	 
		$order = wc_get_order($order_id);
		
		//echo "<pre>";
		//print_r($_POST);
		$payment_id = get_post_meta( $order_id, '_subscription_payment_id', true );	
		//die();
		if(sanitize_text_field( $_POST['refund_amount'] )==''){
			$refundId=sanitize_text_field( $_POST['order_refund_id'] );
			if(is_array($refundId) && !empty($refundId)){
				foreach ($refundId as $key => $value) {
			 		$totalRefundAmount +=  get_post_meta( $value, '_refund_amount', true );
			 	}
			 }
			 $amount  =  $order->get_total() - $totalRefundAmount;
		}else{
			 $amount= wc_format_decimal( sanitize_text_field( $_POST['refund_amount'] ), wc_get_price_decimals());
			
     		 $max_refund  = wc_format_decimal( $order->get_total() - sanitize_text_field( $_POST['refund_amount'] ), wc_get_price_decimals() );
     		 //echo $totalRefundAmount;
		/*echo "GET TOTAL ORDER AMOUNT->".$order->get_total()."<br>";
		echo "GET TOTAL ALREADY REFUNDED ORDER AMOUNT->".$order->get_total_refunded()."<br>";
		echo "MAX REFUND AMOUNT".$max_refund. "<br>";
		echo "Final Amount:->".$amount;
		die();*/
		     if ( ! $amount || $max_refund < $amount) {
		       // throw new exception( __( 'Invalid refund amount', 'woocommerce' ) );
		        $order->add_order_note( sprintf(__('Payment refund Failed due to amount format', 'woocommerce-gateway-peach-payments'),''  ) );
		        return false;
		      }
			
		}

		$parsed_response=$this->execute_refund_payment_status( $order, $amount, $payment_id );

		//echo "<pre>";
		//print_r($parsed_response);
		//die();
		if ( preg_match(PEACHPAYMENT_REQUEST_SUCCESSFULLY_PROCESSED,$parsed_response->result->code) || preg_match(PEACHPAYMENT_TRANSACTION_SUCCEEDED,$parsed_response->result->code)) {			
			$order->add_order_note( sprintf(__('Payment refund successfully: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean( $parsed_response->result->description ) ) );
			return true;
			
		} 
		else {
			$order->update_status('processing', sprintf(__('Refund Failed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean( $parsed_response->result->description ) ) );
			return false;
		}

	}
	/*
    *   Function Name : execute_refund_payment_status
    *   Description   : Execute payment request through POST endpoint and returns redirect URL
    *   Author        : Nitin Sharma    
    *   Parameters    : object $order, string $amount ,string $payment_method_id
    *   Return Value  : string
    */
	function execute_refund_payment_status( $order, $amount, $payment_id ) {
		global $woocommerce;		
		$payment_request = 		   array(
							      	'customParameters[PAYMENT_PLUGIN]'	=> 'WORDPRESS',
							      	'paymentType'					=> 'RF',							      	
						     		'amount'						=> number_format($amount,2),
							      	'currency'						=> get_option( 'woocommerce_currency' ),
							      	'authentication.entityId'		=> $this->channel 
							           	
							      );
		
		
		if($this->access_token!=''){
			$headers = array('Authorization'=> 'BEARER '.$this->access_token);
			$request = $payment_request;

		}
		$ppUrl=  $this->refund_url."/".$payment_id;
		$ppMethod='POST';
		$sslverify='false';
		$response= $this->pp_remote_post_data($ppUrl,$request, $ppMethod, $sslverify, $headers);

		if ( is_wp_error($response) )
			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

		if( empty($response['body']) )
			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

		$parsed_response = json_decode( $response['body'] );
		return $parsed_response ;
	    		
		
	}

	/**
	 * Function : process_checkout_order() 
	 * Process the Switch[checkout] Payments 
	 * Author : Nitin Sharma
	 * Created : 20-Nov-2019
	 * @param array $order_id    
	 * @return array 
	*/

	function process_checkout_order($order_id){

		global $woocommerce;

     	$order = wc_get_order( $order_id );
     // Required for Recipt Page Redirection.
     	if (!add_post_meta($order->id, "_checkout_payment_option", sanitize_text_field($_POST['peach_payment_id']), true)) {
         update_post_meta($order->id, "_checkout_payment_option", sanitize_text_field($_POST['peach_payment_id']));
     	}
   
		return array(
			'result' 	 => 'success',
			'redirect'	 => $order->get_checkout_payment_url( true ),
		);      
	}


	/**
	 * Function : peachPaymentsignData() 
	 * Create the Signature and return merger array
	 * Author : Nitin Sharma
	 * Created : 22-Nov-2019
	 * @param array $data unsigned data
     * @param bool $includeNonce	
     * @param int currentOrderId 	 
	 * @return array 
	*/
    public function peachPaymentsignData($data = [], $includeNonce = true,$currentOrderId)
    {

        assert(count($data) !== 0, 'Error: Sign data can not be empty');
        assert(function_exists('hash_hmac'), 'Error: hash_hmac function does not exist');

        if ($includeNonce) {
            $nonce = $this->getPeachPaymentUuid();
            assert(strlen($nonce) !== 0, 'Error: Nonce can not be empty, something went horribly wrong');
            $data = array_merge($data, ['nonce' => $nonce]);
        }

        $tmp = [];
        foreach ($data as $key => $datum) {  
        	if(!empty($datum)){         
            	$tmp[$key] = $datum;
            }
        }


        ksort($tmp, SORT_STRING);

        $peachPaymentsignDataRaw = '';
        foreach ($tmp as $key => $datum) {
            if ($key !== 'signature') {                
                $peachPaymentsignDataRaw .= $key . $datum;
            }
        }
     
        $peachPaymentsignData = hash_hmac('sha256', $peachPaymentsignDataRaw, $this->secret);
       
        
         if (!add_post_meta($currentOrderId, "_switch_payment_signature", $peachPaymentsignData, true)) {
         update_post_meta($currentOrderId, "_switch_payment_signature", $peachPaymentsignData);
    	 }

        return array_merge($data, ['signature' => $peachPaymentsignData]);
    }

	/**
	 * Function : getPeachPaymentUuid() 
	 * Create the Nonce
	 * Author : Nitin Sharma
	 * Created : 22-Nov-2019	 	 
	 * @return string 
	*/
    public function getPeachPaymentUuid()
    {
        assert(function_exists('openssl_random_pseudo_bytes'), 'Error: Unable to generate random string');
        // NOTE: Allow PHP5 based pseudo random str if PHP7 not present
        $data = !function_exists('random_bytes')
            ? openssl_random_pseudo_bytes(16)
            : random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }


    
    /**
     * Function : switch_payment_response() 
     * Handle the woocommerce_api_wc_switch_peach_payments response POST webhook
     * Author : Nitin Sharma
     * Created : 26-Nov-2019
     * @param Void	 
     * @return Void 
    */
	public function switch_payment_response() {
		global $woocommerce;

		$order_id =$_POST['merchantTransactionId']; 
		


		$orderNewID=$this->checkOrderNumberValidate($order_id);
		
     	$order = wc_get_order( $orderNewID );
     	
     	
     	//print_r($order);
     	//die("Switch Die Process");

     	if ( false !== $order ) {
     		$current_order_status = $order->get_status();
     		$force_complete = false;

     		if ( ('complete' !== $current_order_status) && ('processing' !== $current_order_status)  && ('pre-ordered' !== $current_order_status) ) {
					$this->pp_handle_switch_request( stripslashes_deep( $_POST ) );
					
					wp_safe_redirect( $this->get_return_url( $order ) );
							exit;
			}
			$resultCode =  esc_html($_POST['result_code']);

			if ( !empty($resultCode)) {
			
				wp_safe_redirect( $this->get_return_url( $order ) );
							exit;

			}
		}

	}


	/**
	 * Function : pp_handle_switch_request() 
	 * Validate the complete order process for Switch Payment
	 * Author : Nitin Sharma
	 * Created : 26-Nov-2019
	 * @param array $data	 
	 * @return Void 
	*/
	public function pp_handle_switch_request( $data ) {
		$this->log( PHP_EOL
			. '----------'
			. PHP_EOL . 'peachpayment Switch Payment received'
			. PHP_EOL . '----------'
		);
		$this->log( 'Get posted data' );
		$this->log( 'peachpayment Data: ' . print_r( $data, true ) );

		$peachpayment_error  = false;
		$peachpayment_done   = false;		
		$order_id       =  $this->checkOrderNumberValidate($data['merchantTransactionId']);
		$order_key      = wc_clean( $session_id );
		$order          = wc_get_order( $order_id );
		$original_order = $order;
		$debug_email    = DEBUG_EMAIL;
		$vendor_name    = get_bloginfo( 'name', 'display' );
		$vendor_url     = home_url( '/' );

		if ( false === $data ) {
			$peachpayment_error  = true;
			$peachpayment_error_message = PPAYMENT_ERR_BAD_ACCESS;
		}

		// Verify security signature
		if ( ! $peachpayment_error && ! $peachpayment_done ) {
			$this->log( 'Verify security signature' );

			
			// If signature different, log for debugging
			if ( ! $this->pp_validate_signature( $data ) ) {
				$peachpayment_error         = true;
				$peachpayment_error_message = PPAYMENT_ERR_INVALID_SIGNATURE;
			}
		}
		// Get internal order and verify it hasn't already been processed
		if ( ! $peachpayment_error && ! $peachpayment_done ) {
			

			// Check if order has already been processed
			if ( ('processing' === self::get_order_prop( $order, 'status' )) || ('completed' === self::get_order_prop( $order, 'status' ) )) {
				$this->log( 'Order has already been processed' );
				$peachpayment_done = true;
			}
		}
		
		// If an error occurred
		if ( $peachpayment_error ) {
			$this->log( 'Error occurred: ' . $peachpayment_error_message );
			
			if ( $this->send_debug_email ) {
				$this->log( 'Sending email notification' );

				 // Send an email
				$subject = 'peachpayment Switch error: ' . $peachpayment_error_message;
				$body =
					"Hi,\n\n" .
					"An invalid peachpayment transaction on your website requires attention\n" .
					"------------------------------------------------------------\n" .
					'Site: ' . esc_html( $vendor_name ) . ' (' . esc_url( $vendor_url ) . ")\n" .
					'Remote IP Address: ' . $_SERVER['REMOTE_ADDR'] . "\n" .
					'Remote host name: ' . gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) . "\n" .
					'Purchase ID: ' . self::get_order_prop( $order, 'id' ) . "\n" .
					'User ID: ' . self::get_order_prop( $order, 'user_id' ) . "\n";
				if ( isset( $data['checkoutId'] ) ) {
					$body .= 'peachpayment checkout ID: ' . esc_html( $data['checkoutId'] ) . "\n";
				}
				if ( isset( $data['result_code'] ) ) {
					$body .= 'peachpayment Payment result code: ' . esc_html( $data['result_code'] ) . "\n";
				}

				$body .= "\nError: " . $peachpayment_error_message . "\n";
				

				wp_mail( $debug_email, $subject, $body );
			} // End if().
		} elseif ( ! $peachpayment_done ) {

			$this->log( 'Check status and update order' );


			$resultCode =  esc_html($data['result_code']);
			if ( preg_match(PEACHPAYMENT_REQUEST_SUCCESSFULLY_PROCESSED,$resultCode) || preg_match(PEACHPAYMENT_TRANSACTION_SUCCEEDED,$resultCode)) {
			
				$this->handle_switch_payment_complete( $data, $order );

			} else{
				$this->handle_switch_payment_failed( $data, $order );
			}
		} // End if().

		


		$this->log( PHP_EOL
			. '----------'
			. PHP_EOL . 'End ITN call'
			. PHP_EOL . '----------'
		);

	}

	/**
	 * Function : handle_switch_payment_complete() 
	 * Validate the complete order process for Switch Payment
	 * Author : Nitin Sharma
	 * Created : 26-Nov-2019
	 * @param array $data
	 * @param array $order
	 * @return Void 
	*/
	public function handle_switch_payment_complete( $data, $order ) {
		
		$order_id = self::get_order_prop( $order, 'id' );
		$preOrderStatus = get_post_meta( $order_id, '_wc_pre_orders_is_pre_order', true );
		if($preOrderStatus=='1'){
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
		}else{

			$order->update_status('processing');
		}

		//echo "<pre>";
		//print_r($data);
		//die("handle_switch_payment_complete");
		WC()->session->set( 'ppSessionPaymentMethod' , woocommerce_clean($data['paymentBrand']));


			update_post_meta( $this->get_order_id( $order ), '_subscription_payment_id', $data['id'] );
			$order->add_order_note( sprintf(__('Switch Payment Completed with payment method "%s": Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($data['paymentBrand']  ) ,woocommerce_clean($data['result_description']  ) ) );
		
		
		
		

		
	}
	/**
		 * Function : handle_switch_payment_complete() 
		 * Validate the complete order process for Switch Payment
		 * Author : Nitin Sharma
		 * Created : 26-Nov-2019
		 * @param array $data
		 * @param array $order
		 * @return Void 
		*/
		public function handle_switch_payment_failed( $data, $order ) {
			
			

			$order->update_status('failed', sprintf(__('Switch Payment Failed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($data['result_description'])  ) );
				
				wp_safe_redirect( $this->get_return_url( $order ) );
				exit;

			
		}
	/**
	 * Function : pp_validate_signature() 
	 * Validate the signature against the returned data
	 * Author : Nitin Sharma
	 * Created : 26-Nov-2019
	 * @param array $data
	 * @return string 
	*/
	public function pp_validate_signature( $data ) {


        assert(count($data) !== 0, 'Error: Sign data can not be empty');
        assert(function_exists('hash_hmac'), 'Error: hash_hmac function does not exist');

        

        $tmp = [];
        foreach ($data as $key => $datum) {           
            $tmp[str_replace('_', '.', $key)] = $datum;
        }

        ksort($tmp, SORT_STRING);

        $peachPaymentsignDataRaw = '';
        foreach ($tmp as $key => $datum) {
            if ($key !== 'signature') {                
                $peachPaymentsignDataRaw .= $key . $datum;
            }
        }
     
     	$peachPaymentsignData = hash_hmac('sha256', $peachPaymentsignDataRaw, $this->secret);	    
	    $result = $data['signature'] === $peachPaymentsignData;
	    $this->log( 'Signature = ' . ( $result ? 'valid' : 'invalid' ) );
	    return $result;
	}


	/**
	 * Function : get_order_prop 
	 Get order property with compatibility check on order getter introduced
	 * in WC 3.0.
	 * Author : Nitin Sharma
	 * Created : 27-Nov-2019
	 * @param WC_Order $order Order object.
	 * @param string   $prop  Property name.
	 * @return mixed Property value 
	 */
	public static function get_order_prop( $order, $prop ) {
		switch ( $prop ) {
			case 'order_total':
				$getter = array( $order, 'get_total' );
				break;
			default:
				$getter = array( $order, 'get_' . $prop );
				break;
		}

		return is_callable( $getter ) ? call_user_func( $getter ) : $order->{ $prop };
	}

	public function pp_remote_post_data($url,$request,$method='POST', $sslverify='true', $headers=''){
		$response = wp_remote_post( $url, array(
			'method'		=> $method, 
			'body'			=> $request,
			'headers'		=> $headers,
			'timeout' 		=> 70,
			'sslverify' 	=> $sslverify,
			'user-agent' 	=> 'WooCommerce ' . $woocommerce->version
		));
		return $response;
	}


	public function validate_fields(){
    
    if(  !isset($_POST[ 'peach_payment_id' ]) ) {        
        wc_add_notice(  'payment method required!', 'error' );
        return false;
    }
    return true;
 
}

    /**
     * Function : switch_payment_webhook_response() 
     * Handle the woocommerce_api_switch_payment_webhook_response response POST webhook
     * Author : Nitin Sharma
     * Created : 20-Jan-2020
     * @param Void	 
     * @return Void 
    */
	public function switch_payment_webhook_response() {
		global $woocommerce;		
		$order_id =$this->checkOrderNumberValidate($_POST['merchantTransactionId']);
     	$order = wc_get_order( $order_id );


     	if ( false !== $order ) {
     		$current_order_status = $order->get_status();
     		$force_complete = false;

     		if ( ('complete' !== $current_order_status) && ('processing' !== $current_order_status)   && ('pre-ordered' !== $current_order_status) ) {
					$this->pp_handle_switch_webhook_request( stripslashes_deep( $_POST ) );
					
					
			}
		}

	}


	/**
	 * Function : pp_handle_switch_webhook_request() 
	 * Validate the complete order process for Switch Payment By Webhook
	 * Author : Nitin Sharma
	 * Created : 20Jan2020
	 * @param array $data	 
	 * @return Void 
	*/
	public function pp_handle_switch_webhook_request( $data ) {
		
		$this->log( PHP_EOL
			. '----------'
			. PHP_EOL . 'peachpayment Webhook Switch Payment received'
			. PHP_EOL . '----------'
		);
		$this->log( 'Get posted data' );
		$this->log( 'peachpayment Data: ' . print_r( $data, true ) );

		$peachpayment_error  = false;
		$peachpayment_done   = false;		
		$order_id       =  $this->checkOrderNumberValidate($data['merchantTransactionId']);
		$order_key      = wc_clean( $session_id );
		$order          = wc_get_order( $order_id );
		$original_order = $order;
		$debug_email    = DEBUG_EMAIL;
		$vendor_name    = get_bloginfo( 'name', 'display' );
		$vendor_url     = home_url( '/' );

		if ( false === $data ) {
			$peachpayment_error  = true;
			$peachpayment_error_message = PPAYMENT_ERR_BAD_ACCESS;
		}

		// Verify security signature
		if ( ! $peachpayment_error && ! $peachpayment_done ) {
			$this->log( 'Verify security signature' );

			
			// If signature different, log for debugging
			if ( ! $this->pp_validate_signature( $data ) ) {
				$peachpayment_error         = true;
				$peachpayment_error_message = PPAYMENT_ERR_INVALID_SIGNATURE;
			}
		}
		// Get internal order and verify it hasn't already been processed
		if ( ! $peachpayment_error && ! $peachpayment_done ) {
			

			// Check if order has already been processed
			if ( ('processing' === self::get_order_prop( $order, 'status' )) || ('completed' === self::get_order_prop( $order, 'status' ) )) {
				$this->log( 'Order has already been processed' );
				$peachpayment_done = true;
			}
		}
		/*echo "<pre>";
		print_r($data);
		
		echo $peachpayment_error_message;
		die("pp_handle_switch_request");
*/
		// If an error occurred
		if ( $peachpayment_error ) {
			$this->log( 'Error occurred: ' . $peachpayment_error_message );
			
			if ( $this->send_debug_email ) {
				$this->log( 'Sending email notification' );

				 // Send an email
				$subject = 'peachpayment Switch error: ' . $peachpayment_error_message;
				$body =
					"Hi,\n\n" .
					"An invalid peachpayment transaction on your website requires attention\n" .
					"------------------------------------------------------------\n" .
					'Site: ' . esc_html( $vendor_name ) . ' (' . esc_url( $vendor_url ) . ")\n" .
					'Remote IP Address: ' . $_SERVER['REMOTE_ADDR'] . "\n" .
					'Remote host name: ' . gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) . "\n" .
					'Purchase ID: ' . self::get_order_prop( $order, 'id' ) . "\n" .
					'User ID: ' . self::get_order_prop( $order, 'user_id' ) . "\n";
				if ( isset( $data['checkoutId'] ) ) {
					$body .= 'peachpayment checkout ID: ' . esc_html( $data['checkoutId'] ) . "\n";
				}
				if ( isset( $data['result_code'] ) ) {
					$body .= 'peachpayment Payment result code: ' . esc_html( $data['result_code'] ) . "\n";
				}

				$body .= "\nError: " . $peachpayment_error_message . "\n";
				

				wp_mail( $debug_email, $subject, $body );
			} // End if().
		} elseif ( ! $peachpayment_done ) {

			$this->log( 'Check status and update order' );

			/*if ( self::get_order_prop( $original_order, 'order_key' ) !== $order_key ) {
				$this->log( 'Order key does not match' );
				exit;
			}*/

			$resultCode =  esc_html($data['result_code']);

			if ( preg_match(PEACHPAYMENT_REQUEST_SUCCESSFULLY_PROCESSED,$resultCode) || preg_match(PEACHPAYMENT_TRANSACTION_SUCCEEDED,$resultCode)) {
			
				$this->handle_switch_payment_complete( $data, $order );

			}
		} // End if().

		


		$this->log( PHP_EOL
			. '----------'
			. PHP_EOL . 'End ITN call'
			. PHP_EOL . '----------'
		);

	}

	/**
	 * Function : pp_payment_complete() 
	 * Validate the complete order process for google analytics
	 * Author : Nitin Sharma
	 * Created : 22Jan2020
	 * @param array $data	 
	 * @return Void 
	*/

	function pp_payment_complete( $order_id ){		
	    $order = wc_get_order( $order_id );
	    /*$switchPaymentOption=array('EFTSECURE','MOBICRED','MASTERPASS','OZOW');
	    $getPaymentMethodArray=(get_post_meta($order_id, "_checkout_payment_option", ''));
	     $getPaymentMethodMeta=$getPaymentMethodArray[0];
	     if(in_array($getPaymentMethodMeta, $switchPaymentOption)){
	     	$getPaymentMethod="Via Checkout";
	     }else{
	     	$getPaymentMethod="Via Payon";
	     }
*/
  		$ppPaymentMethod = WC()->session->get( 'ppSessionPaymentMethod' );
  		$payment_token = get_post_meta( $order_id, '_peach_payment_token', true );
 		$checkoutPaymentMethod = get_post_meta( $order_id, '_checkout_payment_option', true );
 		if($ppPaymentMethod=='saveinfo'){
 			$ppGetPaymentMethod="card_with_storage";
 		}elseif($ppPaymentMethod=='dontsave'){
 			$ppGetPaymentMethod="card_without_storage";
 		}else{
 			$ppGetPaymentMethod=strtolower($ppPaymentMethod);
 		}
 	
	    $current_order_status = $order->get_status();
	    if($current_order_status=='failed'){
	    	$eventType="order_unsuccessful";
	    }else{
	    	$eventType="order_received";
	    }
	    $analyticsPageViewData = array("pp_page_title"=>'OrderReceived',	
								);						
				wp_enqueue_script('pp_google_anlaytics_page_view',plugins_url('assets/js/analytics_page_view.js', dirname(__FILE__)));
				wp_localize_script( "pp_google_anlaytics_page_view", "merchant", $analyticsPageViewData );
	    
		$analyticsData = array("siteurl"=>site_url(),
							   "transaction_id"=> $order_id ,
							   "payment_method"=>$ppGetPaymentMethod,
							   "payment_status"=>$current_order_status,
							   "amount"=>self::get_order_prop( $order, 'order_total' ),
							   "event_type"=>$eventType
									);
								wp_enqueue_script('ppEventCompletePayment',plugins_url('assets/js/pp_event_complete_payment.js', dirname(__FILE__)));
								wp_localize_script( "ppEventCompletePayment", "merchant", $analyticsData );
								//die("completed hook");
	//Clear the Session
	$ppPaymentMethod = WC()->session->set( 'ppSessionPaymentMethod','' );
	}
	/**
	 * Function : payon_payment_webhook_response() 
	 * Validate Webhook for payon  payments
	 * Author : Nitin Sharma
	 * Created : 27Jan2020
	 * @param array $data	 
	 * @return Void 
	*/

	function payon_payment_webhook_response(){	
		$jsonString = file_get_contents('php://input');
		$jsonObj = json_decode($jsonString, true);
		$headers = apache_request_headers();
		foreach ($headers as $header => $value) {
		    //echo "$header: $value <br />\n";
		    if($header=='x-initialization-vector'){
		    		$headerVector=$value;
		    }
		    if($header=='x-authentication-tag'){
		    		$headerTag=$value;
		    }	    
		}	 
	    
	    if(SODIUM_LIBRARY_VERSION){
	    	//echo "IF";
		    $key = hex2bin($this->card_webhook_key);
		    $iv = hex2bin($headerVector);
		    $auth_tag = hex2bin($headerTag);
		    $cipher_text = hex2bin($jsonObj['encryptedBody']);
		    
		    $result = openssl_decrypt($cipher_text, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $auth_tag);
	    }else{
	    	//echo "ELSE";
		    $key = hex2bin($this->card_webhook_key);
		    $iv = hex2bin($headerVector);
		    $auth_tag = hex2bin($headerTag);
		    $cipher_text = hex2bin($jsonObj['encryptedBody']);
		    
		    $result = openssl_decrypt($cipher_text, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $auth_tag);

	    }
	    $resultArray=json_decode($result);
	    //print_r($resultArray);

		global $woocommerce;
		///print_r($_POST);
		//die("die with switch_payment_response");
		$parsed_response=$resultArray->payload;
		//echo $parsed_response->paymentType; 
		//print_r($parsed_response);
		$order_id = $this->checkOrderNumberValidate($parsed_response->merchantTransactionId);
	    $order    = wc_get_order( $order_id );
		$resultType=esc_html($resultArray->type);
		if($resultType=='PAYMENT'){
			$this->handle_payon_all_payment($parsed_response,$order);
	 	} 	
	    die();
	}
	/**
	 * Function : handle_payon_all_payment() 
	 * Validate Webhook for payon  payments status
	 * Author : Nitin Sharma
	 * Created : 27Jan2020
	 * @param array $data	 
	 * @return Void 
	*/

	public function handle_payon_all_payment($parsed_response,$order){
		if ( false !== $order ) {
	        $current_order_status = $order->get_status();
	        $force_complete = false;

	        if ( ('complete' !== $current_order_status) && ('processing' !== $current_order_status)   && ('pre-ordered' !== $current_order_status) ) {
				 
				if ( $parsed_response->paymentType  == 'DB' || $parsed_response->paymentType  == 'PA' ) {
					
					if ( preg_match(PEACHPAYMENT_REQUEST_SUCCESSFULLY_PROCESSED,$parsed_response->result->code) || preg_match(PEACHPAYMENT_TRANSACTION_SUCCEEDED,$parsed_response->result->code)) {

						//echo "nitin";
						$order_id = $this->checkOrderNumberValidate($parsed_response->merchantTransactionId);	
						$order                = wc_get_order( $order_id );

						
						$initial_payment = $order->get_total( $order );
						//handle card registration
	                    //$payment_id = $parsed_response->id;
	                    //$payment_id = $parsed_response->registrationId;
						//die();
	                    if($parsed_response->registrationId!=''){
	                        $this->add_customer( $parsed_response );
	                        $payment_id = $parsed_response->registrationId;
	                        update_post_meta( $this->get_order_id( $order ), '_subscription_payment_id', $parsed_response->id );
	                    }
	                    $preOrderStatus = get_post_meta( $order_id, '_wc_pre_orders_is_pre_order', true );
	                    if($preOrderStatus=='1'){
	                    		WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
	                    }else{

	                    	$order->payment_complete();
	                    }

					
						
						update_post_meta( $this->get_order_id( $order ), '_peach_subscription_payment_method', $payment_id );
						

							$order->add_order_note( sprintf(__('Webhook Payment Completed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'),  woocommerce_clean( $parsed_response->result->description ) ) );
							
							

				
					} 
					else {
					}
				
				}
	        }
	    }
	}



	public function process_admin_options(){
		//echo "nitin";
		//die("process_admin_options");
		  parent::process_admin_options();
		$analyticsData = array("pp_page_title"=>'ConfigurationForm');
		wp_enqueue_script('pp_google_anlaytics_admin_configuration',plugins_url('assets/js/pp_admin_success.js', dirname(__FILE__)));
		wp_localize_script( "pp_google_anlaytics_admin_configuration", "merchant", $analyticsData );
	}

	

function getItemTest($item) {
  return <<<HTML
{  
  'name': '{$item['name']}',
  'sku': '{$item['sku']}',  
  'price': '{$item['price']}',
  'quantity': '{$item['quantity']}',
  'product_type':'{$item['product_type']}',
}
HTML;
}

	function ppBasketValues($items,$order_id){
		
		// List of Items Purchased.
		 	foreach($items as $item => $values) { 
		 		$_product =  wc_get_product( $values['data']->get_id()); 
		 		//echo "<pre>";
		 		//echo $this->ppProductType($order_id,$_product->get_id());
		 		//print_r($_product);
		 		$ppItems[]=array('sku'		=>$_product->get_id(),
		 					   'name'		=>$_product->get_title(),
		 					   'price'		=>get_post_meta($values['product_id'] , '_price', true),
		 					   'quantity'	=>$values['quantity'],
		 					   'product_type'=>$this->ppProductType($order_id,$_product->get_id())
		 					   );
			}
			foreach ($ppItems as &$item) {
	  			$getArrayDataForamtted[]= $this->getItemTest($item);
			}
			return $getArrayDataForamtted;
	}


	function ppProductType($order_id,$product_id){
		global $woocommerce;
		$order = wc_get_order( $order_id );
		//echo $product_id;
		//die("sadasd");
		$ppProductFlag=false;
			if(class_exists( 'WC_Pre_Orders_Order' ) && (WC_Pre_Orders_Order::order_contains_pre_order( $order_id )) ){
				if(WC_Pre_Orders_Order::order_will_be_charged_upon_release($order)){
					$product_type="pre-order on release";
				}else{
					$product_type="pre-order upfront";
				}
				$ppProductFlag=true;
			}
			if(function_exists( 'wcs_order_contains_subscription' ) && (wcs_order_contains_subscription( $order_id ) || wcs_order_contains_renewal( $order_id )) ){
				if(wcs_order_contains_subscription( $order_id )){
					$product_type="subscription";
				}else{
					$product_type=" pending renewal order";
				}
				$ppProductFlag=true;
			}
			if(!$ppProductFlag){
				$product_type="once-off";
			}
		return $product_type;
	}
	/**
	 * Function : pp_payment_method_session() 
	 * Validate session and stored it for payment selection
	 * Author : Nitin Sharma
	 * Created : 14Feb2020
	 * @return Void 
	*/


	function pp_payment_method_session(){
		if(isset( $_POST['peach_payment_id'] )) {		
			WC()->session->set( 'ppSessionPaymentMethod' , sanitize_text_field($_POST['peach_payment_id'] ));	
		
		}
		if(is_wc_endpoint_url( 'order-pay' )){
				global $woocommerce;
				$order = wc_get_order( $order_id );	
		 		$ppPaymentMethod = WC()->session->get( 'ppSessionPaymentMethod' ); 		
				if($ppPaymentMethod=='saveinfo'){
					$ppGetPaymentMethod="card_with_storage";
				}elseif($ppPaymentMethod=='dontsave'){
					$ppGetPaymentMethod="card_without_storage";
				}else{
					$ppGetPaymentMethod=strtolower($ppPaymentMethod);
				}
				$cartCounter= $woocommerce->cart->cart_contents_count;
				$items = $woocommerce->cart->get_cart();		
				$getBasketArray=$this->ppBasketValues($items,$order_id);
				$analyticsData = array("siteurl"=>site_url(),
									   "transaction_id"=>$order_id,
									   "pp_version"=>PPAYMENT_CURRENT_VERSION,
									   "wc_version"=>WC()->version,
									   "wp_version"=>get_bloginfo( 'version' ),
										"amount"=>self::get_order_prop( $order, 'order_total' ),
										"pp_mode"=>$this->transaction_mode,							
										'basket'=>$getBasketArray,
										"payment_method"=>$ppGetPaymentMethod,
										

										);
				wp_enqueue_script('peachpaymentGoogleTagPaymentStartJs',plugins_url('assets/js/pp_invoking_plugin.js', dirname(__FILE__)));
				wp_localize_script( "peachpaymentGoogleTagPaymentStartJs", "merchant", $analyticsData );
		}
	}



	public function checkOrderNumberValidate($orderNum) {
		//echo "BEFORE-->".$orderNum;
		if(class_exists( 'WC_Sequential_Order_Numbers_Pro_Loader' ) || class_exists( 'WC_Sequential_Order_Numbers_Loader' )){

			$orderNewID = wc_seq_order_number_pro()->find_order_by_order_number( $orderNum );
		}else{
			$orderNewID =$orderNum; 
		}
		//echo "After sequence-->".$orderNewID;
		//die("numbervalidate");
		return $orderNewID;
	}


}