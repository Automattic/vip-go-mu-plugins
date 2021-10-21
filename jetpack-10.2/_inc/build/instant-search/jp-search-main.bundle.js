/* eslint-disable */
/******/ (function() { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ 9379:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "Bk": function() { return /* binding */ MULTISITE_NO_GROUP_VALUE; },
/* harmony export */   "W1": function() { return /* binding */ SERVER_OBJECT_NAME; },
/* harmony export */   "zg": function() { return /* binding */ OVERLAY_CLASS_NAME; },
/* harmony export */   "oy": function() { return /* binding */ SORT_DIRECTION_ASC; },
/* harmony export */   "Pz": function() { return /* binding */ RESULT_FORMAT_EXPANDED; },
/* harmony export */   "LI": function() { return /* binding */ RESULT_FORMAT_PRODUCT; },
/* harmony export */   "AG": function() { return /* binding */ MINUTE_IN_MILLISECONDS; },
/* harmony export */   "PP": function() { return /* binding */ RELEVANCE_SORT_KEY; },
/* harmony export */   "kQ": function() { return /* binding */ VALID_SORT_KEYS; },
/* harmony export */   "bk": function() { return /* binding */ VALID_RESULT_FORMAT_KEYS; },
/* harmony export */   "aP": function() { return /* binding */ SORT_OPTIONS; },
/* harmony export */   "rs": function() { return /* binding */ PRODUCT_SORT_OPTIONS; }
/* harmony export */ });
/* unused harmony exports SORT_DIRECTION_DESC, RESULT_FORMAT_MINIMAL */
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3163);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/**
 * External dependencies
 */

const alias__ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__;
const __ = alias__;
const MULTISITE_NO_GROUP_VALUE = '__NO_GROUP__';
const SERVER_OBJECT_NAME = 'JetpackInstantSearchOptions';
const OVERLAY_CLASS_NAME = 'jetpack-instant-search__overlay';
const SORT_DIRECTION_ASC = 'ASC';
const SORT_DIRECTION_DESC = 'DESC';
const RESULT_FORMAT_EXPANDED = 'expanded';
const RESULT_FORMAT_MINIMAL = 'minimal';
const RESULT_FORMAT_PRODUCT = 'product';
const MINUTE_IN_MILLISECONDS = 60 * 1000;
const RELEVANCE_SORT_KEY = 'relevance'; // @todo extract this to a function that uses SORT_OPTIONS and PRODUCT_SORT_OPTIONS to avoid duplication

const VALID_SORT_KEYS = ['newest', 'oldest', RELEVANCE_SORT_KEY, 'price_asc', 'price_desc', 'rating_desc'];
const VALID_RESULT_FORMAT_KEYS = [RESULT_FORMAT_EXPANDED, RESULT_FORMAT_MINIMAL, RESULT_FORMAT_PRODUCT];
const SORT_OPTIONS = new Map([[RELEVANCE_SORT_KEY, __('Relevance', 'jetpack')], ['newest', __('Newest', 'jetpack')], ['oldest', __('Oldest', 'jetpack')]]);
const PRODUCT_SORT_OPTIONS = new Map([['price_asc', __('Price: low to high', 'jetpack')], ['price_desc', __('Price: high to low', 'jetpack')], ['rating_desc', __('Rating', 'jetpack')]]);

/***/ }),

/***/ 4880:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "bS": function() { return /* binding */ isInCustomizer; },
/* harmony export */   "Em": function() { return /* binding */ bindCustomizerMessages; },
/* harmony export */   "vJ": function() { return /* binding */ bindCustomizerChanges; }
/* harmony export */ });
/* harmony import */ var _constants__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(9379);
/**
 * Internal dependencies
 */

const SETTINGS_TO_STATE_MAP = new Map([['jetpack_search_color_theme', 'colorTheme'], ['jetpack_search_enable_sort', 'enableSort'], ['jetpack_search_highlight_color', 'highlightColor'], ['jetpack_search_inf_scroll', 'enableInfScroll'], ['jetpack_search_overlay_trigger', 'overlayTrigger'], ['jetpack_search_show_powered_by', 'showPoweredBy'], ['jetpack_search_result_format', 'resultFormat']]);
/**
 * Detects if the current environment is within WP's Customizer.
 *
 * @returns {boolean} is in customizer.
 */

