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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/arrayLikeToArray.js":
/*!******************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/arrayLikeToArray.js ***!
  \******************************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/arrayWithHoles.js":
/*!****************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/arrayWithHoles.js ***!
  \****************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _arrayWithHoles(arr) {
  if (Array.isArray(arr)) return arr;
}

module.exports = _arrayWithHoles;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/assertThisInitialized.js":
/*!***********************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/assertThisInitialized.js ***!
  \***********************************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/classCallCheck.js":
/*!****************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/classCallCheck.js ***!
  \****************************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/construct.js":
/*!***********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/construct.js ***!
  \***********************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var setPrototypeOf = __webpack_require__(/*! ./setPrototypeOf.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/setPrototypeOf.js");

var isNativeReflectConstruct = __webpack_require__(/*! ./isNativeReflectConstruct.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js");

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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/createClass.js":
/*!*************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/createClass.js ***!
  \*************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _defineProperties(target, props) {
  for (var i = 0; i < props.length; i++) {
    var descriptor = props[i];
    descriptor.enumerable = descriptor.enumerable || false;
    descriptor.configurable = true;
    if ("value" in descriptor) descriptor.writable = true;
    Object.defineProperty(target, descriptor.key, descriptor);
  }
}

function _createClass(Constructor, protoProps, staticProps) {
  if (protoProps) _defineProperties(Constructor.prototype, protoProps);
  if (staticProps) _defineProperties(Constructor, staticProps);
  return Constructor;
}

module.exports = _createClass;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/createSuper.js":
/*!*************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/createSuper.js ***!
  \*************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var getPrototypeOf = __webpack_require__(/*! ./getPrototypeOf.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/getPrototypeOf.js");

var isNativeReflectConstruct = __webpack_require__(/*! ./isNativeReflectConstruct.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js");

var possibleConstructorReturn = __webpack_require__(/*! ./possibleConstructorReturn.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/possibleConstructorReturn.js");

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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/defineProperty.js":
/*!****************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/defineProperty.js ***!
  \****************************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/extends.js":
/*!*********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/extends.js ***!
  \*********************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _extends() {
  module.exports = _extends = Object.assign || function (target) {
    for (var i = 1; i < arguments.length; i++) {
      var source = arguments[i];

      for (var key in source) {
        if (Object.prototype.hasOwnProperty.call(source, key)) {
          target[key] = source[key];
        }
      }
    }

    return target;
  };

  module.exports["default"] = module.exports, module.exports.__esModule = true;
  return _extends.apply(this, arguments);
}

module.exports = _extends;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/getPrototypeOf.js":
/*!****************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/getPrototypeOf.js ***!
  \****************************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/inherits.js":
/*!**********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/inherits.js ***!
  \**********************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var setPrototypeOf = __webpack_require__(/*! ./setPrototypeOf.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/setPrototypeOf.js");

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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/isNativeFunction.js":
/*!******************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/isNativeFunction.js ***!
  \******************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _isNativeFunction(fn) {
  return Function.toString.call(fn).indexOf("[native code]") !== -1;
}

module.exports = _isNativeFunction;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js":
/*!**************************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js ***!
  \**************************************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/iterableToArrayLimit.js":
/*!**********************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/iterableToArrayLimit.js ***!
  \**********************************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/nonIterableRest.js":
/*!*****************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/nonIterableRest.js ***!
  \*****************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _nonIterableRest() {
  throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
}

module.exports = _nonIterableRest;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectSpread2.js":
/*!***************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectSpread2.js ***!
  \***************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var defineProperty = __webpack_require__(/*! ./defineProperty.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/defineProperty.js");

function ownKeys(object, enumerableOnly) {
  var keys = Object.keys(object);

  if (Object.getOwnPropertySymbols) {
    var symbols = Object.getOwnPropertySymbols(object);

    if (enumerableOnly) {
      symbols = symbols.filter(function (sym) {
        return Object.getOwnPropertyDescriptor(object, sym).enumerable;
      });
    }

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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectWithoutProperties.js":
/*!*************************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectWithoutProperties.js ***!
  \*************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var objectWithoutPropertiesLoose = __webpack_require__(/*! ./objectWithoutPropertiesLoose.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectWithoutPropertiesLoose.js");

function _objectWithoutProperties(source, excluded) {
  if (source == null) return {};
  var target = objectWithoutPropertiesLoose(source, excluded);
  var key, i;

  if (Object.getOwnPropertySymbols) {
    var sourceSymbolKeys = Object.getOwnPropertySymbols(source);

    for (i = 0; i < sourceSymbolKeys.length; i++) {
      key = sourceSymbolKeys[i];
      if (excluded.indexOf(key) >= 0) continue;
      if (!Object.prototype.propertyIsEnumerable.call(source, key)) continue;
      target[key] = source[key];
    }
  }

  return target;
}

module.exports = _objectWithoutProperties;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectWithoutPropertiesLoose.js":
/*!******************************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectWithoutPropertiesLoose.js ***!
  \******************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _objectWithoutPropertiesLoose(source, excluded) {
  if (source == null) return {};
  var target = {};
  var sourceKeys = Object.keys(source);
  var key, i;

  for (i = 0; i < sourceKeys.length; i++) {
    key = sourceKeys[i];
    if (excluded.indexOf(key) >= 0) continue;
    target[key] = source[key];
  }

  return target;
}

module.exports = _objectWithoutPropertiesLoose;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/possibleConstructorReturn.js":
/*!***************************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/possibleConstructorReturn.js ***!
  \***************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var _typeof = __webpack_require__(/*! @babel/runtime/helpers/typeof */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/typeof.js")["default"];

var assertThisInitialized = __webpack_require__(/*! ./assertThisInitialized.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/assertThisInitialized.js");

function _possibleConstructorReturn(self, call) {
  if (call && (_typeof(call) === "object" || typeof call === "function")) {
    return call;
  }

  return assertThisInitialized(self);
}

module.exports = _possibleConstructorReturn;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/setPrototypeOf.js":
/*!****************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/setPrototypeOf.js ***!
  \****************************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray.js":
/*!***************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray.js ***!
  \***************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var arrayWithHoles = __webpack_require__(/*! ./arrayWithHoles.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/arrayWithHoles.js");

var iterableToArrayLimit = __webpack_require__(/*! ./iterableToArrayLimit.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/iterableToArrayLimit.js");

var unsupportedIterableToArray = __webpack_require__(/*! ./unsupportedIterableToArray.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/unsupportedIterableToArray.js");

var nonIterableRest = __webpack_require__(/*! ./nonIterableRest.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/nonIterableRest.js");

function _slicedToArray(arr, i) {
  return arrayWithHoles(arr) || iterableToArrayLimit(arr, i) || unsupportedIterableToArray(arr, i) || nonIterableRest();
}

module.exports = _slicedToArray;
module.exports["default"] = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/typeof.js":
/*!********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/typeof.js ***!
  \********************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/unsupportedIterableToArray.js":
/*!****************************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/unsupportedIterableToArray.js ***!
  \****************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var arrayLikeToArray = __webpack_require__(/*! ./arrayLikeToArray.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/arrayLikeToArray.js");

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

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/wrapNativeSuper.js":
/*!*****************************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/wrapNativeSuper.js ***!
  \*****************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var getPrototypeOf = __webpack_require__(/*! ./getPrototypeOf.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/getPrototypeOf.js");

var setPrototypeOf = __webpack_require__(/*! ./setPrototypeOf.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/setPrototypeOf.js");

var isNativeFunction = __webpack_require__(/*! ./isNativeFunction.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/isNativeFunction.js");

var construct = __webpack_require__(/*! ./construct.js */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/construct.js");

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

/***/ "../../../node_modules/.pnpm/classnames@2.3.1/node_modules/classnames/index.js":
/*!**************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/classnames@2.3.1/node_modules/classnames/index.js ***!
  \**************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

var __WEBPACK_AMD_DEFINE_ARRAY__, __WEBPACK_AMD_DEFINE_RESULT__;/*!
  Copyright (c) 2018 Jed Watson.
  Licensed under the MIT License (MIT), see
  http://jedwatson.github.io/classnames
*/
/* global define */

(function () {
	'use strict';

	var hasOwn = {}.hasOwnProperty;

	function classNames() {
		var classes = [];

		for (var i = 0; i < arguments.length; i++) {
			var arg = arguments[i];
			if (!arg) continue;

			var argType = typeof arg;

			if (argType === 'string' || argType === 'number') {
				classes.push(arg);
			} else if (Array.isArray(arg)) {
				if (arg.length) {
					var inner = classNames.apply(null, arg);
					if (inner) {
						classes.push(inner);
					}
				}
			} else if (argType === 'object') {
				if (arg.toString === Object.prototype.toString) {
					for (var key in arg) {
						if (hasOwn.call(arg, key) && arg[key]) {
							classes.push(key);
						}
					}
				} else {
					classes.push(arg.toString());
				}
			}
		}

		return classes.join(' ');
	}

	if ( true && module.exports) {
		classNames.default = classNames;
		module.exports = classNames;
	} else if (true) {
		// register as 'classnames', consistent with npm package name
		!(__WEBPACK_AMD_DEFINE_ARRAY__ = [], __WEBPACK_AMD_DEFINE_RESULT__ = (function () {
			return classNames;
		}).apply(exports, __WEBPACK_AMD_DEFINE_ARRAY__),
				__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__));
	} else {}
}());


