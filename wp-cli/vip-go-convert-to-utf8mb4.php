<?php

// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL schema does not use the snake case

class VIP_Go_Convert_To_utf8mb4 extends WPCOM_VIP_CLI_Command {
	/**
	 * Command arguments
	 */
	private $dry_run = true;

	/**
	 * Class properties
	 */
	private $tables = array();

	private $protect_masquerading_utf8 = false;

	const TYPE_MAPPING = array(
		'char'       => 'binary',
		'text'       => 'blob',
		'tinytext'   => 'tinyblob',
		'mediumtext' => 'mediumblob',
		'longtext'   => 'longblob',
		'varchar'    => 'varbinary', // length handled in maybe_protect_column()
		'enum'       => 'enum',
	);

	/**
	 * Convert site using `utf8` or `latin1` to use `utf8mb4`
	 *
	 * Adapted from https://codex.wordpress.org/Converting_Database_Character_Sets, particularly the method for protecting `utf8` masquerading as `latin1`
	 *
	 * Use `--protect-latin-one` when table contains `utf8` characters in `latin1`-encoded columns
	 *
	 * ## OPTIONS
	 *
	 * <protect-latin-one>
	 * : If passed, script assumes that `latin1` columns contain `utf8` content that
	 *   should not be converted directly to `utf8mb4`. Required when migrating
	 *   from WordPress.com.
	 *
	 * <dry-run>
	 * : Whether or not to modify the database, or simply inspect it.
	 * ---
	 * default: false
	 * options:
	 *   - true
	 *   - false
	 * ---
	 *
	 * @subcommand convert
	 * @synopsis [--dry-run=<dry-run>] [--protect-latin-one]
	 */
	public function convert( $args, $assoc_args ) {
		global $wpdb;

		WP_CLI::line( 'CONVERSION TO `utf8mb4` REQUESTED' );

		// Parse arguments
		$_dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', true );
		if ( 'false' === $_dry_run ) {
			$this->dry_run = false;
		}

		$_protect_columns = WP_CLI\Utils\get_flag_value( $assoc_args, 'protect-latin-one', false );
		if ( false !== $_protect_columns ) {
			$this->protect_masquerading_utf8 = true;
		}

		WP_CLI::line( '' );
		WP_CLI::line( 'ARGUMENTS' );
		WP_CLI::line( '* dry run: ' . ( $this->dry_run ? 'yes' : 'no' ) );
		WP_CLI::line( '* protecting utf8 masquerading as latin1: ' . ( $this->protect_masquerading_utf8 ? 'yes' : 'no' ) );
		WP_CLI::line( '' );

		// Validate starting charset to avoid catastrophe
		WP_CLI::line( 'PREFLIGHT CHECKS' );
		if ( in_array( $wpdb->charset, array( 'latin1', 'utf8' ), true ) ) {
			WP_CLI::line( "* Expected charset (`{$wpdb->charset}`) found." );
		} elseif ( 'utf8mb4' === $wpdb->charset ) {
			WP_CLI::error( 'Site is already using `utf8mb4`. Aborting!' );
			return;
		} else {
			WP_CLI::error( "Unacceptable starting encoding: `{$wpdb->charset}`. Aborting!" );
			return;
		}

		// Describe scope
		if ( is_multisite() ) {
			WP_CLI::line( '* Multisite detected, so this process will convert all network and global tables, along with the blog tables for all sites.' );
		} else {
			WP_CLI::line( '* Single site detected, so global and blog-specific tables will be converted. Any multisite tables will be skipped.' );
		}

		// Describe tables to be converted
		$this->get_tables();
		$tables_count  = number_format( count( $this->tables ) );
		$tables_string = implode( ', ', $this->tables );

		WP_CLI::line( "* Found {$tables_count} tables to check and potentially convert: {$tables_string}." );
		WP_CLI::line( '' );

		// Provide an opportunity to abort
		WP_CLI::confirm( 'Proceed with ' . ( $this->dry_run ? 'DRY' : 'LIVE' ) . ' RUN and ' . ( $this->dry_run ? 'test converting' : 'potentially convert' ) . " {$tables_count} tables from `{$wpdb->charset}` to `utf8mb4`?" );
		if ( ! $this->dry_run ) {
			WP_CLI::confirm( 'ARE YOU REALLY SURE?' );
		}
		WP_CLI::line( '' );
		WP_CLI::line( 'Proceeding...' );
		WP_CLI::line( '' );

		unset( $tables_count, $tables_string );

		// Do the work we came here for
		foreach ( $this->tables as $table ) {
			WP_CLI::line( "Converting {$table}..." );

			$converted = $this->maybe_convert_table_to_utf8mb4( $table );

			if ( true === $converted ) {
				WP_CLI::success( "Done with {$table}." );
			} elseif ( false === $converted ) {
				if ( $this->dry_run ) {
					WP_CLI::line( "Table {$table} not converted during dry run." );
				} else {
					WP_CLI::warning( "Table {$table} not converted because it doesn't exist or doesn't contain convertible columns." );
				}
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
				WP_CLI::warning( 'Unknown response: ' . var_export( $converted, true ) );
			}

			WP_CLI::line( '' );
		}

		// Update DB's default charset/collation
		$db_name = $wpdb->get_var( 'SELECT DATABASE() FROM DUAL;' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$convert_db = $wpdb->query( 'ALTER DATABASE ' . $db_name . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
		if ( $convert_db ) {
			WP_CLI::success( 'Set database to utf8mb4' );
		} else {
			WP_CLI::warning( 'Could not update database default charset' );
		}

		// Wrap up
		WP_CLI::line( '' );
		WP_CLI::line( '' );
		WP_CLI::line( 'DONE!' );
		WP_CLI::line( 'Time to update the `db_charset` and `db_collate` sitemeta, and reload web configs.' );
		WP_CLI::line( 'DB_CHARSET: `utf8mb4`' );
		WP_CLI::line( 'DB_COLLATE: `utf8mb4_unicode_ci`' );
	}

	/**
	 * Outputs SQL to convert sites to use `utf8mb4`
	 *
	 * @subcommand create-sql
	 */
	public function create_sql( $args, $assoc_args ) {
		global $wpdb;

		$tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
		$tables = array_column( $tables, 0 ); // Flatten array.

		$pass = 1;
		$sql  = '';
		while ( $pass <= 3 ) {
			foreach ( $tables as $table ) {
				// Get the current CHARSET.
				$table_create = $wpdb->get_results( $wpdb->prepare( 'SHOW CREATE TABLE %s', $table ), ARRAY_N );
				$charset      = array_column( $table_create, 1 )[0]; // Flatten array to string.
				$charset      = preg_match( '/CHARSET=(.*)(?:\s+|$)/Ui', $charset, $matches );

				if ( isset( $matches[1] ) ) {
					$charset = $matches[1];
				} else {
					WP_CLI::error( "Could not find CHARSET for table $table!" );
				}

				// Get the current COLLATE;
				$collate = array_column( $table_create, 1 )[0]; // Flatten array to string.
				preg_match( '/COLLATE=(.*)(?:\s+|$)/Ui', $collate, $matches );

				if ( isset( $matches[1] ) ) {
					$collate = $matches[1];
				} else {
					$collate = '';
				}

				if ( 'utf8mb4' !== $charset || 'utf8mb4_unicode_ci' !== $collate ) {
					$columns_to_fix = 0;
					$columns        = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %s', $table ), ARRAY_N );

					foreach ( $columns as $column ) {
						$column_name = $column[0];
						$column_type = $column[1];

						$new_column_type = false;
						switch ( $column_type ) {
							case 'char':
								$new_column_type = 'BINARY';
								break;
							case 'text':
								$new_column_type = 'BLOB';
								break;
							case 'tinytext':
								$new_column_type = 'TINYBLOB';
								break;
							case 'mediumtext':
								$new_column_type = 'MEDIUMBLOB';
								break;
							case 'longtext':
								$new_column_type = 'LONGBLOB';
								break;
							default:
								if ( false !== strpos( $column_type, 'enum(' ) ) {
									// TODO: Fix enums?  Do they need fixed?  Only if their text has UTF-8?
									$new_column_type = $column_type;
								}

								if ( false !== strpos( $column_type, 'varchar' ) ) {
									preg_match( '/\d+/', $column_type, $matches );
									if ( isset( $matches[0] ) ) {
										$new_column_type = "VARBINARY({$matches[0]})";
									} else {
										WP_CLI::error( "Cannot find VARCHAR size for $column_name in $table!" );
									}
								}
						}

						if ( false !== $new_column_type ) {
							$columns_to_fix++;

							if ( 1 === $columns_to_fix ) {
								$sql .= "ALTER TABLE `$table` ";
							} else {
								$sql .= ', ';
							}

							if ( 1 === $pass ) {
								$sql .= "MODIFY `$column_name` $new_column_type";
							} elseif ( 2 === $pass ) {
								$sql .= "MODIFY `$column_name` $column_type CHARACTER SET $charset";
							} elseif ( 3 === $pass ) {
								$sql .= "MODIFY `$column_name` $column_type CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
							}
						}
					} // End Column Loop.

					if ( $columns_to_fix > 0 ) {
						$sql .= ';' . PHP_EOL;
					}

					if ( 3 === $pass ) {
						$sql .= "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" . PHP_EOL;
					}
				}
			} // End Table loop

			$pass++;
		}

		echo $sql; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * UTILITY METHODS
	 */

	/**
	 * Populate array of tables to possibly convert
	 *
	 * NOTE: We don't use `$wpdb->tables( 'all' )` because it won't include all tables
	 * for every site in a multisite network. Instead, it will only include the current
	 * site's tables, along with the global tables. We want ALL tables, so we query for
	 * sites and merge each site's tables into a single array.
	 */
	private function get_tables() {
		global $wpdb;

		// Start with the global tables
		// Under multisite, this includes the global multisite tables
		$tables = array_values( $wpdb->tables( 'global' ) );

		// Add blog-specific tables
		if ( is_multisite() ) {
			$site_ids = get_sites( array(
				'fields' => 'ids',
			) );

			if ( is_array( $site_ids ) && ! empty( $site_ids ) ) {
				foreach ( $site_ids as $site_id ) {
					$tables = array_merge( $tables, array_values( $wpdb->tables( 'blog', true, $site_id ) ) );
				}

				unset( $site_id );
			}

			unset( $site_ids );
		} else {
			$tables = array_merge( $tables, array_values( $wpdb->tables( 'blog' ) ) );
		}

		// Store and return
		$this->tables = array_unique( $tables );
		unset( $tables );

		return $this->tables;
	}

	/**
	 * If a table only contains latin1, utf8, or utf8mb4 columns, convert it to utf8mb4.
	 *
	 * Copied from wp-admin/includes/upgrade.php, with modifications for CLI usage
	 */
	private function maybe_convert_table_to_utf8mb4( $table ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( "SHOW FULL COLUMNS FROM `$table`" );
		if ( ! $results ) {
			return false;
		}

		foreach ( $results as $column ) {
			if ( $column->Collation ) {
				list( $charset ) = explode( '_', $column->Collation );
				$charset         = strtolower( $charset );
				if ( ! in_array( $charset, array( 'latin1', 'utf8', 'utf8mb4' ), true ) ) {
					// Don't upgrade tables that have columns we can't convert.
					return false;
				}
			}
		}

		$table_details = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ) );
		if ( ! $table_details ) {
			return false;
		}

		list( $table_charset ) = explode( '_', $table_details->Collation );
		$table_charset         = strtolower( $table_charset );
		if ( 'utf8mb4' === $table_charset ) {
			return true;
		}

		if ( $this->dry_run ) {
			return false;
		} else {
			$column_convert_error = null;

			if ( $this->protect_masquerading_utf8 ) {
				$column_convert_error = false;
				$protected_columns    = $this->convert_masquerading_columns( $table );

				// Exclude columns that didn't need conversion
				$protected_columns = array_filter( $protected_columns );

				// Alert to any columns that encountered errors
				foreach ( $protected_columns as $col => $statuses ) {
					if ( in_array( false, $statuses, true ) ) {
						$column_convert_error = true;

						WP_CLI::warning( "Problem converting {$col}" );
					}
				}
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$convert = $wpdb->query( "ALTER TABLE $table CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );

			if ( is_int( $convert ) || $convert ) {
				WP_CLI::line( "Converted table {$table}" );

				$convert = true;
			}

			if ( true === $column_convert_error ) {
				$convert = false;
			}

			return $convert;
		}
	}

	/**
	 * When requested, convert columns that contain utf8 data but are encoded as latin1
	 *
	 * @param string $table
	 * @return array
	 */
	private function convert_masquerading_columns( $table ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM $table" );

		$cols_converted = array();

		foreach ( $columns as $col ) {
			$cols_converted[ $col->Field ] = $this->maybe_convert_column( $col, $table );
		}

		return $cols_converted;
	}

	/**
	 * Convert latin1 columns that actually contain utf8
	 *
	 * @return mixed
	 */
	private function maybe_convert_column( $col, $table ) {
		global $wpdb;
		$from_type = null;
		$to_type   = null;

		foreach ( self::TYPE_MAPPING as $from => $to ) {
			// Most will be exact matches, except for varchar and enum
			if (
				$col->Type === $from ||
				( 'enum' === $from && 0 === stripos( $col->Type, $from ) ) ||
				( 'varchar' === $from && 0 === stripos( $col->Type, $from ) )
			) {
				$from_type = $from;
				$to_type   = $to;
				break;
			}
		}

		unset( $from, $to );

		// Carry on, we don't care about this column
		if ( is_null( $from_type ) || is_null( $to_type ) ) {
			return false;
		}

		// enums are special
		if ( 0 === stripos( $from_type, 'enum' ) ) {
			// If we can't find the options, something is very wrong
			if ( ! preg_match( '#' . preg_quote( $from_type, '#' ) . '\(([^\)]+)\)#i', $col->Type, $options ) ) {
				return false;
			}

			$options       = array_pop( $options );
			$null_not_null = 'yes' === strtolower( $col->Null ) ? 'NULL' : 'NOT NULL';

			WP_CLI::line( "Converting column {$col->Field}" );

			$convert = $wpdb->query( $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"ALTER TABLE {$table} CHANGE %s %s ENUM({$options}) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci %s DEFAULT %s",
				$col->Field,
				$col->Field,
				$null_not_null,
				$col->Default
			) );

			WP_CLI::line( "Finished converting column {$col->Field}" );

			return array(
				'convert' => $convert,
				'restore' => $convert,
			);
		}

		// Maintain varchar length
		if ( 0 === stripos( $from_type, 'varchar' ) ) {
			// If we can't find the length, something is very wrong
			if ( ! preg_match( '#' . preg_quote( $from_type, '#' ) . '\(([\d]+)\)#i', $col->Type, $length ) ) {
				return false;
			}

			$length = array_pop( $length );

			if ( ! is_numeric( $length ) ) {
				return false;
			}

			$from_type .= "({$length})";
			$to_type   .= "({$length})";
		}

		$null_not_null = 'yes' === strtolower( $col->Null ) ? ' NULL' : ' NOT NULL';

		// On with it!
		WP_CLI::line( "Converting column {$col->Field}" );

		// Double conversion corrects column charset without changing its content, as the types converted to do not use charsets
		// See https://codex.wordpress.org/Converting_Database_Character_Sets
		$pattern = 'ALTER TABLE %1$s CHANGE %2$s %2$s %3$s %4$s %5$s DEFAULT "%6$s"';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$convert = $wpdb->query( $wpdb->prepare( $pattern, $table, $col->Field, $to_type, '', $null_not_null, $col->Default ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$restore = $wpdb->query( $wpdb->prepare( $pattern, $table, $col->Field, $from_type, 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $null_not_null, $col->Default ) );

		WP_CLI::line( "Finished converting column {$col->Field}" );

		return compact( 'convert', 'restore' );
	}
}

WP_CLI::add_command( 'vip utf8mb4', 'VIP_Go_Convert_To_utf8mb4' );
