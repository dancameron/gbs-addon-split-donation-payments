<?php


class GBS_Split_Payments_Addon {
	
	public static function init() {

		// Post Type
		require_once 'GBS_Charity_Post_Type.php';
		GB_Charity::init();

		// Controller
		require_once 'GBS_Charities.php';
		GB_Charities::init();

		// Reports
		require_once 'GBS_Charity_Reports.php';
		GBS_Charity_Reports::init();
		require_once GB_CHARITY_PATH . '/library/template-tags.php';
	}

	public static function gb_addon( $addons ) {
		$addons['charity_split_payments'] = array(
			'label' => gb__( 'Advanced Charity Payments' ),
			'description' => gb__( 'Splits up payments between a charity and the site, uses a modified BluePay payment processor.' ),
			'files' => array(),
			'callbacks' => array(
				array( __CLASS__, 'init' ),
			)
		);
		return $addons;
	}
}
