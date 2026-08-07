<?php
// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id = 'g-recaptcha-response-' . uniqid();

?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?compat=recaptcha" async defer></script>
<script>
	var onloadCallback = function() {
		grecaptcha.execute();
	}
	
	function setResponse(response) {
		document.getElementById("<?php echo $id; ?>").value = response; 
	}
</script>
<div class="g-recaptcha" style="text-align: center; margin-bottom: 8px;" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-size="compact" data-callback="setResponse"></div>
<input type="hidden" id="<?php echo $id; ?>" name="captcha-response" />