/***/ }),

/***/ "../../../node_modules/.pnpm/object-assign@4.1.1/node_modules/object-assign/index.js":
/*!********************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/object-assign@4.1.1/node_modules/object-assign/index.js ***!
  \********************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/checkPropTypes.js":
/*!************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/checkPropTypes.js ***!
  \************************************************************************************************************************/
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
  var ReactPropTypesSecret = __webpack_require__(/*! ./lib/ReactPropTypesSecret */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/lib/ReactPropTypesSecret.js");
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

/***/ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/factoryWithThrowingShims.js":
/*!**********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/factoryWithThrowingShims.js ***!
  \**********************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var ReactPropTypesSecret = __webpack_require__(/*! ./lib/ReactPropTypesSecret */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/lib/ReactPropTypesSecret.js");

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

/***/ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/factoryWithTypeCheckers.js":
/*!*********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/factoryWithTypeCheckers.js ***!
  \*********************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */



var ReactIs = __webpack_require__(/*! react-is */ "../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/index.js");
var assign = __webpack_require__(/*! object-assign */ "../../../node_modules/.pnpm/object-assign@4.1.1/node_modules/object-assign/index.js");

var ReactPropTypesSecret = __webpack_require__(/*! ./lib/ReactPropTypesSecret */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/lib/ReactPropTypesSecret.js");
var checkPropTypes = __webpack_require__(/*! ./checkPropTypes */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/checkPropTypes.js");

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

/***/ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js":
/*!***************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js ***!
  \***************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

/**
 * Copyright (c) 2013-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

if (undefined !== 'production') {
  var ReactIs = __webpack_require__(/*! react-is */ "../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/index.js");

  // By explicitly using `prop-types` you are opting into new development behavior.
  // http://fb.me/prop-types-in-prod
  var throwOnDirectAccess = true;
  module.exports = __webpack_require__(/*! ./factoryWithTypeCheckers */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/factoryWithTypeCheckers.js")(ReactIs.isElement, throwOnDirectAccess);
} else {
  // By explicitly using `prop-types` you are opting into new production behavior.
  // http://fb.me/prop-types-in-prod
  module.exports = __webpack_require__(/*! ./factoryWithThrowingShims */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/factoryWithThrowingShims.js")();
}


/***/ }),

/***/ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/lib/ReactPropTypesSecret.js":
/*!**********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/lib/ReactPropTypesSecret.js ***!
  \**********************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/cjs/react-is.development.js":
/*!*******************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/cjs/react-is.development.js ***!
  \*******************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/cjs/react-is.production.min.js":
/*!**********************************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/cjs/react-is.production.min.js ***!
  \**********************************************************************************************************************************/
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

/***/ "../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/index.js":
/*!************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/index.js ***!
  \************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


if (undefined === 'production') {
  module.exports = __webpack_require__(/*! ./cjs/react-is.production.min.js */ "../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/cjs/react-is.production.min.js");
} else {
  module.exports = __webpack_require__(/*! ./cjs/react-is.development.js */ "../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/cjs/react-is.development.js");
}


/***/ }),

/***/ "../../js-packages/components/components/automattic-byline-logo/index.jsx":
/*!*********************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/components/components/automattic-byline-logo/index.jsx ***!
  \*********************************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "default", function() { return AutomatticBylineLogo; });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/extends */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/extends.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectWithoutProperties */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectWithoutProperties.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! classnames */ "../../../node_modules/.pnpm/classnames@2.3.1/node_modules/classnames/index.js");
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(classnames__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);



/**
 * External dependencies
 */



/**
 * AutomatticBylineLogo component definition.
 *
 * @param {object} props - Component properties.
 * @param {string} props.title - Title for SVG.
 * @param {number} props.height - Height for SVG.
 * @param {number} props.className - Additional className for the a wrapper, default only: `jp-automattic-byline-logo`.
 *
 * @returns {React.Component} AutomatticBylineLogo component.
 */

function AutomatticBylineLogo(_ref) {
  var _ref$title = _ref.title,
      title = _ref$title === void 0 ? Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__["__"])('An Automattic Airline', 'jetpack') : _ref$title,
      _ref$height = _ref.height,
      height = _ref$height === void 0 ? 7 : _ref$height,
      _ref$className = _ref.className,
      className = _ref$className === void 0 ? '' : _ref$className,
      otherProps = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default()(_ref, ["title", "height", "className"]);

  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement("svg", _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({
    role: "img",
    x: "0",
    y: "0",
    viewBox: "0 0 935 38.2",
    enableBackground: "new 0 0 935 38.2",
    "aria-labelledby": "jp-automattic-byline-logo-title",
    height: height,
    className: classnames__WEBPACK_IMPORTED_MODULE_3___default()('jp-automattic-byline-logo', className)
  }, otherProps), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement("title", {
    id: "jp-automattic-byline-logo-title"
  }, title), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement("path", {
    d: "M317.1 38.2c-12.6 0-20.7-9.1-20.7-18.5v-1.2c0-9.6 8.2-18.5 20.7-18.5 12.6 0 20.8 8.9 20.8 18.5v1.2C337.9 29.1 329.7 38.2 317.1 38.2zM331.2 18.6c0-6.9-5-13-14.1-13s-14 6.1-14 13v0.9c0 6.9 5 13.1 14 13.1s14.1-6.2 14.1-13.1V18.6zM175 36.8l-4.7-8.8h-20.9l-4.5 8.8h-7L157 1.3h5.5L182 36.8H175zM159.7 8.2L152 23.1h15.7L159.7 8.2zM212.4 38.2c-12.7 0-18.7-6.9-18.7-16.2V1.3h6.6v20.9c0 6.6 4.3 10.5 12.5 10.5 8.4 0 11.9-3.9 11.9-10.5V1.3h6.7V22C231.4 30.8 225.8 38.2 212.4 38.2zM268.6 6.8v30h-6.7v-30h-15.5V1.3h37.7v5.5H268.6zM397.3 36.8V8.7l-1.8 3.1 -14.9 25h-3.3l-14.7-25 -1.8-3.1v28.1h-6.5V1.3h9.2l14 24.4 1.7 3 1.7-3 13.9-24.4h9.1v35.5H397.3zM454.4 36.8l-4.7-8.8h-20.9l-4.5 8.8h-7l19.2-35.5h5.5l19.5 35.5H454.4zM439.1 8.2l-7.7 14.9h15.7L439.1 8.2zM488.4 6.8v30h-6.7v-30h-15.5V1.3h37.7v5.5H488.4zM537.3 6.8v30h-6.7v-30h-15.5V1.3h37.7v5.5H537.3zM569.3 36.8V4.6c2.7 0 3.7-1.4 3.7-3.4h2.8v35.5L569.3 36.8 569.3 36.8zM628 11.3c-3.2-2.9-7.9-5.7-14.2-5.7 -9.5 0-14.8 6.5-14.8 13.3v0.7c0 6.7 5.4 13 15.3 13 5.9 0 10.8-2.8 13.9-5.7l4 4.2c-3.9 3.8-10.5 7.1-18.3 7.1 -13.4 0-21.6-8.7-21.6-18.3v-1.2c0-9.6 8.9-18.7 21.9-18.7 7.5 0 14.3 3.1 18 7.1L628 11.3zM321.5 12.4c1.2 0.8 1.5 2.4 0.8 3.6l-6.1 9.4c-0.8 1.2-2.4 1.6-3.6 0.8l0 0c-1.2-0.8-1.5-2.4-0.8-3.6l6.1-9.4C318.7 11.9 320.3 11.6 321.5 12.4L321.5 12.4z"
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement("path", {
    d: "M37.5 36.7l-4.7-8.9H11.7l-4.6 8.9H0L19.4 0.8H25l19.7 35.9H37.5zM22 7.8l-7.8 15.1h15.9L22 7.8zM82.8 36.7l-23.3-24 -2.3-2.5v26.6h-6.7v-36H57l22.6 24 2.3 2.6V0.8h6.7v35.9H82.8z"
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement("path", {
    d: "M719.9 37l-4.8-8.9H694l-4.6 8.9h-7.1l19.5-36h5.6l19.8 36H719.9zM704.4 8l-7.8 15.1h15.9L704.4 8zM733 37V1h6.8v36H733zM781 37c-1.8 0-2.6-2.5-2.9-5.8l-0.2-3.7c-0.2-3.6-1.7-5.1-8.4-5.1h-12.8V37H750V1h19.6c10.8 0 15.7 4.3 15.7 9.9 0 3.9-2 7.7-9 9 7 0.5 8.5 3.7 8.6 7.9l0.1 3c0.1 2.5 0.5 4.3 2.2 6.1V37H781zM778.5 11.8c0-2.6-2.1-5.1-7.9-5.1h-13.8v10.8h14.4c5 0 7.3-2.4 7.3-5.2V11.8zM794.8 37V1h6.8v30.4h28.2V37H794.8zM836.7 37V1h6.8v36H836.7zM886.2 37l-23.4-24.1 -2.3-2.5V37h-6.8V1h6.5l22.7 24.1 2.3 2.6V1h6.8v36H886.2zM902.3 37V1H935v5.6h-26v9.2h20v5.5h-20v10.1h26V37H902.3z"
  }));
}

/***/ }),

/***/ "../../js-packages/components/components/jetpack-footer/index.jsx":
/*!*************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/components/components/jetpack-footer/index.jsx ***!
  \*************************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "default", function() { return JetpackFooter; });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/extends */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/extends.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectWithoutProperties */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectWithoutProperties.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! classnames */ "../../../node_modules/.pnpm/classnames@2.3.1/node_modules/classnames/index.js");
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(classnames__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _automattic_byline_logo__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../automattic-byline-logo */ "../../js-packages/components/components/automattic-byline-logo/index.jsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./style.scss */ "../../js-packages/components/components/jetpack-footer/style.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_style_scss__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _jetpack_logo__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../jetpack-logo */ "../../js-packages/components/components/jetpack-logo/index.jsx");



