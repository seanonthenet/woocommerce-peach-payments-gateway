<?php
    /**
     * Peach Payments Gateway
     *
     * Provides an Peach Payments WPF Gateway
     *
     * @class 		WC_Peach_Payments_Subscriptions
     * @extends		WC_Peach_Payments
     * @version		1.6.7
     * @package		WC_Peach_Payments
     * @author 		Nitin Sharma
     */
    class WC_Peach_Payments_Subscriptions extends WC_Peach_Payments {

    	function __construct() {

    		parent::__construct();

    		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 3 );
    		add_action( 'woocommerce_subscriptions_changed_failing_payment_method_peach-payments', array( &$this, 'update_failing_payment_method' ), 10, 3 );

    		add_action( 'woocommerce_api_wc_peach_payments_subscriptions', array( &$this, 'process_payment_status') );
    		add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
    		// Allow store managers to manually set Simplify as the payment method on a subscription
    		add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
    		add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );

            add_action('woocommerce_after_checkout_validation', 'pp_prevent_submission');
    					
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
         * Process the subscription payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
    	function process_payment( $order_id ) {

    		$order = wc_get_order( $order_id );
            $switchPaymentOption=array('EFTSECURE','MOBICRED','MASTERPASS','OZOW');
            //$cardPaymentOption=array('VISA','MASTER','AMEX','DINERS');
            /*echo $_POST['peach_payment_id'];
            die();*/

            /*echo "Order Id".$order_id;
            echo "<br>";
            print_r($switchPaymentOption);
            echo function_exists( 'wcs_order_contains_subscription' );
            echo "order renewal".wcs_order_contains_renewal( $order_id );
            echo "order wcs_order_contains_subscription".wcs_order_contains_subscription( $order_id ) ;
            die("process_payment subscription");*/

    		if ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_order_contains_renewal( $order_id )) ) {
    			
    			/*echo "<pre>";
    			print_r($order);
    			die("Subscription_order");*/

    			
    			try {
    				// Check if paying with registered payment method
                    if ( wcs_order_contains_renewal( $order_id ) && ( in_array(sanitize_text_field( $_POST['peach_payment_id'] ),$switchPaymentOption  ) ) ){
                        throw new Exception( __( 'To purchase this order, you need to use a stored card or store a new card', 'woocommerce-gateway-peach-payments' ) );
                    }

                    if ( isset( $_POST['peach_payment_id'] ) && ( in_array(sanitize_text_field( $_POST['peach_payment_id'] ),$switchPaymentOption  ) ) ){
                        /*echo "<script> gtag('send', 'event', 'Form', 'Submission'); </script>";*/
                        /*$analyticsData = array("siteurl"=>site_url(),"transaction_id"=>$order_id);
                        wp_enqueue_script('peachpaymentGoogleTagPaymentMethodJs',plugins_url('assets/js/pp_wrong_pickup_payment_method.js', dirname(__FILE__)));
                        wp_localize_script( "peachpaymentGoogleTagPaymentMethodJs", "merchant", $analyticsData );*/
                    /*    echo "<script>
            window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());

              gtag('config', 'UA-36515646-5', { 'groups': 'peach' });
            </script>
            <script async src='https://www.googletagmanager.com/gtag/js?id=UA-36515646-5'></script>";

                 echo "<script> 
              gtag('event', 'aaaaaaaaaa', {
                'event_category':'bbbbbbbbbb',
                'event_label': 'ccccccccccccc',
                'value': '1',    
                'send_to' : 'peach'
                });</script>";*/

                        throw new Exception( __( 'A subscription product can only be bought with a credit card that is stored.', 'woocommerce-gateway-peach-payments' ) );
                    }


                    if ( isset( $_POST['peach_payment_id'] ) && wp_verify_nonce( $_POST['woocommerce-process-checkout-nonce'], 'woocommerce-process_checkout' ) && 'saveinfo' !== sanitize_text_field( $_POST['peach_payment_id'] ) && 'dontsave' !== sanitize_text_field( $_POST['peach_payment_id'] ) ) {
    					
    					$payment_ids = get_user_meta( $this->get_customer_id( $order ), '_peach_payment_id', false );
    					$payment_id = sanitize_text_field( $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'] );

    					//throw exception if payment method does not exist
    					if ( ! isset( $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'] ) ) {
    						throw new Exception( __( 'Invalid', 'woocommerce-gateway-peach-payments' ) );
    					}

    					$initial_payment = $order->get_total( $order );
    					
    					
                        
    					if ( $initial_payment >= 0 ) {
                            if ( $initial_payment == 0 ) {
                                $parsed_response = $this->execute_post_subscription_payment_request_free_signup( $order, $initial_payment, $payment_id );
                            }else{
    						  $parsed_response = $this->execute_post_subscription_payment_request( $order, $initial_payment, $payment_id );
                            }

    						if ( is_wp_error( $response ) ) {
    							throw new Exception( $response->get_error_message() );
    						}

    						$redirect_url = $this->get_return_url( $order );

    						 if ( preg_match(PEACHPAYMENT_REQUEST_SUCCESSFULLY_PROCESSED,$parsed_response->result->code) || preg_match(PEACHPAYMENT_TRANSACTION_SUCCEEDED,$parsed_response->result->code)) {

    							

    							$order->payment_complete();
    							
    							$force_complete = false;
    							if ( sizeof( $order->get_items() ) > 0 ) {
    								foreach ( $order->get_items() as $item ) {
    									if ( $_product = $order->get_product_from_item( $item ) ) {
    										if($_product->is_downloadable() || $_product->is_virtual()) {
    											$force_complete = true;
    										}
    							
    									}
    								}
    							}	
    							if($force_complete){						
    								$order->update_status('completed');
    							}
    							
    							update_post_meta( $this->get_order_id( $order ), '_peach_subscription_payment_method', $payment_id );
                                update_post_meta( $this->get_order_id( $order ), '_subscription_payment_id', $parsed_response->id );
    							$order->add_order_note( sprintf(__('Payment Completed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'),  woocommerce_clean($parsed_response->result->description  ) ) );
    							$redirect_url = add_query_arg( 'registered_payment', 'ACK', $redirect_url );
    						} else{


    						 	
    							$order->update_status('failed', sprintf(__('Payment Failed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean( $parsed_response->result->description  ) ) );
    							$redirect_url = add_query_arg ('registered_payment', 'NOK', $redirect_url );
    						}

    					} else {
    						
    						$order->payment_complete();
    						update_post_meta( $this->get_order_id( $order ), '_peach_subscription_payment_method', $payment_id );
    						$redirect_url = $this->get_return_url( $order );
    					}

    					return array(
    			          'result'   => 'success',
    			          'redirect' => $redirect_url
    			        );
    		
    				}
    				elseif ( isset( $_POST['peach_payment_id'] ) && ('dontsave' == sanitize_text_field( $_POST['peach_payment_id'] )  ) ) {
    					throw new Exception( __( 'You need to store your payment method in order to purchase a subscription.', 'woocommerce-gateway-peach-payments' ) );
    				}
    				else {
    					$order_request = array(
                            'customParameters[PAYMENT_PLUGIN]'  => 'WORDPRESS',
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
    				        'recurringType'						=> 'INITIAL'
    				       
    			     		);

    				if ( sanitize_text_field( $_POST['peach_payment_id'] ) == 'saveinfo' ) {
    					$payment_request = array(
    						'paymentType'						=> 'DB',
    						'createRegistration'				=> true 
    						
    				      	);

    					if ( $this->transaction_mode == 'CONNECTOR_TEST' || 'LIVE' ) {
    						$payment_request['currency'] = get_option( 'woocommerce_currency' );
    						$payment_request['amount'] = self::get_order_prop( $order, 'order_total' );
    					}

    					
    				} 
    				else {
    					$payment_request = array(
    						'paymentType'						=> 'DB',
    						'merchantInvoiceId'					=> $order->get_order_number(),
    			     		'amount'							=> self::get_order_prop( $order, 'order_total' ),
    				      	'currency'							=> get_option( 'woocommerce_currency' ),
    				      	'createRegistration'				=> true 
    				      	);					
    				}

                    
                    // get if amount <=0, Then using the Payment Type=PA                   
                    $subOrderAmount = self::get_order_prop( $order, 'order_total' );
                    if ( $subOrderAmount <= 0 ){
                        $subOrderAmount=1;
                        $OrderType='PA';
                        $payment_request = array(
                            'paymentType'                       => $OrderType,
                            'merchantInvoiceId'                 => 'Order ' . $order->get_order_number(),
                            'amount'                            => $subOrderAmount,
                            'currency'                          => get_option( 'woocommerce_currency' ),
                            'createRegistration'                => true 
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

    				/*echo "<pre>";
    				print_r($json_token_response);*/
    				//die("Subscription process_payment");
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

    		} else {

    			return parent::process_payment( $order_id );

    		}
    	}

    	/**
    	 * scheduled_subscription_payment function.
    	 *
    	 * @param $amount_to_charge float The amount to charge.
    	 * @param $order WC_Order The WC_Order object of the order which the subscription was purchased in.
    	 * @param $product_id int The ID of the subscription product for which this payment relates.
    	 * @access public
    	 * @return void
    	 */
    	
    	
    	function scheduled_subscription_payment( $amount_to_charge, $order ) {
    	
    		
    		if ( wcs_order_contains_renewal( $this->get_order_id( $order )) ) {
    			$payment_method_order_id = WC_Subscriptions_Renewal_Order::get_parent_order_id( $this->get_order_id( $order ) );
    			$payment_id = get_post_meta( $payment_method_order_id, '_peach_subscription_payment_method', true );
    		}else{
    			$payment_id = get_post_meta( $this->get_order_id( $order ), '_peach_subscription_payment_method', true );
    		}
            /*echo "PAYMENT METHOD ORDER ID".$payment_method_order_id."<br>";
            echo "PAYMENTID--->".$payment_id;
            die();*/


    		$parsed_response = $this->execute_post_subscription_payment_request( $order, $amount_to_charge, $payment_id );

                /*$fileName= ABSPATH.'/response.txt';
                //$fileName="../lib/response.txt";// create logs to trace your application behaviour
                if (file_exists($fileName)){
                    $fp = fopen($fileName, 'a+') or die("can't open file");
                }else{
                    $fp= fopen($fileName, 'x+');// or die("can't open file");
                }

                fwrite($fp,"----------- Resonse For Subscription by Cron ------------- \n ");
                fwrite($fp,print_r($parsed_response, TRUE)."\n");*/
            
               	
    		if ( is_wp_error( $result ) ) {
    			$order->add_order_note( __('Scheduled Subscription Payment Failed: Couldn\'t connect to gateway server - Peach Payments', 'woocommerce-gateway-peach-payments') );
    			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order);
    		}elseif ( preg_match(PEACHPAYMENT_REQUEST_SUCCESSFULLY_PROCESSED,$parsed_response->result->code) || preg_match(PEACHPAYMENT_TRANSACTION_SUCCEEDED,$parsed_response->result->code)) {
    			$order->add_order_note( sprintf(__('Scheduled Subscription Payment Accepted: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'), woocommerce_clean($parsed_response->result->description ) )  );
    			$order->payment_complete();
    			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
    		}else{
    			$order->add_order_note( __('Scheduled Subscription Payment Failed : An unknown error has occured - Peach Payments', 'woocommerce-gateway-peach-payments') );
    			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
    		}
    	
    	}

    	/**
    	 * Execute subscriptions payment request through POST endpoint and returns response array
    	 * 
    	 * @access public
    	 * @param object $order
    	 * @param int $amount
    	 * @param string $payment_method_id
    	 * @return array
    	 */
    	function execute_post_subscription_payment_request( $order, $amount, $payment_method_id ) {
    		global $woocommerce;

    		$order_items = $order->get_items();
    		$product = $this->get_item_product( array_shift( $order_items ), $order );
    		$subscription_name = sprintf( __( 'Subscription for "%s"', 'woocommerce-gateway-peach-payments' ), $product->get_title() ) . ' ' . sprintf( __( '(Order %s)', 'woocommerce-gateway-peach-payments' ), $order->get_order_number() );

    			$this->save_base_request = array(	    			      	
    		      	
    		      	'authentication.entityId'		=> $this->channel  //Replace it For Save Card  	
    		      	
    				);

    		$payment_request = array(
    	      							'customParameters[PAYMENT_PLUGIN]'    => 'WORDPRESS',
                                        'paymentType'					=> 'DB',
    	      							'merchantTransactionId'			=> $order->get_order_number(),
         								'customer.merchantCustomerId'	=> $this->get_customer_id( $order ),  
    	      							'merchantInvoiceId'				=> $order->get_order_number(),
         								'amount'						=> $amount,
    	      							'currency'						=> get_option( 'woocommerce_currency' ),	      	
    	      							'recurringType'					=> 'REPEATED',
    	      	
    	      	);

    		if ( $this->transaction_mode == 'CONNECTOR_TEST' ) {
    			$payment_request['CRITERION.USE_3D_SIMULATOR'] = 'false';
    		}

    		if($this->access_token!=''){
                $headers = array('Authorization'=> 'BEARER '.$this->access_token);                
                $request = $payment_request;
                $request['authentication.entityId'] = $this->channel;

            }
            
            
            $ppUrl= $this->registration_url."/".$payment_method_id."/payments";
            $ppMethod='POST';
            $sslverify='true';
            $response= $this->pp_remote_post_data($ppUrl,$request, $ppMethod, $sslverify, $headers);

    		if ( is_wp_error($response) )
    			return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

    		if( empty($response['body']) )
    			return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

    		$parsed_response = json_decode( $response['body'] );
            /*echo "<pre>";
            print_r($parsed_response);
            die();*/
    		return $parsed_response;
    	}
        /**
         * Execute subscriptions payment request through POST endpoint and returns response array
         * 
         * @access public
         * @param object $order
         * @param int $amount
         * @param string $payment_method_id
         * @return array
         */
        function execute_post_subscription_payment_request_free_signup( $order, $amount, $payment_method_id ) {
            global $woocommerce;
            
            $order_items = $order->get_items();
            $product = $this->get_item_product( array_shift( $order_items ), $order );
            $subscription_name = sprintf( __( 'Subscription for "%s"', 'woocommerce-gateway-peach-payments' ), $product->get_title() ) . ' ' . sprintf( __( '(Order %s)', 'woocommerce-gateway-peach-payments' ), $order->get_order_number() );

                $this->save_base_request = array(                           
                    
                    'authentication.entityId'       => $this->channel  //Replace it For Save Card   
                    
                    );

            $payment_request = array(
                                        'customParameters[PAYMENT_PLUGIN]'  => 'WORDPRESS',
                                        'paymentType'                   => 'PA',
                                        'merchantTransactionId'         => $order->get_order_number(),
                                        'customer.merchantCustomerId'   => $this->get_customer_id( $order ),  
                                        'merchantInvoiceId'             => $order->get_order_number(),
                                        'amount'                        => 1,
                                        'currency'                      => get_option( 'woocommerce_currency' ),            
                                        'recurringType'                 => 'REPEATED',
                
                );

            if ( $this->transaction_mode == 'CONNECTOR_TEST' ) {
                $payment_request['CRITERION.USE_3D_SIMULATOR'] = 'false';
            }

           /* $request = array_merge( $payment_request, $this->save_base_request );
            $response = wp_remote_post(  $this->registration_url."/".$payment_method_id."/payments", array(
                'method'        => 'POST', 
                'body'          => $request,
                'timeout'       => 70,
                'sslverify'     => true,
                'user-agent'    => 'WooCommerce ' . $woocommerce->version
            ));*/


            if($this->access_token!=''){
                $headers = array('Authorization'=> 'BEARER '.$this->access_token);
                $request = $payment_request;

            }
            
            
            $ppUrl= $this->registration_url."/".$payment_method_id."/payments";
            $ppMethod='POST';
            $sslverify='true';
            $response= $this->pp_remote_post_data($ppUrl,$request, $ppMethod, $sslverify, $headers);

            if ( is_wp_error($response) )
                return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

            if( empty($response['body']) )
                return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

            $parsed_response = json_decode( $response['body'] );
            /*echo "<pre>";
            print_r($parsed_response);
            die();*/
            return $parsed_response;
        }

    	/** process_payment_status12 for subscription payment
    	 * WC API endpoint for Subscriptions Copy and Pay response 
    	 *
    	 * @access public
    	 * @return void
    	 */
    	function process_payment_status() {
    		global $woocommerce;
    			$apiToken = sanitize_text_field( $_GET['wc-api'] );
                if ( function_exists( 'wcs_order_contains_subscription' ) && ( $apiToken =='WC_Peach_Payments_Subscriptions') ) {
                    $token = sanitize_text_field( $_GET['id'] );           
                    $parsed_response = $this->get_token_status( $token );
                    $order_id = $this->checkOrderNumberValidateSubscription($parsed_response->merchantTransactionId);
                    $order                = wc_get_order( $order_id );
                   /* echo "<pre>";
                    echo $order_id;
                    print_r($parsed_response);*/
                    switch ($parsed_response->result->code) {
                        case '100.390.103':
                         $order->update_status('failed', __('Payment Failed: Couldn\'t connect to gateway - Peach Payments', 'woocommerce-gateway-peach-payments') );
                            $redirect_url = $this->get_return_url( $order );  
                            wp_safe_redirect( $redirect_url );
                            exit;
                         break;  

                        case '100.390.105':
                          $order->update_status('failed', __('Payment Failed: Couldn\'t connect to gateway - Peach Payments', 'woocommerce-gateway-peach-payments') );
                            $redirect_url = $this->get_return_url( $order );  
                            wp_safe_redirect( $redirect_url );
                            exit;
                         break;  
                        case '100.380.401':
                          $order->update_status('failed', __('Payment Failed: Couldn\'t connect to gateway - Peach Payments', 'woocommerce-gateway-peach-payments') );
                            $redirect_url = $this->get_return_url( $order );  
                            wp_safe_redirect( $redirect_url );
                            exit;
                         break;  
                         case '100.380.102':
                          $order->update_status('failed', __('Payment Failed: Couldn\'t connect to gateway - Peach Payments', 'woocommerce-gateway-peach-payments') );
                            $redirect_url = $this->get_return_url( $order );  
                            wp_safe_redirect( $redirect_url );
                            exit;
                         break;   
                        case '100.380.104':
                          $order->update_status('failed', __('Payment Failed: Couldn\'t connect to gateway - Peach Payments', 'woocommerce-gateway-peach-payments') );
                            $redirect_url = $this->get_return_url( $order );  
                            wp_safe_redirect( $redirect_url );
                            exit;
                         break;                       
                                                     
                                            
                    }

                    
                  // die("subscription_order_die process_payment_status");
                    if ( false !== $order ) {
                        $current_order_status = $order->get_status();
                        $force_complete = false;

                        if ( 'complete' !== $current_order_status && 'processing' !== $current_order_status ) {


                            switch ($parsed_response->result->code) {
                                     case PEACHPAYMENT_NO_PAYMENT_SESSION_FOUND:
                                        $redirect_url = $this->get_return_url( $order );  
                                        wp_safe_redirect( $redirect_url );
                                        exit;
                                     break;                             
                                                                 
                                        
                            }

                			
                			/*echo "<pre>";
                			print_r($parsed_response);
                			die();*/
                			if ( is_wp_error( $parsed_response ) ) {
                				$order->update_status('failed', __('Payment Failed: Couldn\'t connect to gateway - Peach Payments', 'woocommerce-gateway-peach-payments') );
                				wp_safe_redirect( $this->get_return_url( $order ) );
                				exit;
                			}

                			 
                			if ( $parsed_response->paymentType  == 'DB' || $parsed_response->paymentType  == 'PA' ) {
                				if($parsed_response->registrationId!=''){
                						//handle failed registration					
                						switch ($parsed_response->result->code) {
                							 case PEACHPAYMENT_REGISTRATION_NOT_EXISTS:
                                                $order->update_status('pending', __('Registration Failed: Card registration Not Exists - Peach Payments', 'woocommerce-gateway-peach-payments') );
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
                                             /*$order->update_status('failed', __('Payment Failed: Couldn\'t connect to gateway - Peach Payments', 'woocommerce-gateway-peach-payments') );
                                            wp_safe_redirect( $this->get_return_url( $order ) );*/
                                             break;

                						
                						
                					}					
                				}
                				if ( preg_match(PEACHPAYMENT_REQUEST_SUCCESSFULLY_PROCESSED,$parsed_response->result->code) || preg_match(PEACHPAYMENT_TRANSACTION_SUCCEEDED,$parsed_response->result->code)) {

                					//echo "nitin";
                					$order_id = $this->checkOrderNumberValidateSubscription($parsed_response->merchantTransactionId);	
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
                				
                					$order->payment_complete();
                					update_post_meta( $this->get_order_id( $order ), '_peach_subscription_payment_method', $payment_id );
                					

                						$order->add_order_note( sprintf(__('Payment Completed: Payment Response is "%s" - Peach Payments.', 'woocommerce-gateway-peach-payments'),  woocommerce_clean( $parsed_response->result->description ) ) );
                						update_post_meta( $this->get_order_id( $order ), '_peach_subscription_payment_method', $payment_id );
                						$redirect_url = $this->get_return_url( $order );

                			

                				wp_safe_redirect( $redirect_url );
                				exit;



                				//update_post_meta( $order_id, '_peach_payment_id', $parsed_response->id );
                				//$order->payment_complete();
                				//wp_safe_redirect( $this->get_return_url( $order ) );
                				//exit;
                				} 
                				else {
                				//$order->update_status('failed');
                				//wp_safe_redirect( $this->get_return_url( $order ) );
                				//exit;
                				}
                			
                			}
                        }
                    }
                } else {
                    parent::process_payment_status($order_id);
                }

    		
    	}

    		

    		

    	/**
    	 * Generate the Peach Payments Copy and Pay form
         *
         * @access public
         * @param mixed $order_id
         * @return string
         */
        function generate_peach_payments_form( $order_id ) {
    		global $woocommerce;

    		if ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_order_contains_renewal( $order_id )) ) {

                $analyticsData = array("siteurl"=>site_url(),"transaction_id"=>$order_id);
                wp_enqueue_script('ppSubJs',plugins_url('assets/js/pp_sub_type.js', dirname(__FILE__)));
                wp_localize_script( "ppSubJs", "merchant", $analyticsData );    
                // End Here

    			$order = wc_get_order( $order_id );
    			$payment_token = get_post_meta( $order_id, '_peach_payment_token', true );
    			$merchant_endpoint = add_query_arg( 'wc-api', 'WC_Peach_Payments_Subscriptions', home_url( '/' ) );
                foreach ( $this->checkout_methods as $payment_options ){
                    
                     if(in_array($payment_options, $this->cardPaymentOption)){
                        //echo $payment_options;
                        $supported_cards .= $payment_options." ";
                     }
                }                
                //echo  $supported_cards;
                //die("Subscription:generate_peach_payments_form");
    			wp_enqueue_script( 'peachpaymentWidgetJs', PEACHPAYMENT_PAYMENT_GATEWAY_URL . 'v1/paymentWidgets.js?checkoutId='. $payment_token , array(), null,  false );                
    			$checkoutCode .='<form action="' . $merchant_endpoint . '" class="paymentWidgets">' . $supported_cards . '</form>';
    			return $checkoutCode;
    			
    		} else {
    			return parent::generate_peach_payments_form( $order_id );
    		} 

    	}

    		/**
    	 * Don't transfer Peach Payments payment/token meta when creating a parent renewal order.
    	 *
    	 * @access public
    	 * @param array $order_meta_query MySQL query for pulling the metadata
    	 * @param int $original_order_id Post ID of the order being used to purchased the subscription being renewed
    	 * @param int $renewal_order_id Post ID of the order created for renewing the subscription
    	 * @param string $new_order_role The role the renewal order is taking, one of 'parent' or 'child'
    	 * @return void
    	 */
    	function remove_renewal_order_meta( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {

    		if ( 'parent' == $new_order_role )
    			$order_meta_query .= " AND `meta_key` NOT LIKE '_peach_subscription_payment_method' "
    							  .  " AND `meta_key` NOT LIKE '_peach_payment_token' ";

    		return $order_meta_query;
    	}

    	/**
    	 * Update the payment_id for a subscription after using Peach Payments to complete a payment to make up for
    	 * an automatic renewal payment which previously failed.
    	 *
    	 * @access public
    	 * @param WC_Order $original_order The original order in which the subscription was purchased.
    	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
    	 * @param string $subscription_key A subscription key of the form created by @see WC_Subscriptions_Manager::get_subscription_key()
    	 * @return void
    	 */
    	function update_failing_payment_method( $original_order, $renewal_order, $subscription_key ) {
    		global $woocommerce;
    		

    		try {
    			// Check if paying with registered payment method
    			if ( isset( $_POST['peach_payment_id'] ) && ctype_digit( $_POST['peach_payment_id'] ) ) {
    				
    				$payment_ids = get_user_meta( $this->get_customer_id( $original_order ), '_peach_payment_id', false );
    				$payment_id = sanitize_text_field( $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'] );

    				//throw exception if payment method does not exist
    				if ( ! isset( $payment_ids[ $_POST['peach_payment_id'] ]['payment_id'] ) ) {
    					throw new Exception( __( 'Invalid', 'woocommerce-gateway-peach-payments' ) );
    				} else {
    					update_post_meta( $this->get_order_id( $original_order ), '_peach_subscription_payment_method', $payment_id  );	
    				}
    			} elseif ( isset( $_POST['peach_payment_id'] ) && ( sanitize_text_field( $_POST['peach_payment_id'] ) == 'saveinfo' ) ) {
    					$subscription_request = array(
    			     		'merchantTransactionId'				=> $original_order->get_order_number(),
    			     		'customer.merchantCustomerId'		=> $this->get_customer_id( $original_order ),
    			     		'customer.givenName'				=> $original_order->billing_first_name.
    			     												" ".$original_order->billing_last_name,	  		
    				     	'billing.street1'					=> $original_order->billing_address_1,        		
    				        'billing.postcode'					=> $original_order->billing_postcode,
    				        'billing.city'						=> $original_order->billing_city,        		
    				        'billing.state'						=> $original_order->billing_state,
    				        'billing.country'					=> $original_order->billing_country,				        
    				        'customer.email'					=> $original_order->billing_email,
    				        'customer.ip'						=> $_SERVER['REMOTE_ADDR'],
    				        'recurringType'						=> 'INITIAL',
    				        'paymentType'						=> 'DB'



    			     		);

    					
                        if($this->access_token!=''){
                            
                            $request = $subscription_request;
                            $request['authentication.entityId'] = $this->channel;
                            $json_token_response = $this->generate_token_header( $request );
                        }else{
                            $request = array_merge( $subscription_request, $this->base_request );                            
                            $request['authentication.entityId'] = $this->channel;
                            $json_token_response = $this->generate_token( $request );
                        }

    					if ( is_wp_error( $json_token_response ) ) {
    						throw new Exception( $json_token_response->get_error_message() );
    					}

    					//token received - offload payment processing to copyandpay form
    					return array(
    			          'result'   => 'success',
    			          'redirect' => $original_order->get_checkout_payment_url( true )
    			        );
    				} 
    			        
    		} catch( Exception $e ) {
    			
    			wc_add_notice( __('Error:', 'woocommerce-gateway-peach-payments') . ' "' . $e->getMessage() . '"' , 'error' );
    			return;
    		}
    	}
    	
    	/**
    	 * Include the payment meta data required to process automatic recurring payments so that store managers can
    	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
    	 *
    	 * @since 2.4
    	 * @param array $payment_meta associative array of meta data required for automatic payments
    	 * @param WC_Subscription $subscription An instance of a subscription object
    	 * @return array
    	 */
    	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
    		$payment_meta[ $this->id ] = array(
    				'post_meta' => array(
    						'_peach_payment_token' => array(
    								'value' => get_post_meta($this->get_order_id( $subscription ), '_peach_subscription_payment_method', true ),
    								'label' => 'Peach Payment Method',
    						),
    				),
    		);
    		return $payment_meta;
    	}
    	/**
    	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
    	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions 2.0+.
    	 *
    	 * @since 2.4
    	 * @param string $payment_method_id The ID of the payment method to validate
    	 * @param array $payment_meta associative array of meta data required for automatic payments
    	 * @return array
    	 */
    	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
    		/*if ( $this->id === $payment_method_id ) {

               // echo "<pre>";
              //  print_r($this->id );
              //  die();
    			if ( ! isset( $payment_meta['post_meta']['_peach_subscription_payment_method']['value'] ) || empty( $payment_meta['post_meta']['_peach_subscription_payment_method']['value'] ) ) {
    				throw new Exception( 'A "_peach_subscription_payment_method" value is required.' );
    			}
    		}*/
    	}	
    	
    	/**
    	 * Don't transfer customer meta to resubscribe orders.
    	 *
    	 * @access public
    	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
    	 * @return void
    	 */
    	public function delete_resubscribe_meta( $resubscribe_order ) {
    		delete_post_meta( $resubscribe_order->id, '_peach_payment_id' );
    	}

        	/**
         * Store the customer and card IDs on the order and subscriptions in the order
         *
         * @param int $order_id
         * @param string $payment_id
         */
        protected function save_subscription_meta( $order_id, $payment_id ) {
            // Also store it on the subscriptions being purchased in the order
            foreach ( wcs_get_subscriptions_for_order( $order_id ) as $subscription ) {
                update_post_meta( $this->get_order_id( $subscription ), '_peach_subscription_payment_method', $payment_id );
            }
        }



        public function process_refund( $order_id,$amount = NULL, $reason = '') {
        global $woocommerce;
        $order                = wc_get_order( $order_id );

            if ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_order_contains_renewal( $order_id )) ) {
        
               /* $order->update_status('processing', sprintf(__('Refund Failed: Subscription Payment Refund not applicable.', 'woocommerce-gateway-peach-payments'), woocommerce_clean( $parsed_response->result->description ) ) );
                return false;*/
                $totalRefundAmount=0;                 
                
               // echo "<pre>";
               // print_r($_POST);
               
                    $payment_id = get_post_meta( $this->get_order_id( $order ), '_subscription_payment_id', true );
                

                //echo $payment_method_order_id;
               // echo $payment_id;

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
                    
                     $max_refund  = wc_format_decimal( $order->get_total() - $order->get_total_refunded(), wc_get_price_decimals() );

                     if ( ! $amount || $max_refund < $amount) {
                       // throw new exception( __( 'Invalid refund amount', 'woocommerce' ) );
                        $order->add_order_note( sprintf(__('Payment refund Failed due to amount format', 'woocommerce-gateway-peach-payments'),''  ) );
                        return false;
                      }
                    
                }
               // echo $totalRefundAmount;
               // echo "Final Amount:->".$amount;
                //die();
                $parsed_response=$this->execute_subscription_refund_payment_status( $order, $amount, $payment_id );

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
       

               	
            } else {
                 return parent::process_refund( $order_id );
            } 
        }

        /*
    *   Function Name : execute_subscription_refund_payment_status
    *   Description   : Execute payment request through POST endpoint and returns redirect URL
    *   Author        : Nitin Sharma    
    *   Parameters    : object $order, string $amount ,string $payment_method_id
    *   Return Value  : string
    */
    function execute_subscription_refund_payment_status( $order, $amount, $payment_id ) {
        global $woocommerce;        
        $payment_request =         array(
                                    'customParameters[PAYMENT_PLUGIN]'  => 'WORDPRESS',
                                    'paymentType'                   => 'RF',                                    
                                    'amount'                        => number_format($amount,2),
                                    'currency'                      => get_option( 'woocommerce_currency' ),
                                    'authentication.entityId'       => $this->channel
                                        
                                  );


        if($this->access_token!=''){
            $headers = array('Authorization'=> 'BEARER '.$this->access_token);
            $request = $payment_request;

        }
        $ppUrl=  $this->refund_url."/".$payment_id;
        $ppMethod='POST';
        $sslverify='false';
        $response= $this->pp_remote_post_data($ppUrl,$request, $ppMethod, $sslverify, $headers);




        
        $request = array_merge( $payment_request, $this->save_base_request );       
        $response = wp_remote_post( $this->refund_url."/".$payment_id, array(
            'method'        => 'POST', 
            'body'          => $request,
            'timeout'       => 70,
            'sslverify'     => true,
            'user-agent'    => 'WooCommerce ' . $woocommerce->version
        ));

       // echo $payment_id;
       // print_r($request) ;

        if ( is_wp_error($response) )
            return new WP_Error( 'peach_error', __('There was a problem connecting to the payment gateway.', 'woocommerce-gateway-peach-payments') );

        if( empty($response['body']) )
            return new WP_Error( 'peach_error', __('Empty response.', 'woocommerce-gateway-peach-payments') );

        $parsed_response = json_decode( $response['body'] );
        return $parsed_response ;
                
        
    }

    /*public function pp_subscription_remote_post_data($url,$request,$method='POST', $sslverify='true', $headers=''){
        $response = wp_remote_post( $url, array(
            'method'        => $method, 
            'body'          => $request,
            'headers'       => $headers,
            'timeout'       => 70,
            'sslverify'     => $sslverify,
            'user-agent'    => 'WooCommerce ' . $woocommerce->version
        ));
        return $response;
    }   
*/


public function validate_fields(){
    
    if(  !isset($_POST[ 'peach_payment_id' ]) ) {        
        wc_add_notice(  'payment method required!', 'error' );
        return false;
    }
    return true;
 
}


function pp_prevent_submission($posted) {

           
      if ( isset($_POST['peach_payment_id']) && wc_notice_count( 'error' ) == 0 ) {

           wc_add_notice( __( "custom_notice", 'm_example' ), 'error');
// change the data in $posted here

       } 
       
   
    
  /* $analyticsData = array("siteurl"=>site_url(),"transaction_id"=> $order_id ,"payment_method"=>'payon');
                            wp_enqueue_script('ppEventCompletePayment',plugins_url('assets/js/pp_event_complete_payment.js', dirname(__FILE__)));
                            wp_localize_script( "ppEventCompletePayment", "merchant", $analyticsData );*/

   }

   public function checkOrderNumberValidateSubscription($orderNum) {
    //echo "BEFORE-->".$orderNum;
    if(class_exists( 'WC_Sequential_Order_Numbers_Pro_Loader' ) || class_exists( 'WC_Sequential_Order_Numbers_Loader' ) ){

        $orderNewID = wc_seq_order_number_pro()->find_order_by_order_number( $orderNum );
    }else{
        $orderNewID =$orderNum; 
    }
    //echo "After sequence-->".$orderNewID;
    //die("numbervalidate");
    return $orderNewID;
   }




}