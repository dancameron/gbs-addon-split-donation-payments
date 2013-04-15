<?php

class GBS_Charity_Reports extends Group_Buying_Controller {
	const REPORT_SLUG = 'charity';

	public static function init() {
		parent::init();

		// Reports
		/// Purchase Report
		add_filter( 'set_deal_purchase_report_data_column', array( get_class(), 'set_deal_purchase_report_data_column' ), 10, 1 );
		add_filter( 'set_deal_purchase_report_data_records', array( get_class(), 'set_deal_purchase_report_data_records' ), 10, 1 );
		// Merchant Report
		add_filter( 'set_merchant_purchase_report_column', array( get_class(), 'set_deal_purchase_report_data_column' ), 10, 1 );
		add_filter( 'set_merchant_purchase_report_records', array( get_class(), 'set_deal_purchase_report_data_records' ), 10, 1 );
		/// Vouchers
		add_filter( 'set_deal_voucher_report_data_column', array( get_class(), 'set_deal_purchase_report_data_column' ), 10, 1 );
		add_filter( 'set_deal_voucher_report_data_records', array( get_class(), 'set_deal_purchase_report_data_records' ), 10, 1 );
		// Merchant Report
		add_filter( 'set_merchant_voucher_report_data_column', array( get_class(), 'set_deal_purchase_report_data_column' ), 10, 1 );
		add_filter( 'set_merchant_voucher_report_data_records', array( get_class(), 'set_deal_purchase_report_data_records' ), 10, 1 );

		// Create Report
		add_action( 'gb_reports_set_data', array( get_class(), 'create_report' ) );
		add_action( 'group_buying_template_reports/view.php', array( get_class(), 'add_navigation' ), 100, 1 );

		// Filter title
		add_filter( 'gb_reports_get_title', array( get_class(), 'filter_title' ), 10, 2 );
	}

	public function add_navigation( $view ) {
		if ( $_GET['report'] == self::REPORT_SLUG ) {
			return GB_CHARITY_PATH . '/views/report/view.php';
		}
		return $view;
	}


	public function create_report( $report ) {
		if ( $report->report != self::REPORT_SLUG || ( !isset( $_GET['id'] ) || $_GET['id'] == '' ) ) {
			return;
		}
		$report->csv_available = TRUE;

		$columns =
			array(
			'id' => self::__( 'Order #' ),
			'name' => self::__( 'Purchaser Name' ),
			'deal' => self::__( 'Deal' ),
			'quantity' => self::__( 'Quantity' ),
			'price' => self::__( 'Price' ),
			'total' => self::__( 'Purchase Total' ),
			'exp' => self::__( 'Deal Exp.' ),
			'date' => self::__( 'Purchase Date' ),
			'merch_name' => self::__( 'Merchant Name' ),
			'locations' => self::__( 'Deal Locations' ),
			'cats' => self::__( 'Deal Categories' ),
			'tags' => self::__( 'Deal Tags' )
		);
		$report->columns = $columns;
		$purchases = GB_Charities::get_purchase_by_charity( $_GET['id'] );
		$purchase_array = array();
		foreach ( $purchases as $purchase_id ) {
			$purchase = Group_Buying_Purchase::get_instance( $purchase_id );
			$user_id = $purchase->get_user();
			$deals = $purchase->get_products();
			foreach ( $deals as $deal => $key ) {
				$deal = Group_Buying_Deal::get_instance( $key['deal_id'] );
				if ( is_a( $deal, 'Group_Buying_Deal' ) ) {
					if ( TRUE != $deal->never_expires() ) {
						$exp = date( 'F j\, Y H:i:s', $deal->get_expiration_date() );
					} else {
						$exp = self::__( 'N/A' );
					}
					if ( gb_has_merchant( $deal->get_ID() ) ) {
						$merchant = &get_post( gb_get_merchant_id( $deal->get_ID() ) );
						$merchant_title = isset( $merchant->post_title ) ? $merchant->post_title : '';
					} else {
						$merchant_title = self::__( 'N/A' );
					}
					$locations = gb_get_deal_locations( $deal->get_ID() );
					$location_array = array();
					foreach ( $locations as $location ) {
						$location_array[] = $location->name;
					}
					$cats = gb_get_deal_categories( $deal->get_ID() );
					$cats_array = array();
					foreach ( $cats as $cat ) {
						$cats_array[] = $cat->name;
					}
					$tags = gb_get_deal_tags( $deal->get_ID() );
					$tags_array = array();
					foreach ( $tags as $tag ) {
						$tags_array[] = $tag->name;
					}
					$purchase_array[] = array(
						'id' => $purchase_id,
						'deal' => get_the_title( $deal->get_ID() ),
						'merch_name' => $merchant_title,
						'exp' => $exp,
						'date' => date( 'F j\, Y H:i:s', get_the_time( 'U', $purchase_id ) ),
						'name' => gb_get_name( $user_id ),
						'quantity' => $key['quantity'],
						'price' => gb_get_formatted_money( $key['price'] ),
						'total' => gb_get_formatted_money( $purchase->get_total() ),
						'locations' => implode( ',', $location_array ),
						'tags' => implode( ',', $tags_array ),
						'cats' => implode( ',', $cats_array )
					);
				}
			}
		}
		$report->records = $purchase_array;
	}


