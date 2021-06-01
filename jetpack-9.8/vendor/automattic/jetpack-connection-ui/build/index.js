(function(e, a) { for(var i in a) e[i] = a[i]; }(window, /******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = "./_inc/admin.jsx");
/******/ })
/************************************************************************/
/******/ ({

/***/ "../../js-packages/connection/components/in-place-connection/index.jsx":
/*!******************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/in-place-connection/index.jsx ***!
  \******************************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! prop-types */ "../../js-packages/connection/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./style.scss */ "../../js-packages/connection/components/in-place-connection/style.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_style_scss__WEBPACK_IMPORTED_MODULE_3__);
/**
 * External dependencies
 */



/**
 * Internal dependencies
 */


/**
 * The in-place connection component.
 *
 * @param {object} props -- The properties.
 * @param {string} props.title -- Element title.
 * @param {boolean} props.isLoading -- Whether the element is still loading.
 * @param {string|number} props.width -- Iframe width.
 * @param {string|number} props.height -- Iframe height.
 * @param {boolean} props.displayTOS -- Whether the site has connection owner connected.
 * @param {boolean} props.scrollToIframe -- Whether we need to auto-scroll the window upon element rendering.
 * @param {string} props.connectUrl -- The connection URL.
 * @param {Function} props.onComplete -- The callback to be called upon complete of the connection process.
 * @param {Function} props.onThirdPartyCookiesBlocked -- The callback to be called if third-party cookies are disabled.
 * @param {string} props.location -- Component location identifier passed to WP.com.
 *
 * @returns {React.Component} The in-place connection component.
 */

var InPlaceConnection = function InPlaceConnection(props) {
  var title = props.title,
      isLoading = props.isLoading,
      width = props.width,
      displayTOS = props.displayTOS,
      scrollToIframe = props.scrollToIframe,
      connectUrl = props.connectUrl,
      onComplete = props.onComplete,
      onThirdPartyCookiesBlocked = props.onThirdPartyCookiesBlocked,
      location = props.location;
  var height = props.height;
  var iframeWrapRef = Object(react__WEBPACK_IMPORTED_MODULE_0__["useRef"])();
  var iframeRef = Object(react__WEBPACK_IMPORTED_MODULE_0__["useRef"])();
  /**
   * Handles messages received from inside the iframe.
   *
   * @param {object} e -- Event object.
   */

  var receiveData = function receiveData(e) {
    if (e.source !== iframeRef.current.contentWindow) {
      return;
    }

    switch (e.data) {
      case 'close':
        // Remove listener, our job here is done.
        window.removeEventListener('message', receiveData);

        if (onComplete) {
          onComplete();
        }

        break;

      case 'wpcom_nocookie':
        // Third-party cookies blocked.
        if (onThirdPartyCookiesBlocked) {
          onThirdPartyCookiesBlocked();
        }

        break;
    }
  };

  Object(react__WEBPACK_IMPORTED_MODULE_0__["useEffect"])(
  /**
   * The component initialization.
   */
  function () {
    // Scroll to the iframe container
    if (scrollToIframe) {
      window.scrollTo(0, iframeWrapRef.current.offsetTop - 10);
    } // Add an event listener to identify successful authorization via iframe.


    window.addEventListener('message', receiveData);
  }); // The URL looks like https://jetpack.wordpress.com/jetpack.authorize_iframe/1/. We need to include the trailing
  // slash below so that we don't end up with something like /jetpack.authorize_iframe_iframe/

  var src = connectUrl.replace('authorize/', 'authorize_iframe/');

  if (!src.includes('?')) {
    src += '?';
  }

  if (displayTOS) {
    src += '&display-tos';
    height = (parseInt(height) + 50).toString();
  }

  src += '&iframe_height=' + parseInt(height);

  if (location) {
    src += '&iframe_source=' + location;
  }

  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("div", {
    className: "dops-card fade-in jp-iframe-wrap",
    ref: iframeWrapRef
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("h1", null, title), isLoading ? /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("p", null, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__["__"])('Loading…', 'jetpack')) : /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("iframe", {
    title: title,
    width: width,
    height: height,
    src: src,
    ref: iframeRef
  }));
};

InPlaceConnection.propTypes = {
  title: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string.isRequired,
  isLoading: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.bool,
  width: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string,
  height: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string,
  connectUrl: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string.isRequired,
  displayTOS: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.bool.isRequired,
  scrollToIframe: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.bool,
  onComplete: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.func,
  onThirdPartyCookiesBlocked: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.func,
  location: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string
};
InPlaceConnection.defaultProps = {
  isLoading: false,
  height: '220',
  width: '100%',
  scrollToIframe: false
};
/* harmony default export */ __webpack_exports__["default"] = (InPlaceConnection);

/***/ }),

/***/ "../../js-packages/connection/components/in-place-connection/style.scss":
/*!*******************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/in-place-connection/style.scss ***!
  \*******************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ "../../js-packages/connection/components/main/index.jsx":
/*!***************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/main/index.jsx ***!
  \***************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/slicedToArray */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/slicedToArray.js");
/* harmony import */ var _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! prop-types */ "../../js-packages/connection/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _in_place_connection__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../in-place-connection */ "../../js-packages/connection/components/in-place-connection/index.jsx");
/* harmony import */ var _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../tools/jetpack-rest-api-client */ "../../js-packages/connection/tools/jetpack-rest-api-client/index.jsx");


/**
 * External dependencies
 */




/**
 * Internal dependencies
 */



/**
 * The in-place connection component.
 *
 * @param {object} props -- The properties.
 * @param {string} props.authorizationUrl -- The authorization URL.
 * @param {string} props.connectLabel -- The "Connect" button label.
 * @param {string} props.inPlaceTitle -- The title for the In-Place Connection component.
 * @param {boolean} props.forceCalypsoFlow -- Whether to go straight to Calypso flow, skipping the In-Place flow.
 * @param {string} props.apiRoot -- API root URL, required.
 * @param {string} props.apiNonce -- API Nonce, required.
 * @param {string} props.registrationNonce -- Separate registration nonce, required.
 * @param {boolean} props.isRegistered -- Whether the site is registered (has blog token), required.
 * @param {boolean} props.isUserConnected -- Whether the current user is connected (has user token), required.
 * @param {boolean} props.hasConnectedOwner -- Whether the site has connection owner, required.
 * @param {Function} props.onRegistered -- The callback to be called upon registration success.
 * @param {Function} props.onUserConnected -- The callback to be called when the connection is fully established.
 * @param {Function} props.redirectFunc -- The redirect function (`window.location.assign()` by default).
 *
 * @returns {React.Component} The in-place connection component.
 */

var Main = function Main(props) {
  var _useState = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(false),
      _useState2 = _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState, 2),
      isRegistering = _useState2[0],
      setIsRegistering = _useState2[1];

  var _useState3 = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(false),
      _useState4 = _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState3, 2),
      isUserConnecting = _useState4[0],
      setIsUserConnecting = _useState4[1];

  var apiRoot = props.apiRoot,
      apiNonce = props.apiNonce,
      connectLabel = props.connectLabel,
      authorizationUrl = props.authorizationUrl,
      forceCalypsoFlow = props.forceCalypsoFlow,
      isRegistered = props.isRegistered,
      isUserConnected = props.isUserConnected,
      onRegistered = props.onRegistered,
      onUserConnected = props.onUserConnected,
      registrationNonce = props.registrationNonce,
      redirectFunc = props.redirectFunc,
      from = props.from,
      redirectUri = props.redirectUri;
  /**
   * Initialize the REST API.
   */

  Object(react__WEBPACK_IMPORTED_MODULE_1__["useEffect"])(function () {
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_6__["default"].setApiRoot(apiRoot);
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_6__["default"].setApiNonce(apiNonce);
  }, [apiRoot, apiNonce]);
  /**
   * Initialize the user connection process.
   */

  var connectUser = Object(react__WEBPACK_IMPORTED_MODULE_1__["useCallback"])(function (url) {
    url = url || authorizationUrl;

    if (!url.includes('?')) {
      url += '?';
    }

    if (from) {
      url += '&from=' + encodeURIComponent(from);
    }

    if (!url) {
      throw new Error('Authorization URL is required');
    }

    if (forceCalypsoFlow) {
      redirectFunc(url);
      return;
    }

    setIsUserConnecting(true);
  }, [authorizationUrl, forceCalypsoFlow, setIsUserConnecting, redirectFunc, from]);
  /**
   * Callback for the user connection success.
   */

  var onUserConnectedCallback = Object(react__WEBPACK_IMPORTED_MODULE_1__["useCallback"])(function () {
    setIsUserConnecting(false);

    if (onUserConnected) {
      onUserConnected();
    }
  }, [setIsUserConnecting, onUserConnected]);
  /**
   * Initialize the site registration process.
   */

  var registerSite = Object(react__WEBPACK_IMPORTED_MODULE_1__["useCallback"])(function (e) {
    e && e.preventDefault();

    if (isRegistered) {
      connectUser();
      return;
    }

    setIsRegistering(true);
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_6__["default"].registerSite(registrationNonce, redirectUri).then(function (response) {
      setIsRegistering(false);

      if (onRegistered) {
        onRegistered(response);
      }

      connectUser(response.authorizeUrl);
    }).catch(function (error) {
      throw error;
    });
  }, [setIsRegistering, isRegistered, onRegistered, connectUser, registrationNonce, redirectUri]);

  if (isRegistered && isUserConnected) {
    return null;
  }

  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-connection-main"
  }, !isUserConnecting && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__["Button"], {
    label: connectLabel,
    onClick: registerSite,
    isPrimary: true,
    disabled: isRegistering || isUserConnecting
  }, connectLabel), isUserConnecting && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_in_place_connection__WEBPACK_IMPORTED_MODULE_5__["default"], {
    connectUrl: authorizationUrl,
    title: props.inPlaceTitle,
    onComplete: onUserConnectedCallback,
    displayTOS: props.hasConnectedOwner || isRegistered
  }));
};

Main.propTypes = {
  authorizationUrl: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string.isRequired,
  connectLabel: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string,
  inPlaceTitle: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string,
  forceCalypsoFlow: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.bool,
  apiRoot: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string.isRequired,
  apiNonce: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string.isRequired,
  isRegistered: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.bool.isRequired,
  isUserConnected: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.bool.isRequired,
  hasConnectedOwner: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.bool.isRequired,
  onRegistered: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.func,
  onUserConnected: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.func,
  registrationNonce: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string.isRequired,
  redirectFunc: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.func,
  from: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string,
  redirectUri: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string
};
Main.defaultProps = {
  inPlaceTitle: Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__["__"])('Connect your WordPress.com account', 'jetpack'),
  forceCalypsoFlow: false,
  connectLabel: Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__["__"])('Connect', 'jetpack'),
  redirectFunc: function redirectFunc(url) {
    return window.location.assign(url);
  }
};
/* harmony default export */ __webpack_exports__["default"] = (Main);

