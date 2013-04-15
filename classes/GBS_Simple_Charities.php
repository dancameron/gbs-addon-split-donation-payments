<?php

class GBS_Simple_Charities extends Group_Buying_Controller {
	const TAX = 'gb_charities';
	const REWRITE_SLUG = 'charities';
	const REPORT_SLUG = 'charity';
	const META_KEY = 'gb_purchase_charity';

	public static function init() {
		parent::init();

		// Add new taxonomy and remove any conflicting taxonomies
		add_filter( 'gb_attribute_taxonomies', array( get_class(), 'remove_att_tax' ) );
		add_action( 'init', array( get_class(), 'register_tax' ), 0, 0 );

		// Checkout panes
		self::register_payment_pane();
		self::register_review_pane();
		//self::register_confirmation_pane();

		// Save charity record for purchase
		add_action( 'completing_checkout', array( get_class(), 'save_charity' ), 10, 1 );

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

		// Add link to term
		add_action ( self::TAX.'_edit_form_fields', array( get_class(), 'location_input_metabox' ), 10, 2 );
		add_filter( 'manage_edit-'.self::TAX.'_columns', array( get_class(), 'tax_columns' ), 5 );
		add_action( 'manage_'.self::TAX.'_custom_column', array( get_class(), 'tax_column_info' ), 5, 3 );

		// Templates
		add_filter( 'template_include', array( get_class(), 'override_template' ) );
		add_filter( 'gb_deals_index_title', array( get_class(), 'custom_gb_deals_index_title' ) );
	}

