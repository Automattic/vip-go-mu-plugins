<?php











namespace Composer;

use Composer\Autoload\ClassLoader;
use Composer\Semver\VersionParser;






class InstalledVersions
{
private static $installed = array (
  'root' => 
  array (
    'pretty_version' => 'dev-jetpack/branch-9.4',
    'version' => 'dev-jetpack/branch-9.4',
    'aliases' => 
    array (
    ),
    'reference' => 'a2c6afbeb6bf8f63499df6833fdb33015a32b139',
    'name' => 'automattic/jetpack',
  ),
  'versions' => 
  array (
    'automattic/jetpack' => 
    array (
      'pretty_version' => 'dev-jetpack/branch-9.4',
      'version' => 'dev-jetpack/branch-9.4',
      'aliases' => 
      array (
      ),
      'reference' => 'a2c6afbeb6bf8f63499df6833fdb33015a32b139',
    ),
    'automattic/jetpack-a8c-mc-stats' => 
    array (
      'pretty_version' => 'v1.4.0',
      'version' => '1.4.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '278986d7aae98eb662710cfdc84f7a8f60422022',
    ),
    'automattic/jetpack-abtest' => 
    array (
      'pretty_version' => 'v1.9.2',
      'version' => '1.9.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'b86fbf62c5ccc0ab2d84d7c5fd62b53016d9bda6',
    ),
    'automattic/jetpack-assets' => 
    array (
      'pretty_version' => 'v1.11.1',
      'version' => '1.11.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'f7c741c560c367f9281bd4a88aaa800247c1f826',
    ),
    'automattic/jetpack-autoloader' => 
    array (
      'pretty_version' => 'v2.9.0',
      'version' => '2.9.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '6b4cd84d5333f4bf19b9765313d0984aff7682e2',
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
      'pretty_version' => 'v1.2.1',
      'version' => '1.2.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'c33573560f2109f9ecfe0fb1a282f93b4a0211d8',
    ),
    'automattic/jetpack-compat' => 
    array (
      'pretty_version' => 'v1.6.1',
      'version' => '1.6.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'b934da71d4d766bf7a38d91e9a2d6e7fa587aa2f',
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
      'pretty_version' => 'v1.23.1',
      'version' => '1.23.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '03303aa75b34b8bb4eff2f55178d6600f2a80f40',
    ),
    'automattic/jetpack-constants' => 
    array (
      'pretty_version' => 'v1.6.1',
      'version' => '1.6.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '4177e55eef1706dac67705c9ada61be283e4aa8d',
    ),
    'automattic/jetpack-device-detection' => 
    array (
      'pretty_version' => 'v1.3.1',
      'version' => '1.3.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'cae691e4c1f06191f0c97860f9442c3e1baee062',
    ),
    'automattic/jetpack-error' => 
    array (
      'pretty_version' => 'v1.3.1',
      'version' => '1.3.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '63216c911c2c2cf0a9ee541eab27c3757d7bab15',
    ),
    'automattic/jetpack-heartbeat' => 
    array (
      'pretty_version' => 'v1.3.2',
      'version' => '1.3.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'f08038223282c966135162dfdf80dc428e40e2ba',
    ),
    'automattic/jetpack-jitm' => 
    array (
      'pretty_version' => 'v1.13.4',
      'version' => '1.13.4.0',
      'aliases' => 
      array (
      ),
      'reference' => 'c2b1da18c02b5eb3afb3d00ba0c62c818e0b6fbc',
    ),
    'automattic/jetpack-lazy-images' => 
    array (
      'pretty_version' => 'v1.4.0',
      'version' => '1.4.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '11277948b43fad0e112ecabc4fbda2d9853442ff',
    ),
    'automattic/jetpack-licensing' => 
    array (
      'pretty_version' => 'v1.3.2',
      'version' => '1.3.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '3b89526b207f1e2dc64411815a514b36ca303eaf',
    ),
    'automattic/jetpack-logo' => 
    array (
      'pretty_version' => 'v1.5.1',
      'version' => '1.5.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'aa008df2e86ce656076a0b3d364b43fb43231aa0',
    ),
    'automattic/jetpack-options' => 
    array (
      'pretty_version' => 'v1.11.1',
      'version' => '1.11.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'd5e4a5018dee19c192600dbe0f6beed5cd8dceee',
    ),
    'automattic/jetpack-partner' => 
    array (
      'pretty_version' => 'v1.4.1',
      'version' => '1.4.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '4d5f01ca8d59497325a3edcbc572fa95683c9bff',
    ),
    'automattic/jetpack-redirect' => 
    array (
      'pretty_version' => 'v1.5.2',
      'version' => '1.5.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'f2ff0b1c4e23349a688cd2135990c0ee1aa4b860',
    ),
    'automattic/jetpack-roles' => 
    array (
      'pretty_version' => 'v1.4.1',
      'version' => '1.4.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '6d678f10a30a1050a03cbd6391c66313f26d9cdb',
    ),
    'automattic/jetpack-status' => 
    array (
      'pretty_version' => 'v1.7.1',
      'version' => '1.7.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '401b02141d334e39479328a837a61617120a262b',
    ),
    'automattic/jetpack-sync' => 
    array (
      'pretty_version' => 'v1.20.1',
      'version' => '1.20.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '612a75545ff3fa6345a88c042856c09f2a4f01ad',
    ),
    'automattic/jetpack-terms-of-service' => 
    array (
      'pretty_version' => 'v1.9.2',
      'version' => '1.9.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'd246996ecbf943c47afdaf36e31744ed693c0007',
    ),
    'automattic/jetpack-tracking' => 
    array (
      'pretty_version' => 'v1.13.1',
      'version' => '1.13.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '39af72ffcb6a5a9c568e8f961850109a5bdb133f',
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