/***/ }),

/***/ "../../js-packages/connection/helpers/third-party-cookies-fallback.jsx":
/*!******************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/helpers/third-party-cookies-fallback.jsx ***!
  \******************************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/**
 * Performs the fallback redirect if third-party cookies are not available.
 *
 * @param {string} fallbackURL -- The fallback URL.
 */
var thirdPartyCookiesFallback = function thirdPartyCookiesFallback(fallbackURL) {
  window.location.replace(fallbackURL);
};

/* harmony default export */ __webpack_exports__["default"] = (thirdPartyCookiesFallback);

/***/ }),

/***/ "../../js-packages/connection/index.jsx":
/*!***********************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/index.jsx ***!
  \***********************************************************************************/
/*! exports provided: JetpackConnection, InPlaceConnection, thirdPartyCookiesFallbackHelper */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _components_main__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./components/main */ "../../js-packages/connection/components/main/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "JetpackConnection", function() { return _components_main__WEBPACK_IMPORTED_MODULE_0__["default"]; });

/* harmony import */ var _components_in_place_connection__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./components/in-place-connection */ "../../js-packages/connection/components/in-place-connection/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "InPlaceConnection", function() { return _components_in_place_connection__WEBPACK_IMPORTED_MODULE_1__["default"]; });

/* harmony import */ var _helpers_third_party_cookies_fallback__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./helpers/third-party-cookies-fallback */ "../../js-packages/connection/helpers/third-party-cookies-fallback.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "thirdPartyCookiesFallbackHelper", function() { return _helpers_third_party_cookies_fallback__WEBPACK_IMPORTED_MODULE_2__["default"]; });

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/**
 * Components.
 */


/**
 * Helpers.
 */



/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/arrayLikeToArray.js":
/*!*********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/arrayLikeToArray.js ***!
  \*********************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _arrayLikeToArray(arr, len) {
  if (len == null || len > arr.length) len = arr.length;

  for (var i = 0, arr2 = new Array(len); i < len; i++) {
    arr2[i] = arr[i];
  }

  return arr2;
}

module.exports = _arrayLikeToArray;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/arrayWithHoles.js":
/*!*******************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/arrayWithHoles.js ***!
  \*******************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _arrayWithHoles(arr) {
  if (Array.isArray(arr)) return arr;
}

module.exports = _arrayWithHoles;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/assertThisInitialized.js":
/*!**************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/assertThisInitialized.js ***!
  \**************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _assertThisInitialized(self) {
  if (self === void 0) {
    throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
  }

  return self;
}

module.exports = _assertThisInitialized;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/classCallCheck.js":
/*!*******************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/classCallCheck.js ***!
  \*******************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _classCallCheck(instance, Constructor) {
  if (!(instance instanceof Constructor)) {
    throw new TypeError("Cannot call a class as a function");
  }
}

module.exports = _classCallCheck;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/construct.js":
/*!**************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/construct.js ***!
  \**************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var setPrototypeOf = __webpack_require__(/*! ./setPrototypeOf.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/setPrototypeOf.js");

var isNativeReflectConstruct = __webpack_require__(/*! ./isNativeReflectConstruct.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js");

function _construct(Parent, args, Class) {
  if (isNativeReflectConstruct()) {
    module.exports = _construct = Reflect.construct;
    module.exports["default"] = module.exports, module.exports.__esModule = true;
  } else {
    module.exports = _construct = function _construct(Parent, args, Class) {
      var a = [null];
      a.push.apply(a, args);
      var Constructor = Function.bind.apply(Parent, a);
      var instance = new Constructor();
      if (Class) setPrototypeOf(instance, Class.prototype);
      return instance;
    };

    module.exports["default"] = module.exports, module.exports.__esModule = true;
  }

  return _construct.apply(null, arguments);
}

module.exports = _construct;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/createSuper.js":
/*!****************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/createSuper.js ***!
  \****************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var getPrototypeOf = __webpack_require__(/*! ./getPrototypeOf.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/getPrototypeOf.js");

var isNativeReflectConstruct = __webpack_require__(/*! ./isNativeReflectConstruct.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js");

var possibleConstructorReturn = __webpack_require__(/*! ./possibleConstructorReturn.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/possibleConstructorReturn.js");

function _createSuper(Derived) {
  var hasNativeReflectConstruct = isNativeReflectConstruct();
  return function _createSuperInternal() {
    var Super = getPrototypeOf(Derived),
        result;

    if (hasNativeReflectConstruct) {
      var NewTarget = getPrototypeOf(this).constructor;
      result = Reflect.construct(Super, arguments, NewTarget);
    } else {
      result = Super.apply(this, arguments);
    }

    return possibleConstructorReturn(this, result);
  };
}

module.exports = _createSuper;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/getPrototypeOf.js":
/*!*******************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/getPrototypeOf.js ***!
  \*******************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _getPrototypeOf(o) {
  module.exports = _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf : function _getPrototypeOf(o) {
    return o.__proto__ || Object.getPrototypeOf(o);
  };
  module.exports["default"] = module.exports, module.exports.__esModule = true;
  return _getPrototypeOf(o);
}

module.exports = _getPrototypeOf;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/inherits.js":
/*!*************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/inherits.js ***!
  \*************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var setPrototypeOf = __webpack_require__(/*! ./setPrototypeOf.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/setPrototypeOf.js");

function _inherits(subClass, superClass) {
  if (typeof superClass !== "function" && superClass !== null) {
    throw new TypeError("Super expression must either be null or a function");
  }

  subClass.prototype = Object.create(superClass && superClass.prototype, {
    constructor: {
      value: subClass,
      writable: true,
      configurable: true
    }
  });
  if (superClass) setPrototypeOf(subClass, superClass);
}

module.exports = _inherits;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/isNativeFunction.js":
/*!*********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/isNativeFunction.js ***!
  \*********************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _isNativeFunction(fn) {
  return Function.toString.call(fn).indexOf("[native code]") !== -1;
}

module.exports = _isNativeFunction;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js":
/*!*****************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js ***!
  \*****************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _isNativeReflectConstruct() {
  if (typeof Reflect === "undefined" || !Reflect.construct) return false;
  if (Reflect.construct.sham) return false;
  if (typeof Proxy === "function") return true;

  try {
    Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {}));
    return true;
  } catch (e) {
    return false;
  }
}

module.exports = _isNativeReflectConstruct;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/iterableToArrayLimit.js":
/*!*************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/iterableToArrayLimit.js ***!
  \*************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _iterableToArrayLimit(arr, i) {
  var _i = arr && (typeof Symbol !== "undefined" && arr[Symbol.iterator] || arr["@@iterator"]);

  if (_i == null) return;
  var _arr = [];
  var _n = true;
  var _d = false;

  var _s, _e;

  try {
    for (_i = _i.call(arr); !(_n = (_s = _i.next()).done); _n = true) {
      _arr.push(_s.value);

      if (i && _arr.length === i) break;
    }
  } catch (err) {
    _d = true;
    _e = err;
  } finally {
    try {
      if (!_n && _i["return"] != null) _i["return"]();
    } finally {
      if (_d) throw _e;
    }
  }

  return _arr;
}

module.exports = _iterableToArrayLimit;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/nonIterableRest.js":
/*!********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/nonIterableRest.js ***!
  \********************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _nonIterableRest() {
  throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
}

module.exports = _nonIterableRest;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/possibleConstructorReturn.js":
/*!******************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/possibleConstructorReturn.js ***!
  \******************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var _typeof = __webpack_require__(/*! @babel/runtime/helpers/typeof */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/typeof.js")["default"];

