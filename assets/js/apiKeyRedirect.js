jQuery(document).ready(function($) {
	$('.nodeless-api-key-link').click(function(e) {
		e.preventDefault();
		const mode = $('#nodeless_mode').val();
		if (mode === 'production') {
			window.open('https://nodeless.io/app/profile/api-keys');
		} else {
			window.open('https://testnet.nodeless.io/app/profile/api-keys')
		}
	});
});
