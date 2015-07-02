jQuery(document).ready( function($) {

	// AdminExpiringPosts is a localized variable

	// exp-timestamp is not on this page, don't run script
	if ( ! document.getElementById( 'exp-timestamp' ) )
		return;

	var $postStatus = $( document.getElementById( 'post_status' )),
			saveButton = document.getElementById('save-post');

	// JS hack to append Expired to the post status editor
	$postStatus.append( $( document.getElementById( 'expired-status' ) ) );
	// reset post_status value after appending expired option
	$postStatus.val( AdminExpiringPosts.post_status );

	// Set button text to "Update" instead of "Publish" when post is expired
	if ( 'expired' == AdminExpiringPosts.post_status ){
		document.getElementById('post-status-display').innerHTML = AdminExpiringPosts.expired_text;
		saveButton.value = AdminExpiringPosts.save_text;
		$( saveButton ).on('click', function(){
			setTimeout( function(){
				saveButton.value= AdminExpiringPosts.save_text;
			}, 10 );
		});
	}


	var $timestamp = $( document.getElementById( 'exp-timestamp' ) ),
	    $timestampDiv = $( document.getElementById( 'exp-timestampdiv' ) ),
	    $editTimestamp = $( document.querySelector( '.exp-edit-timestamp' ) ),
	    $expEnable = $(document.getElementById( 'exp-enable' ) ),
	    expEnableValue = ( $expEnable.attr('checked') ) ? $expEnable.attr('checked') : false,
	    $expMM = $(document.getElementById( 'exp-mm' ) ),
	    $expJJ = $(document.getElementById( 'exp-jj' ) ),
	    $expAA = $(document.getElementById( 'exp-aa' ) ),
	    $expHH = $(document.getElementById( 'exp-hh' ) ),
	    $expMN = $(document.getElementById( 'exp-mn' ) );

	// Cancel Button
	$( document.querySelector( '.exp-cancel-timestamp' ) ).on( 'click', expCancelTimestamp );

	// Save Button
	$( document.querySelector( '.exp-save-timestamp' ) ).on( 'click', expSaveTimestamp );

	// show / hide time adjustor and Edit button
	$editTimestamp.on( 'click', editTimestampClick );

	function expCancelTimestamp() {
		$timestampDiv.slideUp("normal");
		$expMM.val($( document.getElementById( 'hidden_exp-mm' ) ).val());
		$expJJ.val($( document.getElementById( 'hidden_exp-jj' ) ).val());
		$expAA.val($( document.getElementById( 'hidden_exp-aa' ) ).val());
		$expHH.val($( document.getElementById( 'hidden_exp-hh' ) ).val());
		$expMN.val($( document.getElementById( 'hidden_exp-mn' ) ).val());
		$expEnable.attr( 'checked', expEnableValue );
		$editTimestamp.show();
		return false;
	}

	function expSaveTimestamp() { // crazyhorse - multiple ok cancels
		var aa = $expAA.val(), mm = $expMM.val(), jj = $expJJ.val(), hh = $expHH.val(), mn = $expMN.val(),
		    newD = new Date( aa, mm - 1, jj, hh, mn );

		if ( ! ( expEnableValue = $expEnable.attr('checked') ) )
			expEnableValue = false;

		if ( newD.getFullYear() != aa || (1 + newD.getMonth()) != mm || newD.getDate() != jj || newD.getMinutes() != mn ) {
			$('.exp-timestamp-wrap', '#exp-timestampdiv').addClass('form-invalid');
			return false;
		} else {
			$('.exp-timestamp-wrap', '#exp-timestampdiv').removeClass('form-invalid');
		}

		$timestampDiv.slideUp("normal");
		$editTimestamp.show();

		if ( expEnableValue ) {
			$timestamp.html( AdminExpiringPosts.expires_never );
		} else {
			$timestamp.html(
					AdminExpiringPosts.expires_on + ' <b>' +
							$( '#exp-mm option[value="' + mm + '"]' ).text() + ' ' +
							jj + ', ' +
							aa + ' @ ' +
							hh + ':' +
							mn + '</b> '
			);
		}
		return false;
	}

	function editTimestampClick() {
		if ($timestampDiv.is(":hidden")) {
			$timestampDiv.slideDown("normal");
			$editTimestamp.hide();
		}
		return false;
	}

});