function isInCustomizer() {
  var _window, _window$wp;

  return typeof ((_window = window) === null || _window === void 0 ? void 0 : (_window$wp = _window.wp) === null || _window$wp === void 0 ? void 0 : _window$wp.customize) === 'function';
}
/**
 * Binds iframe messages from the Customizer to SearchApp.
 *
 * @param {Function} callback - function to be invoked following Jetpack Search panel expansion.
 */

function bindCustomizerMessages(callback) {
  if (!isInCustomizer()) {
    return;
  }

  window.addEventListener('message', event => {
    var _event$data;

    if (!event.data) {
      return;
    }

    if (event.target !== window || ((_event$data = event.data) === null || _event$data === void 0 ? void 0 : _event$data.key) !== 'jetpackSearchSectionOpen') {
      return;
    }

    if ('expanded' in event.data) {
      callback(event.data.expanded);
    }
  });
}
/**
 * Binds changes to Customizer controls to SearchApp state.
 *
 * @param {Function} callback - function to be invoked following Customizer changes.
 */

function bindCustomizerChanges(callback) {
  if (!isInCustomizer()) {
    return;
  }

  SETTINGS_TO_STATE_MAP.forEach((jsName, phpName) => {
    window.wp.customize(phpName, value => {
      value.bind(function (newValue) {
        const newOvelayOptions = {
          [jsName]: newValue
        }; // If Instant Search hasn't been injected, update initial server object state

        window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1].showResults = true;
        window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1].overlayOptions = { ...window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1].overlayOptions,
          ...newOvelayOptions
        }; // If callback is available, invoke it.

        callback && callback(newOvelayOptions);
      });
    });
  });
}

/***/ }),

/***/ 8959:
/***/ (function(__unused_webpack_module, __unused_webpack_exports, __webpack_require__) {

// NOTE: Setting this free variable allows us to modify Webpack's public path, enabling us to use
//       dynamic imports. Also note that we don't import any other file to ensure that this operation is
//       completed before any other module imports. See:
//       https://github.com/webpack/webpack/issues/2776#issuecomment-233208623
// eslint-disable-next-line no-undef
__webpack_require__.p = window.JetpackInstantSearchOptions.webpackPublicPath;

/***/ }),

