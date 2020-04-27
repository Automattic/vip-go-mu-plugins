<?php

namespace Automattic\Utils;

use \stdClass;
use PHP_CodeSniffer\Reports\Report;
use PHP_CodeSniffer\Files\File;

class ESLintReport implements Report {
	/**
	 * Generate a partial report for a single processed file.
	 *
	 * Function should return TRUE if it printed or stored data about the file
	 * and FALSE if it ignored the file. Returning TRUE indicates that the file and
	 * its data should be counted in the grand totals.
	 *
	 * @param array					$report		   Prepared report data.
	 * @param \PHP_CodeSniffer\File $phpcsFile	 The file being reported on.
	 * @param bool					$showSources   Show sources?
	 * @param int					$width		   Maximum allowed line width.
	 *
	 * @return bool
	 */
	public function generateFileReport( $report, File $phpcsFile, $showSources = false, $width = 80 ) {
		// The differ in eslines generates full paths so make sure it's absolute.
		$filename = $phpcsFile->path;

		$error_count = $report['errors'];
		$warning_count = $report['warnings'];
		$error_count_fixable = 0;
		$warning_count_fixable = 0;

		$messages = [];

		foreach ( $report['messages'] as $line => $line_errors ) {
			foreach ( $line_errors as $column => $col_errors ) {
				foreach ( $col_errors as $error ) {
					$error['message'] = str_replace( "\n", '\n', $error['message'] );
					$error['message'] = str_replace( "\r", '\r', $error['message'] );
					$error['message'] = str_replace( "\t", '\t', $error['message'] );

					$eslint_error = [];
					$phpcs_error = $error;

					$eslint_error['ruleId'] = $phpcs_error['source'];
					// severity: eslint expects 1 for warnings and 2 for errors.
					$severity = 1;
					if ( 'ERROR' === $phpcs_error['type'] ) {
						$severity = 2;
					}
					$eslint_error['severity'] = $severity;
					$eslint_error['message'] = $phpcs_error['message'];

					$eslint_error['line'] = $line;
					$eslint_error['column'] = $column;

					// PHPCS does not have nodeType
					$eslint_error['nodeType'] = 'n/a';

					$fixable = false;
					if ( true === $phpcs_error['fixable'] ) {
						$fixable = true;
					}
					$eslint_error['fixable'] = $fixable;

					$messages[] = $eslint_error;
				}
			}
		}

		$report = [
			'filePath' => $filename,
			'messages' => $messages,
			'errorCount' => $error_count,
			'warningCount' => $warning_count,
			'fixableErrorCount' => $error_count_fixable,
			'fixableWarningCount' => $warning_count_fixable,
			'source' => '',
		];

		echo json_encode( $report ) . ',';

		return true;

	}

	/**
	 * Generates a eslint JSON report.
	 *
	 * @param string $cachedData	Any partial report data that was returned from
	 *							  generateFileReport during the run.
	 * @param int	$totalFiles	   Total number of files processed during the run.
	 * @param int	$totalErrors   Total number of errors found during the run.
	 * @param int	$totalWarnings Total number of warnings found during the run.
	 * @param int	$totalFixable  Total number of problems that can be fixed.
	 * @param bool  $showSources   Show sources?
	 * @param int	$width		   Maximum allowed line width.
	 * @param bool  $interactive   Are we running in interactive mode?
	 * @param bool  $toScreen	   Is the report being printed to screen?
	 *
	 * @return void
	 */
	public function generate(
		$cachedData,
		$totalFiles,
		$totalErrors,
		$totalWarnings,
		$totalFixable,
		$showSources=false,
		$width=80,
		$interactive=false,
		$toScreen=true
	) {
		echo '[' . rtrim( $cachedData, ',' ) . ']';
		echo PHP_EOL;
	}
}
