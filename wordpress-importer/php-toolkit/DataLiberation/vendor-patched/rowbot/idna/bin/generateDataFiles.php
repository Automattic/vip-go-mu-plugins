<?php

declare( strict_types=1 );

use VendorPrefix\Rowbot\Idna\Bin\IdnaDataBuilder;
use VendorPrefix\Rowbot\Idna\Bin\RegexBuilder;
use VendorPrefix\Rowbot\Idna\Bin\ViramaDataBuilder;

const DS         = DIRECTORY_SEPARATOR;
const ROOT_DIR   = __DIR__ . DS . '..';
const OUTPUT_DIR = ROOT_DIR . DS . 'resources';

require ROOT_DIR . DS . 'vendor' . DS . 'autoload.php';

ViramaDataBuilder::buildHashMap( OUTPUT_DIR );
RegexBuilder::buildRegexClass( OUTPUT_DIR );
IdnaDataBuilder::buildHashMaps( OUTPUT_DIR );
