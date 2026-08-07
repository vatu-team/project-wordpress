<?php
// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<p class="form-row">
	<label for="patchstack_2fa"><?php echo esc_attr__( '2FA Code', 'patchstack' ); ?></label>
	<input class="input-text woocommerce-Input" type="text" name="patchstack_2fa" id="patchstack_2fa" />
</p>
<div class="clear"></div>