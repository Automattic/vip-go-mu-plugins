/**
 * External dependencies
 */
import Cookies from 'js-cookie';

/**
 * Internal dependencies
 */
import {
	getUuidFromVisitorCookie,
	getVisitorCookie,
	getVisitorCookieRaw,
} from '../../../src/js/lib/personalization';

describe( 'lib/personalization', () => {
	beforeEach( () => {
		Object.keys( Cookies.get() ).forEach( ( cookieName ) => Cookies.remove( cookieName ) );
	} );

	describe( 'getVisitorCookieRaw', () => {
		test( 'no cookies are set, so should return undefined', () => {
			expect( getVisitorCookieRaw() ).toBeUndefined();
		} );

		test( 'unset visitor cookie should return undefined', () => {
			global.document.cookie = 'genre=Science Fiction';
			expect( getVisitorCookieRaw() ).toBeUndefined();
		} );

		test( 'visitor cookie with empty value should return empty string', () => {
			global.document.cookie = '_parsely_visitor=';
			global.document.cookie = 'genre=Science Fiction';
			expect( getVisitorCookieRaw() ).toBe( '' );
		} );

		test( 'set visitor cookie should return value', () => {
			global.document.cookie = 'genre=Science Fiction';
			global.document.cookie = '_parsely_visitor=Species 8472';
			expect( getVisitorCookieRaw() ).toBe( 'Species 8472' );
		} );

		test( 'set visitor cookie containing equal sign should return entire value', () => {
			global.document.cookie = 'genre=Science Fiction';
			global.document.cookie = '_parsely_visitor=Janeway=Awesome';
			expect( getVisitorCookieRaw() ).toBe( 'Janeway=Awesome' );
		} );
	} );

	describe( 'getVisitorCookie', () => {
		test( 'Unset visitor cookie should return undefined', () => {
			global.document.cookie = 'tea=hot';
			global.document.cookie = 'earl=gray';
			expect( getVisitorCookie() ).toBeUndefined();
		} );

		test( 'Visitor cookie set but empty returns undefined', () => {
			global.document.cookie = 'rank=Captain';
			global.document.cookie = 'fname=Jean Luc';
			global.document.cookie = 'lname=Picard';
			global.document.cookie = 'lights=4';
			global.document.cookie = '_parsely_visitor=';
			expect( getVisitorCookie() ).toBeUndefined();
		} );

		test( 'Visitor cookie set but non-JSON returns undefined', () => {
			global.document.cookie = 'rank=Ensign';
			global.document.cookie = 'fname=Harry';
			global.document.cookie = 'lname=Kim';
			global.document.cookie = '_parsely_visitor=TheBorg';
			global.document.cookie = 'field_promotions=0';
			expect( getVisitorCookie() ).toBeUndefined();
		} );

		test( 'Visitor cookie set with JSON returns parsed value', () => {
			global.document.cookie = 'rank=Captain';
			global.document.cookie = 'OPAfaction=';
			global.document.cookie = 'fname=James';
			global.document.cookie = 'lname=Holden';
			global.document.cookie = '_parsely_visitor={"protomolecule":"destroyed","pdcs":"full"}';
			expect( getVisitorCookie() ).toStrictEqual( { protomolecule: 'destroyed', pdcs: 'full' } );
		} );

		test( 'Visitor cookie set with JSON containing equal sign returns entire parsed value', () => {
			global.document.cookie = 'fname=Keiko';
			global.document.cookie = 'lname=O\'Brien';
			global.document.cookie = '_parsely_visitor={"password":"b0tany=awesome!"}';
			expect( getVisitorCookie() ).toStrictEqual( { password: 'b0tany=awesome!' } );
		} );
	} );

	describe( 'getUuidFromVisitorCookie', () => {
		test( 'Unset visitor cookie should return undefined', () => {
			global.document.cookie = 'tea=hot';
			global.document.cookie = 'earl=gray';
			expect( getUuidFromVisitorCookie() ).toBeUndefined();
		} );

		test( 'Empty visitor cookie returns undefined', () => {
			global.document.cookie = 'rank=Captain';
			global.document.cookie = 'fname=Jean Luc';
			global.document.cookie = 'lname=Picard';
			global.document.cookie = 'lights=4';
			global.document.cookie = '_parsely_visitor=';
			expect( getUuidFromVisitorCookie() ).toBeUndefined();
		} );

		test( 'Non-JSON visitor cookie returns undefined', () => {
			global.document.cookie = 'rank=Ensign';
			global.document.cookie = 'fname=Harry';
			global.document.cookie = 'lname=Kim';
			global.document.cookie = '_parsely_visitor=TheBorg';
			global.document.cookie = 'field_promotions=0';
			expect( getUuidFromVisitorCookie() ).toBeUndefined();
		} );

		test( 'Visitor cookie set with JSON but no id returns undefined', () => {
			global.document.cookie = 'rank=Captain';
			global.document.cookie = 'OPAfaction=';
			global.document.cookie = 'fname=James';
			global.document.cookie = 'lname=Holden';
			global.document.cookie = '_parsely_visitor={"protomolecule":"destroyed","pdcs":"full"}';
			expect( getUuidFromVisitorCookie() ).toBeUndefined();
		} );

		test( 'Visitor cookie set with JSON and id returns id', () => {
			global.document.cookie = 'type=Light Frigate';
			global.document.cookie = 'class=Corvette';
			global.document.cookie = 'name=Rocinante';
			global.document.cookie =
				'_parsely_visitor={"captain":"James Holden","id":"ECF - 270","Chief Engineer":"Naomi Nagata"}';
			expect( getUuidFromVisitorCookie() ).toBe( 'ECF - 270' );
		} );

		test( 'Visitor cookie set with JSON and id containing equal sign returns id', () => {
			global.document.cookie = '_dlt=1';
			global.document.cookie = 'hasLiveRampMatch=true';
			global.document.cookie =
				'_parsely_visitor={%22id%22:%22pid=abc123%22%2C%22session_count%22:1%2C%22last_session_ts%22:1629211115751}';
			global.document.cookie = '_pnvl=false';
			expect( getUuidFromVisitorCookie() ).toBe( 'pid=abc123' );
		} );
	} );
} );
