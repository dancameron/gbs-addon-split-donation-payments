<?php

class GB_Charity extends Group_Buying_Post_Type {
	const POST_TYPE = 'gb_charities';
	const REWRITE_SLUG = 'charities';

	private static $instances = array();

	private static $meta_keys = array(
		'payment_notes' => '_payment_notes', // string
		'username' => '_bluepay_username', // string
		'password' => '_bluepay_password', // string
		'percentage' => '_percentage', // string
	);

	public static function init() {
		// Register
		self::register_charity_post_type();

	}

	public static function register_charity_post_type() {
		$post_type_args = array(
			'has_archive' => TRUE,
			'rewrite' => array(
				'slug' => self::REWRITE_SLUG,
				'with_front' => FALSE,
			),
			'supports' => array( 'title', 'editor', 'thumbnail' ),
		);
		self::register_post_type( self::POST_TYPE, 'Charity', 'Charities', $post_type_args );
	}


	protected function __construct( $id ) {
		parent::__construct( $id );
	}

	/**
	 *
	 *
	 * @static
	 * @param int     $id
	 * @return Group_Buying_Merchant
	 */
	public static function get_instance( $id = 0 ) {
		if ( !$id ) {
			return NULL;
		}
		if ( !isset( self::$instances[$id] ) || !self::$instances[$id] instanceof self ) {
			self::$instances[$id] = new self( $id );
		}
		if ( self::$instances[$id]->post->post_type != self::POST_TYPE ) {
			return NULL;
		}
		return self::$instances[$id];
	}

	/**
	 *
	 * @static
	 * @return bool Whether the current query is for the charity post type
	 */
	public static function is_charity_query() {
		$post_type = get_query_var( 'post_type' );
		if ( $post_type == self::POST_TYPE ) {
			return TRUE;
		}
		return FALSE;
	}

	public function get_payment_notes() {
		$payment_notes = $this->get_post_meta( self::$meta_keys['payment_notes'] );
		return $payment_notes;
	}

	public function set_payment_notes( $payment_notes ) {
		$this->save_post_meta( array(
				self::$meta_keys['payment_notes'] => $payment_notes
			) );
		return $payment_notes;
	}

	public function get_username() {
		$username = $this->get_post_meta( self::$meta_keys['username'] );
		return $username;
	}

	public function set_username( $username ) {
		$this->save_post_meta( array(
				self::$meta_keys['username'] => $username
			) );
		return $username;
	}

	public function get_password() {
		$password = $this->get_post_meta( self::$meta_keys['password'] );
		return $password;
	}

	public function set_password( $password ) {
		$this->save_post_meta( array(
				self::$meta_keys['password'] => $password
			) );
		return $password;
	}

	public function get_percentage() {
		$percentage = $this->get_post_meta( self::$meta_keys['percentage'] );
		return $percentage;
	}

	public function set_percentage( $percentage ) {
		$this->save_post_meta( array(
				self::$meta_keys['percentage'] => $percentage
			) );
		return $percentage;
	}



	/**
	 * 
	 */
	public static function get_charities() {
		$args = array(
				'post_type' => self::POST_TYPE,
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'fields' => 'ids'
			);
		$charities = new WP_Query( $args );
		return $charities->posts;
	}


}
