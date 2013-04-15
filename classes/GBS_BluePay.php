<?php
/**
 * This class provides a model for a payment processor. To implement a
 * different credit card payment gateway, create a new class that extends
 * Group_Buying_Credit_Card_Processors. The new class should implement
 * the following methods (at a minimum):
 *  - get_instance()
 *  - process_payment()
 *  - register()
 *  - get_payment_method()
 *
 * You may also want to register some settings for the Payment Options page
 */

class Group_Buying_BluePay extends Group_Buying_Credit_Card_Processors {
	const API_ENDPOINT_SANDBOX = 'https://secure.bluepay.com/interfaces/a.net.test';
	const API_ENDPOINT_LIVE = 'https://secure.bluepay.com/gateway/transact.dll';
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';
	const API_USERNAME_OPTION = 'gb_bluepay_username';
	const API_PASSWORD_OPTION = 'gb_bluepay_password';
	const CHARITY_PERCENTAGE = 'gb_bluepay_charity_percentage';
	const API_MODE_OPTION = 'gb_bluepay_mode';
	const PAYMENT_METHOD = 'Credit (BluePay)';
	protected static $instance;
	private $api_mode = self::MODE_TEST;
	private $api_username = '';
	private $api_password = '';
	private $charity_percentage = 15;

	public static function get_instance() {
		if ( !( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function get_api_url() {
		if ( $this->api_mode == self::MODE_LIVE ) {
			return self::API_ENDPOINT_LIVE;
		} else {
			return self::API_ENDPOINT_SANDBOX;
		}
	}

	public function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	protected function __construct() {
		parent::__construct();
		$this->api_username = get_option( self::API_USERNAME_OPTION, '' );
		$this->api_password = get_option( self::API_PASSWORD_OPTION, '' );
		$this->charity_percentage = get_option( self::CHARITY_PERCENTAGE, 15 );
		$this->api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );

		add_action( 'admin_init', array( $this, 'register_settings' ), 10, 0 );
		add_action( 'purchase_completed', array( $this, 'complete_purchase' ), 10, 1 );

		// Limitations
		add_filter( 'group_buying_template_meta_boxes/deal-expiration.php', array( $this, 'display_exp_meta_box' ), 10 );
		add_filter( 'group_buying_template_meta_boxes/deal-price.php', array( $this, 'display_price_meta_box' ), 10 );
		add_filter( 'group_buying_template_meta_boxes/deal-limits.php', array( $this, 'display_limits_meta_box' ), 10 );
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, self::__( 'Bluepay (split)' ) );
	}

	/**
	 * Process a payment
	 *
	 * @param Group_Buying_Checkouts $checkout
	 * @param Group_Buying_Purchase $purchase
	 * @return Group_Buying_Payment|bool FALSE if the payment failed, otherwise a Payment object
	 */
	public function process_payment( Group_Buying_Checkouts $checkout, Group_Buying_Purchase $purchase ) {
		if ( $purchase->get_total( $this->get_payment_method() ) < 0.01 ) {
			// Nothing to do here, another payment handler intercepted and took care of everything
			// See if we can get that payment and just return it
			$payments = Group_Buying_Payment::get_payments_for_purchase( $purchase->get_id() );
			foreach ( $payments as $payment_id ) {
				$payment = Group_Buying_Payment::get_instance( $payment_id );
				return $payment;
			}
		}

		// Check if cart has a donation
		if ( $this->checkout_has_charity( $checkout, $purchase ) ) { // multiple payments
			$payment = $this->create_payment( $checkout, $purchase );
			if ( $payment !== FALSE ) {
				// charity payment
				$charity_payment = $this->create_payment( $checkout, $purchase, TRUE );
			}
		}
		else { // If a single payment
			$payment = $this->create_payment( $checkout, $purchase );
		}
		return $payment;
	}

	public function create_payment( Group_Buying_Checkouts $checkout, Group_Buying_Purchase $purchase, $charity_payment = FALSE ) {

		// If charity payment set a different NVP array
		if ( $charity_payment ) {
			$post_data = $this->charity_payment_aim_data( $checkout, $purchase );
		}
		else {
			$post_data = $this->aim_data( $checkout, $purchase );
		}
		
		if ( self::DEBUG ) error_log( '----------Authorize Net Response----------' . print_r( $post_data, true ) );
		$post_string = "";

		foreach ( $post_data as $key => $value ) {
			if ( $key == 'x_line_item' ) {
				$post_string .= "{$key}=".$value."&";
			} else {
				$post_string .= "{$key}=".urlencode( $value )."&";
			}
		}
		$post_string = rtrim( $post_string, "& " );
		if ( self::DEBUG ) error_log( "post_string: " . print_r( $post_string, true ) );
		$response = wp_remote_post( $this->get_api_url(), array(
				'method' => 'POST',
				'body' => $post_string,
				'timeout' => apply_filters( 'http_request_timeout', 15 ),
				'sslverify' => false
			) );
		if ( is_wp_error( $response ) ) {
			return FALSE;
		}

		$response = explode( $post_data['x_delim_char'], $response['body'] );
		$response_code = $response[0]; // The response we want to validate on
		if ( self::DEBUG ) error_log( '----------Authorize Net Response----------' . print_r( $response, TRUE ) );
		if ( $response_code != 1 ) {
			$this->set_error_messages( $response[3] );
			return FALSE;
		}

		$deal_info = array(); // creating purchased products array for payment below
		foreach ( $purchase->get_products() as $item ) {
			if ( isset( $item['payment_method'][$this->get_payment_method()] ) ) {
				if ( !isset( $deal_info[$item['deal_id']] ) ) {
					$deal_info[$item['deal_id']] = array();
				}
				$deal_info[$item['deal_id']][] = $item;
			}
		}
		if ( isset( $checkout->cache['shipping'] ) ) {
			$shipping_address = array();
			$shipping_address['first_name'] = $checkout->cache['shipping']['first_name'];
			$shipping_address['last_name'] = $checkout->cache['shipping']['last_name'];
			$shipping_address['street'] = $checkout->cache['shipping']['street'];
			$shipping_address['city'] = $checkout->cache['shipping']['city'];
			$shipping_address['zone'] = $checkout->cache['shipping']['zone'];
			$shipping_address['postal_code'] = $checkout->cache['shipping']['postal_code'];
			$shipping_address['country'] = $checkout->cache['shipping']['country'];
		}
		$payment_id = Group_Buying_Payment::new_payment( array(
				'payment_method' => $this->get_payment_method(),
				'purchase' => $purchase->get_id(),
				'amount' => $post_data['x_amount'],
				'data' => array(
					'api_response' => $response,
					'masked_cc_number' => $this->mask_card_number( $this->cc_cache['cc_number'] ), // save for possible credits later
				),
				'deals' => $deal_info,
				'shipping_address' => $shipping_address,
			), Group_Buying_Payment::STATUS_AUTHORIZED );
		if ( !$payment_id ) {
			return FALSE;
		}
		$payment = Group_Buying_Payment::get_instance( $payment_id );
		do_action( 'payment_authorized', $payment );
		$payment->set_data( $response );
		return $payment;
	}

	/**
	 * Complete the purchase after the process_payment action, otherwise vouchers will not be activated.
	 *
	 * @param Group_Buying_Purchase $purchase
	 * @return void
	 */
	public function complete_purchase( Group_Buying_Purchase $purchase ) {
		$items_captured = array(); // Creating simple array of items that are captured
		foreach ( $purchase->get_products() as $item ) {
			$items_captured[] = $item['deal_id'];
		}
		$payments = Group_Buying_Payment::get_payments_for_purchase( $purchase->get_id() );
		foreach ( $payments as $payment_id ) {
			$payment = Group_Buying_Payment::get_instance( $payment_id );
			do_action( 'payment_captured', $payment, $items_captured );
			do_action( 'payment_complete', $payment );
			$payment->set_status( Group_Buying_Payment::STATUS_COMPLETE );
		}
	}


	/**
	 * Grabs error messages from a Authorize response and displays them to the user
	 *
	 * @param array   $response
	 * @param bool    $display
	 * @return void
	 */
	private function set_error_messages( $response, $display = TRUE ) {
		if ( $display ) {
			self::set_message( $response, self::MESSAGE_STATUS_ERROR );
		} else {
			error_log( $response );
		}
	}

	/**
	 * Build the NVP data array for submitting the current checkout to Authorize as an Authorization request
	 *
	 * @param Group_Buying_Checkouts $checkout
	 * @param Group_Buying_Purchase $purchase
	 * @return array
	 */
	private function aim_data( Group_Buying_Checkouts $checkout, Group_Buying_Purchase $purchase ) {
		if ( self::DEBUG ) error_log( "checkout: " . print_r( $checkout->cache, true ) );
		$user = get_userdata( $purchase->get_user() );
		$AIMdata = array();
		$AIMdata ['x_login'] = $this->api_username;
		$AIMdata ['x_tran_key'] = $this->api_password;

		$AIMdata ['x_version'] = '3.1';
		$AIMdata ['x_delim_data'] = 'TRUE';
		$AIMdata ['x_delim_char'] = '|';
		$AIMdata ['x_relay_response'] = 'FALSE';
		$AIMdata ['x_type'] = 'AUTH_CAPTURE';
		$AIMdata ['x_method'] = 'CC';

		$AIMdata ['x_card_num'] = $this->cc_cache['cc_number'];
		$AIMdata ['x_exp_date'] = substr( '0' . $this->cc_cache['cc_expiration_month'], -2 ) . substr( $this->cc_cache['cc_expiration_year'], -2 );
		$AIMdata ['x_card_code'] = $this->cc_cache['cc_cvv'];

		// Modify the payment total
		$charity_payment = $this->get_cart_charity_total( $checkout, $purchase );
		// calculate the total based on charity
		$total_amount = $purchase->get_total( $this->get_payment_method() )-$charity_payment;
		$AIMdata ['x_amount'] = gb_get_number_format( $total_amount );
		$AIMdata['x_freight'] = gb_get_number_format( $purchase->get_shipping_total( $this->get_payment_method() ) );
		$AIMdata['x_tax'] = gb_get_number_format( $purchase->get_tax_total( $this->get_payment_method() ) );

		$AIMdata ['x_first_name'] = $checkout->cache['billing']['first_name'];
		$AIMdata ['x_last_name'] = $checkout->cache['billing']['last_name'];
		$AIMdata ['x_address'] = $checkout->cache['billing']['street'];
		$AIMdata ['x_city'] = $checkout->cache['billing']['city'];
		$AIMdata ['x_state'] = $checkout->cache['billing']['zone'];
		$AIMdata ['x_zip'] = $checkout->cache['billing']['postal_code'];
		$AIMdata ['x_phone'] = $checkout->cache['billing']['phone'];

		$AIMdata ['x_email'] = $user->user_email;
		$AIMdata ['x_cust_id'] = $user->ID;

		$AIMdata['x_invoice_num'] = $purchase->get_id();

		$line_items = '';
		foreach ( $purchase->get_products() as $item ) {
			if ( isset( $item['payment_method'][$this->get_payment_method()] ) ) {
				$deal = Group_Buying_Deal::get_instance( $item['deal_id'] );
				$tax = $deal->get_tax();
				$tax = ( !empty( $tax ) && $tax > '0' ) ? 'Y' : 'N' ;
				$line_items .= $item['deal_id'].'<|>'.substr( $deal->get_slug(), 0, 31 ).'<|><|>'.$item['quantity'].'<|>'.gb_get_number_format( $item['unit_price'] ).'<|>'.$tax.'&x_line_item=';
			}
		}
		$AIMdata['x_line_item'] = rtrim( $line_items, "&x_line_item=" );

		if ( isset( $checkout->cache['shipping'] ) ) {
			$AIMdata['x_ship_to_first_name'] = $checkout->cache['shipping']['first_name'];
			$AIMdata['x_ship_to_last_name'] = $checkout->cache['shipping']['last_name'];
			$AIMdata['x_ship_to_address'] = $checkout->cache['shipping']['street'];
			$AIMdata['x_ship_to_city'] = $checkout->cache['shipping']['city'];
			$AIMdata['x_ship_to_state'] = $checkout->cache['shipping']['zone'];
			$AIMdata['x_ship_to_zip'] = $checkout->cache['shipping']['postal_code'];
			$AIMdata['x_ship_to_country'] = $checkout->cache['shipping']['country'];
		}

		if ( $this->api_mode == self::MODE_TEST ) {
			$AIMdata ['x_test_request'] = 'TRUE';
		}

		$AIMdata = apply_filters( 'gb_bluepay_nvp_data', $AIMdata );

		//$AIMdata = array_map('rawurlencode', $AIMdata);
		return $AIMdata;
	}

	/**
	 * Build the NVP data array for submitting the current checkout to Authorize as an Authorization request
	 *
	 * @param Group_Buying_Checkouts $checkout
	 * @param Group_Buying_Purchase $purchase
	 * @return array
	 */
	private function charity_payment_aim_data( Group_Buying_Checkouts $checkout, Group_Buying_Purchase $purchase ) {
		if ( self::DEBUG ) error_log( "checkout: " . print_r( $checkout->cache, true ) );
		$user = get_userdata( $purchase->get_user() );
		$AIMdata = array();

		// Get username and password from charity
		$charity = GB_Charity::get_instance( $checkout->cache['gb_charity'] );
		if ( !is_a( $charity, 'GB_Charity' ) ) {
			return FALSE;
		}
		$AIMdata ['x_login'] = $charity->get_username();
		$AIMdata ['x_tran_key'] = $charity->get_password();

		$AIMdata ['x_version'] = '3.1';
		$AIMdata ['x_delim_data'] = 'TRUE';
		$AIMdata ['x_delim_char'] = '|';
		$AIMdata ['x_relay_response'] = 'FALSE';
		$AIMdata ['x_type'] = 'AUTH_CAPTURE';
		$AIMdata ['x_method'] = 'CC';

		$AIMdata ['x_card_num'] = $this->cc_cache['cc_number'];
		$AIMdata ['x_exp_date'] = substr( '0' . $this->cc_cache['cc_expiration_month'], -2 ) . substr( $this->cc_cache['cc_expiration_year'], -2 );
		$AIMdata ['x_card_code'] = $this->cc_cache['cc_cvv'];

		// What is the charity payment
		$charity_payment = $this->get_cart_charity_total( $checkout, $purchase );
		if ( !$charity_payment ) {
			return FALSE;
		}
		$AIMdata ['x_amount'] = gb_get_number_format( $charity_payment );

		$AIMdata ['x_first_name'] = $checkout->cache['billing']['first_name'];
		$AIMdata ['x_last_name'] = $checkout->cache['billing']['last_name'];
		$AIMdata ['x_address'] = $checkout->cache['billing']['street'];
		$AIMdata ['x_city'] = $checkout->cache['billing']['city'];
		$AIMdata ['x_state'] = $checkout->cache['billing']['zone'];
		$AIMdata ['x_zip'] = $checkout->cache['billing']['postal_code'];
		$AIMdata ['x_phone'] = $checkout->cache['billing']['phone'];

		$AIMdata ['x_email'] = $user->user_email;
		$AIMdata ['x_cust_id'] = $user->ID;

		$AIMdata['x_invoice_num'] = $purchase->get_id();

		if ( $this->api_mode == self::MODE_TEST ) {
			$AIMdata ['x_test_request'] = 'TRUE';
		}

		$AIMdata = apply_filters( 'gb_bluepay_nvp_data_charity', $AIMdata );

		//$AIMdata = array_map('rawurlencode', $AIMdata);
		return $AIMdata;
	}

	public function get_cart_charity_total( Group_Buying_Checkouts $checkout, Group_Buying_Purchase $purchase ) {
		if ( !$this->checkout_has_charity( $checkout, $purchase ) ) {
			return 0;
		}
		$charity = GB_Charity::get_instance( $checkout->cache['gb_charity'] );
		if ( !is_a( $charity, 'GB_Charity' ) ) {
			return 0;
		}
		$percentage = $charity->get_percentage()*0.01;
		return $purchase->get_total( $this->get_payment_method() )*$percentage;
	}

	public function checkout_has_charity( Group_Buying_Checkouts $checkout, Group_Buying_Purchase $purchase ) {
		return isset( $checkout->cache['gb_charity'] );
		
	}

	public function register_settings() {
		$page = Group_Buying_Payment_Processors::get_settings_page();
		$section = 'gb_authorizenet_settings';
		add_settings_section( $section, self::__( 'Authorize.net' ), array( $this, 'display_settings_section' ), $page );
		register_setting( $page, self::API_MODE_OPTION );
		register_setting( $page, self::API_USERNAME_OPTION );
		register_setting( $page, self::API_PASSWORD_OPTION );
		add_settings_field( self::API_MODE_OPTION, self::__( 'Mode' ), array( $this, 'display_api_mode_field' ), $page, $section );
		add_settings_field( self::API_USERNAME_OPTION, self::__( 'Account Number' ), array( $this, 'display_api_username_field' ), $page, $section );
		add_settings_field( self::API_PASSWORD_OPTION, self::__( 'Secret Key' ), array( $this, 'display_api_password_field' ), $page, $section );
		//add_settings_field(null, self::__('Currency'), array($this, 'display_currency_code_field'), $page, $section);
	}

	public function display_settings_section() {
		echo '<div class="error"><p>This payment process requires "Advanced Charity Payments" add-on to be activated.</p></div>';
	}

	public function display_api_username_field() {
		echo '<input type="text" name="'.self::API_USERNAME_OPTION.'" value="'.$this->api_username.'" size="80" />';
	}

	public function display_api_password_field() {
		echo '<input type="text" name="'.self::API_PASSWORD_OPTION.'" value="'.$this->api_password.'" size="80" />';
	}

	public function display_api_mode_field() {
		echo '<label><input type="radio" name="'.self::API_MODE_OPTION.'" value="'.self::MODE_LIVE.'" '.checked( self::MODE_LIVE, $this->api_mode, FALSE ).'/> '.self::__( 'Live' ).'</label><br />';
		echo '<label><input type="radio" name="'.self::API_MODE_OPTION.'" value="'.self::MODE_TEST.'" '.checked( self::MODE_TEST, $this->api_mode, FALSE ).'/> '.self::__( 'Sandbox' ).'</label>';
	}

	public function display_currency_code_field() {
		echo 'Specified in your BluePay Merchant Interface.';
	}

	public function display_exp_meta_box() {
		return GB_PATH . '/controllers/payment_processors/meta-boxes/exp-only.php';
	}

	public function display_price_meta_box() {
		return GB_PATH . '/controllers/payment_processors/meta-boxes/no-dyn-price.php';
	}

	public function display_limits_meta_box() {
		return GB_PATH . '/controllers/payment_processors/meta-boxes/no-tipping.php';
	}

	// Charity Post Type Options
	


}
Group_Buying_BluePay::register();
