<div class="checkout_block left_form background_alt billing_info clearfix">

	<div class="paymentform-info">
		<h2 class="table_heading contrast font_medium gb_ff"><?php self::_e('Select a Charity'); ?></h2>
	</div>
	<fieldset id="gb-billing">
		<table class="billing">
			<tbody>
				<tr>
					<td><label for="gb_charity"><?php gb_e('Where would you like a portion of your purchase donated to?') ?></label></td>
					<td>
						<select name="gb_charity" id="gb_charity" style="min-width:180px;"/>
							<?php
								$selected = (isset($_POST[ 'gb_charity' ])) ? $_POST[ 'gb_charity' ] : '' ;
								echo '<option value="">'.gb__('Select a Charity').'</option>';
								foreach ($charities as $charity) {
									$option = '<option value="'.$charity->slug.'" '.selected($selected,$charity->slug).'>'.$charity->name.'</option>';
									print $option;
								}
							 ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>
	</fieldset>

</div>

<script type="text/javascript">
jQuery(document).ready(function(){
  	jQuery("#gb_checkout_payment").validate({
		rules: {
			gb_charity: {
				required: true,
				minlength: 1
			}
		},
		messages: {
			gb_charity: "<?php gb_e('Selecting a Charity is Required.') ?>"
		}
	});
});
</script>