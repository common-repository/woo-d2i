<?php
/*
Plugin Name: WooCommerce D2i
Plugin URI: http://www.direct2internet.com
Description: Handle card, bank and invoice payments through D2i
Version: 1.3101
Author: Direct2Internet
Author URI: http://www.d2i.se
*/





/*
 * Has correct but undefined handling of UTF-8 
 * 
 * 
 */

add_action('plugins_loaded', 'init_d2i_pay_gateway', 0);
 
function init_d2i_pay_gateway() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    if ( ! class_exists( 'WC_Logger' ) ) return;
    
    
    class WC_D2ILogger extends WC_Logger {
        
        
        public function __construct( $debug = False, array $handlers = null, string $threshold = null  ) {
            parent::__construct($handlers, $threshold);
            
            $this->debugp = $debug;
        }
        
        public function log( $level, $message, $context = array() ) {
            if($this->debugp) {
                return parent::log($level, $message,$context);
            }
        }
        
    }
    
	
	class WC_D2i_GW extends WC_Payment_Gateway {
			
		public function __construct() { 
			global $woocommerce;

			$this->icon         = plugins_url('',__FILE__) . "/d2i-logo.png";
			$this->id			= 'd2ipay';
			$this->method_title = __('D2i', 'woothemes');
			$this->has_fields 	= false;
			
			// old static paths
			//$this->liveurl 		= 'https://pay.direct2internet.com/pay';
			//$this->testurl		= 'https://pay.direct2internet.com/pay/test';
			//$this->suburl		= 'https://pay.direct2internet.com/admin/subscription_auth';

			// Load the form fields.
			$this->init_form_fields();
			
			// Load the settings.
			$this->init_settings();


			//added because of testing issues
			$this->liveurl 		= $this->get_option('payhost') . '/pay';
			$this->testurl		= $this->get_option('payhost') . '/pay/test';
			$this->suburl		= $this->get_option('payhost') . '/admin/subscription_auth';
				
			
			
			// Define user set variables
			$this->title 				= $this->get_option('title');
			$this->description 			= $this->get_option('description');
			$this->merchantid 			= $this->get_option('merchantid');
			$this->merchantpass			= $this->get_option('merchantpass');
			$this->mode				= $this->get_option('working_mode');
			$this->debug				= $this->get_option('debug');

			$this->card_payment			= $this->get_option('card_payment');
			$this->bank_payment			= $this->get_option('bank_payment');
			$this->invoice_payment			= $this->get_option('invoice_payment');
			$this->swish_payment			= $this->get_option('swish_payment');
			
			$this->capture_now			= $this->get_option( 'capture_now', 'sale' );
			$this->threedsecure			= $this->get_option( '3d_secure' );

			
			$this->splitit_payment      = $this->get_option('splitit_payment');
			$this->callback             = $this->get_option('callback');
			$this->host_url             = $this->get_option('host_url');
			

			$this->log = new WC_D2ILogger('yes' == $this->debug);
        	

			add_action( 'woocommerce_api_wc_d2i_gw', array( $this, 'check_d2i_response' ) );

			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
             			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
          		} else {
             			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
         		}

			add_action('woocommerce_receipt_d2ipay', array( $this, 'receipt_page' ) );

			// Enable subscriptions support if the WooCommerce Subscriptions plugin is active
			if ( class_exists( 'WC_Subscriptions' ) ) {
				$this->supports		= array(
					'products',
					'subscriptions',
					'subscription_cancellation',
					'subscription_suspension',
					'subscription_reactivation',
					'subscription_amount_changes',
					'subscription_date_changes',
					'subscription_payment_method_change'
				);

				add_action( 'scheduled_subscription_payment_' . $this->id, array( &$this, 'process_subscription' ), 10, 3 );
			}
		} 
		
		/**
		 * Initialise Gateway Settings Form Fields
		 */
		function init_form_fields() {
		
			$this->form_fields = array(
				'enabled' => array(
								'title' => __( 'Enable/Disable:', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( 'Enable D2i Redirect Payment Module.', 'woothemes' ), 
								'default' => 'yes'
							),
				'merchantid' => array(
                                                                'title' => __( 'Merchant ID:', 'woothemes' ),
                                                                'type' => 'text',
                                                                'description' => __( 'Please enter your Merchant ID as provided by D2i.', 'woothemes' ),
                                                                'default' => ''
                                                        ),
                                'merchantpass' => array(
                                                                'title' => __( 'Merchant Secret:', 'woothemes' ),
                                                                'type' => 'text',
                                                                'description' => __( 'Please enter your Merchant Secret as provided by D2i.', 'woothemes' ),
                                                                'default' => ''
                                                        ),
					
				//added the url for payments
				'payhost' => array(
						'title' => __( 'Payment server/Url prefix:', 'woothemes' ),
						'type' => 'text',
						'description' => __( 'Keep this as pay.direct2internet.com unless requested to change by D2I', 'woothemes' ),
						'default' => 'https://pay.direct2internet.com'
				),
					
					
					
				'capture_now' => array (
							 	'title'       => __( 'Payment Action', 'woocommerce' ),
                                				'type'        => 'select',
                                				'description' => __( 'Choose whether you wish to capture funds immediately or authorize payment only.', 'woocommerce' ),
                                				'default'     => 'sale',
                                				'desc_tip'    => true,
                                				'options'     => array(
                                        				'sale'          => __( 'Capture', 'woocommerce' ),
                                        				'authorization' => __( 'Authorize', 'woocommerce' )
                                				)
							),
               'working_mode' => array(
                                                                'title' => __( 'Test Mode:', 'woothemes' ),
                                                                'type' => 'checkbox',
                                                                'label' => __( 'Test Mode', 'woothemes' ),
								                                'description' => __( 'Enable to only make test payments.', 'woocommerce' ),
								                                'default' => 'yes'
                                                        ),
				'card_payment' => array(
                                                                'title' => __( 'Card payments:', 'woothemes' ),
                                                                'type' => 'checkbox',
                                                                'label' => __( 'Enable card payments', 'woothemes' ),
                                                                'default' => 'yes'
                                                        ),
				'bank_payment' => array(
                                                                'title' => __( 'Bank payments:', 'woothemes' ),
                                                                'type' => 'checkbox',
                                                                'label' => __( 'Enable bank payments', 'woothemes' ),
                                                                'default' => 'yes'
                                                        ),
				'invoice_payment' => array(
                                                                'title' => __( 'Invoice payments:', 'woothemes' ),
                                                                'type' => 'checkbox',
                                                                'label' => __( 'Enable invoice payments', 'woothemes' ),
                                                                'default' => 'yes'
                                                        ),
			    
			    'swish_payment' => array(
                                        			        'title' => __( 'Swish payments:', 'woothemes' ),
                                        			        'type' => 'checkbox',
                                        			        'label' => __( 'Enable Swish payments', 'woothemes' ),
                                        			        'default' => 'yes'
                                        			    ),
			    
			    'splitit_payment' => array(
                                        			        'title' => __( 'Split payments:', 'woothemes' ),
                                        			        'type' => 'checkbox',
                                        			        'label' => __( 'Allows payments to be split into installments', 'woothemes' ),
                                        			        'default' => 'no',
			                                                 'description' => __( 'Allows users to pay the full price in installments', 'woothemes' )
	                                           		    ),
    			    

				'3d_secure' => array(
																'title' => __( '3DSecure payments:', 'woothemes' ),
																'type' => 'checkbox',
																'label' => __( 'Enable 3DSecure for payments', 'woothemes' ),
																'default' => 'yes'
			                                         	),					
					
					
				'title' => array(
								'title' => __( 'Title:', 'woothemes' ), 
								'type' => 'text', 
								'description' => __( 'The title which the user sees during checkout.', 'woothemes' ), 
								//'default' => __( 'Kort, bank och fakturabetalning', 'woothemes' )
            				    'default' => __( 'Kort,bank,faktura', 'woothemes' )
							),
				'description' => array(
								'title' => __( 'Description:', 'woothemes' ), 
								'type' => 'textarea', 
								'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ), 
								'default' => __('Betala säkert via D2i med kort, bank eller faktura.', 'woothemes')
							),
				'debug' => array(
                                				'title'       => __( 'Debug Log', 'woocommerce' ),
                                				'type'        => 'checkbox',
                                				'label'       => __( 'Enable logging', 'woocommerce' ),
                                				'default'     => 'no',
                                				//'description' => sprintf( __( 'Log events, inside <code>wp-content/wc-logs/log-....log</code>', 'woocommerce' ), sanitize_file_name( wp_hash( 'd2i' ) ) ),
				                                'description' =>  __( 'Log events, inside <code>wp-content/upload/wc-logs/log-....log</code>', 'woocommerce' ) ,
				    
                        			),
			    
			    'callback' => array(
			        'title' => __( 'Callback tag:', 'woothemes' ),
			        'type' => 'text',
			        'description' => __( 'The callback allows overriding plugin settings for each order id. '
			               .'This override can be used to implement multiple merchant support for a single wordpress installation. '
                           .'The callback takes order id as parameter and must return an associated array with data indicating what merchant to use. '
			               .'The options Merchant ID can be overiden using <b>merchant_id</b> and Merchant Secret can be overriden with <b>secret</b>. '
			               .'Other options that can be overriden are <b>pay_method</b>, <b>do_3d_secure</b>, <b>capture_now</b>. '
			               .'See D2I integration documentation on what these values do. '			            
			               .'Set empty if no callback is used. The callback is created using wordpress add_filter function. '
			               .'<code>Example: function myCallback($order_id){return ["merchant_id"=>"1","secret="1"};}'
                                .' add_filter("myTag","myCallback");</code> using myTag as value for the Callback tag field. '
			             , 'woothemes' ),
			        'default' => __( '', 'woothemes' )
			                     ),
//
//Could be useful for testing however in practice this is difficult to setup for testing
//
// 			    'host_url' => array(
// 			        'title' => __( 'Host domain:', 'woothemes' ),
// 			        'type'  => 'text',
//                     'description' => 'Override site url for wordpress. This field should be empty. The field is useful when testing locally and your test machine is accessible on a different host. ' 
// 			                         . 'The format is <code>scheme://host:port</code> and should be set when testing locally without a live server.'
// 			    )
			    
			    
			    
			);
		
		} // End init_form_fields()
		
		public function admin_options() {

			?>
			<h3>D2i</h3>
			<p><?php _e('Accept card, bank and invoices payments through D2i. The customer will be redirected to a secure hosted page to make the payment.', 'woothemes'); ?></p>
			<table class="form-table">
			<?php
				$this->generate_settings_html();
			?>
			</table><!--/.form-table-->
			<?php echo "<p>Current return url is '" . WC()->api_request_url( 'WC_D2i_GW' ) ."', this must be set to your website and include http(s) and domain to allow the plugin to function.";
                echo "If this is not set or incorrect adjust your Site URL '".home_url() ."' by going to wordpress.org and adjusting the Site url. "; 
			 ?>
			<?php
		} // End admin_options()
		
		function payment_fields() {
			if ($this->description) echo wpautop(wptexturize($this->description));
		}
		
		/**
		 * Process the PAY method options ..
		 * 
		 * 
		 */
		function fetchPayMethods() {
		    
		  $paym = [];
		  
		  if( $this->card_payment == "yes" ) {
		      $paym[] = "CARD";
		  }
		  if( $this->bank_payment == "yes" ) {
		      $paym[]="BANK";
		  }
		  if( $this->invoice_payment == "yes" ) {
		      $paym[] = "INVOICE";
		  }		  
		  
		  if( $this->splitit_payment == "yes" ) {
		      $paym[] = "splitit";
		  }

		  if( $this->swish_payment == "yes" ) {
		      $paym[] = "SWISH";
		  }
		  
		  
		  
		  if(sizeof($paym)==0) {
		     $paym[] = "PAYWIN" ;
		  }		  
		  
		  $pay_methods = "";
		  foreach($paym as $method) {
		      if( !strlen($pay_methods
		          ) ) {
		          $pay_methods = $method;          
		      } else {
		          $pay_methods .= "," . $method;
		      }
		  }		  
		  		    		    
		  return $pay_methods;   
		}

		function override($val,$over, $name) {
		    if(array_key_exists($name,$over)) {
    		    $this->log->info( "Override used for $name \n");	
		        return $over[$name];		        
		    } else {
		        $this->log->info( "Vanilla value used for $name \n");		        
		        return $val;
		    }		    
		}
		
        /**
         * Call callback here to get return values..
         * 
         * @return unknown|array
         */
		function getOverrides($order_id) {
		  $callback = $this->callback; // defined in woocommerce plugin admin
          $this->log->info("Called overrides");
		  if(strlen($callback)) {
		      
		      //return call_user_func($callback); // using PHP callbacks
		      //$res =  do_action($callback);  // refuses to return values
		      $res =  apply_filters($callback,$order_id);  // supposedly returns values
		      
		      if($res==NULL) {
    		      $this->log->info( "no payment override returned from action $callback \n");		          
		          return []; 
		      }		         
		      $this->log->info( "payment override specified callback = $callback  res [merchant_id ={$res['merchant_id']}," 
			             ."secret = {$res['secret']}]");
		      return $res; // array such as ["merchant_id"=>"<mid>","secret"=>"<secret>"]
		      
		  } else {
    		  $this->log->info( "no payment override specified");
		      return [];   
		  }		    
		}
		
		function getOverrideValue($val,$name,$order_id) {
		     $overr = $this->getOverrides($order_id);
		     $nval  = $this->override($val,$overr,$name);
		     return $nval;
		}
		
		function getOvverrideValueFromArray($val,$name,$array) {
		    if(array_key_exists("order_id",$array)) {
		        return $this->getOverrideValue($val,$name,$array["order_id"]);
		    } else {
		        return $this->getOverrideValue($val,$name,NULL);		        
		    }
		}
		
		function do_threedSecure_param($overr) {
		    $do_threedSecure="NO";
		    if($this->threedsecure == "yes") {
		        $do_threedSecure="YES";
		    }
		    $do_threedSecure = $this->override($do_threedSecure,$overr,"do_3d_secure");
		    return $do_threedSecure;
		    
		}
		
		function capture_now_param($overr) {
		    $capture_now = "NO";		    
		    if( $this->capture_now == "sale" ) {
		        $capture_now = "YES";
		    }
		    $capture_now = $this->override($capture_now,$overr,"capture_now");
		    
		    return $capture_now;
		    
		}
		

// removed because of the difficulty to setup this test method...		
// 		/**
// 		 * To support local testing with live environment...
// 		 * 
// 		 * @param unknown $callback
// 		 * @return unknown
// 		 */
// 		public function adapt_with_test_callback_url($callback) {
// 		    if($this->host_url==NULL or $this->host_url=='') {
// 		        return $callback;
// 		    } else {
// 		        $parts = parse_url($callback);
// 		        $nurl = $this->host_url; // assumming this contains everyhting ecept query path and fragement #hash
		        
// 		        if(array_key_exists("path",$parts)) {
// 		            $nurl .= "" . $parts["path"];
// 		        }
// 		        if(array_key_exists("query",$parts)) {
// 		            $nurl .= "?" . $parts["query"];
// 		        }
// 		        if(array_key_exists("fragment",$parts)) {
// 		            $nurl .= "#" .$parts["fragment"];
// 		        }
		        
// 		        return $nurl;		        
		        
// 		    }		    
// 		}
		
		public function generate_d2i_pay_form( $order_id )  {
			global $woocommerce;
                       
			$order      = new WC_Order($order_id);

			//a client changed the code to this because of issues in some version to get the actual order id appears to have been caused by __ instead of _
			//$order_id = $order->get_order_number();

            $this->log->info(  'Generating payment form for order ' . $order->get_order_number() . " internal order_id $order_id "); // added incoming order_id

			if ( 'yes' == $this->mode ) {
           			$url = $this->testurl;
         	} else {
             		$url = $this->liveurl;
         	}
         	
         	$overr = $this->getOverrides($order_id);
         	

			$amount = number_format( $order->get_total(), 2, '', '' );
			$merchant_id = $this->override($this->merchantid,$overr,"merchant_id");
			$currency = get_woocommerce_currency();
			$accept_url = WC()->api_request_url( 'WC_D2i_GW' );
			//$cancel_url = esc_url( $order->get_cancel_order_url() );
			$cancel_url = wc_get_checkout_url();
			//$cancel_url = $woocommerce->cart->get_checkout_url();
			$callback_url = $accept_url;
			// removed because of the difficulty to actual set this up in test..
			//$callback = $this->adapt_with_test_callback_url($callback); // to support calls from a remote server into a test environment...
			$secret = $this->override($this->merchantpass,$overr,"secret");
			$return_method = "GET";
			$create_subscription = "NO";
			//run amount in the future?
			$do_threedSecure = $this->do_threedSecure_param($overr);

			//time each name and then trim everything in case both are empty
			$customer_name = trim(trim($order->get_shipping_first_name()) . ' ' . trim($order->get_shipping_last_name()));
            //$company'       => $order->billing_company,
            $customer_street1 = trim($order->get_shipping_address_1());
            $customer_street2 = trim($order->get_shipping_address_2());
            $customer_city = trim($order->get_shipping_city());
            $customer_zip = trim($order->get_shipping_postcode());
            $customer_country = trim($order->get_shipping_country());
            

            $this->log->info( 'customer: ' . $customer_name); // problem with log ??
            
            $capture_now = $this->capture_now_param($overr);
            


			$pay_method = $this->override($this->fetchPayMethods(),$overr,"pay_method"); // if you want to override pay methods			


			if ( class_exists( 'WC_Subscriptions' ) ) {
				if ( WC_Subscriptions_Order::order_contains_subscription($order) ) {
					$create_subscription = "YES";
					$pay_method = "";
					if( $this->card_payment == "yes" ) {
						$pay_method = "CARD";
					}
				}
			}

			// should be fixed in later version (too basic for the future)
			$mac_str = $accept_url . $amount . $callback_url . $cancel_url . $capture_now . $create_subscription 
						. $currency . $customer_city . $customer_country . $customer_name . $customer_street1 
					. $customer_street2 . $customer_zip . $do_threedSecure . $merchant_id . 
						$order_id . $pay_method . $return_method;			
						
            $this->log->info( 'Mac-str: ' . $mac_str );

			$mac_str .= $secret;

            $mac = hash ( "sha256", $mac_str );

            $form = "<form name='d2i_pay_form' id='d2i_pay_form' action='".$url."' method='post'>\n";
            $form .= "<input name='do_3d_secure' value='".$do_threedSecure."' type='hidden'>\n";            
            $form .= "<input name='merchant_id' value='".$merchant_id."' type='hidden'>\n";
            $form .= "<input name='currency' value='".$currency."' type='hidden'>\n";
            $form .= "<input name='accept_url' value='".$accept_url."' type='hidden'>\n";
            $form .= "<input name='cancel_url' value='".$cancel_url."' type='hidden'>\n";
			$form .= "<input name='capture_now' value='".$capture_now."' type='hidden'>\n";
			$form .= "<input name='create_subscription' value='".$create_subscription."' type='hidden'>\n";
            $form .= "<input name='callback_url' value='".$callback_url."' type='hidden'>\n";
            $form .= "<input name='mac' value='".$mac."' type='hidden'>\n";
            $form .= "<input name='order_id' type='hidden' value='".$order_id."'>\n";
            $form .= "<input name='amount' type='hidden' value='".$amount."'>\n";
			$form .= "<input name='return_method' type='hidden' value='".$return_method."'>\n";
			$form .= "<input name='pay_method' type='hidden' value='".$pay_method."'>\n";
			$form .= "<input name='customer_city' type='hidden' value='".$customer_city."'>\n";
			$form .= "<input name='customer_country' type='hidden' value='".$customer_country."'>\n";
			$form .= "<input name='customer_name' type='hidden' value='".$customer_name."'>\n";
			$form .= "<input name='customer_street1' type='hidden' value='".$customer_street1."'>\n";
			$form .= "<input name='customer_street2' type='hidden' value='".$customer_street2."'>\n";
			$form .= "<input name='customer_zipcode' type='hidden' value='".$customer_zip."'>\n";
            $form .= "<input type='submit' id='d2i_pay_form_submit' value='Vidare till betalning'>";
            $form .= "</form>\n";


			 wc_enqueue_js( '
                        	$.blockUI({
                                        message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to D2i to make payment.', 'woocommerce' ) ) . '",
                                        baseZ: 99999,
                                        overlayCSS:
                                        {
                                                background: "#fff",
                                                opacity: 0.6
                                        },
                                        css: {
                                                padding:        "20px",
                                                zindex:         "9999999",
                                                textAlign:      "center",
                                                color:          "#555",
                                                border:         "3px solid #aaa",
                                                backgroundColor:"#fff",
                                                cursor:         "wait",
                                                lineHeight:             "24px",
                                        }
                                });
                        	jQuery("#d2i_pay_form_submit").click();
                	' );

			return $form;

		}

		// trigger post
        function process_payment( $order_id ) {
            $this->log->info('process_payment' );
            
			$order = new WC_Order( $order_id );
	        return array(
	        		'result' => 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
	        );
		}

		// create redirect page here :
        function receipt_page( $order ) {
            $this->log->info( 'receipt_page' );
            echo '<p>'.__('Thank you for your order, please click the button below to pay with D2i Web Pay.', 'woothemes').'</p>';
                        
            echo $this->generate_d2i_pay_form( $order );
         }

		function check_d2i_mac($data) {
			ksort( $data );
			$mac_str = "";
        	foreach($data as $key => $value){
                	if( $key != "mac" && $key != "wc-api" ) {
                        	$mac_str .= $value;
                	}
                	$this->log->info("Data $key -> $value");
        	}

        	$mac_str.=$this->getOvverrideValueFromArray($this->merchantpass,"secret",$data);
        	// cannot get the correct secret ..
        	//$mac_str.=$this->merchantpass;

        	$mac = hash( "sha256", $mac_str );

        	$this->log->info("\nIncoming mac:{$data['mac']}\nmac_str:$mac_str\ncalc mac:$mac");   
        	
        	
        	if( $mac == $data['mac'] ) {
				return True;
			}
			return False;
		}

		function get_json_data() {
			$body = '';
			$fh   = @fopen('php://input', 'r');
			if ($fh) {
  				while (!feof($fh)) {
    					$s = fread($fh, 1024);
    					if (is_string($s)) {
      						$body .= $s;
    					}
  				}
  				fclose($fh);
			}

			$data = json_decode($body,true);

			return $data;
		}

		function check_d2i_response() {
			global $woocommerce;
            $this->log->info('check_d2i_response' );

			$post_type_get = True;

			if (array_key_exists("CONTENT_TYPE",$_SERVER) && strpos($_SERVER["CONTENT_TYPE"],"application/json") !== false) {
                $this->log->info('Got callback' );
				$post_type_get = False;
				$data = $this->get_json_data();
			} else {
                $this->log->info('Accept-url' );
				$data = $_GET;
			}
			// check mac
			if( $this->check_d2i_mac($data) ) {
                $this->log->info( 'Mac is valid' );
			} else {
                $this->log->info('Mac is invalid' );
				wp_die( "D2i Request Failure", "D2i Postback", array( 'response' => 200 ) );
			}
			// check existing order
			$order = new WC_Order( $data['order_id'] );

			// store subscription trans id if available
			if ( isset($data['subscription_trans_id']) ) {
				add_post_meta( $order->id, 'subscription_trans_id', $data['subscription_trans_id'] );
			}

			// check status, update to complete
			$order->add_order_note( __( 'Payment completed, pay_method: '.$data['pay_method'], 'woocommerce' ) );
                      	$order->payment_complete();

			if( $post_type_get ) {
                      		// Empty cart and clear session
                      		WC()->cart->empty_cart();
                      		wp_redirect( $this->get_return_url( $order ) );	
			}

            		exit;
		}
	
		function success_d2i_req($d2i_response) {
			global $woocommerce;

			if( isset($d2i_response) ) {
				$order_id = $d2i_response['order_id'];

				$order = new woocommerce_order( (int) $order_id );

				if ($order->status !== 'completed') {
					$order->payment_complete();
					$order->add_order_note('Payment Successful');
					$woocommerce->cart->empty_cart();
				}
			}
		}
	
        function thankyou_page () {
            global $woocommerce;
                        
            //grab the order ID from the querystring
            $order_id               = $_GET['OrderID'];
            //lookup the order details
            $order                  = new woocommerce_order( (int) $order_id );
                        
            //check the status of the order
            if ($order->status == 'processing') {
				$order->add_order_note('Thank You Page');
				echo "<p>Your payment completed </p>";
			}
		}

		function process_subscription ( $amount_to_charge, $order, $product_id ) {
		    $this->log->info('process_subscription' );

			$result = $this->process_subscription_payment( $amount_to_charge, $order );
			if ( is_wp_error($result) ) {
				WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
			} else {
				WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
			}
		}

		function process_subscription_payment ( $amount_to_charge, $order ) {
		    $this->log->info( 'process_subscription_payment' );

			$sub_trans_id = get_post_meta( $order->id, 'subscription_trans_id', true );
			if ( $sub_trans_id == '' ) {
				return false;
			}

			$merchant_id = $this->merchantid;
			$order_id = $order->id;
			$trans_id = $sub_trans_id;
			$amount = number_format( $amount_to_charge, 2, '', '' );
			$currency = get_woocommerce_currency();
			$secret = $this->merchantpass;

			$capture_now = "NO";
			if( $this->capture_now == "sale" ) {
				$capture_now = "YES";
			}

			$mac_str = $amount . $capture_now . $currency . $merchant_id . $order_id . $trans_id;
			$this->log->info( 'd2i', 'Mac-str: ' . $mac_str );
			$mac_str .= $secret;
			$mac = hash ( "sha256", $mac_str );

			$response = wp_remote_post( $this->suburl, array(
				'body'	=> array(
					'merchant_id'	=> $merchant_id,
					'order_id'	=> $order_id,
					'trans_id'	=> $trans_id,
					'amount'	=> $amount,
					'currency'	=> $currency,
					'capture_now'	=> $capture_now,
					'mac'		=> $mac
				),
				'timeout' => 60
			));
			if ( is_wp_error( $response ) ) {
				return false;
			}

			$data = json_decode( $response['body'], true );
			if ( !$data ) {
				return false;
			}
			if ( !isset($data['status']) ) {
				return false;
			}
			if ( $data['status'] != 0 ) {
				return false;
			}

			return true;
		}
	}
	
	
}

/**
 * Add the gateway to WooCommerce
 **/
function add_d2i_pay_gateway( $methods ) {
	$methods[] = 'WC_D2i_GW'; return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_d2i_pay_gateway' );