var assertThisInitialized = __webpack_require__(/*! ./assertThisInitialized.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/assertThisInitialized.js");

function _possibleConstructorReturn(self, call) {
  if (call && (_typeof(call) === "object" || typeof call === "function")) {
    return call;
  }

  return assertThisInitialized(self);
}

module.exports = _possibleConstructorReturn;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/setPrototypeOf.js":
/*!*******************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/setPrototypeOf.js ***!
  \*******************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _setPrototypeOf(o, p) {
  module.exports = _setPrototypeOf = Object.setPrototypeOf || function _setPrototypeOf(o, p) {
    o.__proto__ = p;
    return o;
  };

  module.exports["default"] = module.exports, module.exports.__esModule = true;
  return _setPrototypeOf(o, p);
}

module.exports = _setPrototypeOf;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/slicedToArray.js":
/*!******************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/slicedToArray.js ***!
  \******************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var arrayWithHoles = __webpack_require__(/*! ./arrayWithHoles.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/arrayWithHoles.js");

var iterableToArrayLimit = __webpack_require__(/*! ./iterableToArrayLimit.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/iterableToArrayLimit.js");

var unsupportedIterableToArray = __webpack_require__(/*! ./unsupportedIterableToArray.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/unsupportedIterableToArray.js");

var nonIterableRest = __webpack_require__(/*! ./nonIterableRest.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/nonIterableRest.js");

function _slicedToArray(arr, i) {
  return arrayWithHoles(arr) || iterableToArrayLimit(arr, i) || unsupportedIterableToArray(arr, i) || nonIterableRest();
}

module.exports = _slicedToArray;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/typeof.js":
/*!***********************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/typeof.js ***!
  \***********************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _typeof(obj) {
  "@babel/helpers - typeof";

  if (typeof Symbol === "function" && typeof Symbol.iterator === "symbol") {
    module.exports = _typeof = function _typeof(obj) {
      return typeof obj;
    };

    module.exports["default"] = module.exports, module.exports.__esModule = true;
  } else {
    module.exports = _typeof = function _typeof(obj) {
      return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj;
    };

    module.exports["default"] = module.exports, module.exports.__esModule = true;
  }

  return _typeof(obj);
}

module.exports = _typeof;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/unsupportedIterableToArray.js":
/*!*******************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/unsupportedIterableToArray.js ***!
  \*******************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var arrayLikeToArray = __webpack_require__(/*! ./arrayLikeToArray.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/arrayLikeToArray.js");

function _unsupportedIterableToArray(o, minLen) {
  if (!o) return;
  if (typeof o === "string") return arrayLikeToArray(o, minLen);
  var n = Object.prototype.toString.call(o).slice(8, -1);
  if (n === "Object" && o.constructor) n = o.constructor.name;
  if (n === "Map" || n === "Set") return Array.from(o);
  if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return arrayLikeToArray(o, minLen);
}

module.exports = _unsupportedIterableToArray;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/@babel/runtime/helpers/wrapNativeSuper.js":
/*!********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/@babel/runtime/helpers/wrapNativeSuper.js ***!
  \********************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var getPrototypeOf = __webpack_require__(/*! ./getPrototypeOf.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/getPrototypeOf.js");

var setPrototypeOf = __webpack_require__(/*! ./setPrototypeOf.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/setPrototypeOf.js");

var isNativeFunction = __webpack_require__(/*! ./isNativeFunction.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/isNativeFunction.js");

var construct = __webpack_require__(/*! ./construct.js */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/construct.js");

function _wrapNativeSuper(Class) {
  var _cache = typeof Map === "function" ? new Map() : undefined;

  module.exports = _wrapNativeSuper = function _wrapNativeSuper(Class) {
    if (Class === null || !isNativeFunction(Class)) return Class;

    if (typeof Class !== "function") {
      throw new TypeError("Super expression must either be null or a function");
    }

    if (typeof _cache !== "undefined") {
      if (_cache.has(Class)) return _cache.get(Class);

      _cache.set(Class, Wrapper);
    }

    function Wrapper() {
      return construct(Class, arguments, getPrototypeOf(this).constructor);
    }

    Wrapper.prototype = Object.create(Class.prototype, {
      constructor: {
        value: Wrapper,
        enumerable: false,
        writable: true,
        configurable: true
      }
    });
    return setPrototypeOf(Wrapper, Class);
  };

  module.exports["default"] = module.exports, module.exports.__esModule = true;
  return _wrapNativeSuper(Class);
}

module.exports = _wrapNativeSuper;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../js-packages/connection/node_modules/object-assign/index.js":
/*!*************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/object-assign/index.js ***!
  \*************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/*
object-assign
(c) Sindre Sorhus
@license MIT
*/


/* eslint-disable no-unused-vars */
var getOwnPropertySymbols = Object.getOwnPropertySymbols;
var hasOwnProperty = Object.prototype.hasOwnProperty;
var propIsEnumerable = Object.prototype.propertyIsEnumerable;

function toObject(val) {
	if (val === null || val === undefined) {
		throw new TypeError('Object.assign cannot be called with null or undefined');
	}

	return Object(val);
}

function shouldUseNative() {
	try {
		if (!Object.assign) {
			return false;
		}

		// Detect buggy property enumeration order in older V8 versions.

		// https://bugs.chromium.org/p/v8/issues/detail?id=4118
		var test1 = new String('abc');  // eslint-disable-line no-new-wrappers
		test1[5] = 'de';
		if (Object.getOwnPropertyNames(test1)[0] === '5') {
			return false;
		}

		// https://bugs.chromium.org/p/v8/issues/detail?id=3056
		var test2 = {};
		for (var i = 0; i < 10; i++) {
			test2['_' + String.fromCharCode(i)] = i;
		}
		var order2 = Object.getOwnPropertyNames(test2).map(function (n) {
			return test2[n];
		});
		if (order2.join('') !== '0123456789') {
			return false;
		}

		// https://bugs.chromium.org/p/v8/issues/detail?id=3056
		var test3 = {};
		'abcdefghijklmnopqrst'.split('').forEach(function (letter) {
			test3[letter] = letter;
		});
		if (Object.keys(Object.assign({}, test3)).join('') !==
				'abcdefghijklmnopqrst') {
			return false;
		}

		return true;
	} catch (err) {
		// We don't expect any of the above to throw, but better to be safe.
		return false;
	}
}

module.exports = shouldUseNative() ? Object.assign : function (target, source) {
	var from;
	var to = toObject(target);
	var symbols;

	for (var s = 1; s < arguments.length; s++) {
		from = Object(arguments[s]);

		for (var key in from) {
			if (hasOwnProperty.call(from, key)) {
				to[key] = from[key];
			}
		}

		if (getOwnPropertySymbols) {
			symbols = getOwnPropertySymbols(from);
			for (var i = 0; i < symbols.length; i++) {
				if (propIsEnumerable.call(from, symbols[i])) {
					to[symbols[i]] = from[symbols[i]];
				}
			}
		}
	}

	return to;
};


/***/ }),

/***/ "../../js-packages/connection/node_modules/prop-types/checkPropTypes.js":
/*!*******************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/prop-types/checkPropTypes.js ***!
  \*******************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var printWarning = function() {};

if (undefined !== 'production') {
  var ReactPropTypesSecret = __webpack_require__(/*! ./lib/ReactPropTypesSecret */ "../../js-packages/connection/node_modules/prop-types/lib/ReactPropTypesSecret.js");
  var loggedTypeFailures = {};
  var has = Function.call.bind(Object.prototype.hasOwnProperty);

  printWarning = function(text) {
    var message = 'Warning: ' + text;
    if (typeof console !== 'undefined') {
      console.error(message);
    }
    try {
      // --- Welcome to debugging React ---
      // This error was thrown as a convenience so that you can use this stack
      // to find the callsite that caused this warning to fire.
      throw new Error(message);
    } catch (x) {}
  };
}

/**
 * Assert that the values match with the type specs.
 * Error messages are memorized and will only be shown once.
 *
 * @param {object} typeSpecs Map of name to a ReactPropType
 * @param {object} values Runtime values that need to be type-checked
 * @param {string} location e.g. "prop", "context", "child context"
 * @param {string} componentName Name of the component for error messages.
 * @param {?Function} getStack Returns the component stack.
 * @private
 */
function checkPropTypes(typeSpecs, values, location, componentName, getStack) {
  if (undefined !== 'production') {
    for (var typeSpecName in typeSpecs) {
      if (has(typeSpecs, typeSpecName)) {
        var error;
        // Prop type validation may throw. In case they do, we don't want to
        // fail the render phase where it didn't fail before. So we log it.
        // After these have been cleaned up, we'll let them throw.
        try {
          // This is intentionally an invariant that gets caught. It's the same
          // behavior as without this statement except with a better message.
          if (typeof typeSpecs[typeSpecName] !== 'function') {
            var err = Error(
              (componentName || 'React class') + ': ' + location + ' type `' + typeSpecName + '` is invalid; ' +
              'it must be a function, usually from the `prop-types` package, but received `' + typeof typeSpecs[typeSpecName] + '`.'
            );
            err.name = 'Invariant Violation';
            throw err;
          }
          error = typeSpecs[typeSpecName](values, typeSpecName, componentName, location, null, ReactPropTypesSecret);
        } catch (ex) {
          error = ex;
        }
        if (error && !(error instanceof Error)) {
          printWarning(
            (componentName || 'React class') + ': type specification of ' +
            location + ' `' + typeSpecName + '` is invalid; the type checker ' +
            'function must return `null` or an `Error` but returned a ' + typeof error + '. ' +
            'You may have forgotten to pass an argument to the type checker ' +
            'creator (arrayOf, instanceOf, objectOf, oneOf, oneOfType, and ' +
            'shape all require an argument).'
          );
        }
        if (error instanceof Error && !(error.message in loggedTypeFailures)) {
          // Only monitor this failure once because there tends to be a lot of the
          // same error.
          loggedTypeFailures[error.message] = true;

          var stack = getStack ? getStack() : '';

          printWarning(
            'Failed ' + location + ' type: ' + error.message + (stack != null ? stack : '')
          );
        }
      }
    }
  }
}

/**
 * Resets warning cache when testing.
 *
 * @private
 */
checkPropTypes.resetWarningCache = function() {
  if (undefined !== 'production') {
    loggedTypeFailures = {};
  }
}

module.exports = checkPropTypes;


/***/ }),

/***/ "../../js-packages/connection/node_modules/prop-types/factoryWithThrowingShims.js":
/*!*****************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/prop-types/factoryWithThrowingShims.js ***!
  \*****************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var ReactPropTypesSecret = __webpack_require__(/*! ./lib/ReactPropTypesSecret */ "../../js-packages/connection/node_modules/prop-types/lib/ReactPropTypesSecret.js");

function emptyFunction() {}
function emptyFunctionWithReset() {}
emptyFunctionWithReset.resetWarningCache = emptyFunction;

module.exports = function() {
  function shim(props, propName, componentName, location, propFullName, secret) {
    if (secret === ReactPropTypesSecret) {
      // It is still safe when called from React.
      return;
    }
    var err = new Error(
      'Calling PropTypes validators directly is not supported by the `prop-types` package. ' +
      'Use PropTypes.checkPropTypes() to call them. ' +
      'Read more at http://fb.me/use-check-prop-types'
    );
    err.name = 'Invariant Violation';
    throw err;
  };
  shim.isRequired = shim;
  function getShim() {
    return shim;
  };
  // Important!
  // Keep this list in sync with production version in `./factoryWithTypeCheckers.js`.
  var ReactPropTypes = {
    array: shim,
    bool: shim,
    func: shim,
    number: shim,
    object: shim,
    string: shim,
    symbol: shim,

    any: shim,
    arrayOf: getShim,
    element: shim,
    elementType: shim,
    instanceOf: getShim,
    node: shim,
    objectOf: getShim,
    oneOf: getShim,
    oneOfType: getShim,
    shape: getShim,
    exact: getShim,

    checkPropTypes: emptyFunctionWithReset,
    resetWarningCache: emptyFunction
  };

  ReactPropTypes.PropTypes = ReactPropTypes;

  return ReactPropTypes;
};


/***/ }),

/***/ "../../js-packages/connection/node_modules/prop-types/factoryWithTypeCheckers.js":
/*!****************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/prop-types/factoryWithTypeCheckers.js ***!
  \****************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var ReactIs = __webpack_require__(/*! react-is */ "../../js-packages/connection/node_modules/react-is/index.js");
var assign = __webpack_require__(/*! object-assign */ "../../js-packages/connection/node_modules/object-assign/index.js");

var ReactPropTypesSecret = __webpack_require__(/*! ./lib/ReactPropTypesSecret */ "../../js-packages/connection/node_modules/prop-types/lib/ReactPropTypesSecret.js");
var checkPropTypes = __webpack_require__(/*! ./checkPropTypes */ "../../js-packages/connection/node_modules/prop-types/checkPropTypes.js");

var has = Function.call.bind(Object.prototype.hasOwnProperty);
var printWarning = function() {};

if (undefined !== 'production') {
  printWarning = function(text) {
    var message = 'Warning: ' + text;
    if (typeof console !== 'undefined') {
      console.error(message);
    }
    try {
      // --- Welcome to debugging React ---
      // This error was thrown as a convenience so that you can use this stack
      // to find the callsite that caused this warning to fire.
      throw new Error(message);
    } catch (x) {}
  };
}

function emptyFunctionThatReturnsNull() {
  return null;
}

