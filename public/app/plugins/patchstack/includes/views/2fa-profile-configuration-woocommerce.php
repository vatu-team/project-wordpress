<?php
// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<br>
<fieldset>
	<legend><?php esc_attr_e( 'Two Factor Authentication Configuration', 'patchstack' ); ?></legend>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="patchstack_2fa_enabled"><?php esc_attr_e( 'Enable 2FA', 'patchstack' ); ?></label>
		<input class="woocommerce-Input" name="patchstack_2fa_enabled" type="checkbox" id="patchstack_2fa_enabled" value="1" <?php echo checked( 1, get_user_option( 'webarx_2fa_enabled', $user->ID ), false ); ?>>
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="patchstack_2fa_secretkey"><?php esc_attr_e( 'Secret Key', 'patchstack' ); ?></label>
		<input class="woocommerce-Input woocommerce-Input--text input-text" name="patchstack_2fa_secretkey" type="text" id="patchstack_2fa_secretkey" value="<?php echo esc_attr( $secret ); ?>" disabled="disabled">
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="password_2"><?php esc_attr_e( 'QR Code Image', 'patchstack' ); ?> (<?php esc_attr_e( 'Scan this image with your 2FA app', 'patchstack' ); ?>)</label>
		<div id="qrcode"></div><br />
		<script src="<?php echo $this->plugin->url; ?>/assets/js/qrcode.min.js" type="text/javascript"></script>
		<script type="text/javascript">
			jQuery(function(){
				new QRCode(document.getElementById("qrcode"), {
					text: "<?php echo 'otpauth://totp/WordPress:' . rawurlencode( get_bloginfo( 'name' ) ) . '?secret=' . rawurlencode( $secret ) . '&issuer=WordPress'; ?>",
					width: 128,
					height: 128,
				});

				jQuery('[name=patchstack_2fa_enabled').on('change', function(){
					if (this.checked) {
						jQuery('.woocommerce-form-patchstack-verification').show();
					} else {
						jQuery('.woocommerce-form-patchstack-verification').hide();
					}
				});
			});
		</script>
	</p>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide woocommerce-form-patchstack-verification" style="display: none;">
		<label for="patchstack_2fa_secretkey_verification"><?php esc_attr_e( 'Enter code from 2FA app to verify', 'patchstack' ); ?></label>
		<input class="woocommerce-Input woocommerce-Input--text input-text" name="patchstack_2fa_secretkey_verification" type="text" id="patchstack_2fa_secretkey_verification">
	</p>
</fieldset>