	/**
	 * Register action hooks for displaying and processing the payment page
	 *
	 * @return void
	 */
	private static function register_payment_pane() {
		add_filter( 'gb_checkout_panes_'.Group_Buying_Checkouts::PAYMENT_PAGE, array( get_class(), 'display_payment_page' ), 10, 2 );
		add_action( 'gb_checkout_action_'.Group_Buying_Checkouts::PAYMENT_PAGE, array( get_class(), 'process_payment_page' ), 10, 1 );
	}

	private static function register_review_pane() {
		add_filter( 'gb_checkout_panes_'.Group_Buying_Checkouts::REVIEW_PAGE, array( get_class(), 'display_review_page' ), 10, 2 );
	}

	private static function register_confirmation_pane() {
		add_filter( 'gb_checkout_panes_'.Group_Buying_Checkouts::CONFIRMATION_PAGE, array( get_class(), 'display_confirmation_page' ), 10, 2 );
	}

	public static function display_payment_page( $panes, $checkout ) {
		$charities = self::get_terms();
		$panes['charity'] = array(
			'weight' => 100,
			'body' => self::_load_view_to_string( 'checkout/charities', array( 'charities' => $charities ) ),
		);
		return $panes;
	}

	public static function process_payment_page( Group_Buying_Checkouts $checkout ) {
		$valid = TRUE;
		if ( isset( $_POST['gb_charity'] ) ) {
			if ( $_POST['gb_charity'] == '' ) {
				self::set_message( "A Charity Selection is Required. ", self::MESSAGE_STATUS_ERROR );
				$valid = FALSE;
			}
		}
		if ( !$valid ) {
			$checkout->mark_page_incomplete( Group_Buying_Checkouts::PAYMENT_PAGE );
		} else {
			$checkout->cache['gb_charity'] = $_POST['gb_charity'];
		}
	}


	/**
	 * Display the review pane with a message about their generosity.
	 *
	 * @param array   $panes
	 * @param Group_Buying_Checkout $checkout
	 * @return array
	 */
	public static function display_review_page( $panes, $checkout ) {
		$charity_id = $checkout->cache['gb_charity'];
		if ( $checkout->cache['gb_charity'] ) {
			$panes['gb_charity'] = array(
				'weight' => 5,
				'body' => self::_load_view_to_string( 'checkout/charity-review', array( 'charity_id' => $charity_id ) ),
			);
		}
		return $panes;
	}


	private static function _load_view_to_string( $path, $args ) {
		ob_start();
		if ( !empty( $args ) ) extract( $args );
		@include 'views/'.$path.'.php';
		return ob_get_clean();
	}

	public static function set_deal_purchase_report_data_column( $columns ) {
		$columns['charity'] = self::__( 'Charity' );
		return $columns;
	}
	public static function set_deal_purchase_report_data_records( $array ) {
		if ( !is_array( $array ) ) {
			return; // nothing to do.
		}
		$new_array = array();
		foreach ( $array as $records ) {
			$items = array();
			$purchase = Group_Buying_Purchase::get_instance( $records['id'] );
			$charity_id = GB_Charities::get_purchase_charity_id( $purchase );
			if ( $charity_id ) {
				$charity = array( 'charity' => get_the_title( $charity_id ) );
			} else {
				$charity = array( 'charity' => self::__( 'N/A' ) );
			}
			$new_array[] = array_merge( $records, $charity );
		}
		return $new_array;
	}

	public function filter_title( $title, $report ) {
		if ( $report == 'charity' ) {
			return get_the_title( $_GET['id'] ).' '.$title;
		}
		return $title;
	}

}
