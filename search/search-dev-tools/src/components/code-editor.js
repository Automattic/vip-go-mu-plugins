import { useEffect, useMemo } from 'preact/hooks';
import { highlight, languages } from 'prismjs/components/prism-core';
import 'prismjs/components/prism-json';
import Editor from 'react-simple-code-editor';

import { countLines, escapeHtml, formatSize, isLargeRequest } from '../utils';

export const EDITOR_HINT = 'Tab indents. Press Esc, then Tab, to leave the editor.';

// Plain (escaped) text above the threshold keeps typing fast in very large requests.
const highlightJson = code => ( isLargeRequest( code ) ? escapeHtml( code ) : highlight( code, languages.json, 'json' ) );

/**
 * JSON editor with line numbers. Cmd/Ctrl+Enter triggers `onRun`.
 *
 * @param {Object}   props               Props.
 * @param {string}   props.value         Editor contents.
 * @param {Function} props.onValueChange Change handler.
 * @param {Function} props.onRun         Run handler.
 * @param {string}   props.id            Textarea id.
 * @param {string}   props.label         Accessible label for the textarea.
 * @return {import('preact').VNode} Code editor.
 */
export const CodeEditor = ( { id, value, onValueChange, onRun, label } ) => {
	const lineCount = useMemo( () => countLines( value ), [ value ] );
	const gutterText = useMemo( () => Array.from( { length: lineCount }, ( _unused, idx ) => idx + 1 ).join( '\n' ), [ lineCount ] );
	const large = isLargeRequest( value );

	// react-simple-code-editor doesn't forward aria-describedby to its textarea; attach the hint directly.
	useEffect( () => {
		document.getElementById( id )?.setAttribute( 'aria-describedby', `${ id }-hint` );
	}, [ id ] );

	// Runs before the editor's own key handling, so Cmd/Ctrl+Enter doesn't also auto-indent a new line.
	const onKeyDown = evt => {
		if ( evt.key === 'Enter' && ( evt.metaKey || evt.ctrlKey ) ) {
			evt.preventDefault();
			onRun();
		}
	};

	return (
		<div className="sdt-code">
			<label className="sdt-visually-hidden" htmlFor={ id }>{ label }</label>
			{ /* Accessible description; the visible copy is in the Request tab bar while the editor has focus. */ }
			<div className="sdt-visually-hidden" id={ `${ id }-hint` }>{ EDITOR_HINT }</div>
			{ large ? <output className="sdt-code__notice">Large request ({ formatSize( value.length ) }): syntax highlighting is off to keep typing fast.</output> : null }
			<div className="sdt-code__inner">
				{ /* One text node, not one element per line: large requests can have tens of thousands of lines. */ }
				<div className="sdt-code__gutter" aria-hidden="true">{ gutterText }</div>
				<Editor
					value={ value }
					onValueChange={ onValueChange }
					onKeyDown={ onKeyDown }
					highlight={ highlightJson }
					padding={ 0 }
					tabSize={ 2 }
					insertSpaces
					className="sdt-code__editor"
					textareaClassName="sdt-code__textarea"
					preClassName="sdt-code__pre"
					textareaId={ id }
				/>
			</div>
		</div>
	);
};
