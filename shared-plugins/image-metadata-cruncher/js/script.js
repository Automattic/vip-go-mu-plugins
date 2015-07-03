/**
 * @author Peter Hudec
 * 
 * Depends on rangy-core.js and rangy-selectionsaverestore.js
 * http://code.google.com/p/rangy/
 */

jQuery(document).ready(function($) {
	var prefix = 'image_metadata_cruncher';
	
	// table with custom metadata templates
	var $customMeta = $('#custom-meta-list');
	
	// Custom meta delete button click handler
	$customMeta.delegate('button', 'click', function (event){
			event.preventDefault();
			
			// remove row
			var $row = getRow($(event.target));
			var $name = $row.find('.name');
			
			// if template has a name ask for confirmation
			var value = $name.val();
			if(value){
				// if not empty, mark red and ask
				$row.addClass('to-be-removed');
				if(confirm('Are you sure you want to remove the template "' + value + '"?')){
					// if confirmed remove row
					$row.remove();
				}else{
					// unmark red
					$row.removeClass('to-be-removed');
				}
			}else{
				// remove
				$row.remove();
			}
			
		});
	
	// updates name attribute of the custom meta template input to the value of the custom meta name field
	$customMeta.delegate('input.name', 'keyup', function(event) {
			$name = $(event.target)
			var $row = getRow($name);
			
			// template field
			var $template = $row.find('td:nth-child(2) > .hidden-input');
			
			// update name attribute
			$template.attr('name', prefix + '[custom_meta]['+ $name.val() +']');
		})
	
	// gets custom meta table row from elements inside the row cells
	function getRow($element) {
		return $element.parent().parent();
	}
	
	// creates new custom meta row
	$('#add-custom-meta').click(function(event) {
		event.preventDefault();
		
		var $name = $('<input type="text" class="name" />');
		var $ce = $('<div class="ce highlighted" contenteditable="true"></div>');
		var $template = $('<textarea class="hidden-input template"></textarea>'); // this field will be saved upon submit
		var $remove = $('<button class="button">Remove</button>');
		
		// create row
		var $row = $('<tr>').append($('<td>').append($name), $('<td>').append($ce, $template), $('<td>').append($remove));
			
		$customMeta.append($row);
	});
	
		
	///////////////////////////////////////////
	// Template tags syntax highlighting
	///////////////////////////////////////////
	
	// checks whether highlighting is allowed by the user
	function enableHighlighting(){
		// highlighting causes problems only by content editable elements which are only on the settings tab
		if($('#enable-highlighting').length > 0){
			// if there is a checkbox we are on the settings tab
			return $('#enable-highlighting').prop("checked");
		}else{
			// allow highlighting
			return true;
		}		
	}
	
	// Highlights all elements of class="highlighted" on keyup
	$('#metadata-cruncher').delegate('.highlighted', 'keyup', function(event) {
		var $target = $(event.target);
		var text;
		
		// highlight and get non HTML text
		if(enableHighlighting()){
			text = highlight(event);
		}else{
			text = $target.text();
		}
		
		// pass the resulting text to the hidden input form field
		$out = $target.parent().children('.hidden-input');
		// but only if that elements exist
		if($out.length){
			$out.html(text);
						
			// find and replace all &nbsp; entities which break functionality
			$out.html($out.html().replace(/&nbsp;/g, ' '));
		}
	})
	
	// triger the keyup event on content editable elements when rangy is ready
	rangy.addInitListener(function(r){
		$('#metadata-cruncher .highlighted').keyup();
	});
	
	// reset highlighted elements
	$('#metadata-cruncher').delegate('#enable-highlighting', 'change', function(event) {
		$('#metadata-cruncher .highlighted').each(function(index) {
			$(this).html($(this).text());
			$(this).keyup();
		});
	});
	
	// before submitting...
	$('#submit').click(function(event) {
		// ...make sure that all textareas are properly filled out
		$('#metadata-cruncher .highlighted').keyup();
		
		// ...remove all custom metadata rows with empty name field
		getRow($('#custom-meta-list .name').filter(function(){
			return $(this).val() == "";
		})).remove();		
	});
	
	// wraps value in HTML span of specified class
	function wrap(value, className){
	    if(value) {
	        return '<span class="' + className + '">' + value + '</span>';
	    }
	}
	
	// adds wrapped value to the result if value exists
	function addToResult(result, value, className){
	    if(value){
	    	if(className){
	    		result += wrap(value, className);
	    	}else{
	    		result += value;
	    	}
	    }
	    return result;
	}
	
	// shortcut for creating RegExp objects with comments
	function re() {
		return RegExp(Array.prototype.join.call(arguments, ''), 'g');
	}
	
	// returns true if the keyup event's keystroke doesn't make problems by syntax highlighting
	function safeKeystroke(event){
		
		var unsafeShiftKeys = [
			16, // shift
			33, // page up
			34, // page down
			35, // end
			36, // home
			37, // left
			38, // up
			39, // right
			40  // down
		];
		
		var unsafeCtrlKeys = [
			17, // ctrl
			67, // c
			65, // a
			89  // y
		];
		
		var shiftDanger = event.shiftKey && jQuery.inArray(event.which, unsafeShiftKeys) > -1;
		var ctrlDanger = event.ctrlKey && jQuery.inArray(event.which, unsafeCtrlKeys) > -1;
		var tabDanger = event.which == 9;
				
		var safe = !shiftDanger && !ctrlDanger && !tabDanger;
		
		if(safe){
			return true;
		}else{
			return false;
		}
	}
	
	// highlights the event target and returns its non html text
	function highlight(event) {
		// only do highlighting when the keystroke doesn't make problems
		if(safeKeystroke(event)){
			var $ = jQuery;			
			
			// input element
			var $in = $(event.target);
			
			// save carret position
			//   Rangy inserts boundary markers at the selection boundary
			var selection = rangy.saveSelection();
			
			// replace rangy boundary markers with this unusual unicode character \u25A8 which survives html to text conversion
			//   and save them to a temporary array
			var p = /<span[^>]*rangySelectionBoundary[^>]*>[^<]*<\/[^>]*>/g
			var markers = [];
			var html = $in.html().replace(p, function(match){
		        // store found marker...
		        markers.push(match);
		        // ...and replace with identifier
		        return '\u25A8';
		   });
		   // put it back to input
		   $in.html(html);
		   
		   // extract text and add markup
		   var newHTML = applyMarkup($in.text());   
		   
		   // restore rangy identifiers
		   newHTML = newHTML.replace('\u25A8', function(match){
		        // retrieve from temp storage
		        return markers.shift();
		   });
		   
		   // update input html
		   $in.html(newHTML);
		   
		   // restore selection
		   rangy.restoreSelection(selection);
		   
		   return $in.text();
		}
	}
	
	// replaces all valid tags with highlighted html
	function applyMarkup(input) {
		
		// matches sequence of word characters including period "." and hash "#"
		//  with min lenght of 1 and proper handling of "\u25A8" cursor character
		var prefixPattern = /(?:[\w.#\u25A8]{2,}|[^\u25A8\s]{1})/.source;
		
		// matches sequence of word characters including period ".", hash "#" and colon ":"
		//  with min lenght of 1 and proper handling of "\u25A8" cursor character
		var keywordPartPattern = /(?:[\w.:#\u25A8\-]{2,}|[^\u25A8\s]{1})/.source;
		
		// matches keyword in form of "abc:def(>ijk)*"
		var keywordPattern = re(
			prefixPattern, // must begin with category prefix
			':', // followed by colon
			keywordPartPattern, // followed by at least one part
			'(?:', // followed by optional subparts (>part)
				'>', // starting with gt
				keywordPartPattern, // and ending with word
			')*'
		).source;
		
		// matches set of keywords delimited by pipe character but not with a trailng pipe
		var keywordsPattern = re(
			keywordPattern, // must begin with at least one keyword
			'(?:', // followed by zero or more groups of ( | keyword)
				'[\\s\u25A8]*', // optional space
				'\\|', // must begin with pipe
				'[\\s\u25A8]*', // optional space
				keywordPattern, // keyword
			')*'
		).source;
		
		// matches and captures sequence of characters beginning and ending with doublequotes
		// respecting doublequote escaped by backslash between beginning and ending quote
		// e.g.
		//	matches: 
		//		"def"		in abc"def"ijk
		//		"def\"ijk"	in abc"def\"ijk"lmn
		//		"def\"		in abc"def\"ijk
		//		"def"		in abc"def"ijk"lmn
		var quotesPattern = re(
			'(', // capture begin
				'"', // must begin with doublequote
		            '(?:',
			            '[^"]', // any non doublequote character...
			            '|', // or...
			            '\\\\\u25A8?"', // escaped doublequote with optional cursor identifier
		            ')*', // zero or more times
	            '"', // must end with doublequote
            ')' // capture end
		).source;
		
		// matches whole tag, captures opening and closing brackets, keywords,
		// identifier and value of success, default and delimiter groups
		// and spaces between them
		var p = re(
			'({)', // (1) opening bracket
			
			'([\\s\u25A8]*)', // (2) space1
			
            '(', // (3) keywords
            	keywordsPattern,
            ')',
            
            '([\\s\u25A8]*)', // (4) space2
			
            '(?:', // begin success group
	            '(@[\\s\u25A8]*)', // (5) success identifier "@"
	            quotesPattern, // (6) success value
            ')?', // end success group
            
            '([\\s\u25A8]*)', // (7) space3
            
            '(?:', // begin default group
	            '(%[\\s\u25A8]*)', // (8) default identifier "?"
	            quotesPattern, // (9) default value
            ')?', // end default group
            
            '([\\s\u25A8]*)', // (10) space4
            
            '(?:', // begin delimiter group
	            '(#[\\s\u25A8]*)', // (11) delimiter identifier ":"
	            quotesPattern, // (12) delimiter value
            ')?', // end delimiter group
            
			'([\\s\u25A8]*)', // (13) space5
			
			'(})' // (14) closing bracket
		)
		
		return input.replace(p, function(
				m,
				openingBracket, // (1)
				space1, // (2)
				keywords, // (3)
				space2, // (4)
				successIdentifier, // (5)
				successValue, // (6)
				space3, // (7)
				defaultIdentifier, // (8)
				defaultValue, // (9)
				space4, // (10)
				delimiterIdentifier, // (11)
				delimiterValue, // (12)
				space5, // (13)
				closingBracket // (14)
			) {
			
			//console.log(m);
			
			// put captured groups back together in order they were captured
			var result = '';
			result = addToResult(result, openingBracket, 'opening bracket');
			result = addToResult(result, space1);
			result = addToResult(result, processKeys(keywords), 'keys group');
			result = addToResult(result, space2);
			result = addToResult(
				result,
				wrap(successIdentifier, 'identifier') + wrap(processSuccessValue(successValue), 'value'),
				'success group'
			);
			result = addToResult(result, space3);
			result = addToResult(
				result,
				wrap(defaultIdentifier, 'identifier') + wrap(defaultValue, 'value'),
				'default group'
			);
			result = addToResult(result, space4);
			result = addToResult(
				result,
				wrap(delimiterIdentifier, 'identifier') + wrap(delimiterValue, 'value'),
				'delimiter group'
			);
			result = addToResult(result, space5);
			result = addToResult(result, closingBracket, 'closing bracket');
			return wrap(result, 'tag group');
		});
	}
	
	// highlites dollar sign inside success value string but only if it's not escaped with backslash
	function processSuccessValue(content){
		if(content){
			var p = re(
				'(', // must be preceded with
					'[^\\\\\u25A8]', // one non-backslash, non-cursor character
					'|', // or
					'[^\\\\]\u25A8', // non-backslash followed by cursor
				')',
				'(\\$)' // dolar
			);
			return content.replace(p, function(m, precedingChar, dolar) {
				return precedingChar + wrap(dolar, 'dolar');
			});
		}
	}
	
	// highlites tag keywords group
	function processKeys(content) {
		// matches keys, captures prefix, colon, key and pipe
		var p = re(
			'([^:\\s]+)', // prefix
            '(?:',
            '(:)', // colon
            '([^|\\s]+)', // key
            ')?',
            '([\\s\u25A8]*\\|)?' // pipe
		);
		
		return content.replace(p, function(m, prefix, colon, key, pipe){
			// put it back together in captured order
			var result = '';
			result = addToResult(result, prefix, 'prefix');
			result = addToResult(result, colon, 'colon');
			result = addToResult(result, processKey(key), 'key');
			result = addToResult(result, pipe, 'pipe');
			return result;
		});
	}
	
	// highlites tag keywords
	function processKey(content) {
		
		// matches key suffix, captures part and qt character
		var p = re(
			'([^>\\s]+)', // key
            '(>)?' // gt
		);
		
		if(content){
			return content.replace(p, function(m, part, gt){
				// put it back together in captured order
				var result = '';
				result = addToResult(result, part, 'part');
				result = addToResult(result, gt, 'gt');
				return result;
			});
		}
	}	
});