/**
 * External dependencies
 */



/**
 * Internal dependencies
 */




/**
 * JetpackFooter component definition.
 *
 * @param {object} props - Component properties.
 * @param {object} props.a8cLogoHref - Link for 'An Automattic Airline'.
 * @param {object} props.moduleName - Name of the module, e.g. 'Jetpack Search'.
 * @param {object} props.className - additional className of the wrapper, default only: `jp-dashboard-footer`.
 *
 * @returns {React.Component} JetpackFooter component.
 */

function JetpackFooter(_ref) {
  var a8cLogoHref = _ref.a8cLogoHref,
      _ref$moduleName = _ref.moduleName,
      moduleName = _ref$moduleName === void 0 ? Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Jetpack', 'jetpack') : _ref$moduleName,
      _ref$className = _ref.className,
      className = _ref$className === void 0 ? '' : _ref$className,
      otherProps = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default()(_ref, ["a8cLogoHref", "moduleName", "className"]);

  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement("div", _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({
    className: classnames__WEBPACK_IMPORTED_MODULE_4___default()('jp-dashboard-footer', className)
  }, otherProps), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement("div", {
    className: "jp-dashboard-footer__footer-left"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement(_jetpack_logo__WEBPACK_IMPORTED_MODULE_7__["default"], {
    logoColor: "#000",
    showText: false,
    height: "16",
    className: "jp-dashboard-footer__jetpack-symbol"
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement("span", {
    className: "jp-dashboard-footer__module-name"
  }, moduleName)), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement("div", {
    className: "jp-dashboard-footer__footer-right"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement("a", {
    href: a8cLogoHref
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2___default.a.createElement(_automattic_byline_logo__WEBPACK_IMPORTED_MODULE_5__["default"], null))));
}

/***/ }),

/***/ "../../js-packages/components/components/jetpack-footer/style.scss":
/*!**************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/components/components/jetpack-footer/style.scss ***!
  \**************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ "../../js-packages/components/components/jetpack-logo/index.jsx":
/*!***********************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/components/components/jetpack-logo/index.jsx ***!
  \***********************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/extends */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/extends.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectWithoutProperties */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectWithoutProperties.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/classCallCheck */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/classCallCheck.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/createClass */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/createClass.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/inherits */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/inherits.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/createSuper */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/createSuper.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/defineProperty */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/defineProperty.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_7__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_8__);
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! classnames */ "../../../node_modules/.pnpm/classnames@2.3.1/node_modules/classnames/index.js");
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(classnames__WEBPACK_IMPORTED_MODULE_9__);








/**
 * External dependencies
 */




var JetpackLogo = /*#__PURE__*/function (_React$Component) {
  _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_4___default()(JetpackLogo, _React$Component);

  var _super = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_5___default()(JetpackLogo);

  function JetpackLogo() {
    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_2___default()(this, JetpackLogo);

    return _super.apply(this, arguments);
  }

  _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_3___default()(JetpackLogo, [{
    key: "render",
    value: function render() {
      var _this$props = this.props,
          logoColor = _this$props.logoColor,
          showText = _this$props.showText,
          className = _this$props.className,
          otherProps = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default()(_this$props, ["logoColor", "showText", "className"]);

      var viewBox = showText ? '0 0 118 32' : '0 0 32 32';
      return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default.a.createElement("svg", _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({
        xmlns: "http://www.w3.org/2000/svg",
        x: "0px",
        y: "0px",
        viewBox: viewBox,
        className: classnames__WEBPACK_IMPORTED_MODULE_9___default()('jetpack-logo', className)
      }, otherProps), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default.a.createElement("path", {
        fill: logoColor,
        d: "M16,0C7.2,0,0,7.2,0,16s7.2,16,16,16s16-7.2,16-16S24.8,0,16,0z M15,19H7l8-16V19z M17,29V13h8L17,29z"
      }), showText && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default.a.createElement(react__WEBPACK_IMPORTED_MODULE_8__["Fragment"], null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default.a.createElement("path", {
        d: "M41.3,26.6c-0.5-0.7-0.9-1.4-1.3-2.1c2.3-1.4,3-2.5,3-4.6V8h-3V6h6v13.4C46,22.8,45,24.8,41.3,26.6z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default.a.createElement("path", {
        d: "M65,18.4c0,1.1,0.8,1.3,1.4,1.3c0.5,0,2-0.2,2.6-0.4v2.1c-0.9,0.3-2.5,0.5-3.7,0.5c-1.5,0-3.2-0.5-3.2-3.1V12H60v-2h2.1V7.1 H65V10h4v2h-4V18.4z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default.a.createElement("path", {
        d: "M71,10h3v1.3c1.1-0.8,1.9-1.3,3.3-1.3c2.5,0,4.5,1.8,4.5,5.6s-2.2,6.3-5.8,6.3c-0.9,0-1.3-0.1-2-0.3V28h-3V10z M76.5,12.3 c-0.8,0-1.6,0.4-2.5,1.2v5.9c0.6,0.1,0.9,0.2,1.8,0.2c2,0,3.2-1.3,3.2-3.9C79,13.4,78.1,12.3,76.5,12.3z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default.a.createElement("path", {
        d: "M93,22h-3v-1.5c-0.9,0.7-1.9,1.5-3.5,1.5c-1.5,0-3.1-1.1-3.1-3.2c0-2.9,2.5-3.4,4.2-3.7l2.4-0.3v-0.3c0-1.5-0.5-2.3-2-2.3 c-0.7,0-2.3,0.5-3.7,1.1L84,11c1.2-0.4,3-1,4.4-1c2.7,0,4.6,1.4,4.6,4.7L93,22z M90,16.4l-2.2,0.4c-0.7,0.1-1.4,0.5-1.4,1.6 c0,0.9,0.5,1.4,1.3,1.4s1.5-0.5,2.3-1V16.4z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default.a.createElement("path", {
        d: "M104.5,21.3c-1.1,0.4-2.2,0.6-3.5,0.6c-4.2,0-5.9-2.4-5.9-5.9c0-3.7,2.3-6,6.1-6c1.4,0,2.3,0.2,3.2,0.5V13 c-0.8-0.3-2-0.6-3.2-0.6c-1.7,0-3.2,0.9-3.2,3.6c0,2.9,1.5,3.8,3.3,3.8c0.9,0,1.9-0.2,3.2-0.7V21.3z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default.a.createElement("path", {
        d: "M110,15.2c0.2-0.3,0.2-0.8,3.8-5.2h3.7l-4.6,5.7l5,6.3h-3.7l-4.2-5.8V22h-3V6h3V15.2z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default.a.createElement("path", {
        d: "M58.5,21.3c-1.5,0.5-2.7,0.6-4.2,0.6c-3.6,0-5.8-1.8-5.8-6c0-3.1,1.9-5.9,5.5-5.9s4.9,2.5,4.9,4.9c0,0.8,0,1.5-0.1,2h-7.3 c0.1,2.5,1.5,2.8,3.6,2.8c1.1,0,2.2-0.3,3.4-0.7C58.5,19,58.5,21.3,58.5,21.3z M56,15c0-1.4-0.5-2.9-2-2.9c-1.4,0-2.3,1.3-2.4,2.9 C51.6,15,56,15,56,15z"
      })));
    }
  }]);

  return JetpackLogo;
}(react__WEBPACK_IMPORTED_MODULE_8___default.a.Component);

_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_6___default()(JetpackLogo, "propTypes", {
  className: prop_types__WEBPACK_IMPORTED_MODULE_7___default.a.string,
  width: prop_types__WEBPACK_IMPORTED_MODULE_7___default.a.number,
  height: prop_types__WEBPACK_IMPORTED_MODULE_7___default.a.number,
  showText: prop_types__WEBPACK_IMPORTED_MODULE_7___default.a.bool,
  logoColor: prop_types__WEBPACK_IMPORTED_MODULE_7___default.a.string
});

_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_6___default()(JetpackLogo, "defaultProps", {
  className: '',
  height: 32,
  showText: true,
  logoColor: '#00BE28'
});

/* harmony default export */ __webpack_exports__["default"] = (JetpackLogo);

/***/ }),

