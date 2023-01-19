jQuery(function ($) {
	// Open media library and get the selected image.
	$('.nodeless-icon-button').click(function (e) {
		e.preventDefault();

		let button = $(this),
			custom_uploader = wp.media({
				title: nodelessGatewayData.titleText,
				library: {
					type: 'image'
				},
				button: {
					text: nodelessGatewayData.buttonText
				},
				multiple: false
			}).on('select', function () { // it also has "open" and "close" events
				let attachment = custom_uploader.state().get('selection').first().toJSON();
				let url = '';
				if (attachment.sizes.thumbnail !== undefined) {
					url = attachment.sizes.thumbnail.url;
				} else {
					url = attachment.url;
				}
				$('.nodeless-icon-image').attr('src', url).show();
				$('.nodeless-icon-remove').show();
				$('.nodeless-icon-value').val(attachment.id);
				button.hide();
			}).open();
	});

	// Handle removal of media image.
	$('.nodeless-icon-remove').click(function (e) {
		e.preventDefault();

		$('.nodeless-icon-value').val('');
		$('.nodeless-icon-image').hide();
		$(this).hide();
		$('.nodeless-icon-button').show();
	});
});
