# Change Log for Yoast PHPUnit Polyfills

All notable changes to this project will be documented in this file.

This projects adheres to [Keep a CHANGELOG](http://keepachangelog.com/) and uses [Semantic Versioning](http://semver.org/).


## [Unreleased]

_Nothing yet._

## [0.2.0] - 2020-11-25

### Added
* `Yoast\PHPUnitPolyfills\TestListeners\TestListenerDefaultImplementation`: a cross-version compatible base implementation for `TestListener`s using snake_case method names to replace the PHPUnit native method names.
* `Yoast\PHPUnitPolyfills\Helpers\AssertAttributeHelper` trait containing a `getProperty()` and a `getPropertyValue()` method.
    This is a stop-gap solution for the removal of the PHPUnit `assertAttribute*()` methods in PHPUnit 9.
    It is strongly recommended to refactor your tests/classes in a way that protected and private properties no longer be tested directly as they should be considered an implementation detail.
	However, if for some reason the value of protected or private properties still needs to be tested, this helper can be used to get access to their value.
* `Yoast\PHPUnitPolyfills\Polyfills\AssertNumericType` trait to polyfill the `Assert::assertFinite()`, `Assert::assertInfinite()` and `Assert::assertNan()` methods as introduced in PHPUnit 5.0.0.
* `Yoast\PHPUnitPolyfills\Polyfills\ExpectException` trait to polyfill the `TestCase::expectException()`, `TestCase::expectExceptionMessage()`, `TestCase::expectExceptionCode()` and `TestCase::expectExceptionMessageRegExp()` methods, as introduced in PHPUnit 5.2.0 to replace the `Testcase::setExpectedException()` and the `Testcase::setExpectedExceptionRegExp()` method.
* `Yoast\PHPUnitPolyfills\Polyfills\AssertFileDirectory` trait to polyfill the `Assert::assertIsReadable()`, `Assert::assertIsWritable()` methods and their file/directory based variations, as introduced in PHPUnit 5.6.0.
* `Yoast\PHPUnitPolyfills\TestCases\TestCase`: support for the `assertPreConditions()` and `assertPostConditions()` methods.

### Changed
* The minimum supported PHP version has been lowered to PHP 5.5 (was 5.6).
* The minimum supported PHPUnit version has been lowered to PHP 4.8.36 (was 5.7).
    Note: for PHPUnit 4, only version 4.8.36 is supported, for PHPUnit 5, only PHPUnit >= 5.7.21 is supported.
* Readme: documentation improvements.


## [0.1.0] - 2020-10-26

Initial release.


[Unreleased]: https://github.com/Yoast/PHPUnit-Polyfills/compare/main...HEAD
[0.2.0]: https://github.com/Yoast/PHPUnit-Polyfills/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/Yoast/PHPUnit-Polyfills/compare/e8f8b7a73737aa9a5974bd9c73d2bd8d09f69873...0.1.0
