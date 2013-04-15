<?php
/*
Plugin Name: GBS Bundles
Version: 2
Plugin URI: http://groupbuyingsite.com/marketplace
Description: Provides another deal to a purchaser, this add-on uses attributes to provide a selection of the secondary deal/item to be given.
Plugin URI: http://groupbuyingsite.com/marketplace
Author: GroupBuyingSite.com
Author URI: http://groupbuyingsite.com/features
Plugin Author: Dan Cameron
Plugin Author URI: http://sproutventure.com/
Text Domain: group-buying
*/

// Load after all other plugins since we need to be compatible with groupbuyingsite
add_action( 'plugins_loaded', 'gb_load_bundles' );
function gb_load_bundles() {
	$gbs_min_version = '4.2.3';
	if ( class_exists( 'Group_Buying_Controller' ) && version_compare( Group_Buying::GB_VERSION, $gbs_min_version, '>=' ) ) {
		require_once 'classes/GBS_Bundles_Addon.php';

		// Hook this plugin into the GBS add-ons controller
		add_filter( 'gb_addons', array( 'GBS_Bundles_Addon', 'gb_addon' ), 10, 1 );
	}
}
