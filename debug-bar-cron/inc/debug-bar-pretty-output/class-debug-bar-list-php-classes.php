<?php
/**
 * Debug_Bar_List_PHP_Classes - Helper class for Debug Bar plugins.
 *
 * Used by the following plugins:
 * - Debug Bar Constants
 * - Debug Bar WP Objects (unreleased)
 *
 * @package    Debug Bar Pretty Output
 * @author     Juliette Reinders Folmer <wpplugins_nospam@adviesenzo.nl>
 * @link       https://github.com/jrfnl/debug-bar-pretty-output
 * @version    1.7.0
 *
 * @copyright  2013-2017 Juliette Reinders Folmer
 * @license    http://creativecommons.org/licenses/GPL/2.0/ GNU General Public License, version 2 or higher.
 */

if ( ! class_exists( 'Debug_Bar_List_PHP_Classes' ) ) {

	/**
	 * This class does nothing, just a way to keep the list of php classes out of the global namespace
	 * You can retrieve the list by using the static variable Debug_Bar_List_PHP_Classes::$PHP_classes
	 * List last updated: 2015-12-05 (just after PHP 7.0.0 release).
	 *
	 * @todo - maybe make parts of the list flexible based on extension_loaded().
	 */
	class Debug_Bar_List_PHP_Classes {

		/**
		 * List of all PHP native class names.
		 *
		 * @var array
		 *
		 * @static
		 */
		public static $PHP_classes = array(

			/* == "Core" == */
			// @see http://php.net/reserved.classes
			// Directory / php_user_filter are listed with their respective books.
			'stdClass',
			'__PHP_Incomplete_Class',

			// Interfaces.
			// @see http://php.net/reserved.interfaces
			'Traversable',
			'Iterator',
			'IteratorAggregate',
			'ArrayAccess',
			'Serializable',
			'Closure',   // PHP 5.3.0+.
			'Generator', // PHP 5.5.0+.
			'Throwable', // PHP 7.0.0+.

			// Exceptions.
			// @see http://php.net/reserved.exceptions
			'Exception',
			'ErrorException',      // PHP 5.1.0+.
			'Error',               // PHP 7.0.0+.
			'ArithmeticError',     // PHP 7.0.0+.
			'AssertionError',      // PHP 7.0.0+.
			'DivisionByZeroError', // PHP 7.0.0+.
			'ParseError',          // PHP 7.0.0+.
			'TypeError',           // PHP 7.0.0+.


			/* == Affecting PHPs Behaviour == */
			// APC.
			// @see http://php.net/book.apc
			'APCIterator',

			// APC User Cache.
			// @see http://php.net/book.apcu
			'APCUIterator',

			// Rrunkit
			// @see http://php.net/book.runkit
			'Runkit_Sandbox',
			'Runkit_Sandbox_Parent',

			// Weakref.
			// @see http://php.net/book.weakref
			'WeakRef',
			'WeakMap',


			/* == Audio Formats Manipulation == */
			// KTaglib.
			// @see http://php.net/book.ktaglib
			'KTaglib_MPEG_File',
			'KTaglib_MPEG_AudioProperties',
			'KTaglib_Tag',
			'KTaglib_ID3v2_Tag',
			'KTaglib_ID3v2_Frame',
			'KTaglib_ID3v2_AttachedPictureFrame',


			/* == Authentication Services == */

			/* == Command Line Specific Extensions == */

			/* == Compression and Archive Extensions == */
			// Phar.
			// @see http://php.net/book.phar
			'Phar',
			'PharData',
			'PharFileInfo',
			'PharException',

			// Rar.
			// @see http://php.net/book.rar
			'RarArchive',
			'RarEntry',
			'RarException',

			// Zip.
			// @see http://php.net/book.zip
			'ZipArchive',


			/* == Credit Card Processing == */

			/* == Cryptography Extensions == */

			/* == Database Extensions == */

			/* = Abstraction Layers = */
			// PDO.
			// @see http://php.net/book.pdo
			'PDO',
			'PDOStatement',
			'PDOException',
			'PDORow',  // Not in PHP docs.


			/* = Vendor Specific Database Extensions = */
			// Mongo.
			// @see http://php.net/book.mongo
			// Mongo Core Classes.
			'MongoClient',
			'MongoDB',
			'MongoCollection',
			'MongoCursor',
			'MongoCursorInterface',
			'MongoCommandCursor',

			// Mongo Types.
			'MongoId',
			'MongoCode',
			'MongoDate',
			'MongoRegex',
			'MongoBinData',
			'MongoInt32',
			'MongoInt64',
			'MongoDBRef',
			'MongoMinKey',
			'MongoMaxKey',
			'MongoTimestamp',

			// Mongo GridFS Classes.
			'MongoGridFS',
			'MongoGridFSFile',
			'MongoGridFSCursor',

			// Mongo Batch Classes.
			'MongoWriteBatch',
			'MongoInsertBatch',
			'MongoUpdateBatch',
			'MongoDeleteBatch',

			// Mongo Miscellaneous.
			'MongoLog',
			'MongoPool',
			'Mongo',

			// Mongo Exceptions.
			'MongoException',
			'MongoResultException',
			'MongoCursorException',
			'MongoCursorTimeoutException',
			'MongoConnectionException',
			'MongoGridFSException',
			'MongoDuplicateKeyException',
			'MongoProtocolException',
			'MongoExecutionTimeoutException',
			'MongoWriteConcernException',

			// PHP driver for MongoDB.
			// @see http://php.net/set.mongodb
			// MongoDB\Driver.
			'MongoDB\Driver\Manager',
			'MongoDB\Driver\Command',
			'MongoDB\Driver\Query',
			'MongoDB\Driver\BulkWrite',
			'MongoDB\Driver\WriteConcern',
			'MongoDB\Driver\ReadPreference',
			'MongoDB\Driver\ReadConcern',
			'MongoDB\Driver\Cursor',
			'MongoDB\Driver\CursorId',
			'MongoDB\Driver\Server',
			'MongoDB\Driver\WriteConcernError',
			'MongoDB\Driver\WriteError',
			'MongoDB\Driver\WriteResult',

			// BSON.
			'MongoDB\BSON\Binary',
			'MongoDB\BSON\Decimal128',
			'MongoDB\BSON\Javascript',
			'MongoDB\BSON\MaxKey',
			'MongoDB\BSON\MinKey',
			'MongoDB\BSON\ObjectID',
			'MongoDB\BSON\Regex',
			'MongoDB\BSON\Timestamp',
			'MongoDB\BSON\UTCDatetime',
			'MongoDB\BSON\Type',
			'MongoDB\BSON\Persistable',
			'MongoDB\BSON\Serializable',
			'MongoDB\BSON\Unserializable',

			// MongoDB Exceptions.
			'MongoDB\Driver\Exception\AuthenticationException',
			'MongoDB\Driver\Exception\BulkWriteException',
			'MongoDB\Driver\Exception\ConnectionException',
			'MongoDB\Driver\Exception\ConnectionTimeoutException',
			'MongoDB\Driver\Exception\Exception',
			'MongoDB\Driver\Exception\ExecutionTimeoutException',  // (No version information available, might only be in Git).
			'MongoDB\Driver\Exception\InvalidArgumentException',
			'MongoDB\Driver\Exception\LogicException',
			'MongoDB\Driver\Exception\RuntimeException',
			'MongoDB\Driver\Exception\SSLConnectionException',
			'MongoDB\Driver\Exception\UnexpectedValueException',
			'MongoDB\Driver\Exception\WriteException',

			'MongoDB\Driver\DuplicateKeyException', // No longer in the manual, possibly deprecated ?
			'MongoDB\Driver\WriteConcernException', // No longer in the manual, possibly deprecated ?

			// MySQL.
			// @see http://php.net/set.mysqlinfo
			// Mysqli - MySQL Improved Extension.
			// @see http://php.net/book.mysqli
			'mysqli',
			'mysqli_stmt',
			'mysqli_result',
			'mysqli_driver',
			'mysqli_warning',
			'mysqli_sql_exception',

			// Mysqlnd_uh - Mysqlnd user handler plugin.
			// @see http://php.net/book.mysqlnd-uh
			'MysqlndUhConnection',
			'MysqlndUhPreparedStatement',

			// OCI8 - Oracle OCI8.
			// @see http://php.net/book.oci8
			'OCI-Collection',
			'OCI-Lob',

			// SQLLite.
			// @see http://php.net/ref.sqlite
			'SQLiteDatabase',   // Not easy to find in PHP docs.
			'SQLiteResult',     // Not easy to find in PHP docs.
			'SQLiteUnbuffered', // Not easy to find in PHP docs.
			'SQLiteException',  // Not easy to find in PHP docs.

			// SQLite3.
			// @see http://php.net/book.sqlite3
			'SQLite3',
			'SQLite3Stmt',
			'SQLite3Result',

			// Tokyo_tyrant.
			// @see http://php.net/book.tokyo-tyrant
			'TokyoTyrant',
			'TokyoTyrantTable',
			'TokyoTyrantQuery',
			'TokyoTyrantIterator',
			'TokyoTyrantException',


			/* == Date and Time Related Extensions == */
			// Date/Time.
			// @see http://php.net/book.datetime
			'DateTime',
			'DateTimeImmutable', // PHP 5.5.0+.
			'DateTimeInterface', // PHP 5.5.0+ (interface, not class).
			'DateTimeZone',
			'DateInterval',
			'DatePeriod',

			// HRTime.
			// @see http://php.net/book.hrtime
			'HRTime\PerformanceCounter',
			'HRTime\StopWatch',
			'HRTime\Unit',


			/* == File System Related Extensions == */
			// Directories.
			// @see http://php.net/book.dir
			'Directory',

			// File Information.
			// @see http://php.net/book.fileinfo
			'finfo', // PHP 5.3.0+.


			/* == Human Language and Character Encoding Support == */
			// Gender.
			// @see http://php.net/book.gender
			'Gender\Gender',

			// Intl - since PHP 5.3.0.
			// @see http://php.net/book.intl
			'Collator',
			'NumberFormatter',
			'Locale',
			'Normalizer',
			'MessageFormatter',
			'IntlCalendar',               // PHP 5.5.0+.
			'IntlTimeZone',               // PHP 5.5.0+.
			'IntlDateFormatter',
			'ResourceBundle',
			'Spoofchecker',
			'Transliterator',
			'IntlBreakIterator',          // (No version information available, might only be in Git).
			'IntlRuleBasedBreakIterator', // (No version information available, might only be in Git).
			'IntlCodePointBreakIterator', // (No version information available, might only be in Git).
			'IntlPartsIterator',          // (No version information available, might only be in Git).
			'UConverter',                 // PHP 5.5.0+.
			'IntlChar',                   // PHP 7.0.0+.
			'IntlException',              // PHP 5.5.0+.
			'IntlIterator',               // (No version information available, might only be in Git).


			/* == Image Processing and Generation == */
			// Cairo.
			// @see http://php.net/book.cairo
			'Cairo',
			'CairoContext',
			'CairoException',
			'CairoStatus',
			'CairoSurface',
			'CairoSvgSurface',
			'CairoImageSurface',
			'CairoPdfSurface',
			'CairoPsSurface',
			'CairoSurfaceType',
			'CairoFontFace',
			'CairoFontOptions',
			'CairoFontSlant',
			'CairoFontType',
			'CairoFontWeight',
			'CairoScaledFont',
			'CairoToyFontFace',
			'CairoPatternType',
			'CairoPattern',
			'CairoGradientPattern',
			'CairoSolidPattern',
			'CairoSurfacePattern',
			'CairoLinearGradient',
			'CairoRadialGradient',
			'CairoAntialias',
			'CairoContent',
			'CairoExtend',
			'CairoFormat',
			'CairoFillRule',
			'CairoFilter',
			'CairoHintMetrics',
			'CairoHintStyle',
			'CairoLineCap',
			'CairoLineJoin',
			'CairoMatrix',
			'CairoOperator',
			'CairoPath',
			'CairoPsLevel',
			'CairoSubpixelOrder',
			'CairoSvgVersion',

			// Gmagick.
			// @see http://php.net/book.gmagick
			'Gmagick',
			'GmagickDraw',
			'GmagickPixel',

			// ImageMagick.
			// @see http://php.net/book.imagick
			'Imagick',
			'ImagickDraw',
			'ImagickPixel',
			'ImagickPixelIterator',
			'ImagickKernel',


			/* == Mail Related Extensions == */

			/* == Mathematical Extensions == */
			// GMP.
			// @see http://php.net/book.gmp
			'GMP', // PHP 5.6.0+.

			// Lapack.
			// @see http://php.net/book.lapack
			'Lapack',
			'LapackException',


			/* == Non-Text MIME Output == */
			// Haru.
			// @see http://php.net/book.haru
			'HaruException',
			'HaruDoc',
			'HaruPage',
			'HaruFont',
			'HaruImage',
			'HaruEncoder',
			'HaruOutline',
			'HaruAnnotation',
			'HaruDestination',

			// Ming.
			// @see http://php.net/book.ming
			'SWFAction',
			'SWFBitmap',
			'SWFButton',
			'SWFDisplayItem',
			'SWFFill',
			'SWFFont',
			'SWFFontChar',
			'SWFGradient',
			'SWFMorph',
			'SWFMovie',
			'SWFPrebuiltClip',
			'SWFShape',
			'SWFSound',
			'SWFSoundInstance',
			'SWFSprite',
			'SWFText',
			'SWFTextField',
			'SWFVideoStream',


			/* == Process Control Extensions == */
			// Ev.
			// @see http://php.net/book.ev
			'Ev',
			'EvCheck',
			'EvChild',
			'EvEmbed',
			'EvFork',
			'EvIdle',
			'EvIo',
			'EvLoop',
			'EvPeriodic',
			'EvPrepare',
			'EvSignal',
			'EvStat',
			'EvTimer',
			'EvWatcher',

			// Pthreads.
			// @see http://php.net/book.pthreads
			'Threaded',
			'Thread',
			'Worker',
			'Collectable', // Interface.
			'Pool',
			'Stackable', // No longer available ?
			'Mutex',
			'Cond',

			// Sync.
			// @see http://php.net/book.sync
			'SyncMutex',
			'SyncSemaphore',
			'SyncEvent',
			'SyncReaderWriter',
			'SyncSharedMemory',


			/* == Other Basic Extensions == */
			// FANN - Fast Artificial Neural Network.
			// @see http://php.net/book.fann
			'FANNConnection',

			// JSON - JavaScript Object Notation.
			// @see http://php.net/book.json
			'JsonSerializable', // PHP 5.4.0+ (interface, not class).

			// Judy - Judy Arrays.
			// @see http://php.net/book.judy
			'Judy',

			// Lua.
			// @see http://php.net/book.lua
			'Lua',
			'LuaClosure',

			// SPL - Standard PHP Library (SPL).
			// @see http://php.net/book.spl
			// SPL Data structures.
			'SplDoublyLinkedList',
			'SplStack',
			'SplQueue',
			'SplHeap',
			'SplMaxHeap',
			'SplMinHeap',
			'SplPriorityQueue',
			'SplFixedArray',
			'SplObjectStorage',

			// SPL Iterators.
			'AppendIterator',
			'ArrayIterator',
			'CachingIterator',
			'CallbackFilterIterator',
			'DirectoryIterator',
			'EmptyIterator',
			'FilesystemIterator',
			'FilterIterator',
			'GlobIterator',
			'InfiniteIterator',
			'IteratorIterator',
			'LimitIterator',
			'MultipleIterator',
			'NoRewindIterator',
			'ParentIterator',
			'RecursiveArrayIterator',
			'RecursiveCachingIterator',
			'RecursiveCallbackFilterIterator',
			'RecursiveDirectoryIterator',
			'RecursiveFilterIterator',
			'RecursiveIteratorIterator',
			'RecursiveRegexIterator',
			'RecursiveTreeIterator',
			'RegexIterator',

			'CachingRecursiveIterator', // Not in PHP docs - deprecated.

			// SPL Interfaces.
			'Countable',
			'OuterIterator',
			'RecursiveIterator',
			'SeekableIterator',

			// SPL Exceptions.
			'BadFunctionCallException',
			'BadMethodCallException',
			'DomainException',
			'InvalidArgumentException',
			'LengthException',
			'LogicException',
			'OutOfBoundsException',
			'OutOfRangeException',
			'OverflowException',
			'RangeException',
			'RuntimeException',
			'UnderflowException',
			'UnexpectedValueException',

			// SPL File Handling.
			'SplFileInfo',
			'SplFileObject',
			'SplTempFileObject',

			// SPL Miscellaneous Classes and Interfaces.
			'ArrayObject',
			'SplObserver',
			'SplSubject',

			// SPL Types - SPL Type Handling.
			// @see http://php.net/book.spl-types
			'SplType',
			'SplInt',
			'SplFloat',
			'SplEnum',
			'SplBool',
			'SplString',

			// Streams.
			// @see http://php.net/book.stream
			'php_user_filter',
			'streamWrapper',

			// Tidy.
			// @see http://php.net/book.tidy
			'tidy',
			'tidyNode',

			// V8js - V8 Javascript Engine Integration.
			// @see http://php.net/book.v8js
			'V8Js',
			'V8JsException',

			// Yaf.
			// @see http://php.net/book.yaf
			'Yaf_Application',
			'Yaf_Bootstrap_Abstract',
			'Yaf_Dispatcher',
			'Yaf_Config_Abstract',
			'Yaf_Config_Ini',
			'Yaf_Config_Simple',
			'Yaf_Controller_Abstract',
			'Yaf_Action_Abstract',
			'Yaf_View_Interface',
			'Yaf_View_Simple',
			'Yaf_Loader',
			'Yaf_Plugin_Abstract',
			'Yaf_Registry',
			'Yaf_Request_Abstract',
			'Yaf_Request_Http',
			'Yaf_Request_Simple',
			'Yaf_Response_Abstract',
			'Yaf_Route_Interface',
			'Yaf_Route_Map',
			'Yaf_Route_Regex',
			'Yaf_Route_Rewrite',
			'Yaf_Router',
			'Yaf_Route_Simple',
			'Yaf_Route_Static',
			'Yaf_Route_Supervar',
			'Yaf_Session',
			'Yaf_Exception',
			'Yaf_Exception_TypeError',
			'Yaf_Exception_StartupError',
			'Yaf_Exception_DispatchFailed',
			'Yaf_Exception_RouterFailed',
			'Yaf_Exception_LoadFailed',
			'Yaf_Exception_LoadFailed_Module',
			'Yaf_Exception_LoadFailed_Controller',
			'Yaf_Exception_LoadFailed_Action',
			'Yaf_Exception_LoadFailed_View',


			/* == Other Services == */
			// Chdb - Constant hash database.
			// @see http://php.net/book.chdb
			'chdb',

			// Curl - Client URL Library.
			// @see http://php.net/book.curl
			'CURLFile',

			// Event.
			// @see http://php.net/book.event
			'Event',
			'EventBase',
			'EventBuffer',
			'EventBufferEvent',
			'EventConfig',
			'EventDnsBase',
			'EventHttp',
			'EventHttpConnection',
			'EventHttpRequest',
			'EventListener',
			'EventSslContext',
			'EventUtil',

			// Gearman.
			// @see http://php.net/book.gearman
			'GearmanClient',
			'GearmanJob',
			'GearmanTask',
			'GearmanWorker',
			'GearmanException',

			// Hyperwave API.
			// @see http://php.net/book.hwapi
			'hw_api',
			'hw_api_attribute',
			'hw_api_content',
			'hw_api_error',
			'hw_api_object',
			'hw_api_reason',

			// Memcache.
			// @see http://php.net/book.memcache
			'Memcache',

			// Memcached.
			// @see http://php.net/book.memcached
			'Memcached',
			'MemcachedException',

			// RRD - RRDtool.
			// @see http://php.net/book.rrd
			'RRDCreator',
			'RRDGraph',
			'RRDUpdater',

			// Simple Asynchronous Messaging.
			// @see http://php.net/book.sam
			'SAMConnection',
			'SAMMessage',

			// SNMP.
			// @see http://php.net/book.snmp
			'SNMP',
			'SNMPException',

			// Stomp - Stomp Client.
			// @see http://php.net/book.stomp
			'Stomp',
			'StompFrame',
			'StompException',

			// SVM - Support Vector Machine.
			// @see http://php.net/book.svm
			'SVM',
			'SVMModel',

			// Varnish.
			// @see http://php.net/book.varnish
			'VarnishAdmin',
			'VarnishStat',
			'VarnishLog',

			// ZMQ - 0MQ messaging.
			// @see http://php.net/book.zmq
			'ZMQ',
			'ZMQContext',
			'ZMQSocket',
			'ZMQPoll',
			'ZMQDevice',

			// ZooKeeper.
			// @see http://php.net/book.zookeeper
			'ZooKeeper',


			/* == Search Engine Extensions == */
			// Solr - Apache Solr.
			// @see http://php.net/book.solr
			'SolrUtils',
			'SolrInputDocument',
			'SolrDocument',
			'SolrDocumentField',
			'SolrObject',
			'SolrClient',
			'SolrResponse',
			'SolrQueryResponse',
			'SolrUpdateResponse',
			'SolrPingResponse',
			'SolrGenericResponse',
			'SolrParams',
			'SolrModifiableParams',
			'SolrQuery',
			'SolrDisMaxQuery',
			'SolrCollapseFunction',
			'SolrException',
			'SolrClientException',
			'SolrServerException',
			'SolrIllegalArgumentException',
			'SolrIllegalOperationException',
			'SolrMissingMandatoryParameterException', // (No version information available, might only be in Git).

			// Sphinx - Sphinx Client.
			// @see http://php.net/book.sphinx
			'SphinxClient',

			// Swish Indexing.
			// @see http://php.net/book.swish
			'Swish',
			'SwishResult',
			'SwishResults',
			'SwishSearch',


			/* == Server Specific Extensions == */

			/* == Session Extensions == */
			// Sessions - Session Handling.
			// @see http://php.net/book.session
			'SessionHandler',
			'SessionHandlerInterface',


			/* == Text Processing == */

			/* == Variable and Type Related Extensions == */
			// Data Structures.
			// PECL icm PHP 7.
			// @see http://php.net/book.ds
			'Ds\Collection', // Interface.
			'Ds\Hashable',   // Interface.
			'Ds\Sequence',   // Interface.
			'Ds\Vector',
			'Ds\Deque',
			'Ds\Map',
			'Ds\Pair',
			'Ds\Set',
			'Ds\Stack',
			'Ds\Queue',
			'Ds\PriorityQueue',

			// Quickhash.
			// @see http://php.net/book.quickhash
			'QuickHashIntSet',
			'QuickHashIntHash',
			'QuickHashStringIntHash',
			'QuickHashIntStringHash',

			// Reflection.
			// @see http://php.net/book.reflection
			'Reflection',
			'ReflectionClass',
			'ReflectionZendExtension',
			'ReflectionExtension',
			'ReflectionFunction',
			'ReflectionFunctionAbstract',
			'ReflectionMethod',
			'ReflectionObject',
			'ReflectionParameter',
			'ReflectionProperty',
			'ReflectionType',      // PHP 7.0.0+.
			'ReflectionGenerator', // PHP 7.0.0+.
			'Reflector',
			'ReflectionException',


			/* == Web Services == */
			// OAuth.
			// @see http://php.net/book.oauth
			'OAuth',
			'OAuthProvider',
			'OAuthException',

			// SCA.
			// @see http://php.net/book.sca
			'SCA',
			'SCA_LocalProxy',
			'SCA_SoapProxy',

			// SOAP.
			// @see http://php.net/book.soap
			'SoapClient',
			'SoapServer',
			'SoapFault',
			'SoapHeader',
			'SoapParam',
			'SoapVar',

			// YAR - Yet Another RPC Framework.
			// @see http://php.net/book.yar
			'Yar_Server',
			'Yar_Client',
			'Yar_Concurrent_Client',
			'Yar_Server_Exception',
			'Yar_Client_Exception',


			/* == Windows Only Extensions == */

			// COM - COM and .Net (Windows).
			// @see http://php.net/book.com
			'COM',
			'DOTNET',
			'VARIANT',
			'COMPersistHelper',    // Not in PHP docs.
			'com_exception',       // Not in PHP docs.
			'com_safearray_proxy', // Not in PHP docs.


			/* == XML Manipulation == */
			// DOM - Document Object Model.
			// @see http://php.net/book.dom
			'DOMAttr',
			'DOMCdataSection',
			'DOMCharacterData',
			'DOMComment',
			'DOMDocument',
			'DOMDocumentFragment',
			'DOMDocumentType',
			'DOMElement',
			'DOMEntity',
			'DOMEntityReference',
			'DOMException',
			'DOMImplementation',
			'DOMNamedNodeMap',
			'DOMNode',
			'DOMNodeList',
			'DOMNotation',
			'DOMProcessingInstruction',
			'DOMText',
			'DOMXPath',

			'DOMConfiguration',        // Not in PHP docs.
			'DOMDocumentType',         // Not in PHP docs.
			'DOMDomError',             // Not in PHP docs.
			'DOMErrorHandler',         // Not in PHP docs.
			'DOMImplementationList',   // Not in PHP docs.
			'DOMImplementationSource', // Not in PHP docs.
			'DOMLocator',              // Not in PHP docs.
			'DOMNameList',             // Not in PHP docs.
			'DOMNameSpaceNode',        // Not in PHP docs.
			'DOMStringExtend',         // Not in PHP docs.
			'DOMStringList',           // Not in PHP docs.
			'DOMTypeinfo',             // Not in PHP docs.
			'DOMUserDataHandler',      // Not in PHP docs.

			// Libxml.
			// @see http://php.net/book.libxml
			'libXMLError',

			// Service Data Objects.
			// @see http://php.net/book.sdo
			'SDO_DAS_ChangeSummary',
			'SDO_DAS_DataFactory',
			'SDO_DAS_DataObject',
			'SDO_DAS_Setting',
			'SDO_DataFactory',
			'SDO_DataObject',
			'SDO_Exception',
			'SDO_List',
			'SDO_Model_Property',
			'SDO_Model_ReflectionDataObject',
			'SDO_Model_Type',
			'SDO_Sequence',

			// SDO Relational Data Access Service.
			// @see http://php.net/book.sdodasrel
			'SDO_DAS_Relational',

			// SDO XML Data Access Service.
			// @see http://php.net/book.sdo-das-xml
			'SDO_DAS_XML',
			'SDO_DAS_XML_Document',

			// SimpleXML.
			// @see http://php.net/book.simplexml
			'SimpleXMLElement',
			'SimpleXMLIterator',

			// XMLDiff - XML diff and merge.
			// @see http://php.net/book.xmldiff
			'XMLDiff\Base',
			'XMLDiff\DOM',
			'XMLDiff\Memory',
			'XMLDiff\File',

			// XMLReader.
			// @see http://php.net/book.xmlreader
			'XMLReader',

			// XMLWriter.
			// @see http://php.net/book.xmlwriter
			'XMLWriter',

			// XSL.
			// @see http://php.net/book.xsl
			'XSLTProcessor',


			/* == GUI Extensions == */
			// UI - PHP7+ & PECL.
			// @see http://php.net/book.ui
			'UI\Point',
			'UI\Size',
			'UI\Window',
			'UI\Control',
			'UI\Menu',
			'UI\MenuItem',
			'UI\Area',
			'UI\Executor',
			'UI\Controls\Tab',
			'UI\Controls\Check',
			'UI\Controls\Button',
			'UI\Controls\ColorButton',
			'UI\Controls\Label',
			'UI\Controls\Entry',
			'UI\Controls\MultilineEntry',
			'UI\Controls\Spin',
			'UI\Controls\Slider',
			'UI\Controls\Progress',
			'UI\Controls\Separator',
			'UI\Controls\Combo',
			'UI\Controls\EditableCombo',
			'UI\Controls\Radio',
			'UI\Controls\Picker',
			'UI\Controls\Form',
			'UI\Controls\Grid',
			'UI\Controls\Group',
			'UI\Controls\Box',
			'UI\Draw\Pen',
			'UI\Draw\Path',
			'UI\Draw\Matrix',
			'UI\Draw\Color',
			'UI\Draw\Stroke',
			'UI\Draw\Brush',
			'UI\Draw\Brush\Gradient',
			'UI\Draw\Brush\LinearGradient',
			'UI\Draw\Brush\RadialGradient',
			'UI\Draw\Text\Layout',
			'UI\Draw\Text\Font',
			'UI\Draw\Text\Font\Descriptor',
			'UI\Draw\Text\Font\Weight',
			'UI\Draw\Text\Font\Italic',
			'UI\Draw\Text\Font\Stretch',
			'UI\Draw\Line\Cap',
			'UI\Draw\Line\Join',
			'UI\Key',
			'UI\Exception\InvalidArgumentException',
			'UI\Exception\RuntimeException',
		);
	} // End of class Debug_Bar_List_PHP_Classes.

} // End of if class_exists wrapper.
