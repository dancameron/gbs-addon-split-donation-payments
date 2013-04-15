<?php

function gb_get_charity_purchases_report_url( $charity_id = null, $csv = FALSE ) {
	if ( isset( $_GET['id'] ) ) {
		$charity_id = $_GET['id'];
	}

	$report = Group_Buying_Reports::get_instance( 'charity' );
	if ( $csv ) {
		return apply_filters( 'gb_get_charity_purchases_report_url', add_query_arg( array( 'report' => 'charity', 'id' => $charity ), $report->get_csv_url() ) );
	}
	return apply_filters( 'gb_get_charity_purchases_report_url', add_query_arg( array( 'report' => 'charity', 'id' => $charity_id ), $report->get_url() ) );
}
	function gb_charity_purchases_report_url( $charity_id = null ) {
		echo apply_filters( 'gb_charity_purchases_report_url', gb_get_charity_purchases_report_url( $charity_id ) );
	}
	function gb_charity_purchases_report_link( $charity_id = null ) {
		$link = '<a href="'.gb_get_charity_purchases_report_url( $charity_id ).'" class="report_button">'.gb__( 'Purchase History' ).'</a>';
		echo apply_filters( 'gb_charity_purchases_report_url', $link );
	}
	function gb_charity_purchases_report_csv_url( $charity_id = null ) {
		echo apply_filters( 'gb_charity_purchases_report_url', gb_get_charity_purchases_report_url( $charity_id, true ) );
	}