/***/ "../../js-packages/components/index.jsx":
/*!***********************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/components/index.jsx ***!
  \***********************************************************************************/
/*! exports provided: JetpackLogo, getRedirectUrl, AutomatticBylineLogo, JetpackFooter */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _components_jetpack_logo__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./components/jetpack-logo */ "../../js-packages/components/components/jetpack-logo/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "JetpackLogo", function() { return _components_jetpack_logo__WEBPACK_IMPORTED_MODULE_0__["default"]; });

/* harmony import */ var _tools_jp_redirect__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./tools/jp-redirect */ "../../js-packages/components/tools/jp-redirect/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "getRedirectUrl", function() { return _tools_jp_redirect__WEBPACK_IMPORTED_MODULE_1__["default"]; });

/* harmony import */ var _components_automattic_byline_logo__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./components/automattic-byline-logo */ "../../js-packages/components/components/automattic-byline-logo/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "AutomatticBylineLogo", function() { return _components_automattic_byline_logo__WEBPACK_IMPORTED_MODULE_2__["default"]; });

/* harmony import */ var _components_jetpack_footer__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./components/jetpack-footer */ "../../js-packages/components/components/jetpack-footer/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "JetpackFooter", function() { return _components_jetpack_footer__WEBPACK_IMPORTED_MODULE_3__["default"]; });

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





/***/ }),

/***/ "../../js-packages/components/tools/jp-redirect/index.jsx":
/*!*****************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/components/tools/jp-redirect/index.jsx ***!
  \*****************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "default", function() { return getRedirectUrl; });
/* global jetpack_redirects */

/**
 * Builds an URL using the jetpack.com/redirect/ service
 *
 * If $source is a simple slug, it will be sent using the source query parameter. e.g. jetpack.com/redirect/?source=slug
 *
 * If $source is a full URL, starting with https://, it will be sent using the url query parameter. e.g. jetpack.com/redirect/?url=https://wordpress.com
 *
 * Note: if using full URL, query parameters and anchor must be passed in args. Any querystring of url fragment in the URL will be discarded.
 *
 * @since 0.2.0
 *
 * @param {string}  source - The URL handler registered in the server or the full destination URL (starting with https://).
 * @param {object}  args - {
 *
 * Additional arguments to build the url.  This is not a complete list as any argument passed here will be sent to as a query parameter to the Redirect server. These parameters will not necessarily be passed over to the final destination URL. If you want to add a parameter to the final destination URL, use the `query` argument.
 *
 * @type {string}  site URL of the current site. Will default to the value of jetpack_redirects.currentSiteRawUrl, if available.
 * @type {string}  path Additional path to be appended to the URL
 * @type {string}  query Query parameters to be added to the final destination URL. should be in query string format (e.g. 'key=value&foo=bar').
 * @type {string}  anchor Anchor to be added to the URL
 * }
 *
 * @returns {string} The redirect URL
 */
function getRedirectUrl(source) {
  var args = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
  var queryVars = {};
  var calypsoEnv;

  if (typeof window !== 'undefined') {
    var _window$Initial_State;

    calypsoEnv = (_window$Initial_State = window.Initial_State) === null || _window$Initial_State === void 0 ? void 0 : _window$Initial_State.calypsoEnv;
  }

  if (source.search('https://') === 0) {
    var parsedUrl = new URL(source); // discard any query and fragments.

    source = "https://".concat(parsedUrl.host).concat(parsedUrl.pathname);
    queryVars.url = encodeURIComponent(source);
  } else {
    queryVars.source = encodeURIComponent(source);
  }

  Object.keys(args).map(function (argName) {
    queryVars[argName] = encodeURIComponent(args[argName]);
  });

  if (!Object.keys(queryVars).includes('site') && typeof jetpack_redirects !== 'undefined' && jetpack_redirects.hasOwnProperty('currentSiteRawUrl')) {
    queryVars.site = jetpack_redirects.currentSiteRawUrl;
  }

  if (calypsoEnv) {
    queryVars.calypso_env = calypsoEnv;
  }

  var queryString = Object.keys(queryVars).map(function (key) {
    return key + '=' + queryVars[key];
  }).join('&');
  return "https://jetpack.com/redirect/?" + queryString;
}

/***/ }),

/***/ "../../js-packages/connection/components/connect-button/index.jsx":
/*!*************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/connect-button/index.jsx ***!
  \*************************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../tools/jetpack-rest-api-client */ "../../js-packages/connection/tools/jetpack-rest-api-client/index.jsx");
/* harmony import */ var _connect_user__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../connect-user */ "../../js-packages/connection/components/connect-user/index.jsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./style.scss */ "../../js-packages/connection/components/connect-button/style.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(_style_scss__WEBPACK_IMPORTED_MODULE_7__);


/**
 * External dependencies
 */




/**
 * Internal dependencies
 */




/**
 * The RNA connection component.
 *
 * @param {object} props -- The properties.
 * @param {string} props.connectLabel -- The "Connect" button label.
 * @param {string} props.apiRoot -- API root URL, required.
 * @param {string} props.apiNonce -- API Nonce, required.
 * @param {string} props.registrationNonce -- Separate registration nonce, required.
 * @param {Function} props.onRegistered -- The callback to be called upon registration success.
 * @param {string} props.redirectUri -- The redirect admin URI.
 * @param {string} props.from -- Where the connection request is coming from.
 * @param {Function} props.statusCallback -- Callback to pull connection status from the component.
 *
 * @returns {React.Component} The RNA connection component.
 */

var ConnectButton = function ConnectButton(props) {
  var _useState = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(false),
      _useState2 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState, 2),
      isRegistering = _useState2[0],
      setIsRegistering = _useState2[1];

  var _useState3 = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(false),
      _useState4 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState3, 2),
      isUserConnecting = _useState4[0],
      setIsUserConnecting = _useState4[1];

  var _useState5 = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(null),
      _useState6 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState5, 2),
      authorizationUrl = _useState6[0],
      setAuthorizationUrl = _useState6[1];

  var _useState7 = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(false),
      _useState8 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState7, 2),
      isFetchingConnectionStatus = _useState8[0],
      setIsFetchingConnectionStatus = _useState8[1];

  var _useState9 = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])({}),
      _useState10 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState9, 2),
      connectionStatus = _useState10[0],
      setConnectionStatus = _useState10[1];

  var apiRoot = props.apiRoot,
      apiNonce = props.apiNonce,
      connectLabel = props.connectLabel,
      onRegistered = props.onRegistered,
      registrationNonce = props.registrationNonce,
      redirectUri = props.redirectUri,
      from = props.from,
      statusCallback = props.statusCallback;
  /**
   * Initialize the REST API.
   */

  Object(react__WEBPACK_IMPORTED_MODULE_1__["useEffect"])(function () {
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_5__["default"].setApiRoot(apiRoot);
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_5__["default"].setApiNonce(apiNonce);
  }, [apiRoot, apiNonce]);
  /**
   * Fetch the connection status on the first render.
   * To be only run once.
   */

  Object(react__WEBPACK_IMPORTED_MODULE_1__["useEffect"])(function () {
    setIsFetchingConnectionStatus(true);
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_5__["default"].fetchSiteConnectionStatus().then(function (response) {
      setIsFetchingConnectionStatus(false);
      setConnectionStatus(response);
    })["catch"](function (error) {
      setIsFetchingConnectionStatus(false);
      throw error;
    });
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  /**
   * Initialize the site registration process.
   */

  var registerSite = Object(react__WEBPACK_IMPORTED_MODULE_1__["useCallback"])(function (e) {
    e && e.preventDefault();

    if (connectionStatus.isRegistered) {
      setIsUserConnecting(true);
      return;
    }

    setIsRegistering(true);
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_5__["default"].registerSite(registrationNonce, redirectUri).then(function (response) {
      setIsRegistering(false);

      if (onRegistered) {
        onRegistered(response);
      }

      setAuthorizationUrl(response.authorizeUrl);
      setIsUserConnecting(true);
    })["catch"](function (error) {
      setIsRegistering(false);
      throw error;
    });
  }, [setIsRegistering, setAuthorizationUrl, connectionStatus, onRegistered, registrationNonce, redirectUri]);
  var statusCallbackWrapped = Object(react__WEBPACK_IMPORTED_MODULE_1__["useCallback"])(function () {
    if (statusCallback && {}.toString.call(statusCallback) === '[object Function]') {
      return statusCallback(connectionStatus);
    }
  }, [connectionStatus, statusCallback]);
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-connect-button"
  }, statusCallbackWrapped(), isFetchingConnectionStatus && "Loading...", (!connectionStatus.isRegistered || !connectionStatus.isUserConnected) && !isFetchingConnectionStatus && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__["Button"], {
    className: "jp-connect-button--button",
    label: connectLabel,
    onClick: registerSite,
    isPrimary: true,
    disabled: isRegistering || isUserConnecting
  }, connectLabel), isUserConnecting && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_connect_user__WEBPACK_IMPORTED_MODULE_6__["default"], {
    connectUrl: authorizationUrl,
    redirectUri: redirectUri,
    from: from
  }));
};

