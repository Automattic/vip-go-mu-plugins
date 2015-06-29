// ---------------------------------------------------------------------------
// Money format handler
// ---------------------------------------------------------------------------
Shopify = {};
Shopify.money_format = "$ {{amount}}";
Shopify.formatMoney = function( cents, format ) {
	if ( typeof cents == 'string' ) cents = cents.replace( '.','' );
	var value = '';
	var patt = /\{\{\s*(\w+)\s*\}\}/;
	var formatString = ( format || this.money_format );

	function addCommas( moneyString ) {
		return moneyString.replace( /(\d+)(\d{3}[\.,]?)/,'$1,$2');
	}

	function floatToString( numeric, decimals ) {
		var amount = numeric.toFixed( decimals ).toString();
		if( amount.match( /^\.\d+/)) {return "0"+amount; }
		else { return amount; }
	}

	switch( formatString.match( patt )[1] ) {
	case 'amount':
		value = addCommas( floatToString( cents/100.0, 2 ) );
		break;
	case 'amount_no_decimals':
		value = addCommas( floatToString( cents/100.0, 0 ) );
		break;
	case 'amount_with_comma_separator':
		value = floatToString( cents/100.0, 2).replace( /\./, ',' );
		break;
	case 'amount_no_decimals_with_comma_separator':
		value = addCommas( floatToString( cents/100.0, 0 )).replace( /\./, ',' );
		break;
	}
	return formatString.replace( patt, value );
};

