<?php
// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id = 'g-recaptcha-response-' . uniqid();

?>
<script id="gRecaptchaSrc" src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr( $site_key ); ?>"></script>
<script>
	grecaptcha.ready(function() {
		grecaptcha.execute('<?php echo esc_attr( $site_key ); ?>', {action: 'submit'}).then(function(token) {
			document.getElementById("<?php echo $id; ?>").value = token; 
		});
	});
</script>
<input type="hidden" id="<?php echo $id; ?>" name="g-recaptcha-response" />