ConnectButton.propTypes = {
  connectLabel: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string,
  apiRoot: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string.isRequired,
  apiNonce: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string.isRequired,
  onRegistered: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.func,
  from: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string,
  redirectUri: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string.isRequired,
  registrationNonce: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string.isRequired
};
ConnectButton.defaultProps = {
  connectLabel: Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__["__"])('Connect', 'jetpack')
};
/* harmony default export */ __webpack_exports__["default"] = (ConnectButton);

/***/ }),

/***/ "../../js-packages/connection/components/connect-button/style.scss":
/*!**************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/connect-button/style.scss ***!
  \**************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ "../../js-packages/connection/components/connect-screen/image-slider.jsx":
/*!********************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/connect-screen/image-slider.jsx ***!
  \********************************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_1__);
/**
 * External dependencies
 */


/**
 * The ImageSlider component.
 *
 * @param {object} props -- The properties.
 * @param {Array} props.images -- Images to display on the right side.
 * @param {string} props.assetBaseUrl -- The assets base URL
 *
 * @returns {React.Component} The `ImageSlider` component.
 */

var ImageSlider = function ImageSlider(props) {
  var images = props.images,
      assetBaseUrl = props.assetBaseUrl;

  if (!images.length) {
    return null;
  }

  var imagesHTML = [];
  images.forEach(function (image) {
    return imagesHTML.push( /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement(react__WEBPACK_IMPORTED_MODULE_0___default.a.Fragment, null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("img", {
      src: assetBaseUrl + image,
      alt: ""
    })));
  });
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("div", {
    className: "jp-connect-screen--image-slider"
  }, imagesHTML);
};

ImageSlider.propTypes = {
  images: prop_types__WEBPACK_IMPORTED_MODULE_1___default.a.arrayOf(prop_types__WEBPACK_IMPORTED_MODULE_1___default.a.string).isRequired,
  assetBaseUrl: prop_types__WEBPACK_IMPORTED_MODULE_1___default.a.string
};
ImageSlider.defaultProps = {
  assetBaseUrl: ''
};
/* harmony default export */ __webpack_exports__["default"] = (ImageSlider);

/***/ }),

/***/ "../../js-packages/connection/components/connect-screen/index.jsx":
/*!*************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/connect-screen/index.jsx ***!
  \*************************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @automattic/jetpack-components */ "../../js-packages/components/index.jsx");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _connect_button__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../connect-button */ "../../js-packages/connection/components/connect-button/index.jsx");
/* harmony import */ var _image_slider__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./image-slider */ "../../js-packages/connection/components/connect-screen/image-slider.jsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./style.scss */ "../../js-packages/connection/components/connect-screen/style.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(_style_scss__WEBPACK_IMPORTED_MODULE_8__);


/**
 * External dependencies
 */





/**
 * Internal dependencies
 */




/**
 * The Connection Screen component.
 *
 * @param {object} props -- The properties.
 * @param {string} props.apiRoot -- API root URL, required.
 * @param {string} props.apiNonce -- API Nonce, required.
 * @param {string} props.registrationNonce -- Separate registration nonce, required.
 * @param {string} props.redirectUri -- The redirect admin URI.
 * @param {string} props.from -- Where the connection request is coming from.
 * @param {string} props.title -- Page title.
 * @param {Function} props.statusCallback -- Callback to pull connection status from the component.
 * @param {Array} props.images -- Images to display on the right side.
 * @param {string} props.assetBaseUrl -- The assets base URL.
 *
 * @returns {React.Component} The `ConnectScreen` component.
 */

var ConnectScreen = function ConnectScreen(props) {
  var title = props.title,
      apiRoot = props.apiRoot,
      apiNonce = props.apiNonce,
      registrationNonce = props.registrationNonce,
      from = props.from,
      redirectUri = props.redirectUri,
      statusCallback = props.statusCallback,
      images = props.images,
      children = props.children,
      assetBaseUrl = props.assetBaseUrl;
  var showImageSlider = images.length;

  var _useState = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])({}),
      _useState2 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState, 2),
      connectionStatus = _useState2[0],
      setConnectionStatus = _useState2[1];

  var statusHandler = Object(react__WEBPACK_IMPORTED_MODULE_1__["useCallback"])(function (status) {
    setConnectionStatus(status);

    if (statusCallback && {}.toString.call(statusCallback) === '[object Function]') {
      return statusCallback(status);
    }
  }, [statusCallback, setConnectionStatus]);
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: 'jp-connect-screen' + (showImageSlider ? ' jp-connect-screen--two-columns' : '') + (connectionStatus.hasOwnProperty('isRegistered') ? '' : ' jp-connect-screen--loading')
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-connect-screen--left"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_4__["JetpackLogo"], null), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("h2", null, title), children, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_connect_button__WEBPACK_IMPORTED_MODULE_6__["default"], {
    apiRoot: apiRoot,
    apiNonce: apiNonce,
    registrationNonce: registrationNonce,
    from: from,
    redirectUri: redirectUri,
    statusCallback: statusHandler,
    connectLabel: Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Set up Jetpack', 'jetpack')
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-connnect-screen--tos"
  }, Object(_wordpress_element__WEBPACK_IMPORTED_MODULE_5__["createInterpolateElement"])(Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('By clicking the button above, you agree to our <tosLink>Terms of Service</tosLink> and to <shareDetailsLink>share details</shareDetailsLink> with WordPress.com.', 'jetpack'), {
    tosLink: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("a", {
      href: Object(_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_4__["getRedirectUrl"])('wpcom-tos'),
      rel: "noopener noreferrer",
      target: "_blank"
    }),
    shareDetailsLink: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("a", {
      href: Object(_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_4__["getRedirectUrl"])('jetpack-support-what-data-does-jetpack-sync'),
      rel: "noopener noreferrer",
      target: "_blank"
    })
  }))), showImageSlider && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-connect-screen--right"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_image_slider__WEBPACK_IMPORTED_MODULE_7__["default"], {
    images: images,
    assetBaseUrl: assetBaseUrl
  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-connect-screen--clearfix"
  }));
};

ConnectScreen.propTypes = {
  title: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string,
  body: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string,
  apiRoot: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string.isRequired,
  apiNonce: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string.isRequired,
  from: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string,
  redirectUri: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string.isRequired,
  registrationNonce: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string.isRequired,
  statusCallback: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.func,
  images: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.arrayOf(prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string),
  assetBaseUrl: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string
};
ConnectScreen.defaultProps = {
  title: Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Over 5 million WordPress sites are faster and more secure', 'jetpack'),
  images: []
};
/* harmony default export */ __webpack_exports__["default"] = (ConnectScreen);

/***/ }),

/***/ "../../js-packages/connection/components/connect-screen/style.scss":
/*!**************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/connect-screen/style.scss ***!
  \**************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ "../../js-packages/connection/components/connect-user/index.jsx":
/*!***********************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/connect-user/index.jsx ***!
  \***********************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../tools/jetpack-rest-api-client */ "../../js-packages/connection/tools/jetpack-rest-api-client/index.jsx");


/**
 * External dependencies
 */


/**
 * Internal dependencies
 */


/**
 * The user connection component.
 *
 * @param {object} props -- The properties.
 * @param {Function} props.redirectFunc -- The redirect function (`window.location.assign()` by default).
 * @param {string} props.connectUrl -- The authorization URL (no-iframe).
 * @param {string} props.redirectUri -- The redirect admin URI.
 * @param {string} props.from -- Where the connection request is coming from.
 *
 * @returns {null} -- Nothing to return.
 */

var ConnectUser = function ConnectUser(props) {
  var redirectFunc = props.redirectFunc,
      connectUrl = props.connectUrl,
      redirectUri = props.redirectUri,
      from = props.from;

  var _useState = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(null),
      _useState2 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState, 2),
      authorizationUrl = _useState2[0],
      setAuthorizationUrl = _useState2[1];

  if (connectUrl && connectUrl !== authorizationUrl) {
    setAuthorizationUrl(connectUrl);
  }
  /**
   * Fetch the authorization URL on the first render.
   * To be only run once.
   */


  Object(react__WEBPACK_IMPORTED_MODULE_1__["useEffect"])(function () {
    if (!authorizationUrl) {
      _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_3__["default"].fetchAuthorizationUrl(redirectUri).then(function (response) {
        return setAuthorizationUrl(response.authorizeUrl);
      })["catch"](function (error) {
        throw error;
      });
    }
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  if (!authorizationUrl) {
    return null;
  }

  redirectFunc(authorizationUrl + (from ? (authorizationUrl.includes('?') ? '&' : '?') + 'from=' + encodeURIComponent(from) : ''));
  return null;
};

ConnectUser.propTypes = {
  connectUrl: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string,
  redirectUri: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string.isRequired,
  from: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string,
  redirectFunc: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.func
};
ConnectUser.defaultProps = {
  redirectFunc: function redirectFunc(url) {
    return window.location.assign(url);
  }
};
/* harmony default export */ __webpack_exports__["default"] = (ConnectUser);

/***/ }),

