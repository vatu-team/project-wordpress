<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Determine if the subscription of the account is expired.
$status = ( $this->plugin->client_id != 'PATCHSTACK_CLIENT_ID' || get_option( 'patchstack_clientid', false ) != false ) && !empty( $this->get_secret_key() );
$free = get_option( 'patchstack_license_free', 0 ) == 1;
$planClass = get_option( 'patchstack_subscription_class', '');
$site_id = (int) get_option( 'patchstack_site_id', 0 );
$app_url = $site_id != 0 ? 'https://app.patchstack.com/site/' . $site_id . '/' : 'https://app.patchstack.com/sites/overview';

// Generate the link to turn on settings management.
$secretToken = get_option( 'patchstack_activation_secret', '' );
$secretTime = get_option( 'patchstack_activation_time', '' );
if (!empty($secretToken) && !$this->is_connected() ){
	echo '<script>jQuery(function(){ window.Patchstack.poller(); });</script>';
}
?>
<div class="patchstack-free patchstack-auto-activate" style="<?php echo $status ? 'display: none;' : ''; ?>">
	<div>
		<div class="is-polling-section has-text-centered">
			<img src="<?php echo esc_url( $this->plugin->url ); ?>assets/images/polling.svg" class="rotate">

			<p class="is-text-medium">
				<?php esc_html_e( 'Checking sync status', 'patchstack' ); ?>
			</p>

			<?php
				$mins = ! empty( $secretTime ) ? floor( ( ( 1800 ) - ( time() - $secretTime ) ) / 60 ) : 0;
				if ( $mins >= 1 ) {
					echo '<p class="patchstack-upsell patchstack-has-text-white patchstack-top-16">' . sprintf('%s %d %s', esc_html__('This plugin is only a connector. Add this website to the Patchstack dashboard within '), $mins, esc_html__(' minutes to initiate automatic sync.')) . '</p>';
				} else {
					if ( ( $this->plugin->client_id != 'PATCHSTACK_CLIENT_ID' || get_option( 'patchstack_clientid', false ) != false ) && empty( $this->get_secret_key() ) ) {
						echo '<p class="patchstack-upsell patchstack-has-text-danger patchstack-top-16">' . esc_html__('The connection with Patchstack was lost, this can happen when your WordPress salts change or after a migration. Please re-enter your API key.', 'patchstack') . '</p>';
					} else {
						echo '<p class="patchstack-upsell patchstack-has-text-white patchstack-top-16">' . esc_html__('This plugin is only a connector. Add this website to the Patchstack dashboard to sync your unique API key.', 'patchstack') . '</p>';
					}
				}
			?>
		</div>

		<div class="patchstack-plan patchstack-plan2">
			<div class="form-table patchstack-form-table">
				<div class="patchstack-activate-wrapper">
					<label for="patchstack_api_key"><?php esc_html_e( 'Paste API key for manual sync', 'patchstack' ); ?></label>
					<input class="regular-text" type="text" id="patchstack_api_key2" placeholder="<?php esc_attr_e( 'Unique API key', 'patchstack' ); ?>">
					<input type="submit" id="patchstack-activate" value="<?php echo esc_attr__( 'Sync', 'patchstack' ); ?>" class="button-primary" />
				</div>
			</div>

			<div style="clear: both;"></div>

			<div class="patchstack-loading"><div></div><div></div><div></div><div></div></div>
		</div>
		<div style="clear: both;"></div>
	</div>
</div>

<p class="patchstack-upsell" style="<?php echo $status ? 'display: none;' : ''; ?>">
	<a href="https://app.patchstack.com/login?free=1" target="_blank"><?php esc_html_e( 'Log in to dashboard', 'patchstack' ); ?></a>
</p>

