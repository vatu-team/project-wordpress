<?php
// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id = 'g-recaptcha-response-' . uniqid();

?>
<script src="https://www.google.com/recaptcha/api.js?onload=onloadCallback" async defer></script>
<script>
	var onloadCallback = function() {
		grecaptcha.execute();
	}
	
	function setResponse(response) {
		document.getElementById("<?php echo $id; ?>").value = response; 
	}
</script>
<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-size="invisible" data-callback="setResponse"></div>
<input type="hidden" id="<?php echo $id; ?>" name="captcha-response" />