module.exports = function(isValidElement, throwOnDirectAccess) {
  /* global Symbol */
  var ITERATOR_SYMBOL = typeof Symbol === 'function' && Symbol.iterator;
  var FAUX_ITERATOR_SYMBOL = '@@iterator'; // Before Symbol spec.

  /**
   * Returns the iterator method function contained on the iterable object.
   *
   * Be sure to invoke the function with the iterable as context:
   *
   *     var iteratorFn = getIteratorFn(myIterable);
   *     if (iteratorFn) {
   *       var iterator = iteratorFn.call(myIterable);
   *       ...
   *     }
   *
   * @param {?object} maybeIterable
   * @return {?function}
   */
  function getIteratorFn(maybeIterable) {
    var iteratorFn = maybeIterable && (ITERATOR_SYMBOL && maybeIterable[ITERATOR_SYMBOL] || maybeIterable[FAUX_ITERATOR_SYMBOL]);
    if (typeof iteratorFn === 'function') {
      return iteratorFn;
    }
  }

  /**
   * Collection of methods that allow declaration and validation of props that are
   * supplied to React components. Example usage:
   *
   *   var Props = require('ReactPropTypes');
   *   var MyArticle = React.createClass({
   *     propTypes: {
   *       // An optional string prop named "description".
   *       description: Props.string,
   *
   *       // A required enum prop named "category".
   *       category: Props.oneOf(['News','Photos']).isRequired,
   *
   *       // A prop named "dialog" that requires an instance of Dialog.
   *       dialog: Props.instanceOf(Dialog).isRequired
   *     },
   *     render: function() { ... }
   *   });
   *
   * A more formal specification of how these methods are used:
   *
   *   type := array|bool|func|object|number|string|oneOf([...])|instanceOf(...)
   *   decl := ReactPropTypes.{type}(.isRequired)?
   *
   * Each and every declaration produces a function with the same signature. This
   * allows the creation of custom validation functions. For example:
   *
   *  var MyLink = React.createClass({
   *    propTypes: {
   *      // An optional string or URI prop named "href".
   *      href: function(props, propName, componentName) {
   *        var propValue = props[propName];
   *        if (propValue != null && typeof propValue !== 'string' &&
   *            !(propValue instanceof URI)) {
   *          return new Error(
   *            'Expected a string or an URI for ' + propName + ' in ' +
   *            componentName
   *          );
   *        }
   *      }
   *    },
   *    render: function() {...}
   *  });
   *
   * @internal
   */

  var ANONYMOUS = '<<anonymous>>';

  // Important!
  // Keep this list in sync with production version in `./factoryWithThrowingShims.js`.
  var ReactPropTypes = {
    array: createPrimitiveTypeChecker('array'),
    bool: createPrimitiveTypeChecker('boolean'),
    func: createPrimitiveTypeChecker('function'),
    number: createPrimitiveTypeChecker('number'),
    object: createPrimitiveTypeChecker('object'),
    string: createPrimitiveTypeChecker('string'),
    symbol: createPrimitiveTypeChecker('symbol'),

    any: createAnyTypeChecker(),
    arrayOf: createArrayOfTypeChecker,
    element: createElementTypeChecker(),
    elementType: createElementTypeTypeChecker(),
    instanceOf: createInstanceTypeChecker,
    node: createNodeChecker(),
    objectOf: createObjectOfTypeChecker,
    oneOf: createEnumTypeChecker,
    oneOfType: createUnionTypeChecker,
    shape: createShapeTypeChecker,
    exact: createStrictShapeTypeChecker,
  };

  /**
   * inlined Object.is polyfill to avoid requiring consumers ship their own
   * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Object/is
   */
  /*eslint-disable no-self-compare*/
  function is(x, y) {
    // SameValue algorithm
    if (x === y) {
      // Steps 1-5, 7-10
      // Steps 6.b-6.e: +0 != -0
      return x !== 0 || 1 / x === 1 / y;
    } else {
      // Step 6.a: NaN == NaN
      return x !== x && y !== y;
    }
  }
  /*eslint-enable no-self-compare*/

  /**
   * We use an Error-like object for backward compatibility as people may call
   * PropTypes directly and inspect their output. However, we don't use real
   * Errors anymore. We don't inspect their stack anyway, and creating them
   * is prohibitively expensive if they are created too often, such as what
   * happens in oneOfType() for any type before the one that matched.
   */
  function PropTypeError(message) {
    this.message = message;
    this.stack = '';
  }
  // Make `instanceof Error` still work for returned errors.
  PropTypeError.prototype = Error.prototype;

  function createChainableTypeChecker(validate) {
    if (undefined !== 'production') {
      var manualPropTypeCallCache = {};
      var manualPropTypeWarningCount = 0;
    }
    function checkType(isRequired, props, propName, componentName, location, propFullName, secret) {
      componentName = componentName || ANONYMOUS;
      propFullName = propFullName || propName;

      if (secret !== ReactPropTypesSecret) {
        if (throwOnDirectAccess) {
          // New behavior only for users of `prop-types` package
          var err = new Error(
            'Calling PropTypes validators directly is not supported by the `prop-types` package. ' +
            'Use `PropTypes.checkPropTypes()` to call them. ' +
            'Read more at http://fb.me/use-check-prop-types'
          );
          err.name = 'Invariant Violation';
          throw err;
        } else if (undefined !== 'production' && typeof console !== 'undefined') {
          // Old behavior for people using React.PropTypes
          var cacheKey = componentName + ':' + propName;
          if (
            !manualPropTypeCallCache[cacheKey] &&
            // Avoid spamming the console because they are often not actionable except for lib authors
            manualPropTypeWarningCount < 3
          ) {
            printWarning(
              'You are manually calling a React.PropTypes validation ' +
              'function for the `' + propFullName + '` prop on `' + componentName  + '`. This is deprecated ' +
              'and will throw in the standalone `prop-types` package. ' +
              'You may be seeing this warning due to a third-party PropTypes ' +
              'library. See https://fb.me/react-warning-dont-call-proptypes ' + 'for details.'
            );
            manualPropTypeCallCache[cacheKey] = true;
            manualPropTypeWarningCount++;
          }
        }
      }
      if (props[propName] == null) {
        if (isRequired) {
          if (props[propName] === null) {
            return new PropTypeError('The ' + location + ' `' + propFullName + '` is marked as required ' + ('in `' + componentName + '`, but its value is `null`.'));
          }
          return new PropTypeError('The ' + location + ' `' + propFullName + '` is marked as required in ' + ('`' + componentName + '`, but its value is `undefined`.'));
        }
        return null;
      } else {
        return validate(props, propName, componentName, location, propFullName);
      }
    }

    var chainedCheckType = checkType.bind(null, false);
    chainedCheckType.isRequired = checkType.bind(null, true);

    return chainedCheckType;
  }

  function createPrimitiveTypeChecker(expectedType) {
    function validate(props, propName, componentName, location, propFullName, secret) {
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== expectedType) {
        // `propValue` being instance of, say, date/regexp, pass the 'object'
        // check, but we can offer a more precise error message here rather than
        // 'of type `object`'.
        var preciseType = getPreciseType(propValue);

        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + preciseType + '` supplied to `' + componentName + '`, expected ') + ('`' + expectedType + '`.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createAnyTypeChecker() {
    return createChainableTypeChecker(emptyFunctionThatReturnsNull);
  }

  function createArrayOfTypeChecker(typeChecker) {
    function validate(props, propName, componentName, location, propFullName) {
      if (typeof typeChecker !== 'function') {
        return new PropTypeError('Property `' + propFullName + '` of component `' + componentName + '` has invalid PropType notation inside arrayOf.');
      }
      var propValue = props[propName];
      if (!Array.isArray(propValue)) {
        var propType = getPropType(propValue);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected an array.'));
      }
      for (var i = 0; i < propValue.length; i++) {
        var error = typeChecker(propValue, i, componentName, location, propFullName + '[' + i + ']', ReactPropTypesSecret);
        if (error instanceof Error) {
          return error;
        }
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createElementTypeChecker() {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      if (!isValidElement(propValue)) {
        var propType = getPropType(propValue);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected a single ReactElement.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createElementTypeTypeChecker() {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      if (!ReactIs.isValidElementType(propValue)) {
        var propType = getPropType(propValue);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected a single ReactElement type.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createInstanceTypeChecker(expectedClass) {
    function validate(props, propName, componentName, location, propFullName) {
      if (!(props[propName] instanceof expectedClass)) {
        var expectedClassName = expectedClass.name || ANONYMOUS;
        var actualClassName = getClassName(props[propName]);
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + actualClassName + '` supplied to `' + componentName + '`, expected ') + ('instance of `' + expectedClassName + '`.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createEnumTypeChecker(expectedValues) {
    if (!Array.isArray(expectedValues)) {
      if (undefined !== 'production') {
        if (arguments.length > 1) {
          printWarning(
            'Invalid arguments supplied to oneOf, expected an array, got ' + arguments.length + ' arguments. ' +
            'A common mistake is to write oneOf(x, y, z) instead of oneOf([x, y, z]).'
          );
        } else {
          printWarning('Invalid argument supplied to oneOf, expected an array.');
        }
      }
      return emptyFunctionThatReturnsNull;
    }

    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      for (var i = 0; i < expectedValues.length; i++) {
        if (is(propValue, expectedValues[i])) {
          return null;
        }
      }

      var valuesString = JSON.stringify(expectedValues, function replacer(key, value) {
        var type = getPreciseType(value);
        if (type === 'symbol') {
          return String(value);
        }
        return value;
      });
      return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of value `' + String(propValue) + '` ' + ('supplied to `' + componentName + '`, expected one of ' + valuesString + '.'));
    }
    return createChainableTypeChecker(validate);
  }

  function createObjectOfTypeChecker(typeChecker) {
    function validate(props, propName, componentName, location, propFullName) {
      if (typeof typeChecker !== 'function') {
        return new PropTypeError('Property `' + propFullName + '` of component `' + componentName + '` has invalid PropType notation inside objectOf.');
      }
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== 'object') {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type ' + ('`' + propType + '` supplied to `' + componentName + '`, expected an object.'));
      }
      for (var key in propValue) {
        if (has(propValue, key)) {
          var error = typeChecker(propValue, key, componentName, location, propFullName + '.' + key, ReactPropTypesSecret);
          if (error instanceof Error) {
            return error;
          }
        }
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createUnionTypeChecker(arrayOfTypeCheckers) {
    if (!Array.isArray(arrayOfTypeCheckers)) {
      undefined !== 'production' ? printWarning('Invalid argument supplied to oneOfType, expected an instance of array.') : void 0;
      return emptyFunctionThatReturnsNull;
    }

    for (var i = 0; i < arrayOfTypeCheckers.length; i++) {
      var checker = arrayOfTypeCheckers[i];
      if (typeof checker !== 'function') {
        printWarning(
          'Invalid argument supplied to oneOfType. Expected an array of check functions, but ' +
          'received ' + getPostfixForTypeWarning(checker) + ' at index ' + i + '.'
        );
        return emptyFunctionThatReturnsNull;
      }
    }

    function validate(props, propName, componentName, location, propFullName) {
      for (var i = 0; i < arrayOfTypeCheckers.length; i++) {
        var checker = arrayOfTypeCheckers[i];
        if (checker(props, propName, componentName, location, propFullName, ReactPropTypesSecret) == null) {
          return null;
        }
      }

      return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` supplied to ' + ('`' + componentName + '`.'));
    }
    return createChainableTypeChecker(validate);
  }

  function createNodeChecker() {
    function validate(props, propName, componentName, location, propFullName) {
      if (!isNode(props[propName])) {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` supplied to ' + ('`' + componentName + '`, expected a ReactNode.'));
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createShapeTypeChecker(shapeTypes) {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== 'object') {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type `' + propType + '` ' + ('supplied to `' + componentName + '`, expected `object`.'));
      }
      for (var key in shapeTypes) {
        var checker = shapeTypes[key];
        if (!checker) {
          continue;
        }
        var error = checker(propValue, key, componentName, location, propFullName + '.' + key, ReactPropTypesSecret);
        if (error) {
          return error;
        }
      }
      return null;
    }
    return createChainableTypeChecker(validate);
  }

  function createStrictShapeTypeChecker(shapeTypes) {
    function validate(props, propName, componentName, location, propFullName) {
      var propValue = props[propName];
      var propType = getPropType(propValue);
      if (propType !== 'object') {
        return new PropTypeError('Invalid ' + location + ' `' + propFullName + '` of type `' + propType + '` ' + ('supplied to `' + componentName + '`, expected `object`.'));
      }
      // We need to check all keys in case some are required but missing from
      // props.
      var allKeys = assign({}, props[propName], shapeTypes);
      for (var key in allKeys) {
        var checker = shapeTypes[key];
        if (!checker) {
          return new PropTypeError(
            'Invalid ' + location + ' `' + propFullName + '` key `' + key + '` supplied to `' + componentName + '`.' +
            '\nBad object: ' + JSON.stringify(props[propName], null, '  ') +
            '\nValid keys: ' +  JSON.stringify(Object.keys(shapeTypes), null, '  ')
          );
        }
        var error = checker(propValue, key, componentName, location, propFullName + '.' + key, ReactPropTypesSecret);
        if (error) {
          return error;
        }
      }
      return null;
    }

    return createChainableTypeChecker(validate);
  }

  function isNode(propValue) {
    switch (typeof propValue) {
      case 'number':
      case 'string':
      case 'undefined':
        return true;
      case 'boolean':
        return !propValue;
      case 'object':
        if (Array.isArray(propValue)) {
          return propValue.every(isNode);
        }
        if (propValue === null || isValidElement(propValue)) {
          return true;
        }

        var iteratorFn = getIteratorFn(propValue);
        if (iteratorFn) {
          var iterator = iteratorFn.call(propValue);
          var step;
          if (iteratorFn !== propValue.entries) {
            while (!(step = iterator.next()).done) {
              if (!isNode(step.value)) {
                return false;
              }
            }
          } else {
            // Iterator will provide entry [k,v] tuples rather than values.
            while (!(step = iterator.next()).done) {
              var entry = step.value;
              if (entry) {
                if (!isNode(entry[1])) {
                  return false;
                }
              }
            }
          }
        } else {
          return false;
        }

        return true;
      default:
        return false;
    }
  }

  function isSymbol(propType, propValue) {
    // Native Symbol.
    if (propType === 'symbol') {
      return true;
    }

    // falsy value can't be a Symbol
    if (!propValue) {
      return false;
    }

    // 19.4.3.5 Symbol.prototype[@@toStringTag] === 'Symbol'
    if (propValue['@@toStringTag'] === 'Symbol') {
      return true;
    }

    // Fallback for non-spec compliant Symbols which are polyfilled.
    if (typeof Symbol === 'function' && propValue instanceof Symbol) {
      return true;
    }

    return false;
  }

  // Equivalent of `typeof` but with special handling for array and regexp.
  function getPropType(propValue) {
    var propType = typeof propValue;
    if (Array.isArray(propValue)) {
      return 'array';
    }
    if (propValue instanceof RegExp) {
      // Old webkits (at least until Android 4.0) return 'function' rather than
      // 'object' for typeof a RegExp. We'll normalize this here so that /bla/
      // passes PropTypes.object.
      return 'object';
    }
    if (isSymbol(propType, propValue)) {
      return 'symbol';
    }
    return propType;
  }

  // This handles more types than `getPropType`. Only used for error messages.
  // See `createPrimitiveTypeChecker`.
  function getPreciseType(propValue) {
    if (typeof propValue === 'undefined' || propValue === null) {
      return '' + propValue;
    }
    var propType = getPropType(propValue);
    if (propType === 'object') {
      if (propValue instanceof Date) {
        return 'date';
      } else if (propValue instanceof RegExp) {
        return 'regexp';
      }
    }
    return propType;
  }

  // Returns a string that is postfixed to a warning about an invalid type.
  // For example, "undefined" or "of type array"
  function getPostfixForTypeWarning(value) {
    var type = getPreciseType(value);
    switch (type) {
      case 'array':
      case 'object':
        return 'an ' + type;
      case 'boolean':
      case 'date':
      case 'regexp':
        return 'a ' + type;
      default:
        return type;
    }
  }

  // Returns class name of the object, if any.
  function getClassName(propValue) {
    if (!propValue.constructor || !propValue.constructor.name) {
      return ANONYMOUS;
    }
    return propValue.constructor.name;
  }

  ReactPropTypes.checkPropTypes = checkPropTypes;
  ReactPropTypes.resetWarningCache = checkPropTypes.resetWarningCache;
  ReactPropTypes.PropTypes = ReactPropTypes;

  return ReactPropTypes;
};


/***/ }),

/***/ "../../js-packages/connection/node_modules/prop-types/index.js":
/*!**********************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/prop-types/index.js ***!
  \**********************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

if (undefined !== 'production') {
  var ReactIs = __webpack_require__(/*! react-is */ "../../js-packages/connection/node_modules/react-is/index.js");

  // By explicitly using `prop-types` you are opting into new development behavior.
  // http://fb.me/prop-types-in-prod
  var throwOnDirectAccess = true;
  module.exports = __webpack_require__(/*! ./factoryWithTypeCheckers */ "../../js-packages/connection/node_modules/prop-types/factoryWithTypeCheckers.js")(ReactIs.isElement, throwOnDirectAccess);
} else {
  // By explicitly using `prop-types` you are opting into new production behavior.
  // http://fb.me/prop-types-in-prod
  module.exports = __webpack_require__(/*! ./factoryWithThrowingShims */ "../../js-packages/connection/node_modules/prop-types/factoryWithThrowingShims.js")();
}


/***/ }),

/***/ "../../js-packages/connection/node_modules/prop-types/lib/ReactPropTypesSecret.js":
/*!*****************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/prop-types/lib/ReactPropTypesSecret.js ***!
  \*****************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var ReactPropTypesSecret = 'SECRET_DO_NOT_PASS_THIS_OR_YOU_WILL_BE_FIRED';

module.exports = ReactPropTypesSecret;


/***/ }),

/***/ "../../js-packages/connection/node_modules/react-is/cjs/react-is.development.js":
/*!***************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/react-is/cjs/react-is.development.js ***!
  \***************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/** @license React v16.13.1
 * react-is.development.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */





if (undefined !== "production") {
  (function() {
'use strict';

// The Symbol used to tag the ReactElement-like types. If there is no native Symbol
// nor polyfill, then a plain number is used for performance.
var hasSymbol = typeof Symbol === 'function' && Symbol.for;
var REACT_ELEMENT_TYPE = hasSymbol ? Symbol.for('react.element') : 0xeac7;
var REACT_PORTAL_TYPE = hasSymbol ? Symbol.for('react.portal') : 0xeaca;
var REACT_FRAGMENT_TYPE = hasSymbol ? Symbol.for('react.fragment') : 0xeacb;
var REACT_STRICT_MODE_TYPE = hasSymbol ? Symbol.for('react.strict_mode') : 0xeacc;
var REACT_PROFILER_TYPE = hasSymbol ? Symbol.for('react.profiler') : 0xead2;
var REACT_PROVIDER_TYPE = hasSymbol ? Symbol.for('react.provider') : 0xeacd;
var REACT_CONTEXT_TYPE = hasSymbol ? Symbol.for('react.context') : 0xeace; // TODO: We don't use AsyncMode or ConcurrentMode anymore. They were temporary
// (unstable) APIs that have been removed. Can we remove the symbols?

var REACT_ASYNC_MODE_TYPE = hasSymbol ? Symbol.for('react.async_mode') : 0xeacf;
var REACT_CONCURRENT_MODE_TYPE = hasSymbol ? Symbol.for('react.concurrent_mode') : 0xeacf;
var REACT_FORWARD_REF_TYPE = hasSymbol ? Symbol.for('react.forward_ref') : 0xead0;
var REACT_SUSPENSE_TYPE = hasSymbol ? Symbol.for('react.suspense') : 0xead1;
var REACT_SUSPENSE_LIST_TYPE = hasSymbol ? Symbol.for('react.suspense_list') : 0xead8;
var REACT_MEMO_TYPE = hasSymbol ? Symbol.for('react.memo') : 0xead3;
var REACT_LAZY_TYPE = hasSymbol ? Symbol.for('react.lazy') : 0xead4;
var REACT_BLOCK_TYPE = hasSymbol ? Symbol.for('react.block') : 0xead9;
var REACT_FUNDAMENTAL_TYPE = hasSymbol ? Symbol.for('react.fundamental') : 0xead5;
var REACT_RESPONDER_TYPE = hasSymbol ? Symbol.for('react.responder') : 0xead6;
var REACT_SCOPE_TYPE = hasSymbol ? Symbol.for('react.scope') : 0xead7;

function isValidElementType(type) {
  return typeof type === 'string' || typeof type === 'function' || // Note: its typeof might be other than 'symbol' or 'number' if it's a polyfill.
  type === REACT_FRAGMENT_TYPE || type === REACT_CONCURRENT_MODE_TYPE || type === REACT_PROFILER_TYPE || type === REACT_STRICT_MODE_TYPE || type === REACT_SUSPENSE_TYPE || type === REACT_SUSPENSE_LIST_TYPE || typeof type === 'object' && type !== null && (type.$$typeof === REACT_LAZY_TYPE || type.$$typeof === REACT_MEMO_TYPE || type.$$typeof === REACT_PROVIDER_TYPE || type.$$typeof === REACT_CONTEXT_TYPE || type.$$typeof === REACT_FORWARD_REF_TYPE || type.$$typeof === REACT_FUNDAMENTAL_TYPE || type.$$typeof === REACT_RESPONDER_TYPE || type.$$typeof === REACT_SCOPE_TYPE || type.$$typeof === REACT_BLOCK_TYPE);
}

function typeOf(object) {
  if (typeof object === 'object' && object !== null) {
    var $$typeof = object.$$typeof;

    switch ($$typeof) {
      case REACT_ELEMENT_TYPE:
        var type = object.type;

        switch (type) {
          case REACT_ASYNC_MODE_TYPE:
          case REACT_CONCURRENT_MODE_TYPE:
          case REACT_FRAGMENT_TYPE:
          case REACT_PROFILER_TYPE:
          case REACT_STRICT_MODE_TYPE:
          case REACT_SUSPENSE_TYPE:
            return type;

          default:
            var $$typeofType = type && type.$$typeof;

            switch ($$typeofType) {
              case REACT_CONTEXT_TYPE:
              case REACT_FORWARD_REF_TYPE:
              case REACT_LAZY_TYPE:
              case REACT_MEMO_TYPE:
              case REACT_PROVIDER_TYPE:
                return $$typeofType;

              default:
                return $$typeof;
            }

        }

      case REACT_PORTAL_TYPE:
        return $$typeof;
    }
  }

  return undefined;
} // AsyncMode is deprecated along with isAsyncMode

var AsyncMode = REACT_ASYNC_MODE_TYPE;
var ConcurrentMode = REACT_CONCURRENT_MODE_TYPE;
var ContextConsumer = REACT_CONTEXT_TYPE;
var ContextProvider = REACT_PROVIDER_TYPE;
var Element = REACT_ELEMENT_TYPE;
var ForwardRef = REACT_FORWARD_REF_TYPE;
var Fragment = REACT_FRAGMENT_TYPE;
var Lazy = REACT_LAZY_TYPE;
var Memo = REACT_MEMO_TYPE;
var Portal = REACT_PORTAL_TYPE;
var Profiler = REACT_PROFILER_TYPE;
var StrictMode = REACT_STRICT_MODE_TYPE;
var Suspense = REACT_SUSPENSE_TYPE;
var hasWarnedAboutDeprecatedIsAsyncMode = false; // AsyncMode should be deprecated

function isAsyncMode(object) {
  {
    if (!hasWarnedAboutDeprecatedIsAsyncMode) {
      hasWarnedAboutDeprecatedIsAsyncMode = true; // Using console['warn'] to evade Babel and ESLint

      console['warn']('The ReactIs.isAsyncMode() alias has been deprecated, ' + 'and will be removed in React 17+. Update your code to use ' + 'ReactIs.isConcurrentMode() instead. It has the exact same API.');
    }
  }

  return isConcurrentMode(object) || typeOf(object) === REACT_ASYNC_MODE_TYPE;
}
function isConcurrentMode(object) {
  return typeOf(object) === REACT_CONCURRENT_MODE_TYPE;
}
function isContextConsumer(object) {
  return typeOf(object) === REACT_CONTEXT_TYPE;
}
function isContextProvider(object) {
  return typeOf(object) === REACT_PROVIDER_TYPE;
}
function isElement(object) {
  return typeof object === 'object' && object !== null && object.$$typeof === REACT_ELEMENT_TYPE;
}
function isForwardRef(object) {
  return typeOf(object) === REACT_FORWARD_REF_TYPE;
}
function isFragment(object) {
  return typeOf(object) === REACT_FRAGMENT_TYPE;
}
function isLazy(object) {
  return typeOf(object) === REACT_LAZY_TYPE;
}
function isMemo(object) {
  return typeOf(object) === REACT_MEMO_TYPE;
}
function isPortal(object) {
  return typeOf(object) === REACT_PORTAL_TYPE;
}
function isProfiler(object) {
  return typeOf(object) === REACT_PROFILER_TYPE;
}
function isStrictMode(object) {
  return typeOf(object) === REACT_STRICT_MODE_TYPE;
}
function isSuspense(object) {
  return typeOf(object) === REACT_SUSPENSE_TYPE;
}

exports.AsyncMode = AsyncMode;
exports.ConcurrentMode = ConcurrentMode;
exports.ContextConsumer = ContextConsumer;
exports.ContextProvider = ContextProvider;
exports.Element = Element;
exports.ForwardRef = ForwardRef;
exports.Fragment = Fragment;
exports.Lazy = Lazy;
exports.Memo = Memo;
exports.Portal = Portal;
exports.Profiler = Profiler;
exports.StrictMode = StrictMode;
exports.Suspense = Suspense;
exports.isAsyncMode = isAsyncMode;
exports.isConcurrentMode = isConcurrentMode;
exports.isContextConsumer = isContextConsumer;
exports.isContextProvider = isContextProvider;
exports.isElement = isElement;
exports.isForwardRef = isForwardRef;
exports.isFragment = isFragment;
exports.isLazy = isLazy;
exports.isMemo = isMemo;
exports.isPortal = isPortal;
exports.isProfiler = isProfiler;
exports.isStrictMode = isStrictMode;
exports.isSuspense = isSuspense;
exports.isValidElementType = isValidElementType;
exports.typeOf = typeOf;
  })();
}


/***/ }),

/***/ "../../js-packages/connection/node_modules/react-is/cjs/react-is.production.min.js":
/*!******************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/react-is/cjs/react-is.production.min.js ***!
  \******************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/** @license React v16.13.1
 * react-is.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

var b="function"===typeof Symbol&&Symbol.for,c=b?Symbol.for("react.element"):60103,d=b?Symbol.for("react.portal"):60106,e=b?Symbol.for("react.fragment"):60107,f=b?Symbol.for("react.strict_mode"):60108,g=b?Symbol.for("react.profiler"):60114,h=b?Symbol.for("react.provider"):60109,k=b?Symbol.for("react.context"):60110,l=b?Symbol.for("react.async_mode"):60111,m=b?Symbol.for("react.concurrent_mode"):60111,n=b?Symbol.for("react.forward_ref"):60112,p=b?Symbol.for("react.suspense"):60113,q=b?
Symbol.for("react.suspense_list"):60120,r=b?Symbol.for("react.memo"):60115,t=b?Symbol.for("react.lazy"):60116,v=b?Symbol.for("react.block"):60121,w=b?Symbol.for("react.fundamental"):60117,x=b?Symbol.for("react.responder"):60118,y=b?Symbol.for("react.scope"):60119;
function z(a){if("object"===typeof a&&null!==a){var u=a.$$typeof;switch(u){case c:switch(a=a.type,a){case l:case m:case e:case g:case f:case p:return a;default:switch(a=a&&a.$$typeof,a){case k:case n:case t:case r:case h:return a;default:return u}}case d:return u}}}function A(a){return z(a)===m}exports.AsyncMode=l;exports.ConcurrentMode=m;exports.ContextConsumer=k;exports.ContextProvider=h;exports.Element=c;exports.ForwardRef=n;exports.Fragment=e;exports.Lazy=t;exports.Memo=r;exports.Portal=d;
exports.Profiler=g;exports.StrictMode=f;exports.Suspense=p;exports.isAsyncMode=function(a){return A(a)||z(a)===l};exports.isConcurrentMode=A;exports.isContextConsumer=function(a){return z(a)===k};exports.isContextProvider=function(a){return z(a)===h};exports.isElement=function(a){return"object"===typeof a&&null!==a&&a.$$typeof===c};exports.isForwardRef=function(a){return z(a)===n};exports.isFragment=function(a){return z(a)===e};exports.isLazy=function(a){return z(a)===t};
exports.isMemo=function(a){return z(a)===r};exports.isPortal=function(a){return z(a)===d};exports.isProfiler=function(a){return z(a)===g};exports.isStrictMode=function(a){return z(a)===f};exports.isSuspense=function(a){return z(a)===p};
exports.isValidElementType=function(a){return"string"===typeof a||"function"===typeof a||a===e||a===m||a===g||a===f||a===p||a===q||"object"===typeof a&&null!==a&&(a.$$typeof===t||a.$$typeof===r||a.$$typeof===h||a.$$typeof===k||a.$$typeof===n||a.$$typeof===w||a.$$typeof===x||a.$$typeof===y||a.$$typeof===v)};exports.typeOf=z;


/***/ }),

