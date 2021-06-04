<?php











namespace Composer;

use Composer\Autoload\ClassLoader;
use Composer\Semver\VersionParser;








class InstalledVersions
{
private static $installed = array (
  'root' => 
  array (
    'pretty_version' => 'dev-master',
    'version' => 'dev-master',
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
      'pretty_version' => 'dev-master',
      'version' => 'dev-master',
      'aliases' => 
      array (
      ),
      'reference' => NULL,
    ),
    'automattic/jetpack-a8c-mc-stats' => 
    array (
      'pretty_version' => 'v1.4.3',
      'version' => '1.4.3.0',
      'aliases' => 
      array (
      ),
      'reference' => 'fb7d2f80de987911853b5158423b58002bac7be3',
    ),
    'automattic/jetpack-abtest' => 
    array (
      'pretty_version' => 'v1.9.6',
      'version' => '1.9.6.0',
      'aliases' => 
      array (
      ),
      'reference' => '80703243da4741339e4875a7d77d21570bedf9ac',
    ),
    'automattic/jetpack-assets' => 
    array (
      'pretty_version' => 'v1.11.4',
      'version' => '1.11.4.0',
      'aliases' => 
      array (
      ),
      'reference' => 'b07f8d9fac7cf57029872c2d443a8976118f2c78',
    ),
    'automattic/jetpack-autoloader' => 
    array (
      'pretty_version' => 'v2.10.2',
      'version' => '2.10.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '79a541381c1617b02980ab48d4ed0a0fb6ba462d',
    ),
    'automattic/jetpack-backup' => 
    array (
      'pretty_version' => 'v1.0.5',
      'version' => '1.0.5.0',
      'aliases' => 
      array (
      ),
      'reference' => '8499b674df06df51936b571fdbcd6e94bd55ad47',
    ),
    'automattic/jetpack-blocks' => 
    array (
      'pretty_version' => 'v1.3.0',
      'version' => '1.3.0.0',
      'aliases' => 
      array (
      ),
      'reference' => 'e281aa8c75d7f287d1abdd03dafe2b57539f2178',
    ),
    'automattic/jetpack-compat' => 
    array (
      'pretty_version' => 'v1.6.4',
      'version' => '1.6.4.0',
      'aliases' => 
      array (
      ),
      'reference' => '8b55ce46b4ba49ab7ec14c80278145edcc23ba30',
    ),
    'automattic/jetpack-config' => 
    array (
      'pretty_version' => 'v1.4.5',
      'version' => '1.4.5.0',
      'aliases' => 
      array (
      ),
      'reference' => '19d94664e0e79e1d3080e9818af6a76087878d72',
    ),
    'automattic/jetpack-connection' => 
    array (
      'pretty_version' => 'v1.26.0',
      'version' => '1.26.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '80f7891d747719bdd73f2063d632a86947a84271',
    ),
    'automattic/jetpack-connection-ui' => 
    array (
      'pretty_version' => 'v1.0.2',
      'version' => '1.0.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '96d01a6a707e58f53d7fcce8a9249f9b23d1cd5a',
    ),
    'automattic/jetpack-constants' => 
    array (
      'pretty_version' => 'v1.6.4',
      'version' => '1.6.4.0',
      'aliases' => 
      array (
      ),
      'reference' => 'cebd2c4760636595c7024e9381d5ee5a79825dbc',
    ),
    'automattic/jetpack-device-detection' => 
    array (
      'pretty_version' => 'v1.4.1',
      'version' => '1.4.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'c99b027e10357a7a29c206d367abdf0aa7d048e8',
    ),
    'automattic/jetpack-error' => 
    array (
      'pretty_version' => 'v1.3.4',
      'version' => '1.3.4.0',
      'aliases' => 
      array (
      ),
      'reference' => '53f15a57d82d53e317defa0f2393c3dfe4373698',
    ),
    'automattic/jetpack-heartbeat' => 
    array (
      'pretty_version' => 'v1.3.6',
      'version' => '1.3.6.0',
      'aliases' => 
      array (
      ),
      'reference' => '5af1732cbe46311ba7f7487beaccd3f1c6d70654',
    ),
    'automattic/jetpack-jitm' => 
    array (
      'pretty_version' => 'v1.15.1',
      'version' => '1.15.1.0',
      'aliases' => 
      array (
      ),
      'reference' => 'e026a3e860758c98ccd4d5f915df97bee7d5ec42',
    ),
    'automattic/jetpack-lazy-images' => 
    array (
      'pretty_version' => 'v1.4.3',
      'version' => '1.4.3.0',
      'aliases' => 
      array (
      ),
      'reference' => '0f606f0cff78957165f2037affb7e29a5f4692bd',
    ),
    'automattic/jetpack-licensing' => 
    array (
      'pretty_version' => 'v1.4.1',
      'version' => '1.4.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '0bbf3337ad642825e46e90a93ae052b7ba369a64',
    ),
    'automattic/jetpack-logo' => 
    array (
      'pretty_version' => 'v1.5.4',
      'version' => '1.5.4.0',
      'aliases' => 
      array (
      ),
      'reference' => '37e3c8feefebecf4d801647df171c6b823d91413',
    ),
    'automattic/jetpack-options' => 
    array (
      'pretty_version' => 'v1.12.0',
      'version' => '1.12.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '12de2a6b1989e3a0620ed65e1c6ab6003fe9177a',
    ),
    'automattic/jetpack-partner' => 
    array (
      'pretty_version' => 'v1.5.0',
      'version' => '1.5.0.0',
      'aliases' => 
      array (
      ),
      'reference' => 'd3f97d518a1da91512fc8658d331795b2ba29afb',
    ),
    'automattic/jetpack-password-checker' => 
    array (
      'pretty_version' => 'v0.1.1',
      'version' => '0.1.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '52b8debf87226291e618f84df23e151b04924435',
    ),
    'automattic/jetpack-redirect' => 
    array (
      'pretty_version' => 'v1.5.5',
      'version' => '1.5.5.0',
      'aliases' => 
      array (
      ),
      'reference' => '30ef3a5ba81a13733b1fb3627a718487c324ddca',
    ),
    'automattic/jetpack-roles' => 
    array (
      'pretty_version' => 'v1.4.4',
      'version' => '1.4.4.0',
      'aliases' => 
      array (
      ),
      'reference' => '66fc99a92fb17352ca8b9eb7d990c95655acd491',
    ),
    'automattic/jetpack-status' => 
    array (
      'pretty_version' => 'v1.7.5',
      'version' => '1.7.5.0',
      'aliases' => 
      array (
      ),
      'reference' => '18ef84f8e3e299c0cb9c548c92743ab9aee0d8f7',
    ),
    'automattic/jetpack-sync' => 
    array (
      'pretty_version' => 'v1.21.2',
      'version' => '1.21.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'e18159f51af01eca76d18b6caca400c6f1fdaf9c',
    ),
    'automattic/jetpack-terms-of-service' => 
    array (
      'pretty_version' => 'v1.9.6',
      'version' => '1.9.6.0',
      'aliases' => 
      array (
      ),
      'reference' => '1c16fae405b434e9f3263a3993062e82fc785adb',
    ),
    'automattic/jetpack-tracking' => 
    array (
      'pretty_version' => 'v1.13.5',
      'version' => '1.13.5.0',
      'aliases' => 
      array (
      ),
      'reference' => '6eae59b42a43ecad681be549f7ebcb4db0d2d8af',
    ),
    'nojimage/twitter-text-php' => 
    array (
      'pretty_version' => 'v3.1.2',
      'version' => '3.1.2.0',
      'aliases' => 
      array (
      ),
      'reference' => '979bcf6a92d543b61588c7c0c0a87d0eb473d8f6',
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
