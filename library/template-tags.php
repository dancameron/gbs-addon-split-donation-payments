<?php

function gb_deal_has_bundle( $deal_id, $attributes_id = 0 ) {
	return GBS_Bundles_Addon::has_bundle( $deal_id, $attributes_id );
}