/***/ "../../js-packages/connection/node_modules/react-is/index.js":
/*!********************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/node_modules/react-is/index.js ***!
  \********************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


if (undefined === 'production') {
  module.exports = __webpack_require__(/*! ./cjs/react-is.production.min.js */ "../../js-packages/connection/node_modules/react-is/cjs/react-is.production.min.js");
} else {
  module.exports = __webpack_require__(/*! ./cjs/react-is.development.js */ "../../js-packages/connection/node_modules/react-is/cjs/react-is.development.js");
}


/***/ }),

/***/ "../../js-packages/connection/tools/jetpack-rest-api-client/index.jsx":
/*!*****************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/tools/jetpack-rest-api-client/index.jsx ***!
  \*****************************************************************************************************************/
/*! exports provided: JsonParseError, JsonParseAfterRedirectError, Api404Error, Api404AfterRedirectError, FetchNetworkError, default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "JsonParseError", function() { return JsonParseError; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "JsonParseAfterRedirectError", function() { return JsonParseAfterRedirectError; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "Api404Error", function() { return Api404Error; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "Api404AfterRedirectError", function() { return Api404AfterRedirectError; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "FetchNetworkError", function() { return FetchNetworkError; });
/* harmony import */ var _babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/classCallCheck */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/classCallCheck.js");
/* harmony import */ var _babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @babel/runtime/helpers/inherits */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/inherits.js");
/* harmony import */ var _babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @babel/runtime/helpers/createSuper */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/createSuper.js");
/* harmony import */ var _babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @babel/runtime/helpers/wrapNativeSuper */ "../../js-packages/connection/node_modules/@babel/runtime/helpers/wrapNativeSuper.js");
/* harmony import */ var _babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! lodash */ "lodash");
/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(lodash__WEBPACK_IMPORTED_MODULE_4__);





