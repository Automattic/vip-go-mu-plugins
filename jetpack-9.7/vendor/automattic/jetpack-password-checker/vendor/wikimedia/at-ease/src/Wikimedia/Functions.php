<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace Wikimedia;

use Wikimedia\AtEase\AtEase;

if ( !function_exists( __NAMESPACE__ . '\\suppressWarnings' ) ) {

	/**
	 * Reference-counted warning suppression
	 *
	 * @deprecated use AtEase::suppressWarnings
	 * @param bool $end Whether to restore warnings
	 */
	function suppressWarnings( $end = false ) {
		AtEase::suppressWarnings( $end );
	}

	/**
	 * Restore error level to previous value
	 *
	 * @deprecated use AtEase::restoreWarnings
	 */
	function restoreWarnings() {
		AtEase::restoreWarnings();
	}

	/**
	 * Call the callback given by the first parameter, suppressing any warnings.
	 *
	 * @deprecated use AtEase::quietCall
	 * @param callable $callback Function to call
	 * @param mixed ...$args Optional arguments for the function call
	 * @return mixed
	 */
	function quietCall( callable $callback, ...$args ) {
		return AtEase::quietCall( $callback, ...$args );
	}

}