/***/ "../../js-packages/connection/components/connection-status-card/index.jsx":
/*!*********************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/connection-status-card/index.jsx ***!
  \*********************************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _connect_user__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../connect-user */ "../../js-packages/connection/components/connect-user/index.jsx");
/* harmony import */ var _disconnect_dialog__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../disconnect-dialog */ "../../js-packages/connection/components/disconnect-dialog/index.jsx");
/* harmony import */ var _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../tools/jetpack-rest-api-client */ "../../js-packages/connection/tools/jetpack-rest-api-client/index.jsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./style.scss */ "../../js-packages/connection/components/connection-status-card/style.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(_style_scss__WEBPACK_IMPORTED_MODULE_8__);


/**
 * External dependencies
 */




/**
 * Internal dependencies
 */





/**
 * The RNA Connection Status Card component.
 *
 * @param {object}   props -- The properties.
 * @param {string}   props.apiRoot -- API root URL, required.
 * @param {string}   props.apiNonce -- API Nonce, required.
 * @param {boolean}  props.isRegistered -- Whether a site level connection has already been established, required. If not, the component will not render.
 * @param {string}   props.isUserConnected -- Whether the current user has connected their WordPress.com account, required.
 * @param {string}   props.redirectUri -- The redirect admin URI after the user has connected their WordPress.com account.
 * @param {string}   props.title -- The Card title.
 * @param {string}   props.connectionInfoText -- The text that will be displayed under the title, containing info how to leverage the connection.
 * @param {Function} props.onDisconnected -- The callback to be called upon disconnection success.
 *
 * @returns {React.Component} The `ConnectionStatusCard` component.
 */

var ConnectionStatusCard = function ConnectionStatusCard(props) {
  var apiRoot = props.apiRoot,
      apiNonce = props.apiNonce,
      isRegistered = props.isRegistered,
      isUserConnected = props.isUserConnected,
      redirectUri = props.redirectUri,
      title = props.title,
      connectionInfoText = props.connectionInfoText,
      onDisconnected = props.onDisconnected;

  var _useState = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(false),
      _useState2 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState, 2),
      isFetchingConnectionData = _useState2[0],
      setIsFetchingConnectionData = _useState2[1];

  var _useState3 = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])({}),
      _useState4 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState3, 2),
      connectedUserData = _useState4[0],
      setConnectedUserData = _useState4[1];

  var _useState5 = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(false),
      _useState6 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState5, 2),
      isUserConnecting = _useState6[0],
      setIsUserConnecting = _useState6[1];

  var avatarRef = Object(react__WEBPACK_IMPORTED_MODULE_1__["useRef"])();
  /**
   * Initialize the REST API.
   */

  Object(react__WEBPACK_IMPORTED_MODULE_1__["useEffect"])(function () {
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_7__["default"].setApiRoot(apiRoot);
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_7__["default"].setApiNonce(apiNonce);
  }, [apiRoot, apiNonce]);
  /**
   * Fetch the connection data on the first render.
   * To be only run once.
   */

  Object(react__WEBPACK_IMPORTED_MODULE_1__["useEffect"])(function () {
    setIsFetchingConnectionData(true);
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_7__["default"].fetchSiteConnectionData().then(function (response) {
      var _response$currentUser, _response$currentUser2, _response$currentUser3;

      setIsFetchingConnectionData(false);
      setConnectedUserData((_response$currentUser = response.currentUser) === null || _response$currentUser === void 0 ? void 0 : _response$currentUser.wpcomUser);
      var avatar = (_response$currentUser2 = response.currentUser) === null || _response$currentUser2 === void 0 ? void 0 : (_response$currentUser3 = _response$currentUser2.wpcomUser) === null || _response$currentUser3 === void 0 ? void 0 : _response$currentUser3.avatar;

      if (avatar) {
        avatarRef.current.style.backgroundImage = "url('".concat(avatar, "')");
      }
    })["catch"](function (error) {
      setIsFetchingConnectionData(false);
      throw error;
    });
  }, [setIsFetchingConnectionData, setConnectedUserData]);
  var onDisconnectedCallback = Object(react__WEBPACK_IMPORTED_MODULE_1__["useCallback"])(function (e) {
    e && e.preventDefault();

    if (onDisconnected) {
      onDisconnected();
    }
  }, [onDisconnected]); // Prevent component from rendering if site is not connected.

  if (!isRegistered) {
    return null;
  }

  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-connection-status-card"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("h3", null, title), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("p", null, connectionInfoText), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-connection-status-card--status"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-connection-status-card--cloud"
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: 'jp-connection-status-card--line' + (isUserConnected ? '' : ' jp-connection-status-card--site-only')
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-connection-status-card--jetpack-logo"
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-connection-status-card--avatar",
    ref: avatarRef
  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("ul", {
    className: "jp-connection-status-card--list"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("li", {
    className: "jp-connection-status-card--list-item-success"
  }, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__["__"])('Site connected.', 'jetpack'), "\xA0", /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_disconnect_dialog__WEBPACK_IMPORTED_MODULE_6__["default"], {
    apiRoot: apiRoot,
    apiNonce: apiNonce,
    onDisconnected: onDisconnectedCallback
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("h2", null, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__["__"])('Jetpack is currently powering multiple products on your site.', 'jetpack'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("br", null), Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__["__"])('Once you disconnect Jetpack, these will no longer work.', 'jetpack')))), isUserConnected && !isFetchingConnectionData && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("li", {
    className: "jp-connection-status-card--list-item-success"
  }, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__["__"])('Logged in as', 'jetpack'), " ", connectedUserData === null || connectedUserData === void 0 ? void 0 : connectedUserData.display_name), !isUserConnected && !isFetchingConnectionData && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("li", {
    className: "jp-connection-status-card--list-item-error"
  }, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__["__"])('Your WordPress.com account is not connected.', 'jetpack'))), !isUserConnected && !isFetchingConnectionData && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__["Button"], {
    isPrimary: true,
    disabled: isUserConnecting,
    onClick: setIsUserConnecting,
    className: "jp-connection-status-card--btn-connect-user"
  }, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__["__"])('Connect your WordPress.com account', 'jetpack')), isUserConnecting && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_connect_user__WEBPACK_IMPORTED_MODULE_5__["default"], {
    redirectUri: redirectUri
  }));
};

ConnectionStatusCard.propTypes = {
  apiRoot: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string.isRequired,
  apiNonce: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string.isRequired,
  isRegistered: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.bool.isRequired,
  isUserConnected: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.bool.isRequired,
  redirectUri: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string.isRequired,
  title: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string,
  connectionInfoText: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.string,
  onDisconnected: prop_types__WEBPACK_IMPORTED_MODULE_4___default.a.func
};
ConnectionStatusCard.defaultProps = {
  title: Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__["__"])('Connection', 'jetpack'),
  connectionInfoText: Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__["__"])('Leverages the Jetpack Cloud for more features on your side.', 'jetpack')
};
/* harmony default export */ __webpack_exports__["default"] = (ConnectionStatusCard);

/***/ }),

/***/ "../../js-packages/connection/components/connection-status-card/style.scss":
/*!**********************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/connection-status-card/style.scss ***!
  \**********************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

/***/ "../../js-packages/connection/components/disconnect-dialog/index.jsx":
/*!****************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/disconnect-dialog/index.jsx ***!
  \****************************************************************************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/slicedToArray.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @automattic/jetpack-components */ "../../js-packages/components/index.jsx");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../tools/jetpack-rest-api-client */ "../../js-packages/connection/tools/jetpack-rest-api-client/index.jsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./style.scss */ "../../js-packages/connection/components/disconnect-dialog/style.scss");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(_style_scss__WEBPACK_IMPORTED_MODULE_8__);


/**
 * External dependencies
 */






/**
 * Internal dependencies
 */



/**
 * The RNA Disconnect Dialog component.
 *
 * @param {object} props -- The properties.
 * @param {string} props.apiRoot -- API root URL, required.
 * @param {string} props.apiNonce -- API Nonce, required.
 * @param {string} props.title -- The modal title.
 * @param {Function} props.onDisconnected -- The callback to be called upon disconnection success.
 * @param {Function} props.onError -- The callback to be called upon disconnection failure.
 * @param {Function} props.errorMessage -- The error message to display upon disconnection failure.
 *
 * @returns {React.Component} The `DisconnectDialog` component.
 */

