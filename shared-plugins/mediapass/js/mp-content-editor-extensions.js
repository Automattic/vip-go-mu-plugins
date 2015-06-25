MM = typeof MM === 'undefined' ? {} : MM;
MM.WP = typeof MM.WP === 'undefined' ? {} : MM.WP;

MM.WP.ContentEditorExtensions = function() {
	
	var $ 				= jQuery,
		hasTinyMCE 		= typeof tinyMCE !== 'undefined',
		$editorCanvas 	= typeof edCanvas !== 'undefined' ? $(edCanvas) : null;
	
	var isActiveTinyMCE = function() {
		if( hasTinyMCE ) {
			var editor = tinyMCE.activeEditor;
			var isHidden = editor == null || editor.isHidden();
			
			return isHidden ? null : editor;
		}
	}
	
	var wrapSelectedTextWith = function(begin,end){
		var mce = isActiveTinyMCE();
		
		if(mce != null) {
			mce.selection.setContent(begin + mce.selection.getContent() + end);
		} else {
			var sel  = $editorCanvas.getSelection(),
				text = sel.text,
				val  = $editorCanvas.val();
			
			var newValue = val.substr(0,sel.start) + begin + text + end + val.substr(sel.end);
			
			$editorCanvas.val( newValue );
		}
	};

	var connectToMetaControlBox = function() {
		
	};

	return {
		wrapSelectedTextWith: wrapSelectedTextWith
	};
};