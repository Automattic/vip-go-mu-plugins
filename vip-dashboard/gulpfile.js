/**
 * Settings
 *
 * Setup your project paths and requirements here
 */
var settings = {

	// react
	componentpath: ['components/**/*.jsx', 'components/**/*.js'],
	js: './components/vip-dashboard.jsx',
	jspath: 'assets/js/',

	// path to main scss file
	scss: 'components/style.scss',

	// path to output css file
	css: 'assets/css/style.css',

	// path to watch for changed scss files
	scsswatch: 'components/**/*.scss',

	// path to output css folder
	csspath: 'assets/css/',

	// path to images
	imagespath: 'src/img/',
	imagesdistpath: 'assets/img/',

	// path to base
	basepath: './',

	// path to html
	htmlpath: ['./*.html', './*.php'],

	// enable the static file server and browsersync
	// check for unused styles in static html? - seems buggy, requires html
	staticserver: false,
	checkunusedcss: false,

	// enable the proxied local server for browsersync
	// static above server must be disabled
	proxyserver: true,
	proxylocation: 'vip.w.dev'

};

/**
 * Load node modules
 */
var	gulp = require( 'gulp' ),

	// Plugins
	assign = require( 'lodash.assign' ),
	autoprefixer = require( 'gulp-autoprefixer' ),
	browserify = require( 'browserify' ),
	browsersync = require( 'browser-sync' ),
	buffer = require( 'vinyl-buffer' ),
	checkcss = require( 'gulp-check-unused-css' ),
	concat = require( 'gulp-concat' ),
	csscomb = require( 'gulp-csscomb' ),
	eslint = require( 'gulp-eslint' ),
	filter = require( 'gulp-filter' ),
	imagemin = require( 'gulp-imagemin' ),
	install = require( 'gulp-install' ),
	minifycss = require( 'gulp-minify-css' ),
	parker = require( 'gulp-parker' ),
	plumber = require( 'gulp-plumber' ),
	react = require( 'gulp-react' ),
	sass = require( 'gulp-sass' ),
	source = require( 'vinyl-source-stream' ),
	reactify = require( 'reactify' ),
	sourcemaps = require( 'gulp-sourcemaps' ),
	sync = require( 'gulp-config-sync' ),
	uglify = require( 'gulp-uglify' ),
	util = require( 'gulp-util' ),
	watch = require( 'gulp-watch' ),
	watchify = require( 'watchify' );

/**
 * Generic error handler used by plumber
 *
 * Display an OS notification and sound with error message
 */
var onError = function( err ) {
	if ( err.lineNumber ) {
		util.log( util.colors.red( 'Error: (Line: ' + err.lineNumber + ') ' + err.message ) );
	} else {
		util.log( util.colors.red( 'Error: ' + err.message ) );
	}
	this.emit( 'end' );
};

/**
 * Default Task
 *
 * Watch for changes and run tasks
 */
gulp.task( 'default', function() {
	// Install
	gulp.start( 'install' );

	// Compile Styles on start
	gulp.start( 'styles' );

	// Process Images on start
	gulp.start( 'images' );

	// Process react on start
	gulp.start( 'react' );

	// Browsersync and local server
	// Options: http://www.browsersync.io/docs/options/
	if ( settings.staticserver ) {
		browsersync( {
			server: settings.basepath
		} );

		// Check to see if the CSS is being used
		if ( settings.checkunusedcss ) {
			gulp.watch( settings.css, ['checkcss'] );
		}
	}

	if ( settings.proxyserver ) {
		browsersync( {
			proxy: settings.proxylocation
		} );
	}

	// Watch for SCSS changes
	gulp.watch( settings.scsswatch, ['styles'] );

	// Watch for image changes
	gulp.watch( settings.imagespath, ['images'] );

	// Watch for HTML changes
	gulp.watch( settings.htmlpath, ['markup'] );

	// Watch for react components
	gulp.watch( settings.componentpath, ['react'] );
} );