<div class="patchstack-free" style="<?php echo !$status ? 'display: none;' : ''; ?>">
	<div>
		<p class="patchstack-upsell patchstack-has-text-white" style="<?php echo $managed ? 'display: none;' : ''; ?>">
			<?php if ($free) { ?>
				<?php echo esc_attr__('Log in to our dashboard to access vulnerability data, to manage protection modules and remote settings.', 'patchstack' ); ?>
			<?php } else { ?>
				<?php echo sprintf('%s %s %s', esc_attr__('Log in to our dashboard to access vulnerability data, to manage protection modules and remote ', 'patchstack' ), '<a href="' . $app_url . 'hardening/firewall" target="_blank">hardening</a>', esc_attr('settings for this website.', 'patchstack' )); ?>
			<?php } ?>
		</p>

		<div class="patchstack-plan patchstack-plan2">
			<?php
				if (!isset($_GET['key'])) {
			?>
			<div class="patchstack-rows">
				<div class="patchstack-connection-row">
					<span class="hint--top" aria-label="<?php !$this->is_connected() ? esc_html_e( 'There is not a proper connection to Patchstack', 'patchstack' ) : esc_html_e( 'Actively monitoring for new vulnerabilities', 'patchstack' ); ?>">
						<?php esc_html_e( 'Connection status', 'patchstack' ); ?>
						<span><img src="<?php echo esc_url( $this->plugin->url ); ?>assets/images/info.svg" alt=""></span>
						<a href="#" class="patchstack-retry-link"><?php esc_html_e( 'Retry', 'patchstack' ); ?></a>
					</span>

					<span class="patchstack-connection-status <?php echo $this->is_connected() ? 'is-connected' : 'is-disconnected'; ?>">
						<span class="patchstack-last-sync"><?php echo esc_html( $this->format_relative_time( $this->get_last_sync_time() ) ); ?></span>
						<span class="patchstack-status-dot" aria-hidden="true"></span>
					</span>
				</div>

				<div>
					<span>
						<?php esc_html_e( 'Protection', 'patchstack' ); ?>
					</span>

					<span class="<?php echo !$this->is_protected() && empty($planClass) ? 'ps-label has-error' : 'ps-label has-success'; ?>">
						<?php !$this->is_protected() && empty($planClass) ? esc_html_e( 'Disabled', 'patchstack' ) : esc_html_e( 'Enabled', 'patchstack' ); ?>
					</span>
				</div>

				<div>
					<span>
						<?php esc_html_e( 'Vulnerabilities present', 'patchstack' ); ?>
					</span>

					<span>
						<?php echo esc_html(get_option('patchstack_vulns_present', '?')); ?>
					</span>
				</div>

				<div>
					<span class="hint--top" aria-label="<?php esc_attr_e( 'The number of vulnerabilities which have a fix available through an update.', 'patchstack' ); ?>">
						<?php esc_html_e( 'Fixes available', 'patchstack' ); ?>
						<span><img src="<?php echo esc_url( $this->plugin->url ); ?>assets/images/info.svg" alt=""></span>
					</span>

					<span>
						<?php echo esc_attr(get_option('patchstack_fixes_present', '?')); ?>
					</span>
				</div>

				<?php if ($this->is_protected() || !empty($planClass)) { ?>
				<div>
					<span class="hint--top" aria-label="<?php esc_attr_e( 'The number of vulnerability specific vPatches. This is 0 when no severe vulnerabilities are present.', 'patchstack' ); ?>">
						<?php esc_html_e( 'vPatches present', 'patchstack' ); ?>
						<span><img src="<?php echo esc_url( $this->plugin->url ); ?>assets/images/info.svg" alt=""></span>
					</span>

					<span>
						<?php echo esc_html(get_option('patchstack_vpatches_present', '?')); ?>
					</span>
				</div>

				<div>
					<span class="hint--top" aria-label="<?php esc_attr_e( 'The number of firewall rules. These are not vulnerability specific.', 'patchstack' ); ?>">
						<?php esc_html_e( 'Firewall rules present', 'patchstack' ); ?>
						<span><img src="<?php echo esc_url( $this->plugin->url ); ?>assets/images/info.svg" alt=""></span>
					</span>

					<span>
						<?php echo esc_html(get_option('patchstack_non_vpatches_present', '?')); ?>
					</span>
				</div>

				<div>
					<span>
						<?php esc_html_e( 'Attacks blocked last 30 days', 'patchstack' ); ?>
					</span>

					<span>
						<?php echo $this->plugin->firewall_base->get_hits_counter(); ?>
					</span>
				</div>

				<div>
					<span class="hint--top" aria-label="Since July 9th, 2024.">
						<?php esc_html_e( 'Attacks blocked in total', 'patchstack' ); ?>
						<span><img src="<?php echo esc_url( $this->plugin->url ); ?>assets/images/info.svg" alt=""></span>
					</span>

					<span>
						<?php echo esc_html(get_option('patchstack_hits_all_time', 0)); ?>
					</span>
				</div>
				<?php } ?>
			</div>

			<div class="patchstack-license-button patchstack-login-button" <?php echo $managed ? 'style="display: none;"' : ''; ?>>
				<a href="<?php echo $app_url; ?>" class="patchstack-button button-primary patchstack-fullwidth" target="_blank">
					<?php esc_attr_e( 'Log in to view details', 'patchstack' ); ?>
				</a>
			</div>

			<?php
				} else {
			?>
			<div class="form-table patchstack-form-table">
				<label for="patchstack_api_key">
					<?php esc_html_e('API key', 'patchstack'); ?>
				</label>
				<input class="regular-text" type="text" id="patchstack_api_key" value="<?php echo get_option( 'patchstack_clientid', false ) ? esc_attr( $this->get_secret_key() . '-' . get_option( 'patchstack_clientid', false ) ) : ''; ?>" placeholder="<?php esc_attr_e('Unique API key', 'patchstack'); ?>">
			</div>

			<?php 
				if ( ! get_option( 'patchstack_clientid', false ) ) {
					echo '<p class="patchstack-error">' . esc_html__('To use this plugin, login and add it to the App dashboard and sync using the API key provided there.', 'patchstack') . '</p>';
				}
			?>

			<div class="patchstack-license-button">
				<input type="submit" id="patchstack-activate" value="<?php $status ? esc_attr_e( 'Re-sync with App', 'patchstack' ) : esc_attr_e( 'Activate', 'patchstack' ); ?>" class="button-primary patchstack-fullwidth" />
			</div>

			<div style="clear: both;"></div>

			<?php
				}

				if (isset($_GET['resync'])) {
					echo '<p class="patchstack-resync">' . esc_html__('The plugin is connected and synchronized.', 'patchstack') . '</p>';
				}
			?>

			<div class="patchstack-loading"><div></div><div></div><div></div><div></div></div>

			<?php
				if (isset($_GET['ps_autoa'])) {
					echo '<p class="patchstack-resync">' . esc_html__('Attempting to auto-activate the plugin, this will take a moment...', 'patchstack') . '</p>';
				}
			?>
		</div>
		<div style="clear: both;"></div>
	</div>
	
	<p class="patchstack-upsell patchstack-footer">
		<?php if ( ! $managed && $free ) { ?>
			<a href="<?php echo $app_url; ?>" target="_blank"><?php esc_html_e( 'Protect this site for $5/mo', 'patchstack' ); ?></a>
			<span></span>
		<?php } ?>

		<?php if ( ! isset( $_GET['key'] ) ) { ?>
			<a href="options-general.php?page=patchstack&key=1"><?php esc_html_e( 'Change API key', 'patchstack' ); ?></a>
		<?php } else { ?>
			<a href="options-general.php?page=patchstack"><?php esc_html_e( 'Back to overview', 'patchstack' ); ?></a>
		<?php } ?>
	</p>
</div>