<?php
// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p>
	<label for="patchstack_2fa"><?php echo esc_attr__( '2FA Code', 'patchstack' ); ?>
		<span style="font-size: 9px;">(<?php echo esc_attr__( 'leave empty if no 2FA setup', 'patchstack' ); ?>)</span><br />
		<input type="text" name="patchstack_2fa" id="patchstack_2fa" class="input" value="" size="25" autocomplete="off" />
	</label>
</p>