/**
 * External dependencies
 */

/* eslint-disable no-unused-vars */

/**
 * Helps create new custom error classes to better notify upper layers.
 *
 * @param {string} name - The Error name that will be available in `Error.name`.
 * @returns {Error}      a new custom error class.
 */

function createCustomError(name) {
  var CustomError = /*#__PURE__*/function (_Error) {
    _babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1___default()(CustomError, _Error);

    var _super = _babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2___default()(CustomError);

    function CustomError() {
      var _this;

      _babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0___default()(this, CustomError);

      for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
        args[_key] = arguments[_key];
      }

      _this = _super.call.apply(_super, [this].concat(args));
      _this.name = name;
      return _this;
    }

    return CustomError;
  }( /*#__PURE__*/_babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3___default()(Error));

  return CustomError;
}

var JsonParseError = createCustomError('JsonParseError');
var JsonParseAfterRedirectError = createCustomError('JsonParseAfterRedirectError');
var Api404Error = createCustomError('Api404Error');
var Api404AfterRedirectError = createCustomError('Api404AfterRedirectError');
var FetchNetworkError = createCustomError('FetchNetworkError');
/**
 * Jetpack REST API Client.
 *
 * @param {string} root - The API root URL.
 * @param {string} nonce - The API nonce.
 * @class
 */

