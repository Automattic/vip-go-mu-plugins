<?php

// Standalone fixture callback used by APCu_Backend::initialize().
// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore, PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.FunctionDoubleUnderscore
function __return_zero(): int {
	return 0;
}
