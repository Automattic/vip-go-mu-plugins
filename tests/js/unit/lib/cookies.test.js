jest.mock( '../../../../src/js/lib/cookies/utils' );
import { documentCookieWrapper } from '../../../../src/js/lib/cookies/utils';
import { getCookieValue } from '../../../../src/js/lib/cookies';

describe( 'lib/cookies', () => {
	test( 'Unset key should return undefined', () => {
		documentCookieWrapper.mockReturnValue( 'tea=hot; earl=gray;' );
		expect( getCookieValue( 'biscuits' ) ).toBeUndefined();
	} );

	test( 'All Keys with populated values should return value', () => {
		documentCookieWrapper.mockReturnValue( 'rank=Captain; fname=Jean Luc; lname=Picard; lights=4' );
		expect( getCookieValue( 'rank' ) ).toBe( 'Captain' );
		expect( getCookieValue( 'fname' ) ).toBe( 'Jean Luc' );
		expect( getCookieValue( 'lname' ) ).toBe( 'Picard' );
		expect( getCookieValue( 'lights' ) ).toBe( '4' );
	} );

	test( 'Keys without populated values should return empty string', () => {
		documentCookieWrapper.mockReturnValue( 'rank=Captain; OPAfaction=; fname=James; lname=Holden' );
		expect( getCookieValue( 'rank' ) ).toBe( 'Captain' );
		expect( getCookieValue( 'fname' ) ).toBe( 'James' );
		expect( getCookieValue( 'lname' ) ).toBe( 'Holden' );
		expect( getCookieValue( 'OPAfaction' ) ).toBe( '' );
	} );
} );
