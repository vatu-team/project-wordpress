<?php
// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (checked( 1, get_user_option( 'webarx_2fa_enabled', $user->ID ), false )) {
?>
<table class="form-table">
	<tbody>
		<tr>
			<th scope="row"><?php esc_attr_e( 'Enable 2FA', 'patchstack' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php esc_attr_e( 'Enable 2FA', 'patchstack' ); ?></span></legend>
					<label for="patchstack_2fa_enabled"><input name="patchstack_2fa_enabled" type="checkbox" id="patchstack_2fa_enabled" value="1" <?php echo checked( 1, get_user_option( 'webarx_2fa_enabled', $user->ID ), false ); ?>> <?php esc_attr_e( 'Enable 2FA on this account', 'patchstack' ); ?></label><br />
				</fieldset>
			</td>
		</tr>
	</tbody>
</table>
<?php
}
?>