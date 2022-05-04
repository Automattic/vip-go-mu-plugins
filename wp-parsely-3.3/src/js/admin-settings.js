document.querySelector( '.media-single-image button.browse' ).addEventListener( 'click', selectImage );

function selectImage() {
	const optionName = this.dataset.option;

	const imageFrame = wp.media( {
		multiple: false,
		library: {
			type: 'image',
		},
	} );

	imageFrame.on( 'select', function() {
		const url = imageFrame.state().get( 'selection' ).first().toJSON().url;
		const inputSelector = '#media-single-image-' + optionName + ' input.file-path';
		document.querySelector( inputSelector ).value = url;
	} );

	imageFrame.open();
}