function JetpackRestApiClient(root, nonce) {
  var apiRoot = root,
      headers = {
    'X-WP-Nonce': nonce
  },
      getParams = {
    credentials: 'same-origin',
    headers: headers
  },
      postParams = {
    method: 'post',
    credentials: 'same-origin',
    headers: Object(lodash__WEBPACK_IMPORTED_MODULE_4__["assign"])({}, headers, {
      'Content-type': 'application/json'
    })
  };
  var methods = {
    setApiRoot: function setApiRoot(newRoot) {
      apiRoot = newRoot;
    },
    setApiNonce: function setApiNonce(newNonce) {
      headers = {
        'X-WP-Nonce': newNonce
      };
      getParams = {
        credentials: 'same-origin',
        headers: headers
      };
      postParams = {
        method: 'post',
        credentials: 'same-origin',
        headers: Object(lodash__WEBPACK_IMPORTED_MODULE_4__["assign"])({}, headers, {
          'Content-type': 'application/json'
        })
      };
    },
    registerSite: function registerSite(registrationNonce, redirectUri) {
      return postRequest("".concat(apiRoot, "jetpack/v4/connection/register"), postParams, {
        body: JSON.stringify({
          registration_nonce: registrationNonce,
          no_iframe: true,
          redirect_uri: redirectUri
        })
      }).then(checkStatus).then(parseJsonResponse);
    }
  };
  /**
   * Add the cache buster value to the URL.
   *
   * @param {string} route - The API route URL.
   * @returns {string} The API route URL with cache buster added.
   */

  function addCacheBuster(route) {
    var parts = route.split('?'),
        query = parts.length > 1 ? parts[1] : '',
        args = query.length ? query.split('&') : [];
    args.push('_cacheBuster=' + new Date().getTime());
    return parts[0] + '?' + args.join('&');
  }
  /**
   * Perform a GET API request.
   *
   * @param {string} route - The API route.
   * @param {object} params - The request params.
   * @returns {Promise<Response>} The request result promise.
   */


  function getRequest(route, params) {
    return fetch(addCacheBuster(route), params);
  }
  /**
   * Perform a GET API request.
   *
   * @param {string} route - The API route.
   * @param {object} params - The request params.
   * @param {object} [body] - The request body.
   * @returns {Promise<Response>} The request result promise.
   */


  function postRequest(route, params, body) {
    return fetch(route, Object(lodash__WEBPACK_IMPORTED_MODULE_4__["assign"])({}, params, body)).catch(catchNetworkErrors);
  }

  Object(lodash__WEBPACK_IMPORTED_MODULE_4__["assign"])(this, methods);
}
/* eslint-enable no-unused-vars */

/**
 * Check the response status.
 *
 * @param {object} response - The API response.
 * @returns {Promise} - The status promise.
 */


function checkStatus(response) {
  // Regular success responses
  if (response.status >= 200 && response.status < 300) {
    return response;
  }

  if (response.status === 404) {
    return new Promise(function () {
      var err = response.redirected ? new Api404AfterRedirectError(response.redirected) : new Api404Error();
      throw err;
    });
  }

  return response.json().catch(function (e) {
    return catchJsonParseError(e);
  }).then(function (json) {
    var error = new Error("".concat(json.message, " (Status ").concat(response.status, ")"));
    error.response = json;
    error.name = 'ApiError';
    throw error;
  });
}
/**
 * Parse JSON response.
 *
 * @param {string} response - The JSON string.
 * @returns {object} The parsed JSON object.
 */


function parseJsonResponse(response) {
  return response.json().catch(function (e) {
    return catchJsonParseError(e, response.redirected, response.url);
  });
}
/**
 * Catch a JSON parse error.
 *
 * @param {object} e - The error.
 * @param {boolean} redirected - Whether it is an "after redirect" parse error.
 * @param {string} url - The redirect URL.
 */


function catchJsonParseError(e, redirected, url) {
  var err = redirected ? new JsonParseAfterRedirectError(url) : new JsonParseError();
  throw err;
}
/**
 * Catches TypeError coming from the Fetch API implementation
 */


function catchNetworkErrors() {
  //Either one of:
  // * A preflight error like a redirection to an external site (which results in a CORS)
  // * A preflight error like ERR_TOO_MANY_REDIRECTS
  throw new FetchNetworkError();
}

var restApi = new JetpackRestApiClient();
/* harmony default export */ __webpack_exports__["default"] = (restApi);

/***/ }),

/***/ "./_inc/actions/connection-data.js":
/*!*****************************************!*\
  !*** ./_inc/actions/connection-data.js ***!
  \*****************************************/
/*! exports provided: CONNECTION_DATA_SET_AUTHORIZATION_URL, default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "CONNECTION_DATA_SET_AUTHORIZATION_URL", function() { return CONNECTION_DATA_SET_AUTHORIZATION_URL; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "default", function() { return connectionDataActions; });
var CONNECTION_DATA_SET_AUTHORIZATION_URL = 'CONNECTION_DATA_AUTHORIZATION_URL_SET';
var connectionDataActions = {
  connectionDataSetAuthorizationUrl: function connectionDataSetAuthorizationUrl(url) {
    return {
      type: CONNECTION_DATA_SET_AUTHORIZATION_URL,
      url: url
    };
  }
};


/***/ }),

/***/ "./_inc/actions/connection-status.js":
/*!*******************************************!*\
  !*** ./_inc/actions/connection-status.js ***!
  \*******************************************/
/*! exports provided: CONNECTION_STATUS_REGISTERED, CONNECTION_STATUS_USER_CONNECTED, default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "CONNECTION_STATUS_REGISTERED", function() { return CONNECTION_STATUS_REGISTERED; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "CONNECTION_STATUS_USER_CONNECTED", function() { return CONNECTION_STATUS_USER_CONNECTED; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "default", function() { return connectionStatusActions; });
var CONNECTION_STATUS_REGISTERED = 'CONNECTION_STATUS_REGISTERED';
var CONNECTION_STATUS_USER_CONNECTED = 'CONNECTION_STATUS_USER_CONNECTED';
var connectionStatusActions = {
  connectionStatusSetRegistered: function connectionStatusSetRegistered(isRegistered) {
    return {
      type: CONNECTION_STATUS_REGISTERED,
      isRegistered: isRegistered
    };
  },
  connectionStatusSetUserConnected: function connectionStatusSetUserConnected(isUserConnected) {
    return {
      type: CONNECTION_STATUS_USER_CONNECTED,
      isUserConnected: isUserConnected
    };
  }
};


/***/ }),

/***/ "./_inc/actions/index.js":
/*!*******************************!*\
  !*** ./_inc/actions/index.js ***!
  \*******************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/objectSpread2 */ "./node_modules/@babel/runtime/helpers/objectSpread2.js");
/* harmony import */ var _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _connection_status__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./connection-status */ "./_inc/actions/connection-status.js");
/* harmony import */ var _connection_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./connection-data */ "./_inc/actions/connection-data.js");


/**
 * Internal dependencies
 */



var actions = _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()(_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, _connection_status__WEBPACK_IMPORTED_MODULE_1__["default"]), _connection_data__WEBPACK_IMPORTED_MODULE_2__["default"]);

/* harmony default export */ __webpack_exports__["default"] = (actions);

/***/ }),

/***/ "./_inc/admin.jsx":
/*!************************!*\
  !*** ./_inc/admin.jsx ***!
  \************************/
/*! no exports provided */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react_dom__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react-dom */ "react-dom");
/* harmony import */ var react_dom__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react_dom__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _components_admin__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./components/admin */ "./_inc/components/admin/index.jsx");
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./store */ "./_inc/store.js");
/**
 * External dependencies
 */



/**
 * Internal dependencies
 */



Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_2__["registerStore"])(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"], _store__WEBPACK_IMPORTED_MODULE_4__["storeConfig"]);
/**
 * The initial renderer function.
 */

function render() {
  var container = document.getElementById('jetpack-connection-ui-container');

  if (null === container) {
    return;
  }

  react_dom__WEBPACK_IMPORTED_MODULE_0___default.a.render( /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_components_admin__WEBPACK_IMPORTED_MODULE_3__["default"], null), container);
}

render();

/***/ }),

/***/ "./_inc/components/admin/index.jsx":
/*!*****************************************!*\
  !*** ./_inc/components/admin/index.jsx ***!
  \*****************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "default", function() { return Admin; });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _automattic_jetpack_connection__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @automattic/jetpack-connection */ "../../js-packages/connection/index.jsx");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../store */ "./_inc/store.js");
/* harmony import */ var _header__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../header */ "./_inc/components/header/index.jsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./style.scss */ "./_inc/components/admin/style.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_style_scss__WEBPACK_IMPORTED_MODULE_6__);
/**
 * External dependencies
 */




/**
 * Internal dependencies
 */




/**
 * The Connection IU Admin App.
 *
 * @returns {object} The Admin component.
 */

function Admin() {
  var connectionStatus = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useSelect"])(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]).getConnectionStatus();
  }, []);
  var APINonce = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useSelect"])(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]).getAPINonce();
  }, []);
  var APIRoot = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useSelect"])(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]).getAPIRoot();
  }, []);
  var authorizationUrl = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useSelect"])(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]).getAuthorizationUrl();
  }, []);
  var doNotUseConnectionIframe = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useSelect"])(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]).getDoNotUseConnectionIframe();
  }, []);
  var registrationNonce = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useSelect"])(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]).getRegistrationNonce();
  }, []);

  var _useDispatch = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useDispatch"])(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]),
      connectionStatusSetRegistered = _useDispatch.connectionStatusSetRegistered,
      connectionStatusSetUserConnected = _useDispatch.connectionStatusSetUserConnected,
      connectionDataSetAuthorizationUrl = _useDispatch.connectionDataSetAuthorizationUrl;

  var onUserConnected = Object(react__WEBPACK_IMPORTED_MODULE_0__["useCallback"])(function () {
    connectionStatusSetUserConnected(true);
  }, [connectionStatusSetUserConnected]);
  var onRegistered = Object(react__WEBPACK_IMPORTED_MODULE_0__["useCallback"])(function (response) {
    connectionStatusSetRegistered(true);

    if (response.authorizeUrl) {
      connectionDataSetAuthorizationUrl(response.authorizeUrl);
    }
  }, [connectionStatusSetRegistered, connectionDataSetAuthorizationUrl]);
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement(react__WEBPACK_IMPORTED_MODULE_0___default.a.Fragment, null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement(_header__WEBPACK_IMPORTED_MODULE_5__["default"], null), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("div", {
    className: "connection-status-card"
  }, connectionStatus.isRegistered && !connectionStatus.isUserConnected && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("strong", null, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Site Registered', 'jetpack')), connectionStatus.isRegistered && connectionStatus.isUserConnected && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("strong", null, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Site and User Connected', 'jetpack'))), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement(_automattic_jetpack_connection__WEBPACK_IMPORTED_MODULE_2__["JetpackConnection"], {
    apiRoot: APIRoot,
    apiNonce: APINonce,
    authorizationUrl: authorizationUrl,
    isRegistered: connectionStatus.isRegistered,
    isUserConnected: connectionStatus.isUserConnected,
    hasConnectedOwner: connectionStatus.hasConnectedOwner,
    forceCalypsoFlow: doNotUseConnectionIframe,
    onRegistered: onRegistered,
    onUserConnected: onUserConnected,
    registrationNonce: registrationNonce,
    from: "connection-ui",
    redirectUri: "tools.php?page=wpcom-connection-manager"
  }));
}

