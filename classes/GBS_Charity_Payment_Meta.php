<?php

class GBS_Charity_Payment_Meta extends Group_Buying_Controller {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 10, 0 );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 10, 2 );

		// Attributes
		if ( class_exists( 'Group_Buying_Attribute' ) ) {
			add_action( 'gb_meta_box_deal_attributes_rows_header', array( __CLASS__, 'add_attribute_column_header' ) );
			add_action( 'gb_meta_box_deal_attributes_rows', array( __CLASS__, 'add_attribute_row' ) );
			add_action( 'gb_meta_box_deal_attributes_rows_js', array( __CLASS__, 'add_attribute_row_js' ) );


			add_action( 'save_post', array( __CLASS__, 'save_attributes' ), 10, 2 );
		}
	}

	public static function add_meta_box() {
		add_meta_box( 'gbs_bundles', gb__( 'Bundles' ), array( __CLASS__, 'show_meta_box' ), Group_Buying_Deal::POST_TYPE, 'side' );
	}

	public static function show_meta_box( $post ) {
		$items = array();
		$items = GBS_Bundles_Addon::get_bundles( $post->ID );
?>
		<script type="text/javascript">
			jQuery(document).ready( function($) {

				var show_deal_name = function(e) {
					var $field = $(this);
					var $wrap = $field.parents('.bundled_items');
					var $items_span = $wrap.find( '.associated_items' );
					var $span = $wrap.find( '.deals_name_ajax' );
					var $item_id = $field.val();

					if ( !$item_id ) {
						$span.removeClass('loading_gif');
						return;
					}

					// Delay the ajax call and the loader in case the user is faster than the callbacks
					setTimeout(function(){
						if ( $field.val() == $item_id ) {
							$span.addClass('loading_gif').empty();
							$.ajax({
								type: 'POST',
								dataType: 'json',
								url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
								data: {
									action: 'gbs_ajax_get_deal_info',
									id: $item_id
								},
								success: function(data) {
									if ( data !== null ) {
										$span.removeClass('loading_gif');
										$span.empty().append( '<a href="javascript:void(0)" class="add_item" data-id="' + data.deal_id + '" data-name="' + data.title + '">+ ' + data.title + '</a>');
									};
								}
							});
						}
					},500);

				};
				var add_input_item = function(e) {
					var $att_selected = $(this);
					var $att_wrap = $att_selected.parents('.bundled_items');
					var $att_items_span = $att_wrap.find( '.associated_items' );
					var $att_field = $att_wrap.find( '.deal_id_input' );
					var $att_span = $att_wrap.find( '.deals_name_ajax' );
					var $att_item_id = $att_selected.data('id');
					var $att_item_title = $att_selected.data('name');
					var $att_id = $att_wrap.data('att_id');

					// remove note
					$att_wrap.find('.no_items').remove();

					// Add the input
					$att_items_span.append( '<label><input type="checkbox" checked value="' + $att_item_id + '" name="gbs_bundles[]" /> ' + $att_item_title + '</label><br/>' );
					// Reset the input
					$att_field.val('');
					$att_span.empty();

				};
				// AJAX search based on deal id
				if ( $('.deal_id_input').length > 0 ) {
					$('.deal_id_input').live('keyup',show_deal_name);
				}
				// After selecting the deal the item is added to the list.
				$('.add_item').live('click',add_input_item);
			});
		</script>
		<style type="text/css">
			.loading_gif {
				background: url( '<?php echo GB_URL; ?>/resources/img/loader.gif') no-repeat 0 center;
				width: auto;
				height: 16px;
				padding-right: 16px;
				padding-bottom: 2px;
				margin-left: 10px;
				margin-top: 10px;
			}
		</style>
		<div class="bundled_items clearfix">
			<strong><?php gb_e( 'Bundled Deals' ) ?></strong><br/>
			<p class="associated_items">
				<?php if ( empty( $items ) ): ?>
					<span class="no_items"><?php gb_e( 'No items bundled yet.' ) ?></span>
				<?php else: ?>
					<?php foreach ( $items as $item_id ): ?>
						<label><input type="checkbox" checked value="<?php echo $item_id ?>" name="gbs_bundles[]" /> <?php echo get_the_title( $item_id ) ?></label><br/>
					<?php endforeach ?>
				<?php endif ?>
			</p>
			<p>
				<label><?php gb_e( 'Enter a Deal ID' ) ?></label>
				<input type="text" size="8" class="deal_id_input" placeholder="<?php gb_e( 'Deal ID' ) ?>" />
				<br/><span class="deals_name_ajax">&nbsp;</span>
			</p>
		</div><!--  .bundled_items -->

		<?php
	}

	public static function save_meta_box( $post_id, $post ) {
		// only continue if it's a deal post
		if ( $post->post_type != Group_Buying_Deal::POST_TYPE ) {
			return;
		}
		// don't do anything on autosave, auto-draft, bulk edit, or quick edit
		if ( empty( $_POST['gbs_bundles'] ) || wp_is_post_autosave( $post_id ) || $post->post_status == 'auto-draft' || defined( 'DOING_AJAX' ) || isset( $_GET['bulk_edit'] ) ) {
			return;
		}
		if ( empty( $_POST['gbs_bundles'] ) ) {
			GBS_Bundles_Addon::set_bundles( $post_id, array() );
			return;
		}
		// $exploded = preg_split( '/[\,\s]/', $_POST['gbs_bundles'], -1, PREG_SPLIT_NO_EMPTY );
		$items = array_unique( $_POST['gbs_bundles'] );
		foreach ( $items as $key => $item_id ) {
			// Must be a deal
			if ( get_post_type( $item_id ) !== Group_Buying_Deal::POST_TYPE ) {
				unset( $items[$key] );
			}
			// Can't bundle ones self
			if ( $item_id == $post_id ) {
				unset( $items[$key] );
			}
		}
		GBS_Bundles_Addon::set_bundles( $post_id, $items );
	}

	////////////////
	// Attributes //
	////////////////

	public function add_attribute_column_header() {
		echo '<th id="bundled_items">' . gb__( 'Bundled Items' ) . '</th>';
	}

	public function add_attribute_row( $post_id, $data ) {
		$items = array();
		$items = GBS_Bundles_Addon::get_bundles( $post_id );
?>
			<td class="attribute_bundled_items" data-att_id="<?php echo $post_id ?>">
				<p id="<?php echo $post_id ?>_attributes_associated_items" class="attributes_associated_items">
					<?php if ( empty( $items ) ): ?>
						<span class="no_items"><?php gb_e( 'No items bundled yet.' ) ?></span>
					<?php else: ?>
						<?php foreach ( $items as $item_id ): ?>
							<label><input type="checkbox" checked value="<?php echo $item_id ?>" name="gb-attribute[gbs_bundles][<?php echo $post_id ?>][]" /> <?php echo get_the_title( $item_id ) ?></label><br/>
						<?php endforeach ?>
					<?php endif ?>
				</p>
				<p>
					<label><?php gb_e( 'Enter a Deal ID' ) ?></label>
					<input type="text" size="8" id="<?php echo $post_id ?>_deal_id_input" class="deal_id_input" placeholder="<?php gb_e( 'Deal ID' ) ?>" />
					<br/><span id="<?php echo $post_id ?>_deals_name_ajax" class="deals_name_ajax">&nbsp;</span>
				</p>
			</td>
		<?php
	}

	public function add_attribute_row_js() {
?>
			<script type="text/javascript">
				jQuery(document).ready( function($) {

					var show_deal_name = function(e) {
						var $field = $(this);
						var $wrap = $field.parents('.attribute_bundled_items');
						var $items_span = $wrap.find( '.attributes_associated_items' );
						var $span = $wrap.find( '.deals_name_ajax' );
						var $item_id = $field.val();

						if ( !$item_id ) {
							$span.removeClass('loading_gif');
							return;
						}

						// Delay the ajax call and the loader in case the user is faster than the callbacks
						setTimeout(function(){
							if ( $field.val() == $item_id ) {
								$span.addClass('loading_gif').empty();
								$.ajax({
									type: 'POST',
									dataType: 'json',
									url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
									data: {
										action: 'gbs_ajax_get_deal_info',
										id: $item_id
									},
									success: function(data) {
										if ( data !== null ) {
											$span.removeClass('loading_gif');
											$span.empty().append( '<a href="javascript:void(0)" class="add_item" data-id="' + data.deal_id + '" data-name="' + data.title + '">+ ' + data.title + '</a>');
										};
									}
								});
							}
						},500);

					};
					var add_input_item = function(e) {
						var $att_selected = $(this);
						var $att_wrap = $att_selected.parents('.attribute_bundled_items');
						var $att_items_span = $att_wrap.find( '.attributes_associated_items' );
						var $att_field = $att_wrap.find( '.deal_id_input' );
						var $att_span = $att_wrap.find( '.deals_name_ajax' );
						var $att_item_id = $att_selected.data('id');
						var $att_item_title = $att_selected.data('name');
						var $att_id = $att_wrap.data('att_id');

						// remove note
						$att_wrap.find('.no_items').remove();

						// Add the input
						$att_items_span.append( '<label><input type="checkbox" checked value="' + $att_item_id + '" name="gb-attribute[gbs_bundles][' + $att_id + '][]" /> ' + $att_item_title + '</label><br/>' );
						// Reset the input
						$att_field.val('');
						$att_span.empty();

					};
					// AJAX search based on deal id
					if ( $('.deal_id_input').length > 0 ) {
						$('.deal_id_input').live('keyup',show_deal_name);
					}
					// After selecting the deal the item is added to the list.
					$('.add_item').live('click',add_input_item);
				});
			</script>
			<style type="text/css">
				.loading_gif {
					background: url( '<?php echo GB_URL; ?>/resources/img/loader.gif') no-repeat 0 center;
					width: auto;
					height: 16px;
					padding-right: 16px;
					padding-bottom: 2px;
					margin-left: 10px;
					margin-top: 10px;
				}
			</style>
			<td class="bundled_items">
				<p class="attributes_associated_items">
					<span class="no_items"><?php gb_e( 'This attribute needs to be saved or published before a bundles can be added.' ) ?></span>
				</p>
				<?php /*/ ?>
				<p class="attributes_associated_items">
					<input class="no_items" type="hidden" value="0" name="gb-attribute[gbs_bundles][][]" />
					<span class="no_items"><?php gb_e('No items bundled yet.') ?></span>
				</p>
				<p>
					<label><?php gb_e( 'Enter a Deal ID' ) ?></label>
					<input type="text" size="8"class="deal_id_input" placeholder="<?php gb_e( 'Deal ID' ) ?>" />
					<br/><span class="deals_name_ajax">&nbsp;</span>
				</p>
				<?php /**/ ?>
			</td>
		<?php
	}

	public function save_attributes( $post_id, $post ) {
		if ( isset( $_POST['gb-attribute']['gbs_bundles'] ) ) {
			foreach ( $_POST['gb-attribute']['gbs_bundles'] as $post_id => $items ) {
				$items = array_unique( $items );
				GBS_Bundles_Addon::set_bundles( $post_id, $items );
			}
		}
	}

}
