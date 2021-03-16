<?php











namespace Composer;

use Composer\Autoload\ClassLoader;
use Composer\Semver\VersionParser;






class InstalledVersions
{
private static $installed = array (
  'root' => 
  array (
    'pretty_version' => 'dev-monorepo',
    'version' => 'dev-monorepo',
    'aliases' => 
    array (
    ),
    'reference' => NULL,
    'name' => 'automattic/jetpack',
  ),
  'versions' => 
  array (
    'automattic/jetpack' => 
    array (
      'pretty_version' => 'dev-monorepo',
      'version' => 'dev-monorepo',
      'aliases' => 
      array (
      ),
      'reference' => NULL,
    ),
    'automattic/jetpack-a8c-mc-stats' => 
    array (
      'pretty_version' => '1.4.1',
      'version' => '1.4.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'b2f6b20aa3046848391593e6a18f87ae3797e888',
    ),
    'automattic/jetpack-abtest' => 
    array (
      'pretty_version' => 'v1.9.4',
      'version' => '1.9.4.0',
      'aliases' => 
      array (
      ),
      'reference' => '2e10d5cb00a9dbde23f5baebdded9773b046399e',
    ),
    'automattic/jetpack-assets' => 
    array (
      'pretty_version' => 'v1.11.2',
      'version' => '1.11.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '833a6840b5f39ef05b2a65def8a1e61dc7c45a7a',
    ),
    'automattic/jetpack-autoloader' => 
    array (
      'pretty_version' => 'v2.10.0',
      'version' => '2.10.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '6409c42b32ed82aff50869fd0a6a2e5c7a96fbd6',
    ),
    'automattic/jetpack-backup' => 
    array (
      'pretty_version' => 'v1.0.3',
      'version' => '1.0.3.0',
      'aliases' => 
      array (
      ),
      'reference' => 'ff4bd4363ae5b7a6251edc2067e1288c89312f11',
    ),
    'automattic/jetpack-blocks' => 
    array (
      'pretty_version' => 'v1.2.2',
      'version' => '1.2.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'c662a4df69eb0d548ab9942d5cdc0cdf4e061e2f',
    ),
    'automattic/jetpack-compat' => 
    array (
      'pretty_version' => 'v1.6.2',
      'version' => '1.6.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'b1b378411efc28362836f7be357d0bd0387b647e',
    ),
    'automattic/jetpack-config' => 
    array (
      'pretty_version' => 'v1.4.3',
      'version' => '1.4.3.0',
      'aliases' => 
      array (
      ),
      'reference' => '6bc77aa73d90510c9488e2d8fdaad4b056d3a066',
    ),
    'automattic/jetpack-connection' => 
    array (
      'pretty_version' => 'v1.24.0',
      'version' => '1.24.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '5ffc22ecc7210cb229ab4a9833e2ba1def817ff8',
    ),
    'automattic/jetpack-connection-ui' => 
    array (
      'pretty_version' => 'v1.0.0',
      'version' => '1.0.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '6d6b3dcf640bcc686c70d65cf88240c2d89b0f77',
    ),
    'automattic/jetpack-constants' => 
    array (
      'pretty_version' => 'v1.6.2',
      'version' => '1.6.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '84c8f09c5a6467104094243912fdcfe8eaed945b',
    ),
    'automattic/jetpack-device-detection' => 
    array (
      'pretty_version' => 'v1.3.2',
      'version' => '1.3.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'eb200bd5fb6fb1f77ef13cc13b21d82ebdeebc00',
    ),
    'automattic/jetpack-error' => 
    array (
      'pretty_version' => 'v1.3.2',
      'version' => '1.3.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '598db8d96eaccc1f4c0dcb86c6903ef291f00395',
    ),
    'automattic/jetpack-heartbeat' => 
    array (
      'pretty_version' => 'v1.3.3',
      'version' => '1.3.3.0',
      'aliases' => 
      array (
      ),
      'reference' => 'd6e906c15a539bee5f52957126ba1b43d11e63da',
    ),
    'automattic/jetpack-jitm' => 
    array (
      'pretty_version' => 'v1.14.0',
      'version' => '1.14.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '743f11caf7b801da5df3e889d6a8b769a47b0f8d',
    ),
    'automattic/jetpack-lazy-images' => 
    array (
      'pretty_version' => 'v1.4.1',
      'version' => '1.4.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '2ce4daee02018ac4ba4213bf28bfecd9def3c172',
    ),
    'automattic/jetpack-licensing' => 
    array (
      'pretty_version' => 'v1.3.4',
      'version' => '1.3.4.0',
      'aliases' => 
      array (
      ),
      'reference' => 'bc8c48f3e2f4afc926401e75e68182a7d4258929',
    ),
    'automattic/jetpack-logo' => 
    array (
      'pretty_version' => 'v1.5.2',
      'version' => '1.5.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'f8396034b28efec6ce4f75926434b12f314874c7',
    ),
    'automattic/jetpack-options' => 
    array (
      'pretty_version' => 'v1.11.2',
      'version' => '1.11.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '1889f3648782b5ceaac255f2a5ee96c1de8cca1e',
    ),
    'automattic/jetpack-partner' => 
    array (
      'pretty_version' => 'v1.4.2',
      'version' => '1.4.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '932f5524bfdee862fa547d24311f9b400fb86834',
    ),
    'automattic/jetpack-redirect' => 
    array (
      'pretty_version' => 'v1.5.3',
      'version' => '1.5.3.0',
      'aliases' => 
      array (
      ),
      'reference' => '85c9de58f88216e21110324e00a515c78f7f435b',
    ),
    'automattic/jetpack-roles' => 
    array (
      'pretty_version' => 'v1.4.2',
      'version' => '1.4.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'ad4e0c4483945b24db10c09696558a2f381236ee',
    ),
    'automattic/jetpack-status' => 
    array (
      'pretty_version' => 'v1.7.2',
      'version' => '1.7.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'a0f1f7450f045afc2669135ba576632ca0889506',
    ),
    'automattic/jetpack-sync' => 
    array (
      'pretty_version' => 'v1.21.0',
      'version' => '1.21.0.0',
      'aliases' => 
      array (
      ),
      'reference' => 'ea6e3a2508ceb8cc8d6da039ee1a34ec1a5613e0',
    ),
    'automattic/jetpack-terms-of-service' => 
    array (
      'pretty_version' => 'v1.9.3',
      'version' => '1.9.3.0',
      'aliases' => 
      array (
      ),
      'reference' => 'f8435228d918a5bd32912339b1124d35911efde1',
    ),
    'automattic/jetpack-tracking' => 
    array (
      'pretty_version' => 'v1.13.2',
      'version' => '1.13.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'b2dc6688098798111e21bd7d5d052fb828fa0932',
    ),
    'nojimage/twitter-text-php' => 
    array (
      'pretty_version' => 'v3.1.1',
      'version' => '3.1.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '7f466b331cebfdd00e3568acaf45f2e90a39a320',
    ),
  ),
);
private static $canGetVendors;
private static $installedByVendor = array();







public static function getInstalledPackages()
{
$packages = array();
foreach (self::getInstalled() as $installed) {
$packages[] = array_keys($installed['versions']);
}


if (1 === \count($packages)) {
return $packages[0];
}

return array_keys(array_flip(\call_user_func_array('array_merge', $packages)));
}









public static function isInstalled($packageName)
{
foreach (self::getInstalled() as $installed) {
if (isset($installed['versions'][$packageName])) {
return true;
}
}

return false;
}














public static function satisfies(VersionParser $parser, $packageName, $constraint)
{
$constraint = $parser->parseConstraints($constraint);
$provided = $parser->parseConstraints(self::getVersionRanges($packageName));

return $provided->matches($constraint);
}










public static function getVersionRanges($packageName)
{
foreach (self::getInstalled() as $installed) {
if (!isset($installed['versions'][$packageName])) {
continue;
}

$ranges = array();
if (isset($installed['versions'][$packageName]['pretty_version'])) {
$ranges[] = $installed['versions'][$packageName]['pretty_version'];
}
if (array_key_exists('aliases', $installed['versions'][$packageName])) {
$ranges = array_merge($ranges, $installed['versions'][$packageName]['aliases']);
}
if (array_key_exists('replaced', $installed['versions'][$packageName])) {
$ranges = array_merge($ranges, $installed['versions'][$packageName]['replaced']);
}
if (array_key_exists('provided', $installed['versions'][$packageName])) {
$ranges = array_merge($ranges, $installed['versions'][$packageName]['provided']);
}

return implode(' || ', $ranges);
}

throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}





public static function getVersion($packageName)
{
foreach (self::getInstalled() as $installed) {
if (!isset($installed['versions'][$packageName])) {
continue;
}

if (!isset($installed['versions'][$packageName]['version'])) {
return null;
}

return $installed['versions'][$packageName]['version'];
}

throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}





public static function getPrettyVersion($packageName)
{
foreach (self::getInstalled() as $installed) {
if (!isset($installed['versions'][$packageName])) {
continue;
}

if (!isset($installed['versions'][$packageName]['pretty_version'])) {
return null;
}

return $installed['versions'][$packageName]['pretty_version'];
}

throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}





public static function getReference($packageName)
{
foreach (self::getInstalled() as $installed) {
if (!isset($installed['versions'][$packageName])) {
continue;
}

if (!isset($installed['versions'][$packageName]['reference'])) {
return null;
}

return $installed['versions'][$packageName]['reference'];
}

throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}





public static function getRootPackage()
{
$installed = self::getInstalled();

return $installed[0]['root'];
}







public static function getRawData()
{
return self::$installed;
}



















public static function reload($data)
{
self::$installed = $data;
self::$installedByVendor = array();
}




private static function getInstalled()
{
if (null === self::$canGetVendors) {
self::$canGetVendors = method_exists('Composer\Autoload\ClassLoader', 'getRegisteredLoaders');
}

$installed = array();

if (self::$canGetVendors) {
foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
if (isset(self::$installedByVendor[$vendorDir])) {
$installed[] = self::$installedByVendor[$vendorDir];
} elseif (is_file($vendorDir.'/composer/installed.php')) {
$installed[] = self::$installedByVendor[$vendorDir] = require $vendorDir.'/composer/installed.php';
}
}
}

$installed[] = self::$installed;

return $installed;
}
}
