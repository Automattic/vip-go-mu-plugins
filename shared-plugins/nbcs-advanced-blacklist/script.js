jQuery(document).ready(function($) {
	$('#new-blacklist-term').keyup(updateBlacklistNotes);
	$('#new-blacklist-term').keypress(function(ev) {
		if (ev.which == 13) {
			$('#advanced-blacklist-add-button').click();
			return false;
		}
	});
	$('#advanced-blacklist-add-button').click(function(ev) {
		var type = jQuery('input[name=advanced-blacklist-term-type]:checked').val();
		var term = jQuery.trim(jQuery('#new-blacklist-term').val());
		
		switch (type) {
			case 'default':
				var blacklist = jQuery('textarea[name=blacklist_keys]').val();
				if (("\n" + blacklist + "\n").match("\n" + regexpEscape(term) + "\n")) {
					alert('The term "' + term + '" is already on the standard blacklist');
					return false;
				}
				if (blacklist > '') {
					blacklist += "\n";
				}
				jQuery('textarea[name=blacklist_keys]').val(blacklist + term);
				alert('The term "' + term + '" has been added to the standard blacklist');
				jQuery('#new-blacklist-term').val('');
				updateBlacklistNotes();
				break;

			case 'word':
				var blacklist = jQuery('textarea[name=nbcs-word-blacklist]').val();
				if (("\n" + blacklist + "\n").match("\n" + regexpEscape(term) + "\n")) {
					alert('The term "' + term + '" is already on the standard blacklist');
					return false;
				}
				if (blacklist > '') {
					blacklist += "\n";
				}
				jQuery('textarea[name=nbcs-word-blacklist]').val(blacklist + term);
				alert('The word "' + term + '" has been added to the full word blacklist');
				jQuery('#new-blacklist-term').val('');
				updateBlacklistNotes();
				break;

			case 'user':
				var blacklist = jQuery('textarea[name=nbcs-user-blacklist]').val();
				if (("\n" + blacklist + "\n").match("\n" + regexpEscape(term) + "\n")) {
					alert('The term "' + term + '" is already on the standard blacklist');
					return false;
				}
				if (blacklist > '') {
					blacklist += "\n";
				}
				jQuery('textarea[name=nbcs-user-blacklist]').val(blacklist + term);

				var termType = 'username';
				if (term.match(/@/)) {
					termType = 'e-mail address';
				} else if (term.match(/^\s*[\d.]+\s*$/)) {
					termType = 'IP address';
				}
				
				alert('The ' + termType + ' "' + term + '" has been added to the user blacklist');
				jQuery('#new-blacklist-term').val('');
				updateBlacklistNotes();
				break;
		}
		
		return false;
	});
});

function updateBlacklistNotes()
{
	var term = jQuery.trim(jQuery('#new-blacklist-term').val());
	
	jQuery('#advanced-blacklist-add-button')[0].disabled = (term == '');
	
	updateBlacklistDefaultNotes(term);
	updateBlacklistWordNotes(term);
	updateBlacklistUserNotes(term);
}

function updateBlacklistDefaultNotes(term)
{
	var note = 'Block any post which contains this term in the post contents or user information';
	var showTerm = '<span class="ab-term">' + term + '</span>';

	if (term.match(/^\s*[\d.]+\s*$/)) {
		note = 'Block any post posted from an IP address containing "' + showTerm + '" or with post contents containing "' + showTerm + '" (including as part of a longer term such as 128.' + showTerm + '.0)';
	} else if (term != '') {
		var plural = term.match(/\s/);
		note = 'Block any post containing the term "' + showTerm + '" in the post contents or user information; this includes any use of the ' + (plural ? 'phrase' : 'word') + ' "' + showTerm + '" and variants such as "' + showTerm + 's", "' + showTerm + 'ing", or "super' + showTerm + '" (in post contents, usernames, user emails, and so on)';
	}

	jQuery('#advanced-blacklist-default-description').html(note);
}

function updateBlacklistWordNotes(term)
{
	var note = 'Block any post which contains this exact word in the post contents or user information';
	var showTerm = '<span class="ab-term">' + term + '</span>';
	var plural = term.match(/\s/);

	if (term != '') {
		note = 'Block any post containing the exact ' + (plural ? 'phrase' : 'word') + ' "' + showTerm + '" in the post contents; this does not include any variant of the ' + (plural ? 'phrase' : 'word');
	}

	jQuery('#advanced-blacklist-word-description').html(note);
}

function updateBlacklistUserNotes(term)
{
	var note = 'Block any post which is made by a user with this username, IP address, or e-mail address';
	var showTerm = '<span class="ab-term">' + term + '</span>';
	
	if (term.match(/@/)) {
		note = 'Block any post made by a user with the e-mail address "' + showTerm + '"';
	} else if (term.match(/^\s*[\d.]+\s*$/)) {
		note = 'Block any post made by a user with the IP address "' + showTerm + '"';
	} else if (term != '') {
		note = 'Block any post made by the user with the username "' + showTerm + '"';
	}
	jQuery('#advanced-blacklist-user-description').html(note);
}

// Add escaping for regexes to do literal matches
function regexpEscape(text)
{
    return text.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&");
}
