const { resolve, join } = require( 'path' );
const webpack = require( 'webpack' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const CssMinimizerPlugin = require( 'css-minimizer-webpack-plugin' );
const HtmlWebpackPlugin = require( 'html-webpack-plugin' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );

module.exports = function( env ) {
	const dev = ( env.WEBPACK_WATCH || env.WEBPACK_SERVE ) && ! env.production;

	process.env.NODE_ENV = dev ? 'development' : 'production';

	return {
		context: resolve( __dirname ),
		mode: dev ? 'development' : 'production',
		devtool: dev ? 'eval' : false,
		node: {
			global: true,
		},
		entry: {
			bundle: './src/index.js',
		},
		output: {
			path: resolve( join( __dirname, 'build' ) ),
			publicPath: '/',
			filename: '[name].js',
			chunkFilename: '[name].chunk.js',
			assetModuleFilename: '[file][query]',
		},
		devServer: {
			headers: {
				'Access-Control-Allow-Origin': '*',
			},
			compress: true,
			port: +process.env.PORT || 8080,
			historyApiFallback: true,
			devMiddleware: {
				writeToDisk: true,
			},
		},
		resolve: {
			alias: {
				react: 'preact/compat',
				'react-dom': 'preact/compat',
				'preact-compat': 'preact/compat',
			},
			extensions: [
				'.mjs', '.js', '.jsx',
			],
		},
		module: {
			rules: [
				{
					enforce: 'pre',
					test: /\.m?jsx?$/,
					exclude: /node_modules/u,
					resolve: { mainFields: [ 'module', 'jsnext:main', 'browser', 'main' ] },
					type: 'javascript/auto',
					loader: 'babel-loader',
				},
				{
					enforce: 'pre',
					test: /\.s[ac]ss$/,
					use: [
						{
							loader: 'sass-loader',
							options: {
								sourceMap: true,
								additionalData: '\n\t\t\t\t@import "./src/style/mixins.scss";\n\t\t\t\t',
							},
						},
					],
				},
				{
					test: /\.s[ac]ss$/,
					include: resolve( join( __dirname, 'src', 'components' ) ),
					use: [
						dev ? 'style-loader' : MiniCssExtractPlugin.loader,
						{
							loader: 'css-loader',
							options: {
								modules: {
									localIdentName: '[local]__[hash:base64:5]',
								},
								importLoaders: 1,
								sourceMap: true,
							},
						},
						{
							loader: 'postcss-loader',
							options: {
								sourceMap: true,
								postcssOptions: {
									config: resolve( __dirname ),
								},
							},
						},
					],
				},
				{
					test: /\.s[ac]ss$/,
					exclude: resolve( join( __dirname, 'src', 'components' ) ),
					use: [
						dev ? 'style-loader' : MiniCssExtractPlugin.loader,
						{
							loader: 'css-loader',
							options: {
								sourceMap: true,
							},
						},
						{
							loader: 'postcss-loader',
							options: {
								sourceMap: true,
								postcssOptions: {
									config: resolve( __dirname ),
								},
							},
						},
					],
					sideEffects: true,
				},
				{
					test: /\.(svg)(\?.*)?$/i,
					type: 'asset/inline',
				},
			],
		},
		plugins: [
			new CleanWebpackPlugin(),
			new webpack.DefinePlugin( {
				'process.env.NODE_ENV': JSON.stringify( dev ? 'development' : 'production' ),
			} ),
			new webpack.ProvidePlugin( {
				// eslint-disable-next-line id-length
				h: [ 'preact', 'h' ],
				Fragment: [ 'preact', 'Fragment' ],
			} ),
			new MiniCssExtractPlugin( {
				filename: '[name].css',
				chunkFilename: '[name].chunk.css',
			} ),
			new HtmlWebpackPlugin( {
				filename: 'index.html',
				template: './src/template.html',
			} ),
		],
		performance: {
			hints: 'warning',
			maxAssetSize: dev || env.WEBPACK_SERVE ? Infinity : 200000,
			maxEntrypointSize: dev || env.WEBPACK_SERVE ? Infinity : 200000,
		},
		optimization: {
			moduleIds: 'deterministic',
			minimize: true,
			realContentHash: true,
			minimizer: dev ? [] : [
				new TerserPlugin( {
					parallel: true,
					terserOptions: {
						output: {
							comments: false,
						},
						sourceMap: true,
						mangle: true,
						compress: {
							keep_fargs: false,
							pure_getters: true,
							hoist_funs: true,
							pure_funcs: [
								'classCallCheck',
								'_classCallCheck',
								'_possibleConstructorReturn',
								'Object.freeze',
								'invariant',
								'warning',
							],
						},
					},
					extractComments: false,
				} ),
				new CssMinimizerPlugin(),
			],
		},
	};
};
