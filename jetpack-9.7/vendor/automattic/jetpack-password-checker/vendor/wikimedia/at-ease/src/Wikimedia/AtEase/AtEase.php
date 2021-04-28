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

namespace Wikimedia\AtEase;

class AtEase {
	private static $suppressCount = 0;
	private static $originalLevel = false;

	/**
	 * Reference-counted warning suppression
	 *
	 * @param bool $end Whether to restore warnings
	 */
	public static function suppressWarnings( $end = false ) {
		if ( $end ) {
			if ( self::$suppressCount ) {
				--self::$suppressCount;
				if ( !self::$suppressCount ) {
					error_reporting( self::$originalLevel );
				}
			}
		} else {
			if ( !self::$suppressCount ) {
				self::$originalLevel =
					error_reporting( E_ALL & ~(
						E_WARNING |
						E_NOTICE |
						E_USER_WARNING |
						E_USER_NOTICE |
						E_DEPRECATED |
						E_USER_DEPRECATED |
						E_STRICT
					) );
			}
			++self::$suppressCount;
		}
	}

	/**
	 * Restore error level to previous value
	 */
	public static function restoreWarnings() {
		self::suppressWarnings( true );
	}

	/**
	 * Call the callback given by the first parameter, suppressing any warnings.
	 *
	 * @param callable $callback Function to call
	 * @param mixed ...$args Optional arguments for the function call
	 * @return mixed
	 */
	public static function quietCall( callable $callback, ...$args ) {
		self::suppressWarnings();
		try {
			$rv = $callback( ...$args );
		} finally {
			self::restoreWarnings();
		}
		return $rv;
	}

}
