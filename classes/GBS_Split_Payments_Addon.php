<?php


class GBS_Split_Payments_Addon {
	
	public static function init() {
		// Simple Charities
		require_once 'GBS_Simple_Charities.php';
		GBS_Simple_Charities::init();

		if ( is_admin() ) {
			// Attributes meta
			require_once 'GBS_Chairty_Payment_Meta.php';
			GBS_Chairty_Payment_Meta::init();
		}
	}

	public static function gb_addon( $addons ) {
		$addons['charity_split_payments'] = array(
			'label' => gb__( 'Advanced Charity Payments' ),
			'description' => gb__( 'Splits up payments between a charity and the site.' ),
			'files' => array(),
			'callbacks' => array(
				array( __CLASS__, 'init' ),
			)
		);
		return $addons;
	}
}