var DisconnectDialog = function DisconnectDialog(props) {
  var _useState = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(false),
      _useState2 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState, 2),
      isOpen = _useState2[0],
      setOpen = _useState2[1];

  var _useState3 = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(false),
      _useState4 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState3, 2),
      isDisconnecting = _useState4[0],
      setIsDisconnecting = _useState4[1];

  var _useState5 = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(false),
      _useState6 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState5, 2),
      isDisconnected = _useState6[0],
      setIsDisconnected = _useState6[1];

  var _useState7 = Object(react__WEBPACK_IMPORTED_MODULE_1__["useState"])(false),
      _useState8 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState7, 2),
      disconnectError = _useState8[0],
      setDisconnectError = _useState8[1];

  var apiRoot = props.apiRoot,
      apiNonce = props.apiNonce,
      title = props.title,
      onDisconnected = props.onDisconnected,
      onError = props.onError,
      errorMessage = props.errorMessage,
      children = props.children;
  /**
   * Initialize the REST API.
   */

  Object(react__WEBPACK_IMPORTED_MODULE_1__["useEffect"])(function () {
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_7__["default"].setApiRoot(apiRoot);
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_7__["default"].setApiNonce(apiNonce);
  }, [apiRoot, apiNonce]);
  /**
   * Open the Disconnect Dialog.
   */

  var openModal = Object(react__WEBPACK_IMPORTED_MODULE_1__["useCallback"])(function (e) {
    e && e.preventDefault();
    setOpen(true);
  }, [setOpen]);
  /**
   * Close the Disconnect Dialog.
   */

  var closeModal = Object(react__WEBPACK_IMPORTED_MODULE_1__["useCallback"])(function (e) {
    e && e.preventDefault();
    setOpen(false);
  }, [setOpen]);
  /**
   * Disconnect - Triggered upon clicking the 'Disconnect' button.
   */

  var disconnect = Object(react__WEBPACK_IMPORTED_MODULE_1__["useCallback"])(function (e) {
    e && e.preventDefault();
    setDisconnectError(false);
    setIsDisconnecting(true);
    _tools_jetpack_rest_api_client__WEBPACK_IMPORTED_MODULE_7__["default"].disconnectSite().then(function () {
      setIsDisconnecting(false);
      setIsDisconnected(true);
    })["catch"](function (error) {
      setIsDisconnecting(false);
      setDisconnectError(error);

      if (onError) {
        onError(error);
      }
    });
  }, [setIsDisconnecting, setIsDisconnected, setDisconnectError, onError]);
  /**
   * Close modal and fire 'onDisconnected' callback if exists.
   * Triggered upon clicking the 'Back To WordPress' button.
   */

  var backToWordpress = Object(react__WEBPACK_IMPORTED_MODULE_1__["useCallback"])(function (e) {
    e && e.preventDefault();

    if (onDisconnected) {
      onDisconnected();
    }

    closeModal();
  }, [onDisconnected, closeModal]);
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(react__WEBPACK_IMPORTED_MODULE_1___default.a.Fragment, null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__["Button"], {
    variant: "link",
    onClick: openModal,
    className: "jp-disconnect-dialog__link"
  }, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Disconnect', 'jetpack')), isOpen && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__["Modal"], {
    title: "",
    contentLabel: title,
    aria: {
      labelledby: 'jp-disconnect-dialog__heading'
    },
    onRequestClose: closeModal,
    shouldCloseOnClickOutside: false,
    shouldCloseOnEsc: false,
    isDismissible: false,
    className: 'jp-disconnect-dialog' + (isDisconnected ? ' jp-disconnect-dialog__success' : '')
  }, !isDisconnected && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-disconnect-dialog__content"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("h1", {
    id: "jp-disconnect-dialog__heading"
  }, title), children), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-disconnect-dialog__actions"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-row"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "lg-col-span-8 md-col-span-8 sm-col-span-4"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("p", null, Object(_wordpress_element__WEBPACK_IMPORTED_MODULE_6__["createInterpolateElement"])(Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('<strong>Need help?</strong> Learn more about the <jpConnectionInfoLink>Jetpack connection</jpConnectionInfoLink> or <jpSupportLink>contact Jetpack support</jpSupportLink>', 'jetpack'), {
    strong: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("strong", null),
    jpConnectionInfoLink: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("a", {
      href: Object(_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_5__["getRedirectUrl"])('why-the-wordpress-com-connection-is-important-for-jetpack'),
      rel: "noopener noreferrer",
      target: "_blank",
      className: "jp-disconnect-dialog__link"
    }),
    jpSupportLink: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("a", {
      href: Object(_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_5__["getRedirectUrl"])('jetpack-support'),
      rel: "noopener noreferrer",
      target: "_blank",
      className: "jp-disconnect-dialog__link"
    })
  }))), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", {
    className: "jp-disconnect-dialog__button-wrap lg-col-span-4 md-col-span-8 sm-col-span-4"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__["Button"], {
    isPrimary: true,
    disabled: isDisconnecting,
    onClick: closeModal,
    className: "jp-disconnect-dialog__btn-dismiss"
  }, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Stay connected', 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__["Button"], {
    isPrimary: true,
    disabled: isDisconnecting,
    onClick: disconnect,
    className: "jp-disconnect-dialog__btn-disconnect"
  }, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Disconnect', 'jetpack')))), disconnectError && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("p", {
    className: "jp-disconnect-dialog__error"
  }, errorMessage))), isDisconnected && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("div", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_5__["JetpackLogo"], null), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("h1", null, Object(_wordpress_element__WEBPACK_IMPORTED_MODULE_6__["createInterpolateElement"])(Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Jetpack has been <br/>successfully disconnected.', 'jetpack'), {
    br: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement("br", null)
  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default.a.createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__["Button"], {
    isPrimary: true,
    onClick: backToWordpress,
    className: "jp-disconnect-dialog__btn-back-to-wp"
  }, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Back to WordPress', 'jetpack')))));
};

DisconnectDialog.propTypes = {
  apiRoot: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string.isRequired,
  apiNonce: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string.isRequired,
  title: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string,
  onDisconnected: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.func,
  onError: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.func,
  errorMessage: prop_types__WEBPACK_IMPORTED_MODULE_2___default.a.string
};
DisconnectDialog.defaultProps = {
  title: Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Are you sure you want to disconnect?', 'jetpack'),
  errorMessage: Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Failed to disconnect. Please try again.', 'jetpack')
};
/* harmony default export */ __webpack_exports__["default"] = (DisconnectDialog);

/***/ }),

/***/ "../../js-packages/connection/components/disconnect-dialog/style.scss":
/*!*****************************************************************************************************************!*\
  !*** /home/runner/work/jetpack/jetpack/projects/js-packages/connection/components/disconnect-dialog/style.scss ***!
  \*****************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin

/***/ }),

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
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
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
    if (!iframeRef.current || e.source !== iframeRef.current.contentWindow) {
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
  height: '300',
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
/*! exports provided: ConnectScreen, ConnectButton, InPlaceConnection, ConnectUser, ConnectionStatusCard, DisconnectDialog, thirdPartyCookiesFallbackHelper */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _components_connect_screen__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./components/connect-screen */ "../../js-packages/connection/components/connect-screen/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "ConnectScreen", function() { return _components_connect_screen__WEBPACK_IMPORTED_MODULE_0__["default"]; });

/* harmony import */ var _components_connect_button__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./components/connect-button */ "../../js-packages/connection/components/connect-button/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "ConnectButton", function() { return _components_connect_button__WEBPACK_IMPORTED_MODULE_1__["default"]; });

/* harmony import */ var _components_in_place_connection__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./components/in-place-connection */ "../../js-packages/connection/components/in-place-connection/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "InPlaceConnection", function() { return _components_in_place_connection__WEBPACK_IMPORTED_MODULE_2__["default"]; });

/* harmony import */ var _components_connect_user__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./components/connect-user */ "../../js-packages/connection/components/connect-user/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "ConnectUser", function() { return _components_connect_user__WEBPACK_IMPORTED_MODULE_3__["default"]; });

/* harmony import */ var _components_connection_status_card__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./components/connection-status-card */ "../../js-packages/connection/components/connection-status-card/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "ConnectionStatusCard", function() { return _components_connection_status_card__WEBPACK_IMPORTED_MODULE_4__["default"]; });

/* harmony import */ var _components_disconnect_dialog__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./components/disconnect-dialog */ "../../js-packages/connection/components/disconnect-dialog/index.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "DisconnectDialog", function() { return _components_disconnect_dialog__WEBPACK_IMPORTED_MODULE_5__["default"]; });

/* harmony import */ var _helpers_third_party_cookies_fallback__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./helpers/third-party-cookies-fallback */ "../../js-packages/connection/helpers/third-party-cookies-fallback.jsx");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "thirdPartyCookiesFallbackHelper", function() { return _helpers_third_party_cookies_fallback__WEBPACK_IMPORTED_MODULE_6__["default"]; });

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
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/classCallCheck */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/classCallCheck.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/inherits */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/inherits.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/createSuper */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/createSuper.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/wrapNativeSuper */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/wrapNativeSuper.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! lodash */ "lodash");
/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(lodash__WEBPACK_IMPORTED_MODULE_4__);





