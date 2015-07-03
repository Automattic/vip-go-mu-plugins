/**
 * jquery ui autocomplete with category
 */
$.widget( "custom.catcomplete", $.ui.autocomplete, {
    _renderMenu:function ( ul, items ) {
        var that = this,
            currentCategory = "";
        if ( items[0].label != "0" ) {
            $.each( items, function ( index, item ) {
                if ( item.category != currentCategory ) {
                    ul.append( "<li class='ui-autocomplete-category'>" + item.category + "</li>" );
                    currentCategory = item.category;
                }
                that._renderItemData( ul, item );
            } );
        } else
            ul.append( "<li class='ui-autocomplete-category'>No search results.</li>" );
    }
} );


/**
 * time ago from a sepeciice data
 * @param time
 */
function nc_time_ago( time ) {

    switch ( typeof time ) {
        case 'number':
            break;
        case 'string':
            time = +new Date( time );
            break;
        case 'object':
            if ( time.constructor === Date ) time = time.getTime();
            break;
        default:
            time = +new Date();
    }
    var time_formats = [
        [60, 'seconds', 1],
        // 60
        [120, '1 minute ago', '1 minute ago'],
        // 60*2
        [3600, 'minutes', 60],
        // 60*60, 60
        [7200, '1 hour ago', '1 hour ago'],
        // 60*60*2
        [86400, 'hours', 3600],
        // 60*60*24, 60*60
        [172800, 'Yesterday', 'Tomorrow'],
        // 60*60*24*2
        [604800, 'days', 86400],
        // 60*60*24*7, 60*60*24
        [1209600, 'Last week', 'Next week'],
        // 60*60*24*7*4*2
        [2419200, 'weeks', 604800],
        // 60*60*24*7*4, 60*60*24*7
        [4838400, 'Last month', 'Next month'],
        // 60*60*24*7*4*2
        [29030400, 'months', 2419200],
        // 60*60*24*7*4*12, 60*60*24*7*4
        [58060800, 'Last year', 'Next year'],
        // 60*60*24*7*4*12*2
        [2903040000, 'years', 29030400],
        // 60*60*24*7*4*12*100, 60*60*24*7*4*12
        [5806080000, 'Last century', 'Next century'],
        // 60*60*24*7*4*12*100*2
        [58060800000, 'centuries', 2903040000] // 60*60*24*7*4*12*100*20, 60*60*24*7*4*12*100
    ];
    var seconds = (+new Date() - time) / 1000,
        token = 'ago', list_choice = 1;

    if ( seconds == 0 ) {
        return 'Just now'
    }
    if ( seconds < 0 ) {
        seconds = Math.abs( seconds );
        token = 'ago';
        list_choice = 2;
    }
    var i = 0, format;
    while ( format = time_formats[i++] )
        if ( seconds < format[0] ) {
            if ( typeof format[2] == 'string' )
                return format[list_choice];
            else
                return Math.floor( seconds / format[2] ) + ' ' + format[1] + ' ' + token;
        }
    return time;
}

/**
 * insert text in mouse cursor position in TEXTAREA
 */
$.fn.extend( {
    insertAtCaret:function ( myValue ) {
        return this.each( function ( i ) {
            if ( document.selection ) {
                //For browsers like Internet Explorer
                this.focus();
                sel = document.selection.createRange();
                sel.text = myValue;
                this.focus();
            }
            else if ( this.selectionStart || this.selectionStart == '0' ) {
                //For browsers like Firefox and Webkit based
                var startPos = this.selectionStart;
                var endPos = this.selectionEnd;
                var scrollTop = this.scrollTop;
                this.value = this.value.substring( 0, startPos ) + myValue + this.value.substring( endPos, this.value.length );
                this.focus();
                this.selectionStart = startPos + myValue.length;
                this.selectionEnd = startPos + myValue.length;
                this.scrollTop = scrollTop;
            } else {
                this.value += myValue;
                this.focus();
            }
        } )
    }
} );

// get current url parameter
function nc_getURLParameter( name ) {
    return decodeURI(
        (RegExp( name + '=' + '(.+?)(&|$)' ).exec( location.search ) || [, null])[1]
    );
}