<?php
// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_attr_e( 'Two Factor Authentication Configuration', 'patchstack' ); ?></h2>
<table class="form-table">
	<tbody>
		<tr>
			<th scope="row"><?php esc_attr_e( 'Enable 2FA', 'patchstack' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php esc_attr_e( 'Enable 2FA', 'patchstack' ); ?></span></legend>
					<label for="patchstack_2fa_enabled"><input name="patchstack_2fa_enabled" type="checkbox" id="patchstack_2fa_enabled" value="1" <?php echo checked( 1, get_user_option( 'webarx_2fa_enabled', $user->ID ), false ); ?>> <?php esc_attr_e( 'Enable 2FA on your account.', 'patchstack' ); ?></label><br />
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_attr_e( 'Secret Key', 'patchstack' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php esc_attr_e( 'Secret Key', 'patchstack' ); ?></span></legend>
					<label for="patchstack_2fa_secretkey"><input name="patchstack_2fa_secretkey" type="text" id="patchstack_2fa_secretkey" value="<?php echo esc_attr( $secret ); ?>"></label><br />
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_attr_e( 'QR Code Image', 'patchstack' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php esc_attr_e( 'QR Code Image', 'patchstack' ); ?></span></legend>
					<div id="qrcode"></div><br />
					<?php esc_attr_e( 'Scan this image with your 2FA app.', 'patchstack' ); ?>
					<script type="text/javascript">
						jQuery(function(){
							new QRCode(document.getElementById("qrcode"), {
								text: "<?php echo 'otpauth://totp/WordPress:' . rawurlencode( get_bloginfo( 'name' ) ) . '?secret=' . rawurlencode( $secret ) . '&issuer=WordPress'; ?>",
								width: 128,
								height: 128,
							});
						})
					</script>
				</fieldset>
			</td>
		</tr>
	</tbody>
</table>
