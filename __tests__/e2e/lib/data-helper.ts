/**
 * External dependencies
 */
import phrase from 'asana-phrase';

/**
 * Given either a string or array of strings, returns a single string with each word in TitleCase.
 *
 * @param {string[]|string} words Either string or array of strings to be converted to TitleCase.
 * @return {string} String with each distinct word converted to TitleCase.
 */
export function toTitleCase( words: string[] | string ): string {
    if ( typeof words === 'string' ) {
        words = words.trim().split( ' ' );
    }

    const result = words.map( word => {
        return word.charAt( 0 ).toUpperCase() + word.slice( 1 );
    } );

    return result.join( ' ' );
}

/**
 * Generates a random phrase in proper case (Sample Sentence Text).
 *
 * @return {string} Generated text.
 */
export function getRandomPhrase(): string {
    const generated: Array<string> = phrase.default32BitFactory().randomPhrase();
    return toTitleCase( generated );
}