/***/ 3163:
/***/ (function(module) {

"use strict";
module.exports = window["wp"]["i18n"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			id: moduleId,
/******/ 			loaded: false,
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Flag the module as loaded
/******/ 		module.loaded = true;
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	!function() {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = function(module) {
/******/ 			var getter = module && module.__esModule ?
/******/ 				function() { return module['default']; } :
/******/ 				function() { return module; };
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/ensure chunk */
/******/ 	!function() {
/******/ 		__webpack_require__.f = {};
/******/ 		// This file contains only the entry chunk.
/******/ 		// The chunk loading function for additional chunks
/******/ 		__webpack_require__.e = function(chunkId) {
/******/ 			return Promise.all(Object.keys(__webpack_require__.f).reduce(function(promises, key) {
/******/ 				__webpack_require__.f[key](chunkId, promises);
/******/ 				return promises;
/******/ 			}, []));
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/get javascript chunk filename */
/******/ 	!function() {
/******/ 		// This function allow to reference async chunks
/******/ 		__webpack_require__.u = function(chunkId) {
/******/ 			// return url for filenames based on template
/******/ 			return "jp-search.chunk-" + (chunkId === 161 ? "main-payload" : chunkId) + "." + {"161":"bb0b04f99d42866e0cbf","270":"5debd14194f6d84d0307"}[chunkId] + ".min.js";
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/get mini-css chunk filename */
/******/ 	!function() {
/******/ 		// This function allow to reference all chunks
/******/ 		__webpack_require__.miniCssF = function(chunkId) {
/******/ 			// return url for filenames based on template
/******/ 			return "jp-search.chunk-" + "main-payload" + "." + "0c8aded5c0164479ce63" + ".min.css";
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/get mini-css chunk filename with rtl */
/******/ 	!function() {
/******/ 		__webpack_require__.miniCssF = (
/******/ 					function(originalFn) { return function(chunkId) {
/******/ 				var isCssRtlEnabled = document.dir === 'rtl';
/******/ 				var originalUrl = originalFn(chunkId);
/******/ 				return isCssRtlEnabled ? originalUrl.replace(".css",".rtl.css") : originalUrl;
/******/ 		}; }
/******/ 				)(__webpack_require__.miniCssF)
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/global */
/******/ 	!function() {
/******/ 		__webpack_require__.g = (function() {
/******/ 			if (typeof globalThis === 'object') return globalThis;
/******/ 			try {
/******/ 				return this || new Function('return this')();
/******/ 			} catch (e) {
/******/ 				if (typeof window === 'object') return window;
/******/ 			}
/******/ 		})();
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/harmony module decorator */
/******/ 	!function() {
/******/ 		__webpack_require__.hmd = function(module) {
/******/ 			module = Object.create(module);
/******/ 			if (!module.children) module.children = [];
/******/ 			Object.defineProperty(module, 'exports', {
/******/ 				enumerable: true,
/******/ 				set: function() {
/******/ 					throw new Error('ES Modules may not assign module.exports or exports.*, Use ESM export syntax, instead: ' + module.id);
/******/ 				}
/******/ 			});
/******/ 			return module;
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/load script */
/******/ 	!function() {
/******/ 		var inProgress = {};
/******/ 		var dataWebpackPrefix = "Jetpack:";
/******/ 		// loadScript function to load a script via script tag
/******/ 		__webpack_require__.l = function(url, done, key, chunkId) {
/******/ 			if(inProgress[url]) { inProgress[url].push(done); return; }
/******/ 			var script, needAttach;
/******/ 			if(key !== undefined) {
/******/ 				var scripts = document.getElementsByTagName("script");
/******/ 				for(var i = 0; i < scripts.length; i++) {
/******/ 					var s = scripts[i];
/******/ 					if(s.getAttribute("src") == url || s.getAttribute("data-webpack") == dataWebpackPrefix + key) { script = s; break; }
/******/ 				}
/******/ 			}
/******/ 			if(!script) {
/******/ 				needAttach = true;
/******/ 				script = document.createElement('script');
/******/ 		
/******/ 				script.charset = 'utf-8';
/******/ 				script.timeout = 120;
/******/ 				if (__webpack_require__.nc) {
/******/ 					script.setAttribute("nonce", __webpack_require__.nc);
/******/ 				}
/******/ 				script.setAttribute("data-webpack", dataWebpackPrefix + key);
/******/ 				script.src = url;
/******/ 			}
/******/ 			inProgress[url] = [done];
/******/ 			var onScriptComplete = function(prev, event) {
/******/ 				// avoid mem leaks in IE.
/******/ 				script.onerror = script.onload = null;
/******/ 				clearTimeout(timeout);
/******/ 				var doneFns = inProgress[url];
/******/ 				delete inProgress[url];
/******/ 				script.parentNode && script.parentNode.removeChild(script);
/******/ 				doneFns && doneFns.forEach(function(fn) { return fn(event); });
/******/ 				if(prev) return prev(event);
/******/ 			}
/******/ 			;
/******/ 			var timeout = setTimeout(onScriptComplete.bind(null, undefined, { type: 'timeout', target: script }), 120000);
/******/ 			script.onerror = onScriptComplete.bind(null, script.onerror);
/******/ 			script.onload = onScriptComplete.bind(null, script.onload);
/******/ 			needAttach && document.head.appendChild(script);
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	!function() {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = function(exports) {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/publicPath */
/******/ 	!function() {
/******/ 		var scriptUrl;
/******/ 		if (__webpack_require__.g.importScripts) scriptUrl = __webpack_require__.g.location + "";
/******/ 		var document = __webpack_require__.g.document;
/******/ 		if (!scriptUrl && document) {
/******/ 			if (document.currentScript)
/******/ 				scriptUrl = document.currentScript.src
/******/ 			if (!scriptUrl) {
/******/ 				var scripts = document.getElementsByTagName("script");
/******/ 				if(scripts.length) scriptUrl = scripts[scripts.length - 1].src
/******/ 			}
/******/ 		}
/******/ 		// When supporting browsers where an automatic publicPath is not supported you must specify an output.publicPath manually via configuration
/******/ 		// or pass an empty string ("") and set the __webpack_public_path__ variable from your code to use your own logic.
/******/ 		if (!scriptUrl) throw new Error("Automatic publicPath is not supported in this browser");
/******/ 		scriptUrl = scriptUrl.replace(/#.*$/, "").replace(/\?.*$/, "").replace(/\/[^\/]+$/, "/");
/******/ 		__webpack_require__.p = scriptUrl;
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/css loading */
/******/ 	!function() {
/******/ 		var createStylesheet = function(chunkId, fullhref, resolve, reject) {
/******/ 			var linkTag = document.createElement("link");
/******/ 			linkTag.setAttribute("data-webpack", true);
/******/ 			linkTag.rel = "stylesheet";
/******/ 			linkTag.type = "text/css";
/******/ 			var onLinkComplete = function(event) {
/******/ 				// avoid mem leaks.
/******/ 				linkTag.onerror = linkTag.onload = null;
/******/ 				if (event.type === 'load') {
/******/ 					resolve();
/******/ 				} else {
/******/ 					var errorType = event && (event.type === 'load' ? 'missing' : event.type);
/******/ 					var realHref = event && event.target && event.target.href || fullhref;
/******/ 					var err = new Error("Loading CSS chunk " + chunkId + " failed.\n(" + realHref + ")");
/******/ 					err.code = "CSS_CHUNK_LOAD_FAILED";
/******/ 					err.type = errorType;
/******/ 					err.request = realHref;
/******/ 					linkTag.parentNode.removeChild(linkTag)
/******/ 					reject(err);
/******/ 				}
/******/ 			}
/******/ 			linkTag.onerror = linkTag.onload = onLinkComplete;
/******/ 			linkTag.href = fullhref;
/******/ 		
/******/ 			document.head.appendChild(linkTag);
/******/ 			return linkTag;
/******/ 		};
/******/ 		var findStylesheet = function(href, fullhref) {
/******/ 			var existingLinkTags = document.getElementsByTagName("link");
/******/ 			for(var i = 0; i < existingLinkTags.length; i++) {
/******/ 				var tag = existingLinkTags[i];
/******/ 				var dataHref = tag.getAttribute("data-href") || tag.getAttribute("href");
/******/ 				if(tag.rel === "stylesheet" && (dataHref === href || dataHref === fullhref)) return tag;
/******/ 			}
/******/ 			var existingStyleTags = document.getElementsByTagName("style");
/******/ 			for(var i = 0; i < existingStyleTags.length; i++) {
/******/ 				var tag = existingStyleTags[i];
/******/ 				var dataHref = tag.getAttribute("data-href");
/******/ 				if(dataHref === href || dataHref === fullhref) return tag;
/******/ 			}
/******/ 		};
/******/ 		var loadStylesheet = function(chunkId) {
/******/ 			return new Promise(function(resolve, reject) {
/******/ 				var href = __webpack_require__.miniCssF(chunkId);
/******/ 				var fullhref = __webpack_require__.p + href;
/******/ 				if(findStylesheet(href, fullhref)) return resolve();
/******/ 				createStylesheet(chunkId, fullhref, resolve, reject);
/******/ 			});
/******/ 		}
/******/ 		// object to store loaded CSS chunks
/******/ 		var installedCssChunks = {
/******/ 			179: 0
/******/ 		};
/******/ 		
/******/ 		__webpack_require__.f.miniCss = function(chunkId, promises) {
/******/ 			var cssChunks = {"161":1};
/******/ 			if(installedCssChunks[chunkId]) promises.push(installedCssChunks[chunkId]);
/******/ 			else if(installedCssChunks[chunkId] !== 0 && cssChunks[chunkId]) {
/******/ 				promises.push(installedCssChunks[chunkId] = loadStylesheet(chunkId).then(function() {
/******/ 					installedCssChunks[chunkId] = 0;
/******/ 				}, function(e) {
/******/ 					delete installedCssChunks[chunkId];
/******/ 					throw e;
/******/ 				}));
/******/ 			}
/******/ 		};
/******/ 		
/******/ 		// no hmr
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	!function() {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			179: 0
/******/ 		};
/******/ 		
/******/ 		__webpack_require__.f.j = function(chunkId, promises) {
/******/ 				// JSONP chunk loading for javascript
/******/ 				var installedChunkData = __webpack_require__.o(installedChunks, chunkId) ? installedChunks[chunkId] : undefined;
/******/ 				if(installedChunkData !== 0) { // 0 means "already installed".
/******/ 		
/******/ 					// a Promise means "currently loading".
/******/ 					if(installedChunkData) {
/******/ 						promises.push(installedChunkData[2]);
/******/ 					} else {
/******/ 						if(true) { // all chunks have JS
/******/ 							// setup Promise in chunk cache
/******/ 							var promise = new Promise(function(resolve, reject) { installedChunkData = installedChunks[chunkId] = [resolve, reject]; });
/******/ 							promises.push(installedChunkData[2] = promise);
/******/ 		
/******/ 							// start chunk loading
/******/ 							var url = __webpack_require__.p + __webpack_require__.u(chunkId);
/******/ 							// create error before stack unwound to get useful stacktrace later
/******/ 							var error = new Error();
/******/ 							var loadingEnded = function(event) {
/******/ 								if(__webpack_require__.o(installedChunks, chunkId)) {
/******/ 									installedChunkData = installedChunks[chunkId];
/******/ 									if(installedChunkData !== 0) installedChunks[chunkId] = undefined;
/******/ 									if(installedChunkData) {
/******/ 										var errorType = event && (event.type === 'load' ? 'missing' : event.type);
/******/ 										var realSrc = event && event.target && event.target.src;
/******/ 										error.message = 'Loading chunk ' + chunkId + ' failed.\n(' + errorType + ': ' + realSrc + ')';
/******/ 										error.name = 'ChunkLoadError';
/******/ 										error.type = errorType;
/******/ 										error.request = realSrc;
/******/ 										installedChunkData[1](error);
/******/ 									}
/******/ 								}
/******/ 							};
/******/ 							__webpack_require__.l(url, loadingEnded, "chunk-" + chunkId, chunkId);
/******/ 						} else installedChunks[chunkId] = 0;
/******/ 					}
/******/ 				}
/******/ 		};
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		// no on chunks loaded
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = function(parentChunkLoadingFunction, data) {
/******/ 			var chunkIds = data[0];
/******/ 			var moreModules = data[1];
/******/ 			var runtime = data[2];
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some(function(id) { return installedChunks[id] !== 0; })) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkIds[i]] = 0;
/******/ 			}
/******/ 		
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = self["webpackChunkwebpack"] = self["webpackChunkwebpack"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	}();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be in strict mode.
!function() {
"use strict";
/* harmony import */ var _set_webpack_public_path__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(8959);
/* harmony import */ var _set_webpack_public_path__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_set_webpack_public_path__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _lib_constants__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(9379);
/* harmony import */ var _lib_customize__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(4880);
// NOTE: This must be imported first before any other imports.
// See: https://github.com/webpack/webpack/issues/2776#issuecomment-233208623

/**
 * Internal dependencies
 */



/**
 * Loads and runs the main chunk for Instant Search.
 */

function init() {
  Promise.all(/* import() | main-payload */[__webpack_require__.e(270), __webpack_require__.e(161)]).then(__webpack_require__.bind(__webpack_require__, 5298)).then(instantSearch => instantSearch.initialize());
} // Bind customizer changes immediately.


if (window[_lib_constants__WEBPACK_IMPORTED_MODULE_1__/* .SERVER_OBJECT_NAME */ .W1]) {
  (0,_lib_customize__WEBPACK_IMPORTED_MODULE_2__/* .bindCustomizerChanges */ .vJ)();
} // Initialize Instant Search when DOMContentLoaded is fired, or immediately if it already has been.


if (document.readyState !== 'loading') {
  init();
} else {
  document.addEventListener('DOMContentLoaded', init);
}
}();
/******/ })()
;