jQuery(document).ready(function($) {
	function isValidUrl(serverUrl) {
		try {
			const url = new URL(serverUrl);
			if (url.protocol !== 'https:' && url.protocol !== 'http:') {
				return false;
			}
		} catch (e) {
			console.error(e);
			return false;
		}
		return true;
 	}

	$('.nodeless-api-key-link').click(function(e) {
		e.preventDefault();
		const url = $('#nodeless_url').val();
		if (isValidUrl(url)) {
			window.open(url);
		} else {
			alert('Please enter a valid url including https:// in the Nodeless.io URL input field.')
		}
	});
});
