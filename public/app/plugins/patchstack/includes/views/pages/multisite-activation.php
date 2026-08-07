<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="patchstack-plan" style="margin-bottom: 64px;">
	<h2 style="padding: 0;">Multisite Activation</h2>

	<p><strong>To activate Patchstack on your multisite network, follow these steps:</strong></p>
	
	<ol style="margin-left: 20px;">
		<li><strong>Add all your network sites to Patchstack:</strong><br>Go to <a href="https://app.patchstack.com/sites/overview?add=1" target="_blank">app.patchstack.com</a> and add each of your multisite URLs individually.<br><br></li>
		<li><strong>Activate API credentials on each site:</strong><br>For each site in your network, visit its individual WordPress admin panel and go to <strong>Settings → Security</strong> to add the API credentials. An overview of sites and its settings page can be found <a href="admin.php?page=patchstack-multisite">here</a>.</li>
	</ol>

	<p>
		<strong>Note:</strong> All sites in your multisite network must be added to your Patchstack account individually and then activated with API keys on each site. For more information, visit the <a href="https://docs.patchstack.com/patchstack-plugin/installing-patchstack-on-a-multisite" target="_blank">multisite setup documentation</a>.
	</p>
</div>
