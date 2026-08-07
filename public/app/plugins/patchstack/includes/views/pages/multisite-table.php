<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Output the form and table.
require_once dirname( __FILE__ ) . '/../../admin/multisite-table.php';
?>
<div class="patchstack-font">
	<?php
		if ( isset( $_GET['success'], $_GET['site'] ) && ctype_digit( $_GET['site'] ) ) {
			$site = get_site( $_GET['site'] );
			echo '<div class="notice notice-success inline" style="margin: 20px 0 0 0;"><p><strong>The database migration has been re-run for the site: ' . esc_url( $site->siteurl ) . '</strong></p></div>';
		}
	?>

	<div class="notice notice-success inline" style="margin: 20px 0 0 0;">
		<p>
			<strong>In order to activate Patchstack on a site, go to the <a href="admin.php?page=patchstack-multisite-settings">activation page.</a></strong>
		</p>
	</div>

	<form method="GET" style="display: table;">
		<div class="wrap">
			<h2>Patchstack - Available Sites</h2>
		</div>
		<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
		<?php
			$table = new Patchstack_Network_Sites_Table();
			$table->prepare_items();
		?>
	</form>
	<?php
		$table->display();
	?>
</div>