	public function add_navigation( $view ) {
		if ( $_GET['report'] == self::REPORT_SLUG ) {
			return GB_SC_PATH . '/views/report/view.php';
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
		$purchases = self::get_purchase_by_charity( $_GET['id'] );
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
	 * Registers the charity taxonomy.
	 *
	 * @static
	 * @return void
	 */
	public static function register_tax() {
		$labels = array(
			'name' => _x( 'Charities', 'taxonomy general name' ),
			'singular_name' => _x( 'Charity', 'taxonomy singular name' ),
			'search_items' =>  __( 'Search Charities' ),
			'popular_items' => __( 'Popular Charities' ),
			'all_items' => __( 'All Charities' ),
			'parent_item' => null,
			'parent_item_colon' => null,
			'edit_item' => __( 'Edit Charity' ),
			'update_item' => __( 'Update Charity' ),
			'add_new_item' => __( 'Add New Charity' ),
			'new_item_name' => __( 'New Charity Name' ),
			'separate_items_with_commas' => __( 'Separate charity with commas' ),
			'add_or_remove_items' => __( 'Add or remove charities' ),
			'choose_from_most_used' => __( 'Choose from the most used charities' ),
			'menu_name' => __( 'Charities' ),
		);
		$taxonomy_args = array(
			'hierarchical' => TRUE,
			'labels' => $labels,
			'show_ui' => TRUE,
			'rewrite' => array(
				'slug' => self::REWRITE_SLUG,
				'with_front' => FALSE,
				'hierarchical' => FALSE,
			),
		);
		register_taxonomy( self::TAX, Group_Buying_Deal::POST_TYPE, $taxonomy_args );
	}

	public static function get_url() {
		return get_term_link( self::TERM, self::TAX );
	}

	public static function is_charity_query( WP_Query $query = NULL ) {
		$taxonomy = get_query_var( 'taxonomy' );
		if ( $taxonomy == self::TAX || $taxonomy == self::TAX || $taxonomy == self::TAX ) {
			return TRUE;
		}
		return FALSE;
	}

	public static function get_terms() {
		return get_terms( self::TAX, array( 'hide_empty'=>0, 'fields'=>'all' ) );
	}

	public static function remove_att_tax( $taxonomies ) {
		unset( $taxonomies['charity'] );
		return $taxonomies;
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
		$charity_term = get_term_by( 'slug', $checkout->cache['gb_charity'], self::TAX );
		if ( $checkout->cache['gb_charity'] ) {
			$panes['gb_charity'] = array(
				'weight' => 5,
				'body' => self::_load_view_to_string( 'checkout/charity-review', array( 'charity_term' => $charity_term ) ),
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


	public static function save_charity( $checkout ) {
		if ( $checkout->cache['gb_charity'] && $checkout->cache['purchase_id'] ) {
			$purchase = Group_Buying_Purchase::get_instance( $checkout->cache['purchase_id'] );
			self::set_purchase_charity( $purchase, $checkout->cache['gb_charity'] );
		}
	}

	public function set_purchase_charity( Group_Buying_Purchase $purchase, $charity_slug ) {
		$purchase->save_post_meta( array(
				self::META_KEY => $charity_slug,
			) );
	}

	public function get_purchase_charity( Group_Buying_Purchase $purchase ) {
		return $purchase->get_post_meta( self::META_KEY );
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
			$charity = self::get_purchase_charity( $purchase );
			if ( !empty( $charity ) ) {
				$charity_term = get_term_by( 'slug', $charity, self::TAX );
				$charity = array( 'charity' => $charity_term->name );
			} else {
				$charity = array( 'charity' => self::__( 'N/A' ) );
			}
			$new_array[] = array_merge( $records, $charity );
		}
		return $new_array;
	}



	public static function get_purchase_by_charity( $charity = null, $date_range = null ) {
		if ( null == $charity ) return; // nothing more to to

		$args = array(
			'fields' => 'ids',
			'post_type' => gb_get_purchase_post_type(),
			'post_status' => 'any',
			'posts_per_page' => -1, // return this many
			'meta_query' => array(
				array(
					'key' => self::META_KEY,
					'value' => $charity,
					'compare' => '='
				)
			)
		);
		add_filter( 'posts_where', array( get_class(), 'filter_where' ) );
		$purchases = new WP_Query( $args );
		remove_filter( 'posts_where', array( get_class(), 'filter_where' ) );
		return $purchases->posts;
	}

	public function filter_where( $where = '' ) {
		// range based
		if ( isset( $_GET['range'] ) ) {
			$range = ( empty( $_GET['range'] ) ) ? 7 : intval( $_GET['range'] ) ;
			$where .= " AND post_date > '" . date( 'Y-m-d', strtotime( '-'.$range.'days' ) ) . "'";
			return $where;
		}
		// date based
		if ( isset( $_GET['from'] ) ) {
			// from
			$from = $_GET['from'];
			// to
			if ( !isset( $_GET['to'] ) || $_GET['to'] == '' ) {
				$now = time() + ( get_option( 'gmt_offset' ) * 3600 );
				$to = gmdate( 'Y-m-d', $now );
			} else {
				$to = $_GET['to'];
			}

			$where .= " AND post_date >= '".$from."' AND post_date < '".$to."'";
		}
		return $where;
	}

	public function filter_title( $title, $report ) {
		if ( $report == 'charity' ) {
			$term = get_term_by( 'slug', $_GET['id'], self::TAX );
			return $term->name.' '.$title;
		}
		return $title;
	}

	public static function location_input_metabox( $tag ) {
?>
				</tbody>
			</table>
			<h3><?php gb_e( 'Reports' ) ?></h3>
			<table class="form-table">
				<tbody>
					<tr class="form-field">
						<th scope="row" valign="top"></th>
						<td><a href="<?php gb_get_charity_purchases_report_url( $tag->slug ) ?>" class="button" target="_blank"><?php gb_e( 'Purchase Reports' ) ?></a></td>
					</tr>
				</tbody>
			</table>
		<?php
	}

	function tax_columns( $defaults ) {
		$defaults['riv_news_type_ids'] = __( 'Report' );
		return $defaults;
	}

	function tax_column_info( $value, $column_name, $id ) {
		if ( $column_name == 'riv_news_type_ids' ) {
			$term = get_term_by( 'id', $id, self::TAX );
			return '<a href="'.gb_get_charity_purchases_report_url( $term->slug ).'" class="button" target="_blank">'.gb__( 'Purchase Report' ).'</a>';
		}
	}

	public static function override_template( $template ) {
		if ( self::is_charity_query() ) {
			$taxonomy = get_query_var( 'taxonomy' );
			$template = self::locate_template( array(
					'deals/deal-'.$taxonomy.'.php',
					'deals/deal-type.php',
					'deals/deal-types.php',
					'deals/deals.php',
					'deals/index.php',
					'deals/archive.php',
				), $template );
		}
		return $template;
	}

	public static function custom_gb_deals_index_title( $title ) {
		if ( self::is_charity_query() ) {
			$title = gb_e( 'Charitable Deals' );
		}
		return $title;

	}

}