/**
 * External dependencies
 */

/**
 * Helps create new custom error classes to better notify upper layers.
 *
 * @param {string} name - The Error name that will be available in `Error.name`.
 * @returns {Error}      a new custom error class.
 */

function createCustomError(name) {
  var CustomError = /*#__PURE__*/function (_Error) {
    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1___default()(CustomError, _Error);

    var _super = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2___default()(CustomError);

    function CustomError() {
      var _this;

      _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0___default()(this, CustomError);

      for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
        args[_key] = arguments[_key];
      }

      _this = _super.call.apply(_super, [this].concat(args));
      _this.name = name;
      return _this;
    }

    return CustomError;
  }( /*#__PURE__*/_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3___default()(Error));

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
    },
    fetchAuthorizationUrl: function fetchAuthorizationUrl(redirectUri) {
      return getRequest("".concat(apiRoot, "jetpack/v4/connection/authorize_url?no_iframe=1&redirect_uri=").concat(encodeURIComponent(redirectUri)), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchSiteConnectionStatus: function fetchSiteConnectionStatus() {
      return getRequest("".concat(apiRoot, "jetpack/v4/connection"), getParams).then(parseJsonResponse);
    },
    fetchSiteConnectionData: function fetchSiteConnectionData() {
      return getRequest("".concat(apiRoot, "jetpack/v4/connection/data"), getParams).then(parseJsonResponse);
    },
    disconnectSite: function disconnectSite() {
      return postRequest("".concat(apiRoot, "jetpack/v4/connection"), postParams, {
        body: JSON.stringify({
          isActive: false
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
    return fetch(route, Object(lodash__WEBPACK_IMPORTED_MODULE_4__["assign"])({}, params, body))["catch"](catchNetworkErrors);
  }

  Object(lodash__WEBPACK_IMPORTED_MODULE_4__["assign"])(this, methods);
}
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

  return response.json()["catch"](function (e) {
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
  return response.json()["catch"](function (e) {
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

/***/ "./_inc/actions/connection-status.js":
/*!*******************************************!*\
  !*** ./_inc/actions/connection-status.js ***!
  \*******************************************/
/*! exports provided: SET_CONNECTION_STATUS, default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "SET_CONNECTION_STATUS", function() { return SET_CONNECTION_STATUS; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "default", function() { return connectionStatusActions; });
var SET_CONNECTION_STATUS = 'SET_CONNECTION_STATUS';
var connectionStatusActions = {
  setConnectionStatus: function setConnectionStatus(connectionStatus) {
    return {
      type: SET_CONNECTION_STATUS,
      connectionStatus: connectionStatus
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
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectSpread2 */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectSpread2.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _connection_status__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./connection-status */ "./_inc/actions/connection-status.js");


/**
 * Internal dependencies
 */


var actions = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, _connection_status__WEBPACK_IMPORTED_MODULE_1__["default"]);

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

/***/ "./_inc/components/admin/assets/connect-right.png":
/*!********************************************************!*\
  !*** ./_inc/components/admin/assets/connect-right.png ***!
  \********************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__.p + "images/connect-right-d53058f843bf7071f1859f89639782c1.png";

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
/* harmony import */ var _assets_connect_right_png__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./assets/connect-right.png */ "./_inc/components/admin/assets/connect-right.png");
/* harmony import */ var _assets_connect_right_png__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(_assets_connect_right_png__WEBPACK_IMPORTED_MODULE_7__);
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
  var APINonce = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useSelect"])(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]).getAPINonce();
  }, []);
  var APIRoot = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useSelect"])(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]).getAPIRoot();
  }, []);
  var registrationNonce = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useSelect"])(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]).getRegistrationNonce();
  }, []);
  var assetBuildUrl = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useSelect"])(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]).getAssetBuildUrl();
  }, []);
  var connectionStatus = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useSelect"])(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]).getConnectionStatus();
  }, []);

  var _useDispatch = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__["useDispatch"])(_store__WEBPACK_IMPORTED_MODULE_4__["STORE_ID"]),
      setConnectionStatus = _useDispatch.setConnectionStatus;

  var statusCallback = Object(react__WEBPACK_IMPORTED_MODULE_0__["useCallback"])(function (status) {
    setConnectionStatus(status);
  }, [setConnectionStatus]);
  var onDisconnectedCallback = Object(react__WEBPACK_IMPORTED_MODULE_0__["useCallback"])(function () {
    setConnectionStatus({
      isActive: false,
      isRegistered: false,
      isUserConnected: false
    });
  }, [setConnectionStatus]);
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement(react__WEBPACK_IMPORTED_MODULE_0___default.a.Fragment, null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement(_header__WEBPACK_IMPORTED_MODULE_5__["default"], null), connectionStatus.isRegistered && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement(_automattic_jetpack_connection__WEBPACK_IMPORTED_MODULE_2__["ConnectionStatusCard"], {
    isRegistered: connectionStatus.isRegistered,
    isUserConnected: connectionStatus.isUserConnected,
    apiRoot: APIRoot,
    apiNonce: APINonce,
    onDisconnected: onDisconnectedCallback,
    redirectUri: "tools.php?page=wpcom-connection-manager"
  }), !connectionStatus.isRegistered && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement(_automattic_jetpack_connection__WEBPACK_IMPORTED_MODULE_2__["ConnectScreen"], {
    apiRoot: APIRoot,
    apiNonce: APINonce,
    registrationNonce: registrationNonce,
    from: "connection-ui",
    redirectUri: "tools.php?page=wpcom-connection-manager",
    statusCallback: statusCallback,
    images: [_assets_connect_right_png__WEBPACK_IMPORTED_MODULE_7___default.a],
    assetBaseUrl: assetBuildUrl
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("p", null, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])("Secure and speed up your site for free with Jetpack's powerful WordPress tools.", 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("ul", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("li", null, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Measure your impact with beautiful stats', 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("li", null, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Speed up your site with optimized images', 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("li", null, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Protect your site against bot attacs', 'jetpacks')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("li", null, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Get notifications if your site goes offline', 'jetpacks')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default.a.createElement("li", null, Object(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__["__"])('Enhance your site with dozens of other features', 'jetpack')))));
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

/***/ "./_inc/reducers/assets.js":
/*!*********************************!*\
  !*** ./_inc/reducers/assets.js ***!
  \*********************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
var assets = function assets() {
  var state = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
  return state;
};

/* harmony default export */ __webpack_exports__["default"] = (assets);

/***/ }),

/***/ "./_inc/reducers/connection-status.js":
/*!********************************************!*\
  !*** ./_inc/reducers/connection-status.js ***!
  \********************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _actions_connection_status__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../actions/connection-status */ "./_inc/actions/connection-status.js");
/**
 * Internal dependencies
 */


var connectionStatus = function connectionStatus() {
  var state = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
  var action = arguments.length > 1 ? arguments[1] : undefined;

  switch (action.type) {
    case _actions_connection_status__WEBPACK_IMPORTED_MODULE_0__["SET_CONNECTION_STATUS"]:
      return action.connectionStatus;
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
/* harmony import */ var _assets__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./assets */ "./_inc/reducers/assets.js");
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */




var reducer = Object(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__["combineReducers"])({
  connectionStatus: _connection_status__WEBPACK_IMPORTED_MODULE_1__["default"],
  API: _api__WEBPACK_IMPORTED_MODULE_2__["default"],
  assets: _assets__WEBPACK_IMPORTED_MODULE_3__["default"]
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

/***/ "./_inc/selectors/assets.js":
/*!**********************************!*\
  !*** ./_inc/selectors/assets.js ***!
  \**********************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
var assetsSelectors = {
  getAssetBuildUrl: function getAssetBuildUrl(state) {
    return state.assets.buildUrl || null;
  }
};
/* harmony default export */ __webpack_exports__["default"] = (assetsSelectors);

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
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! /home/runner/work/jetpack/jetpack/node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectSpread2 */ "../../../node_modules/.pnpm/@babel+runtime@7.14.0/node_modules/@babel/runtime/helpers/objectSpread2.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _connection_status__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./connection-status */ "./_inc/selectors/connection-status.js");
/* harmony import */ var _api__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./api */ "./_inc/selectors/api.js");
/* harmony import */ var _assets__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./assets */ "./_inc/selectors/assets.js");


/**
 * Internal dependencies
 */




var selectors = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_14_0_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, _connection_status__WEBPACK_IMPORTED_MODULE_1__["default"]), _api__WEBPACK_IMPORTED_MODULE_2__["default"]), _assets__WEBPACK_IMPORTED_MODULE_3__["default"]);

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

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/*! no static exports found */
/***/ (function(module, exports) {

(function() { module.exports = window["wp"]["element"]; }());

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