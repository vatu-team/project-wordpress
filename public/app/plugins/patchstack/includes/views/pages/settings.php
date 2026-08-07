<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$managed = get_option( 'patchstack_managed', false );
?>
<div class="patchstack-content-wrap patchstack-free">
	<div class="patchstack-top">
		<div class="patchstack-top-logo">
			<img src="<?php echo esc_url( $this->plugin->url ); ?>assets/images/logo.svg" alt="">
		</div>
	</div>

	<div class="patchstack-top">
		<h1 <?php echo !$managed ? 'style="display: none;"' : ''; ?>>
			<?php echo wp_kses(get_option( 'patchstack_managed_text', '' ), $this->allowed_html); ?>
		</h1>
	</div>

	<div class="patchstack-content-table">
		<div class="patchstack-content-inner patchstack-premium">
			<?php
				if (isset($_GET['page']) && $_GET['page'] == 'patchstack-multisite-settings' && is_multisite()) {
					require 'multisite-activation.php';
				} else {
					require 'license.php';
				}
			?>
		</div>
	</div>
</div>