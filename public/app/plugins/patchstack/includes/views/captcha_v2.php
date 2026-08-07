<?php
// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
<noscript>
	<iframe src="https://www.google.com/recaptcha/api/fallback?k=<?php echo esc_attr( $site_key ); ?>"></iframe>
	<textarea id="g-recaptcha-response" name="g-recaptcha-response" class="g-recaptcha-response"></textarea>
</noscript>
<script id="gRecaptchaSrc" src="https://www.google.com/recaptcha/api.js"></script>