/***/ }),

/***/ "./_inc/components/admin/style.scss":
/*!******************************************!*\
  !*** ./_inc/components/admin/style.scss ***!
  \******************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ "./_inc/components/header/index.jsx":
/*!******************************************!*\
  !*** ./_inc/components/header/index.jsx ***!
  \******************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./style.scss */ "./_inc/components/header/style.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_style_scss__WEBPACK_IMPORTED_MODULE_2__);
/**
 * External dependencies
 */


/**
 * Internal dependencies
 */


/**
 * The Connection UI header.
 *
 * @returns {object} The header component.
 */

var Header = function Header() {
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("div", {
    className: "jetpack-cui__header"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("h1", null, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__["__"])('Connection Manager', 'jetpack')));
};

/* harmony default export */ __webpack_exports__["default"] = (Header);

/***/ }),

/***/ "./_inc/components/header/style.scss":
/*!*******************************************!*\
  !*** ./_inc/components/header/style.scss ***!
  \*******************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ "./_inc/reducers/api.js":
/*!******************************!*\
  !*** ./_inc/reducers/api.js ***!
  \******************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
var API = function API() {
  var state = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
  return state;
};

/* harmony default export */ __webpack_exports__["default"] = (API);

/***/ }),

/***/ "./_inc/reducers/connection-data.js":
/*!******************************************!*\
  !*** ./_inc/reducers/connection-data.js ***!
  \******************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/objectSpread2 */ "./node_modules/@babel/runtime/helpers/objectSpread2.js");
/* harmony import */ var _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _actions_connection_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../actions/connection-data */ "./_inc/actions/connection-data.js");


/**
 * Internal dependencies
 */


var settings = function settings() {
  var state = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
  var action = arguments.length > 1 ? arguments[1] : undefined;

  switch (action.type) {
    case _actions_connection_data__WEBPACK_IMPORTED_MODULE_1__["CONNECTION_DATA_SET_AUTHORIZATION_URL"]:
      return _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()(_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, state), {}, {
        authorizationUrl: action.url
      });
  }

  return state;
};

/* harmony default export */ __webpack_exports__["default"] = (settings);

/***/ }),

/***/ "./_inc/reducers/connection-status.js":
/*!********************************************!*\
  !*** ./_inc/reducers/connection-status.js ***!
  \********************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/objectSpread2 */ "./node_modules/@babel/runtime/helpers/objectSpread2.js");
/* harmony import */ var _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _actions_connection_status__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../actions/connection-status */ "./_inc/actions/connection-status.js");


/**
 * Internal dependencies
 */


var connectionStatus = function connectionStatus() {
  var state = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
  var action = arguments.length > 1 ? arguments[1] : undefined;

  switch (action.type) {
    case _actions_connection_status__WEBPACK_IMPORTED_MODULE_1__["CONNECTION_STATUS_REGISTERED"]:
      return _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()(_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, state), {}, {
        isRegistered: action.isRegistered
      });

    case _actions_connection_status__WEBPACK_IMPORTED_MODULE_1__["CONNECTION_STATUS_USER_CONNECTED"]:
      return _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()(_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, state), {}, {
        isUserConnected: action.isUserConnected
      });
  }

  return state;
};

/* harmony default export */ __webpack_exports__["default"] = (connectionStatus);

/***/ }),

/***/ "./_inc/reducers/index.js":
/*!********************************!*\
  !*** ./_inc/reducers/index.js ***!
  \********************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _connection_status__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./connection-status */ "./_inc/reducers/connection-status.js");
/* harmony import */ var _api__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./api */ "./_inc/reducers/api.js");
/* harmony import */ var _connection_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./connection-data */ "./_inc/reducers/connection-data.js");
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */




var reducer = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__["combineReducers"])({
  connectionStatus: _connection_status__WEBPACK_IMPORTED_MODULE_1__["default"],
  API: _api__WEBPACK_IMPORTED_MODULE_2__["default"],
  connectionData: _connection_data__WEBPACK_IMPORTED_MODULE_3__["default"]
});
/* harmony default export */ __webpack_exports__["default"] = (reducer);

/***/ }),

/***/ "./_inc/selectors/api.js":
/*!*******************************!*\
  !*** ./_inc/selectors/api.js ***!
  \*******************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
var APISelectors = {
  getAPIRoot: function getAPIRoot(state) {
    return state.API.WP_API_root || null;
  },
  getAPINonce: function getAPINonce(state) {
    return state.API.WP_API_nonce || null;
  },
  getRegistrationNonce: function getRegistrationNonce(state) {
    return state.API.registrationNonce || null;
  }
};
/* harmony default export */ __webpack_exports__["default"] = (APISelectors);

/***/ }),

/***/ "./_inc/selectors/connection-data.js":
/*!*******************************************!*\
  !*** ./_inc/selectors/connection-data.js ***!
  \*******************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
var settingsSelectors = {
  getDoNotUseConnectionIframe: function getDoNotUseConnectionIframe(state) {
    return state.connectionData.doNotUseConnectionIframe || null;
  },
  getAuthorizationUrl: function getAuthorizationUrl(state) {
    return state.connectionData.authorizationUrl || null;
  }
};
/* harmony default export */ __webpack_exports__["default"] = (settingsSelectors);

/***/ }),

/***/ "./_inc/selectors/connection-status.js":
/*!*********************************************!*\
  !*** ./_inc/selectors/connection-status.js ***!
  \*********************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
var connectionSelectors = {
  getConnectionStatus: function getConnectionStatus(state) {
    return state.connectionStatus || {};
  }
};
/* harmony default export */ __webpack_exports__["default"] = (connectionSelectors);

/***/ }),

/***/ "./_inc/selectors/index.js":
/*!*********************************!*\
  !*** ./_inc/selectors/index.js ***!
  \*********************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/objectSpread2 */ "./node_modules/@babel/runtime/helpers/objectSpread2.js");
/* harmony import */ var _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _connection_status__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./connection-status */ "./_inc/selectors/connection-status.js");
/* harmony import */ var _api__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./api */ "./_inc/selectors/api.js");
/* harmony import */ var _connection_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./connection-data */ "./_inc/selectors/connection-data.js");


/**
 * Internal dependencies
 */




var selectors = _babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()(_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()(_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, _connection_status__WEBPACK_IMPORTED_MODULE_1__["default"]), _api__WEBPACK_IMPORTED_MODULE_2__["default"]), _connection_data__WEBPACK_IMPORTED_MODULE_3__["default"]);

/* harmony default export */ __webpack_exports__["default"] = (selectors);

/***/ }),

/***/ "./_inc/store.js":
/*!***********************!*\
  !*** ./_inc/store.js ***!
  \***********************/
/*! exports provided: STORE_ID, storeConfig */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "STORE_ID", function() { return STORE_ID; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "storeConfig", function() { return storeConfig; });
/* harmony import */ var _reducers__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./reducers */ "./_inc/reducers/index.js");
/* harmony import */ var _actions__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./actions */ "./_inc/actions/index.js");
/* harmony import */ var _selectors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./selectors */ "./_inc/selectors/index.js");
/**
 * Internal dependencies
 */



var STORE_ID = 'jetpack-connection-ui';
var storeConfig = {
  reducer: _reducers__WEBPACK_IMPORTED_MODULE_0__["default"],
  actions: _actions__WEBPACK_IMPORTED_MODULE_1__["default"],
  selectors: _selectors__WEBPACK_IMPORTED_MODULE_2__["default"],
  initialState: window.CUI_INITIAL_STATE || {}
};

/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/defineProperty.js":
/*!***************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/defineProperty.js ***!
  \***************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _defineProperty(obj, key, value) {
  if (key in obj) {
    Object.defineProperty(obj, key, {
      value: value,
      enumerable: true,
      configurable: true,
      writable: true
    });
  } else {
    obj[key] = value;
  }

  return obj;
}

module.exports = _defineProperty;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/objectSpread2.js":
/*!**************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/objectSpread2.js ***!
  \**************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var defineProperty = __webpack_require__(/*! ./defineProperty.js */ "./node_modules/@babel/runtime/helpers/defineProperty.js");

function ownKeys(object, enumerableOnly) {
  var keys = Object.keys(object);

  if (Object.getOwnPropertySymbols) {
    var symbols = Object.getOwnPropertySymbols(object);
    if (enumerableOnly) symbols = symbols.filter(function (sym) {
      return Object.getOwnPropertyDescriptor(object, sym).enumerable;
    });
    keys.push.apply(keys, symbols);
  }

  return keys;
}

function _objectSpread2(target) {
  for (var i = 1; i < arguments.length; i++) {
    var source = arguments[i] != null ? arguments[i] : {};

    if (i % 2) {
      ownKeys(Object(source), true).forEach(function (key) {
        defineProperty(target, key, source[key]);
      });
    } else if (Object.getOwnPropertyDescriptors) {
      Object.defineProperties(target, Object.getOwnPropertyDescriptors(source));
    } else {
      ownKeys(Object(source)).forEach(function (key) {
        Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key));
      });
    }
  }

  return target;
}

module.exports = _objectSpread2;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/*! no static exports found */
/***/ (function(module, exports) {

(function() { module.exports = window["wp"]["components"]; }());

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/*! no static exports found */
/***/ (function(module, exports) {

(function() { module.exports = window["wp"]["data"]; }());

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/*! no static exports found */
/***/ (function(module, exports) {

(function() { module.exports = window["wp"]["i18n"]; }());

/***/ }),

/***/ "lodash":
/*!*************************!*\
  !*** external "lodash" ***!
  \*************************/
/*! no static exports found */
/***/ (function(module, exports) {

(function() { module.exports = window["lodash"]; }());

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/*! no static exports found */
/***/ (function(module, exports) {

(function() { module.exports = window["React"]; }());

/***/ }),

/***/ "react-dom":
/*!***************************!*\
  !*** external "ReactDOM" ***!
  \***************************/
/*! no static exports found */
/***/ (function(module, exports) {

(function() { module.exports = window["ReactDOM"]; }());

/***/ })

/******/ })));
//# sourceMappingURL=index.js.map