/**
 * Install Task
 * Ensure our packages are upto date
 */
gulp.task( 'install', function() {
	gulp.src( ['./package.json'] )
		.pipe( install() );
} );

/**
 * Stylesheet Task
 *
 * SCSS -> CSS
 * Autoprefix
 * CSSComb
 * Sourcemaps
 * Minify
 * Report
 */
gulp.task( 'styles', function() {
	return gulp.src( settings.scss )
		.pipe( plumber( {errorHandler: onError} ) )
		.pipe( sass( {
			style: 'expanded',
			errLogToConsole: false
		} ) )
		.pipe( sourcemaps.init() )
		.pipe( autoprefixer( 'last 2 versions', 'ie 8', 'ie 9' ) )
		.pipe( csscomb() )
		.pipe( sourcemaps.write( './' ) )
		.pipe( minifycss() )
		.pipe( gulp.dest( settings.csspath ) )
		.pipe( filter( '**/*.css' ) )
		.pipe( browsersync.reload( {stream: true} ) )
		.pipe( parker() );
} );

/**
 * React Tast
 *
 * Compile JSX etc
 */
var reactopts = {
	entries: [settings.js],
	debug: true,
	extensions: ['.jsx']
};
var opts = assign( {}, watchify.args, reactopts );
var b = watchify( browserify( opts ) );
b.transform( reactify );
 //b.on('log', util.log); // output build logs to terminal

gulp.task( 'react', ['lint', 'set-node-env'], function() {
	return b.bundle()
		.on( 'error', onError )
		.pipe( source( 'vip-dashboard.js' ) )
		.pipe( buffer() )
		.pipe( sourcemaps.init( {loadMaps: true} ) )
		.pipe( uglify() )
		.pipe( sourcemaps.write( './' ) )
		.pipe( gulp.dest( settings.jspath ) )
		.pipe( browsersync.reload( {stream: true} ) );
} );

/**
 * Set env variable for production
 */
gulp.task( 'set-node-env', function() {
	return process.env.NODE_ENV = 'production';
} );

/**
 * Compress the JS
 */
gulp.task( 'compress', function() {
	return gulp.src( settings.jspath + 'vip-dashboard.js' )
		.pipe( uglify() )
		.pipe( gulp.dest( settings.jspath ) );
} );

/**
 * Lint Task
 *
 * Run before react task above to check for errors
 */
gulp.task( 'lint', function() {
	return gulp.src( settings.componentpath )
		.pipe( eslint( {
			baseConfig: {
				ecmaFeatures: {
					jsx: true
				}
			}
		} ) )
		.pipe( eslint.format( ) )
		.pipe( eslint.failAfterError( ) )
		.on( 'error', onError );
} );

/**
 * Images Task
 *
 * Run independantly when you want to optimise image assets
 */
gulp.task( 'images', function() {
	return gulp.src( settings.imagespath + '**/*.{gif,jpg,png}' )
		.pipe( plumber( {errorHandler: onError} ) )
		.pipe( imagemin( {
			progressive: true,
			interlaced: true,
			//svgoPlugins: [ {removeViewBox:false}, {removeUselessStrokeAndFill:false} ]
		} ) )
		.pipe( gulp.dest( settings.imagesdistpath ) )
		.pipe( browsersync.reload( {stream: true} ) );
} );

/**
 * CheckCSS Task
 *
 * Are all our styles being used correctly?
 */
gulp.task( 'checkcss', function() {
	return gulp.src( [ settings.css, settings.staticlocation + '*.html' ] )
		.pipe( plumber( {errorHandler: onError} ) )
		.pipe( checkcss() );
} );

/**
 * Reload HTML files
 *
 * If modified, refreshes HTML files
 */
gulp.task( 'markup', function() {
	return gulp.src( settings.htmlpath )
		.pipe( browsersync.reload( {stream: true} ) );
} );
