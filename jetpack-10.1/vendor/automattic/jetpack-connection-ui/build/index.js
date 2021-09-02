/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/arrayLikeToArray.js":
/*!*****************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/arrayLikeToArray.js ***!
  \*****************************************************************************************************************/
/***/ ((module) => {

function _arrayLikeToArray(arr, len) {
  if (len == null || len > arr.length) len = arr.length;

  for (var i = 0, arr2 = new Array(len); i < len; i++) {
    arr2[i] = arr[i];
  }

  return arr2;
}

module.exports = _arrayLikeToArray;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/arrayWithHoles.js":
/*!***************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/arrayWithHoles.js ***!
  \***************************************************************************************************************/
/***/ ((module) => {

function _arrayWithHoles(arr) {
  if (Array.isArray(arr)) return arr;
}

module.exports = _arrayWithHoles;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/assertThisInitialized.js":
/*!**********************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/assertThisInitialized.js ***!
  \**********************************************************************************************************************/
/***/ ((module) => {

function _assertThisInitialized(self) {
  if (self === void 0) {
    throw new ReferenceError("this hasn't been initialised - super() hasn't been called");
  }

  return self;
}

module.exports = _assertThisInitialized;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/classCallCheck.js":
/*!***************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/classCallCheck.js ***!
  \***************************************************************************************************************/
/***/ ((module) => {

function _classCallCheck(instance, Constructor) {
  if (!(instance instanceof Constructor)) {
    throw new TypeError("Cannot call a class as a function");
  }
}

module.exports = _classCallCheck;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/construct.js":
/*!**********************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/construct.js ***!
  \**********************************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

var setPrototypeOf = __webpack_require__(/*! ./setPrototypeOf.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/setPrototypeOf.js");

var isNativeReflectConstruct = __webpack_require__(/*! ./isNativeReflectConstruct.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js");

function _construct(Parent, args, Class) {
  if (isNativeReflectConstruct()) {
    module.exports = _construct = Reflect.construct;
    module.exports.default = module.exports, module.exports.__esModule = true;
  } else {
    module.exports = _construct = function _construct(Parent, args, Class) {
      var a = [null];
      a.push.apply(a, args);
      var Constructor = Function.bind.apply(Parent, a);
      var instance = new Constructor();
      if (Class) setPrototypeOf(instance, Class.prototype);
      return instance;
    };

    module.exports.default = module.exports, module.exports.__esModule = true;
  }

  return _construct.apply(null, arguments);
}

module.exports = _construct;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createClass.js":
/*!************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createClass.js ***!
  \************************************************************************************************************/
/***/ ((module) => {

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
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createSuper.js":
/*!************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createSuper.js ***!
  \************************************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

var getPrototypeOf = __webpack_require__(/*! ./getPrototypeOf.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/getPrototypeOf.js");

var isNativeReflectConstruct = __webpack_require__(/*! ./isNativeReflectConstruct.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js");

var possibleConstructorReturn = __webpack_require__(/*! ./possibleConstructorReturn.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/possibleConstructorReturn.js");

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
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/defineProperty.js":
/*!***************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/defineProperty.js ***!
  \***************************************************************************************************************/
/***/ ((module) => {

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
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/extends.js":
/*!********************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/extends.js ***!
  \********************************************************************************************************/
/***/ ((module) => {

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

  module.exports.default = module.exports, module.exports.__esModule = true;
  return _extends.apply(this, arguments);
}

module.exports = _extends;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/getPrototypeOf.js":
/*!***************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/getPrototypeOf.js ***!
  \***************************************************************************************************************/
/***/ ((module) => {

function _getPrototypeOf(o) {
  module.exports = _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf : function _getPrototypeOf(o) {
    return o.__proto__ || Object.getPrototypeOf(o);
  };
  module.exports.default = module.exports, module.exports.__esModule = true;
  return _getPrototypeOf(o);
}

module.exports = _getPrototypeOf;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/inherits.js":
/*!*********************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/inherits.js ***!
  \*********************************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

var setPrototypeOf = __webpack_require__(/*! ./setPrototypeOf.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/setPrototypeOf.js");

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
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/isNativeFunction.js":
/*!*****************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/isNativeFunction.js ***!
  \*****************************************************************************************************************/
/***/ ((module) => {

function _isNativeFunction(fn) {
  return Function.toString.call(fn).indexOf("[native code]") !== -1;
}

module.exports = _isNativeFunction;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js":
/*!*************************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/isNativeReflectConstruct.js ***!
  \*************************************************************************************************************************/
/***/ ((module) => {

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
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/iterableToArrayLimit.js":
/*!*********************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/iterableToArrayLimit.js ***!
  \*********************************************************************************************************************/
/***/ ((module) => {

function _iterableToArrayLimit(arr, i) {
  var _i = arr == null ? null : typeof Symbol !== "undefined" && arr[Symbol.iterator] || arr["@@iterator"];

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
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/nonIterableRest.js":
/*!****************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/nonIterableRest.js ***!
  \****************************************************************************************************************/
/***/ ((module) => {

function _nonIterableRest() {
  throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
}

module.exports = _nonIterableRest;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2.js":
/*!**************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2.js ***!
  \**************************************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

var defineProperty = __webpack_require__(/*! ./defineProperty.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/defineProperty.js");

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
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectWithoutProperties.js":
/*!************************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectWithoutProperties.js ***!
  \************************************************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

var objectWithoutPropertiesLoose = __webpack_require__(/*! ./objectWithoutPropertiesLoose.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectWithoutPropertiesLoose.js");

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
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectWithoutPropertiesLoose.js":
/*!*****************************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectWithoutPropertiesLoose.js ***!
  \*****************************************************************************************************************************/
/***/ ((module) => {

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
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/possibleConstructorReturn.js":
/*!**************************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/possibleConstructorReturn.js ***!
  \**************************************************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

var _typeof = __webpack_require__(/*! @babel/runtime/helpers/typeof */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/typeof.js").default;

var assertThisInitialized = __webpack_require__(/*! ./assertThisInitialized.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/assertThisInitialized.js");

function _possibleConstructorReturn(self, call) {
  if (call && (_typeof(call) === "object" || typeof call === "function")) {
    return call;
  } else if (call !== void 0) {
    throw new TypeError("Derived constructors may only return object or undefined");
  }

  return assertThisInitialized(self);
}

module.exports = _possibleConstructorReturn;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/setPrototypeOf.js":
/*!***************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/setPrototypeOf.js ***!
  \***************************************************************************************************************/
/***/ ((module) => {

function _setPrototypeOf(o, p) {
  module.exports = _setPrototypeOf = Object.setPrototypeOf || function _setPrototypeOf(o, p) {
    o.__proto__ = p;
    return o;
  };

  module.exports.default = module.exports, module.exports.__esModule = true;
  return _setPrototypeOf(o, p);
}

module.exports = _setPrototypeOf;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray.js":
/*!**************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray.js ***!
  \**************************************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

var arrayWithHoles = __webpack_require__(/*! ./arrayWithHoles.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/arrayWithHoles.js");

var iterableToArrayLimit = __webpack_require__(/*! ./iterableToArrayLimit.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/iterableToArrayLimit.js");

var unsupportedIterableToArray = __webpack_require__(/*! ./unsupportedIterableToArray.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/unsupportedIterableToArray.js");

var nonIterableRest = __webpack_require__(/*! ./nonIterableRest.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/nonIterableRest.js");

function _slicedToArray(arr, i) {
  return arrayWithHoles(arr) || iterableToArrayLimit(arr, i) || unsupportedIterableToArray(arr, i) || nonIterableRest();
}

module.exports = _slicedToArray;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/typeof.js":
/*!*******************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/typeof.js ***!
  \*******************************************************************************************************/
/***/ ((module) => {

function _typeof(obj) {
  "@babel/helpers - typeof";

  if (typeof Symbol === "function" && typeof Symbol.iterator === "symbol") {
    module.exports = _typeof = function _typeof(obj) {
      return typeof obj;
    };

    module.exports.default = module.exports, module.exports.__esModule = true;
  } else {
    module.exports = _typeof = function _typeof(obj) {
      return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj;
    };

    module.exports.default = module.exports, module.exports.__esModule = true;
  }

  return _typeof(obj);
}

module.exports = _typeof;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/unsupportedIterableToArray.js":
/*!***************************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/unsupportedIterableToArray.js ***!
  \***************************************************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

var arrayLikeToArray = __webpack_require__(/*! ./arrayLikeToArray.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/arrayLikeToArray.js");

function _unsupportedIterableToArray(o, minLen) {
  if (!o) return;
  if (typeof o === "string") return arrayLikeToArray(o, minLen);
  var n = Object.prototype.toString.call(o).slice(8, -1);
  if (n === "Object" && o.constructor) n = o.constructor.name;
  if (n === "Map" || n === "Set") return Array.from(o);
  if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return arrayLikeToArray(o, minLen);
}

module.exports = _unsupportedIterableToArray;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/wrapNativeSuper.js":
/*!****************************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/wrapNativeSuper.js ***!
  \****************************************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

var getPrototypeOf = __webpack_require__(/*! ./getPrototypeOf.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/getPrototypeOf.js");

var setPrototypeOf = __webpack_require__(/*! ./setPrototypeOf.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/setPrototypeOf.js");

var isNativeFunction = __webpack_require__(/*! ./isNativeFunction.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/isNativeFunction.js");

var construct = __webpack_require__(/*! ./construct.js */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/construct.js");

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

  module.exports.default = module.exports, module.exports.__esModule = true;
  return _wrapNativeSuper(Class);
}

module.exports = _wrapNativeSuper;
module.exports.default = module.exports, module.exports.__esModule = true;

/***/ }),

/***/ "../../../node_modules/.pnpm/classnames@2.3.1/node_modules/classnames/index.js":
/*!*************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/classnames@2.3.1/node_modules/classnames/index.js ***!
  \*************************************************************************************/
/***/ ((module, exports) => {

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

/***/ "../../js-packages/components/components/spinner/style.scss":
/*!******************************************************************!*\
  !*** ../../js-packages/components/components/spinner/style.scss ***!
  \******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "../../js-packages/connection/components/connect-button/style.scss":
/*!*************************************************************************!*\
  !*** ../../js-packages/connection/components/connect-button/style.scss ***!
  \*************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "../../js-packages/connection/components/connect-screen/style.scss":
/*!*************************************************************************!*\
  !*** ../../js-packages/connection/components/connect-screen/style.scss ***!
  \*************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "../../js-packages/connection/components/connection-status-card/style.scss":
/*!*********************************************************************************!*\
  !*** ../../js-packages/connection/components/connection-status-card/style.scss ***!
  \*********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "../../js-packages/connection/components/disconnect-dialog/style.scss":
/*!****************************************************************************!*\
  !*** ../../js-packages/connection/components/disconnect-dialog/style.scss ***!
  \****************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./_inc/components/admin/style.scss":
/*!******************************************!*\
  !*** ./_inc/components/admin/style.scss ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./_inc/components/header/style.scss":
/*!*******************************************!*\
  !*** ./_inc/components/header/style.scss ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "../../../node_modules/.pnpm/object-assign@4.1.1/node_modules/object-assign/index.js":
/*!*******************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/object-assign@4.1.1/node_modules/object-assign/index.js ***!
  \*******************************************************************************************/
/***/ ((module) => {

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
/*!***********************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/checkPropTypes.js ***!
  \***********************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

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
/*!*********************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/factoryWithThrowingShims.js ***!
  \*********************************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

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
/*!********************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/factoryWithTypeCheckers.js ***!
  \********************************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

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
/*!**************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js ***!
  \**************************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

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
/*!*********************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/lib/ReactPropTypesSecret.js ***!
  \*********************************************************************************************************/
/***/ ((module) => {

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
/*!******************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/cjs/react-is.development.js ***!
  \******************************************************************************************************/
/***/ ((__unused_webpack_module, exports) => {

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
/*!*********************************************************************************************************!*\
  !*** ../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/cjs/react-is.production.min.js ***!
  \*********************************************************************************************************/
/***/ ((__unused_webpack_module, exports) => {

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
/*!***********************************************************************************!*\
  !*** ../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/index.js ***!
  \***********************************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

"use strict";


if (undefined === 'production') {
  module.exports = __webpack_require__(/*! ./cjs/react-is.production.min.js */ "../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/cjs/react-is.production.min.js");
} else {
  module.exports = __webpack_require__(/*! ./cjs/react-is.development.js */ "../../../node_modules/.pnpm/react-is@16.13.1/node_modules/react-is/cjs/react-is.development.js");
}


/***/ }),

/***/ "../../js-packages/api/index.jsx":
/*!***************************************!*\
  !*** ../../js-packages/api/index.jsx ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "JsonParseError": () => (/* binding */ JsonParseError),
/* harmony export */   "JsonParseAfterRedirectError": () => (/* binding */ JsonParseAfterRedirectError),
/* harmony export */   "Api404Error": () => (/* binding */ Api404Error),
/* harmony export */   "Api404AfterRedirectError": () => (/* binding */ Api404AfterRedirectError),
/* harmony export */   "FetchNetworkError": () => (/* binding */ FetchNetworkError),
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/classCallCheck */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/classCallCheck.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/inherits */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/inherits.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createSuper */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createSuper.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/wrapNativeSuper */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/wrapNativeSuper.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! lodash */ "lodash");
/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(lodash__WEBPACK_IMPORTED_MODULE_4__);





/**
 * External dependencies
 */

/**
 * Helps create new custom error classes to better notify upper layers.
 *
 * @param {string} name - the Error name that will be availble in Error.name
 * @returns {Error}      a new custom error class.
 */

function createCustomError(name) {
  var CustomError = /*#__PURE__*/function (_Error) {
    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_1___default()(CustomError, _Error);

    var _super = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_2___default()(CustomError);

    function CustomError() {
      var _this;

      _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0___default()(this, CustomError);

      for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
        args[_key] = arguments[_key];
      }

      _this = _super.call.apply(_super, [this].concat(args));
      _this.name = name;
      return _this;
    }

    return CustomError;
  }( /*#__PURE__*/_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_wrapNativeSuper__WEBPACK_IMPORTED_MODULE_3___default()(Error));

  return CustomError;
}

var JsonParseError = createCustomError('JsonParseError');
var JsonParseAfterRedirectError = createCustomError('JsonParseAfterRedirectError');
var Api404Error = createCustomError('Api404Error');
var Api404AfterRedirectError = createCustomError('Api404AfterRedirectError');
var FetchNetworkError = createCustomError('FetchNetworkError');
/**
 * Create a Jetpack Rest Api Client
 *
 * @param {string} root - The API root
 * @param {string} nonce - The API Nonce
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
    headers: (0,lodash__WEBPACK_IMPORTED_MODULE_4__.assign)({}, headers, {
      'Content-type': 'application/json'
    })
  },
      cacheBusterCallback = addCacheBuster;
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
        headers: (0,lodash__WEBPACK_IMPORTED_MODULE_4__.assign)({}, headers, {
          'Content-type': 'application/json'
        })
      };
    },
    setCacheBusterCallback: function setCacheBusterCallback(callback) {
      cacheBusterCallback = callback;
    },
    registerSite: function registerSite(registrationNonce, redirectUri) {
      var params = {
        registration_nonce: registrationNonce,
        no_iframe: true
      };

      if (null !== redirectUri) {
        params.redirect_uri = redirectUri;
      }

      return postRequest("".concat(apiRoot, "jetpack/v4/connection/register"), postParams, {
        body: JSON.stringify(params)
      }).then(checkStatus).then(parseJsonResponse);
    },
    fetchAuthorizationUrl: function fetchAuthorizationUrl(redirectUri) {
      return getRequest("".concat(apiRoot, "jetpack/v4/connection/authorize_url?no_iframe=1&redirect_uri=").concat(encodeURIComponent(redirectUri)), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchSiteConnectionData: function fetchSiteConnectionData() {
      return getRequest("".concat(apiRoot, "jetpack/v4/connection/data"), getParams).then(parseJsonResponse);
    },
    fetchSiteConnectionStatus: function fetchSiteConnectionStatus() {
      return getRequest("".concat(apiRoot, "jetpack/v4/connection"), getParams).then(parseJsonResponse);
    },
    fetchSiteConnectionTest: function fetchSiteConnectionTest() {
      return getRequest("".concat(apiRoot, "jetpack/v4/connection/test"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchUserConnectionData: function fetchUserConnectionData() {
      return getRequest("".concat(apiRoot, "jetpack/v4/connection/data"), getParams).then(parseJsonResponse);
    },
    fetchUserTrackingSettings: function fetchUserTrackingSettings() {
      return getRequest("".concat(apiRoot, "jetpack/v4/tracking/settings"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    updateUserTrackingSettings: function updateUserTrackingSettings(newSettings) {
      return postRequest("".concat(apiRoot, "jetpack/v4/tracking/settings"), postParams, {
        body: JSON.stringify(newSettings)
      }).then(checkStatus).then(parseJsonResponse);
    },
    disconnectSite: function disconnectSite() {
      return postRequest("".concat(apiRoot, "jetpack/v4/connection"), postParams, {
        body: JSON.stringify({
          isActive: false
        })
      }).then(checkStatus).then(parseJsonResponse);
    },
    fetchConnectUrl: function fetchConnectUrl() {
      return getRequest("".concat(apiRoot, "jetpack/v4/connection/url"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    unlinkUser: function unlinkUser() {
      return postRequest("".concat(apiRoot, "jetpack/v4/connection/user"), postParams, {
        body: JSON.stringify({
          linked: false
        })
      }).then(checkStatus).then(parseJsonResponse);
    },
    reconnect: function reconnect() {
      return postRequest("".concat(apiRoot, "jetpack/v4/connection/reconnect"), postParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchConnectedPlugins: function fetchConnectedPlugins() {
      return getRequest("".concat(apiRoot, "jetpack/v4/connection/plugins"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchModules: function fetchModules() {
      return getRequest("".concat(apiRoot, "jetpack/v4/module/all"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchModule: function fetchModule(slug) {
      return getRequest("".concat(apiRoot, "jetpack/v4/module/").concat(slug), getParams).then(checkStatus).then(parseJsonResponse);
    },
    activateModule: function activateModule(slug) {
      return postRequest("".concat(apiRoot, "jetpack/v4/module/").concat(slug, "/active"), postParams, {
        body: JSON.stringify({
          active: true
        })
      }).then(checkStatus).then(parseJsonResponse);
    },
    deactivateModule: function deactivateModule(slug) {
      return postRequest("".concat(apiRoot, "jetpack/v4/module/").concat(slug, "/active"), postParams, {
        body: JSON.stringify({
          active: false
        })
      });
    },
    updateModuleOptions: function updateModuleOptions(slug, newOptionValues) {
      return postRequest("".concat(apiRoot, "jetpack/v4/module/").concat(slug), postParams, {
        body: JSON.stringify(newOptionValues)
      }).then(checkStatus).then(parseJsonResponse);
    },
    updateSettings: function updateSettings(newOptionValues) {
      return postRequest("".concat(apiRoot, "jetpack/v4/settings"), postParams, {
        body: JSON.stringify(newOptionValues)
      }).then(checkStatus).then(parseJsonResponse);
    },
    getProtectCount: function getProtectCount() {
      return getRequest("".concat(apiRoot, "jetpack/v4/module/protect/data"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    resetOptions: function resetOptions(options) {
      return postRequest("".concat(apiRoot, "jetpack/v4/options/").concat(options), postParams, {
        body: JSON.stringify({
          reset: true
        })
      }).then(checkStatus).then(parseJsonResponse);
    },
    activateVaultPress: function activateVaultPress() {
      return postRequest("".concat(apiRoot, "jetpack/v4/plugins"), postParams, {
        body: JSON.stringify({
          slug: 'vaultpress',
          status: 'active'
        })
      }).then(checkStatus).then(parseJsonResponse);
    },
    getVaultPressData: function getVaultPressData() {
      return getRequest("".concat(apiRoot, "jetpack/v4/module/vaultpress/data"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    installPlugin: function installPlugin(slug, source) {
      var props = {
        slug: slug,
        status: 'active'
      };

      if (source) {
        props.source = source;
      }

      return postRequest("".concat(apiRoot, "jetpack/v4/plugins"), postParams, {
        body: JSON.stringify(props)
      }).then(checkStatus).then(parseJsonResponse);
    },
    activateAkismet: function activateAkismet() {
      return postRequest("".concat(apiRoot, "jetpack/v4/plugins"), postParams, {
        body: JSON.stringify({
          slug: 'akismet',
          status: 'active'
        })
      }).then(checkStatus).then(parseJsonResponse);
    },
    getAkismetData: function getAkismetData() {
      return getRequest("".concat(apiRoot, "jetpack/v4/module/akismet/data"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    checkAkismetKey: function checkAkismetKey() {
      return getRequest("".concat(apiRoot, "jetpack/v4/module/akismet/key/check"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    checkAkismetKeyTyped: function checkAkismetKeyTyped(apiKey) {
      return postRequest("".concat(apiRoot, "jetpack/v4/module/akismet/key/check"), postParams, {
        body: JSON.stringify({
          api_key: apiKey
        })
      }).then(checkStatus).then(parseJsonResponse);
    },
    fetchStatsData: function fetchStatsData(range) {
      return getRequest(statsDataUrl(range), getParams).then(checkStatus).then(parseJsonResponse).then(handleStatsResponseError);
    },
    getPluginUpdates: function getPluginUpdates() {
      return getRequest("".concat(apiRoot, "jetpack/v4/updates/plugins"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    getPlans: function getPlans() {
      return getRequest("".concat(apiRoot, "jetpack/v4/plans"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchSettings: function fetchSettings() {
      return getRequest("".concat(apiRoot, "jetpack/v4/settings"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    updateSetting: function updateSetting(updatedSetting) {
      return postRequest("".concat(apiRoot, "jetpack/v4/settings"), postParams, {
        body: JSON.stringify(updatedSetting)
      }).then(checkStatus).then(parseJsonResponse);
    },
    fetchSiteData: function fetchSiteData() {
      return getRequest("".concat(apiRoot, "jetpack/v4/site"), getParams).then(checkStatus).then(parseJsonResponse).then(function (body) {
        return JSON.parse(body.data);
      });
    },
    fetchSiteFeatures: function fetchSiteFeatures() {
      return getRequest("".concat(apiRoot, "jetpack/v4/site/features"), getParams).then(checkStatus).then(parseJsonResponse).then(function (body) {
        return JSON.parse(body.data);
      });
    },
    fetchSiteProducts: function fetchSiteProducts() {
      return getRequest("".concat(apiRoot, "jetpack/v4/site/products"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchSitePurchases: function fetchSitePurchases() {
      return getRequest("".concat(apiRoot, "jetpack/v4/site/purchases"), getParams).then(checkStatus).then(parseJsonResponse).then(function (body) {
        return JSON.parse(body.data);
      });
    },
    fetchSiteBenefits: function fetchSiteBenefits() {
      return getRequest("".concat(apiRoot, "jetpack/v4/site/benefits"), getParams).then(checkStatus).then(parseJsonResponse).then(function (body) {
        return JSON.parse(body.data);
      });
    },
    fetchSetupQuestionnaire: function fetchSetupQuestionnaire() {
      return getRequest("".concat(apiRoot, "jetpack/v4/setup/questionnaire"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchRecommendationsData: function fetchRecommendationsData() {
      return getRequest("".concat(apiRoot, "jetpack/v4/recommendations/data"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchRecommendationsProductSuggestions: function fetchRecommendationsProductSuggestions() {
      return getRequest("".concat(apiRoot, "jetpack/v4/recommendations/product-suggestions"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchRecommendationsUpsell: function fetchRecommendationsUpsell() {
      return getRequest("".concat(apiRoot, "jetpack/v4/recommendations/upsell"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    saveRecommendationsData: function saveRecommendationsData(data) {
      return postRequest("".concat(apiRoot, "jetpack/v4/recommendations/data"), postParams, {
        body: JSON.stringify({
          data: data
        })
      }).then(checkStatus);
    },
    fetchProducts: function fetchProducts() {
      return getRequest("".concat(apiRoot, "jetpack/v4/products"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchRewindStatus: function fetchRewindStatus() {
      return getRequest("".concat(apiRoot, "jetpack/v4/rewind"), getParams).then(checkStatus).then(parseJsonResponse).then(function (body) {
        return JSON.parse(body.data);
      });
    },
    fetchScanStatus: function fetchScanStatus() {
      return getRequest("".concat(apiRoot, "jetpack/v4/scan"), getParams).then(checkStatus).then(parseJsonResponse).then(function (body) {
        return JSON.parse(body.data);
      });
    },
    dismissJetpackNotice: function dismissJetpackNotice(notice) {
      return postRequest("".concat(apiRoot, "jetpack/v4/notice/").concat(notice), postParams, {
        body: JSON.stringify({
          dismissed: true
        })
      }).then(checkStatus).then(parseJsonResponse);
    },
    fetchPluginsData: function fetchPluginsData() {
      return getRequest("".concat(apiRoot, "jetpack/v4/plugins"), getParams).then(checkStatus).then(parseJsonResponse);
    },
    fetchVerifySiteGoogleStatus: function fetchVerifySiteGoogleStatus(keyringId) {
      var request = keyringId !== null ? getRequest("".concat(apiRoot, "jetpack/v4/verify-site/google/").concat(keyringId), getParams) : getRequest("".concat(apiRoot, "jetpack/v4/verify-site/google"), getParams);
      return request.then(checkStatus).then(parseJsonResponse);
    },
    verifySiteGoogle: function verifySiteGoogle(keyringId) {
      return postRequest("".concat(apiRoot, "jetpack/v4/verify-site/google"), postParams, {
        body: JSON.stringify({
          keyring_id: keyringId
        })
      }).then(checkStatus).then(parseJsonResponse);
    },
    sendMobileLoginEmail: function sendMobileLoginEmail() {
      return postRequest("".concat(apiRoot, "jetpack/v4/mobile/send-login-email"), postParams).then(checkStatus).then(parseJsonResponse);
    },
    submitSurvey: function submitSurvey(surveyResponse) {
      return postRequest("".concat(apiRoot, "jetpack/v4/marketing/survey"), postParams, {
        body: JSON.stringify(surveyResponse)
      }).then(checkStatus).then(parseJsonResponse);
    },
    saveSetupQuestionnaire: function saveSetupQuestionnaire(props) {
      return postRequest("".concat(apiRoot, "jetpack/v4/setup/questionnaire"), postParams, {
        body: JSON.stringify(props)
      }).then(checkStatus).then(parseJsonResponse);
    },
    updateLicensingError: function updateLicensingError(props) {
      return postRequest("".concat(apiRoot, "jetpack/v4/licensing/error"), postParams, {
        body: JSON.stringify(props)
      }).then(checkStatus).then(parseJsonResponse);
    },
    updateLicenseKey: function updateLicenseKey(license) {
      return postRequest("".concat(apiRoot, "jetpack/v4/licensing/set-license"), postParams, {
        body: JSON.stringify({
          license: license
        })
      }).then(checkStatus).then(parseJsonResponse);
    },
    updateRecommendationsStep: function updateRecommendationsStep(step) {
      return postRequest("".concat(apiRoot, "jetpack/v4/recommendations/step"), postParams, {
        body: JSON.stringify({
          step: step
        })
      }).then(checkStatus);
    }
  };
  /**
   * The default callback to add a cachebuster parameter to route
   *
   * @param {string} route - the route
   * @returns {string} - the route with the cachebuster appended
   */

  function addCacheBuster(route) {
    var parts = route.split('?'),
        query = parts.length > 1 ? parts[1] : '',
        args = query.length ? query.split('&') : [];
    args.push('_cacheBuster=' + new Date().getTime());
    return parts[0] + '?' + args.join('&');
  }
  /**
   * Generate a request promise for the route and params. Automatically adds a cachebuster.
   *
   * @param {string} route - the route
   * @param {object} params - the params
   * @returns {Promise<Response>} - the http request promise
   */


  function getRequest(route, params) {
    return fetch(cacheBusterCallback(route), params);
  }
  /**
   * Generate a POST request promise for the route and params. Automatically adds a cachebuster.
   *
   * @param {string} route - the route
   * @param {object} params - the params
   * @param {string} body - the body
   * @returns {Promise<Response>} - the http response promise
   */


  function postRequest(route, params, body) {
    return fetch(route, (0,lodash__WEBPACK_IMPORTED_MODULE_4__.assign)({}, params, body))["catch"](catchNetworkErrors);
  }
  /**
   * Returns the stats data URL for the given date range
   *
   * @param {string} range - the range
   * @returns {string} - the stats URL
   */


  function statsDataUrl(range) {
    var url = "".concat(apiRoot, "jetpack/v4/module/stats/data");

    if (url.indexOf('?') !== -1) {
      url = url + "&range=".concat(encodeURIComponent(range));
    } else {
      url = url + "?range=".concat(encodeURIComponent(range));
    }

    return url;
  }
  /**
   * Returns stats data if possible, otherwise an empty object
   *
   * @param {object} statsData - the stats data or error
   * @returns {object} - the handled stats data
   */


  function handleStatsResponseError(statsData) {
    // If we get a .response property, it means that .com's response is errory.
    // Probably because the site does not have stats yet.
    var responseOk = statsData.general && statsData.general.response === undefined || statsData.week && statsData.week.response === undefined || statsData.month && statsData.month.response === undefined;
    return responseOk ? statsData : {};
  }

  (0,lodash__WEBPACK_IMPORTED_MODULE_4__.assign)(this, methods);
}

var restApi = new JetpackRestApiClient();
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (restApi);
/**
 * Check the status of the response. Throw an error if it was not OK
 *
 * @param {Response} response - the API response
 * @returns {Promise<object>} - a promise to return the parsed JSON body as an object
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
 * Parse the JSON response
 *
 * @param {Response} response - the response object
 * @returns {Promise<object>} - promise to return the parsed json object
 */


function parseJsonResponse(response) {
  return response.json()["catch"](function (e) {
    return catchJsonParseError(e, response.redirected, response.url);
  });
}
/**
 * Throw appropriate exception given an API error
 *
 * @param {Error} e - the error
 * @param {boolean} redirected - are we being redirected?
 * @param {string} url - the URL that returned the error
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

/***/ }),

/***/ "../../js-packages/components/components/jetpack-logo/index.jsx":
/*!**********************************************************************!*\
  !*** ../../js-packages/components/components/jetpack-logo/index.jsx ***!
  \**********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/extends */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/extends.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectWithoutProperties */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectWithoutProperties.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/classCallCheck */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/classCallCheck.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createClass */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createClass.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/inherits */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/inherits.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createSuper */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createSuper.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/defineProperty */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/defineProperty.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_7__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_8__);
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! classnames */ "../../../node_modules/.pnpm/classnames@2.3.1/node_modules/classnames/index.js");
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(classnames__WEBPACK_IMPORTED_MODULE_9__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_10___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_10__);







var _excluded = ["logoColor", "showText", "className"];

/**
 * External dependencies
 */



/**
 * WordPress dependencies
 */


var __ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_10__.__;

var JetpackLogo = /*#__PURE__*/function (_React$Component) {
  _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_inherits__WEBPACK_IMPORTED_MODULE_4___default()(JetpackLogo, _React$Component);

  var _super = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createSuper__WEBPACK_IMPORTED_MODULE_5___default()(JetpackLogo);

  function JetpackLogo() {
    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_2___default()(this, JetpackLogo);

    return _super.apply(this, arguments);
  }

  _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_3___default()(JetpackLogo, [{
    key: "render",
    value: function render() {
      var _this$props = this.props,
          logoColor = _this$props.logoColor,
          showText = _this$props.showText,
          className = _this$props.className,
          otherProps = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default()(_this$props, _excluded);

      var viewBox = showText ? '0 0 118 32' : '0 0 32 32';
      return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default().createElement("svg", _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({
        xmlns: "http://www.w3.org/2000/svg",
        x: "0px",
        y: "0px",
        viewBox: viewBox,
        className: classnames__WEBPACK_IMPORTED_MODULE_9___default()('jetpack-logo', className),
        "aria-labelledby": "jetpack-logo-title"
      }, otherProps), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default().createElement("title", {
        id: "jetpack-logo-title"
      }, __('Jetpack Logo', 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default().createElement("path", {
        fill: logoColor,
        d: "M16,0C7.2,0,0,7.2,0,16s7.2,16,16,16s16-7.2,16-16S24.8,0,16,0z M15,19H7l8-16V19z M17,29V13h8L17,29z"
      }), showText && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default().createElement(react__WEBPACK_IMPORTED_MODULE_8__.Fragment, null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default().createElement("path", {
        d: "M41.3,26.6c-0.5-0.7-0.9-1.4-1.3-2.1c2.3-1.4,3-2.5,3-4.6V8h-3V6h6v13.4C46,22.8,45,24.8,41.3,26.6z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default().createElement("path", {
        d: "M65,18.4c0,1.1,0.8,1.3,1.4,1.3c0.5,0,2-0.2,2.6-0.4v2.1c-0.9,0.3-2.5,0.5-3.7,0.5c-1.5,0-3.2-0.5-3.2-3.1V12H60v-2h2.1V7.1 H65V10h4v2h-4V18.4z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default().createElement("path", {
        d: "M71,10h3v1.3c1.1-0.8,1.9-1.3,3.3-1.3c2.5,0,4.5,1.8,4.5,5.6s-2.2,6.3-5.8,6.3c-0.9,0-1.3-0.1-2-0.3V28h-3V10z M76.5,12.3 c-0.8,0-1.6,0.4-2.5,1.2v5.9c0.6,0.1,0.9,0.2,1.8,0.2c2,0,3.2-1.3,3.2-3.9C79,13.4,78.1,12.3,76.5,12.3z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default().createElement("path", {
        d: "M93,22h-3v-1.5c-0.9,0.7-1.9,1.5-3.5,1.5c-1.5,0-3.1-1.1-3.1-3.2c0-2.9,2.5-3.4,4.2-3.7l2.4-0.3v-0.3c0-1.5-0.5-2.3-2-2.3 c-0.7,0-2.3,0.5-3.7,1.1L84,11c1.2-0.4,3-1,4.4-1c2.7,0,4.6,1.4,4.6,4.7L93,22z M90,16.4l-2.2,0.4c-0.7,0.1-1.4,0.5-1.4,1.6 c0,0.9,0.5,1.4,1.3,1.4s1.5-0.5,2.3-1V16.4z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default().createElement("path", {
        d: "M104.5,21.3c-1.1,0.4-2.2,0.6-3.5,0.6c-4.2,0-5.9-2.4-5.9-5.9c0-3.7,2.3-6,6.1-6c1.4,0,2.3,0.2,3.2,0.5V13 c-0.8-0.3-2-0.6-3.2-0.6c-1.7,0-3.2,0.9-3.2,3.6c0,2.9,1.5,3.8,3.3,3.8c0.9,0,1.9-0.2,3.2-0.7V21.3z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default().createElement("path", {
        d: "M110,15.2c0.2-0.3,0.2-0.8,3.8-5.2h3.7l-4.6,5.7l5,6.3h-3.7l-4.2-5.8V22h-3V6h3V15.2z"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_8___default().createElement("path", {
        d: "M58.5,21.3c-1.5,0.5-2.7,0.6-4.2,0.6c-3.6,0-5.8-1.8-5.8-6c0-3.1,1.9-5.9,5.5-5.9s4.9,2.5,4.9,4.9c0,0.8,0,1.5-0.1,2h-7.3 c0.1,2.5,1.5,2.8,3.6,2.8c1.1,0,2.2-0.3,3.4-0.7C58.5,19,58.5,21.3,58.5,21.3z M56,15c0-1.4-0.5-2.9-2-2.9c-1.4,0-2.3,1.3-2.4,2.9 C51.6,15,56,15,56,15z"
      })));
    }
  }]);

  return JetpackLogo;
}((react__WEBPACK_IMPORTED_MODULE_8___default().Component));

_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_6___default()(JetpackLogo, "propTypes", {
  className: (prop_types__WEBPACK_IMPORTED_MODULE_7___default().string),
  width: (prop_types__WEBPACK_IMPORTED_MODULE_7___default().number),
  height: (prop_types__WEBPACK_IMPORTED_MODULE_7___default().number),
  showText: (prop_types__WEBPACK_IMPORTED_MODULE_7___default().bool),
  logoColor: (prop_types__WEBPACK_IMPORTED_MODULE_7___default().string)
});

_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_6___default()(JetpackLogo, "defaultProps", {
  className: '',
  height: 32,
  showText: true,
  logoColor: '#00BE28'
});

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (JetpackLogo);

/***/ }),

/***/ "../../js-packages/components/components/spinner/index.jsx":
/*!*****************************************************************!*\
  !*** ../../js-packages/components/components/spinner/index.jsx ***!
  \*****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./style.scss */ "../../js-packages/components/components/spinner/style.scss");
/**
 * External dependencies
 */


/**
 * Internal dependencies
 */



var Spinner = function Spinner(props) {
  var className = props.className + ' jp-components-spinner';
  var style = {
    width: props.size,
    height: props.size,
    fontSize: props.size // allows border-width to be specified in em units

  };
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", {
    className: className
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", {
    className: "jp-components-spinner__outer",
    style: style
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", {
    className: "jp-components-spinner__inner"
  })));
};

Spinner.propTypes = {
  className: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().string),
  size: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().number)
};
Spinner.defaultProps = {
  size: 20,
  className: ''
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Spinner);

/***/ }),

/***/ "../../js-packages/components/tools/jp-redirect/index.jsx":
/*!****************************************************************!*\
  !*** ../../js-packages/components/tools/jp-redirect/index.jsx ***!
  \****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ getRedirectUrl)
/* harmony export */ });
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
 * @param {string}  source - The URL handler registered in the server or the full destination URL (starting with https://).
 * @param {object}  args - {
 *
 * Additional arguments to build the url.  This is not a complete list as any argument passed here will be sent to as a query parameter to the Redirect server. These parameters will not necessarily be passed over to the final destination URL. If you want to add a parameter to the final destination URL, use the `query` argument.
 * @type {string}  site URL of the current site. Will default to the value of jetpack_redirects.currentSiteRawUrl, if available.
 * @type {string}  path Additional path to be appended to the URL
 * @type {string}  query Query parameters to be added to the final destination URL. should be in query string format (e.g. 'key=value&foo=bar').
 * @type {string}  anchor Anchor to be added to the URL
 * }
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
/*!************************************************************************!*\
  !*** ../../js-packages/connection/components/connect-button/index.jsx ***!
  \************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @automattic/jetpack-api */ "../../js-packages/api/index.jsx");
/* harmony import */ var _automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @automattic/jetpack-components */ "../../js-packages/components/components/spinner/index.jsx");
/* harmony import */ var _connect_user__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../connect-user */ "../../js-packages/connection/components/connect-user/index.jsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./style.scss */ "../../js-packages/connection/components/connect-button/style.scss");


/**
 * External dependencies
 */


var __ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__;




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
 * @param {object} props.connectionStatus -- The connection status object.
 * @param {boolean} props.connectionStatusIsFetching -- The flag indicating that connection status is being fetched.
 * @param {boolean} props.autoTrigger -- Whether to initiate the connection process automatically upon rendering the component.
 * @returns {React.Component} The RNA connection component.
 */

var ConnectButton = function ConnectButton(props) {
  var _useState = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(false),
      _useState2 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState, 2),
      isRegistering = _useState2[0],
      setIsRegistering = _useState2[1];

  var _useState3 = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(false),
      _useState4 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState3, 2),
      isUserConnecting = _useState4[0],
      setIsUserConnecting = _useState4[1];

  var _useState5 = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(null),
      _useState6 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState5, 2),
      authorizationUrl = _useState6[0],
      setAuthorizationUrl = _useState6[1];

  var apiRoot = props.apiRoot,
      apiNonce = props.apiNonce,
      connectLabel = props.connectLabel,
      onRegistered = props.onRegistered,
      registrationNonce = props.registrationNonce,
      redirectUri = props.redirectUri,
      from = props.from,
      connectionStatus = props.connectionStatus,
      connectionStatusIsFetching = props.connectionStatusIsFetching,
      autoTrigger = props.autoTrigger;
  /**
   * Initialize the REST API.
   */

  (0,react__WEBPACK_IMPORTED_MODULE_1__.useEffect)(function () {
    _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_5__.default.setApiRoot(apiRoot);
    _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_5__.default.setApiNonce(apiNonce);
  }, [apiRoot, apiNonce]);
  /**
   * Initialize the site registration process.
   */

  var registerSite = (0,react__WEBPACK_IMPORTED_MODULE_1__.useCallback)(function (e) {
    e && e.preventDefault();

    if (connectionStatus.isRegistered) {
      setIsUserConnecting(true);
      return;
    }

    setIsRegistering(true);
    _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_5__.default.registerSite(registrationNonce, redirectUri).then(function (response) {
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
  /**
   * Auto-trigger the flow, only do it once.
   */

  (0,react__WEBPACK_IMPORTED_MODULE_1__.useEffect)(function () {
    if (autoTrigger && !isRegistering && !isUserConnecting) {
      registerSite();
    }
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-connect-button"
  }, connectionStatusIsFetching && "Loading...", (!connectionStatus.isRegistered || !connectionStatus.isUserConnected) && !connectionStatusIsFetching && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
    className: "jp-connect-button--button",
    label: connectLabel,
    onClick: registerSite,
    isPrimary: true,
    disabled: isRegistering || isUserConnecting
  }, isRegistering || isUserConnecting ? /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_7__.default, null) : connectLabel), isUserConnecting && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_connect_user__WEBPACK_IMPORTED_MODULE_8__.default, {
    connectUrl: authorizationUrl,
    redirectUri: redirectUri,
    from: from
  }));
};

ConnectButton.propTypes = {
  connectLabel: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().string),
  apiRoot: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().string.isRequired),
  apiNonce: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().string.isRequired),
  onRegistered: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().func),
  from: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().string),
  redirectUri: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().string.isRequired),
  registrationNonce: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().string.isRequired),
  autoTrigger: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().bool)
};
ConnectButton.defaultProps = {
  connectLabel: __('Connect', 'jetpack'),
  redirectUri: null,
  autoTrigger: false
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ConnectButton);

/***/ }),

/***/ "../../js-packages/connection/components/connect-screen/image-slider.jsx":
/*!*******************************************************************************!*\
  !*** ../../js-packages/connection/components/connect-screen/image-slider.jsx ***!
  \*******************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
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
    return imagesHTML.push( /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement((react__WEBPACK_IMPORTED_MODULE_0___default().Fragment), null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("img", {
      src: assetBaseUrl + image,
      alt: ""
    })));
  });
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", {
    className: "jp-connect-screen--image-slider"
  }, imagesHTML);
};

ImageSlider.propTypes = {
  images: prop_types__WEBPACK_IMPORTED_MODULE_1___default().arrayOf((prop_types__WEBPACK_IMPORTED_MODULE_1___default().string)).isRequired,
  assetBaseUrl: (prop_types__WEBPACK_IMPORTED_MODULE_1___default().string)
};
ImageSlider.defaultProps = {
  assetBaseUrl: ''
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ImageSlider);

/***/ }),

/***/ "../../js-packages/connection/components/connect-screen/index.jsx":
/*!************************************************************************!*\
  !*** ../../js-packages/connection/components/connect-screen/index.jsx ***!
  \************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @automattic/jetpack-components */ "../../js-packages/components/components/jetpack-logo/index.jsx");
/* harmony import */ var _automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! @automattic/jetpack-components */ "../../js-packages/components/tools/jp-redirect/index.jsx");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _connect_button__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../connect-button */ "../../js-packages/connection/components/connect-button/index.jsx");
/* harmony import */ var _with_connection_status__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../with-connection-status */ "../../js-packages/connection/components/with-connection-status/index.jsx");
/* harmony import */ var _image_slider__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./image-slider */ "../../js-packages/connection/components/connect-screen/image-slider.jsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./style.scss */ "../../js-packages/connection/components/connect-screen/style.scss");


/**
 * External dependencies
 */



var __ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__;


/**
 * Internal dependencies
 */





var ConnectButtonWithConnectionStatus = (0,_with_connection_status__WEBPACK_IMPORTED_MODULE_6__.default)(_connect_button__WEBPACK_IMPORTED_MODULE_7__.default);
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
 * @param {boolean} props.autoTrigger -- Whether to initiate the connection process automatically upon rendering the component.
 * @returns {React.Component} The `ConnectScreen` component.
 */

var ConnectScreen = function ConnectScreen(props) {
  var title = props.title,
      buttonLabel = props.buttonLabel,
      apiRoot = props.apiRoot,
      apiNonce = props.apiNonce,
      registrationNonce = props.registrationNonce,
      from = props.from,
      redirectUri = props.redirectUri,
      statusCallback = props.statusCallback,
      images = props.images,
      children = props.children,
      assetBaseUrl = props.assetBaseUrl,
      autoTrigger = props.autoTrigger;
  var showImageSlider = images.length;

  var _useState = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)({}),
      _useState2 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState, 2),
      connectionStatus = _useState2[0],
      setConnectionStatus = _useState2[1];

  var statusHandler = (0,react__WEBPACK_IMPORTED_MODULE_1__.useCallback)(function (status) {
    setConnectionStatus(status);

    if (statusCallback && {}.toString.call(statusCallback) === '[object Function]') {
      return statusCallback(status);
    }
  }, [statusCallback, setConnectionStatus]);
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: 'jp-connect-screen' + (showImageSlider ? ' jp-connect-screen--two-columns' : '') + (connectionStatus.hasOwnProperty('isRegistered') ? '' : ' jp-connect-screen--loading')
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-connect-screen--left"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_8__.default, null), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("h2", null, title), children, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(ConnectButtonWithConnectionStatus, {
    apiRoot: apiRoot,
    apiNonce: apiNonce,
    registrationNonce: registrationNonce,
    from: from,
    redirectUri: redirectUri,
    statusCallback: statusHandler,
    connectLabel: buttonLabel,
    autoTrigger: autoTrigger
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-connect-screen--tos"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.createInterpolateElement)(__('By clicking the button above, you agree to our <tosLink>Terms of Service</tosLink> and to <shareDetailsLink>share details</shareDetailsLink> with WordPress.com.', 'jetpack'), {
    tosLink: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("a", {
      href: (0,_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_9__.default)('wpcom-tos'),
      rel: "noopener noreferrer",
      target: "_blank"
    }),
    shareDetailsLink: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("a", {
      href: (0,_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_9__.default)('jetpack-support-what-data-does-jetpack-sync'),
      rel: "noopener noreferrer",
      target: "_blank"
    })
  }))), showImageSlider ? /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-connect-screen--right"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_image_slider__WEBPACK_IMPORTED_MODULE_10__.default, {
    images: images,
    assetBaseUrl: assetBaseUrl
  })) : null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-connect-screen--clearfix"
  }));
};

ConnectScreen.propTypes = {
  title: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string),
  body: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string),
  buttonLabel: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string),
  apiRoot: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string.isRequired),
  apiNonce: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string.isRequired),
  from: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string),
  redirectUri: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string.isRequired),
  registrationNonce: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string.isRequired),
  statusCallback: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().func),
  images: prop_types__WEBPACK_IMPORTED_MODULE_2___default().arrayOf((prop_types__WEBPACK_IMPORTED_MODULE_2___default().string)),
  assetBaseUrl: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string),
  autoTrigger: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().bool)
};
ConnectScreen.defaultProps = {
  title: __('Over 5 million WordPress sites are faster and more secure', 'jetpack'),
  buttonLabel: __('Set up Jetpack', 'jetpack'),
  images: [],
  redirectUri: null,
  autoTrigger: false
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ConnectScreen);

/***/ }),

/***/ "../../js-packages/connection/components/connect-user/index.jsx":
/*!**********************************************************************!*\
  !*** ../../js-packages/connection/components/connect-user/index.jsx ***!
  \**********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @automattic/jetpack-api */ "../../js-packages/api/index.jsx");


/**
 * External dependencies
 */



/**
 * The user connection component.
 *
 * @param {object} props -- The properties.
 * @param {Function} props.redirectFunc -- The redirect function (`window.location.assign()` by default).
 * @param {string} props.connectUrl -- The authorization URL (no-iframe).
 * @param {string} props.redirectUri -- The redirect admin URI.
 * @param {string} props.from -- Where the connection request is coming from.
 * @returns {null} -- Nothing to return.
 */

var ConnectUser = function ConnectUser(props) {
  var redirectFunc = props.redirectFunc,
      connectUrl = props.connectUrl,
      redirectUri = props.redirectUri,
      from = props.from;

  var _useState = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(null),
      _useState2 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState, 2),
      authorizationUrl = _useState2[0],
      setAuthorizationUrl = _useState2[1];

  if (connectUrl && connectUrl !== authorizationUrl) {
    setAuthorizationUrl(connectUrl);
  }
  /**
   * Fetch the authorization URL on the first render.
   * To be only run once.
   */


  (0,react__WEBPACK_IMPORTED_MODULE_1__.useEffect)(function () {
    if (!authorizationUrl) {
      _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_3__.default.fetchAuthorizationUrl(redirectUri).then(function (response) {
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
  connectUrl: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string),
  redirectUri: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string.isRequired),
  from: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string),
  redirectFunc: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().func)
};
ConnectUser.defaultProps = {
  redirectFunc: function redirectFunc(url) {
    return window.location.assign(url);
  },
  redirectUri: null
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ConnectUser);

/***/ }),

/***/ "../../js-packages/connection/components/connection-status-card/index.jsx":
/*!********************************************************************************!*\
  !*** ../../js-packages/connection/components/connection-status-card/index.jsx ***!
  \********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @automattic/jetpack-api */ "../../js-packages/api/index.jsx");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _connect_user__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ../connect-user */ "../../js-packages/connection/components/connect-user/index.jsx");
/* harmony import */ var _disconnect_dialog__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../disconnect-dialog */ "../../js-packages/connection/components/disconnect-dialog/index.jsx");
/* harmony import */ var _state_store__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../../state/store */ "../../js-packages/connection/state/store.jsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./style.scss */ "../../js-packages/connection/components/connection-status-card/style.scss");


/**
 * External dependencies
 */


var __ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__;




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

  var _useState = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(false),
      _useState2 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState, 2),
      isFetchingConnectionData = _useState2[0],
      setIsFetchingConnectionData = _useState2[1];

  var _useState3 = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)({}),
      _useState4 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState3, 2),
      connectedUserData = _useState4[0],
      setConnectedUserData = _useState4[1];

  var _useState5 = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(false),
      _useState6 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState5, 2),
      isUserConnecting = _useState6[0],
      setIsUserConnecting = _useState6[1];

  var _useDispatch = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_6__.useDispatch)(_state_store__WEBPACK_IMPORTED_MODULE_8__.STORE_ID),
      setConnectionStatus = _useDispatch.setConnectionStatus;

  var avatarRef = (0,react__WEBPACK_IMPORTED_MODULE_1__.useRef)();
  /**
   * Initialize the REST API.
   */

  (0,react__WEBPACK_IMPORTED_MODULE_1__.useEffect)(function () {
    _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_5__.default.setApiRoot(apiRoot);
    _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_5__.default.setApiNonce(apiNonce);
  }, [apiRoot, apiNonce]);
  /**
   * Fetch the connection data on the first render.
   * To be only run once.
   */

  (0,react__WEBPACK_IMPORTED_MODULE_1__.useEffect)(function () {
    setIsFetchingConnectionData(true);
    _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_5__.default.fetchSiteConnectionData().then(function (response) {
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
  var onDisconnectedCallback = (0,react__WEBPACK_IMPORTED_MODULE_1__.useCallback)(function (e) {
    e && e.preventDefault();
    setConnectionStatus({
      isActive: false,
      isRegistered: false,
      isUserConnected: false
    });

    if (onDisconnected && {}.toString.call(onDisconnected) === '[object Function]') {
      onDisconnected();
    }
  }, [onDisconnected, setConnectionStatus]); // Prevent component from rendering if site is not connected.

  if (!isRegistered) {
    return null;
  }

  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-connection-status-card"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("h3", null, title), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("p", null, connectionInfoText), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-connection-status-card--status"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-connection-status-card--cloud"
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: 'jp-connection-status-card--line' + (isUserConnected ? '' : ' jp-connection-status-card--site-only')
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-connection-status-card--jetpack-logo"
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-connection-status-card--avatar",
    ref: avatarRef
  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("ul", {
    className: "jp-connection-status-card--list"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("li", {
    className: "jp-connection-status-card--list-item-success"
  }, __('Site connected.', 'jetpack'), "\xA0", /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_disconnect_dialog__WEBPACK_IMPORTED_MODULE_9__.default, {
    apiRoot: apiRoot,
    apiNonce: apiNonce,
    onDisconnected: onDisconnectedCallback
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("h2", null, __('Jetpack is currently powering multiple products on your site.', 'jetpack'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("br", null), __('Once you disconnect Jetpack, these will no longer work.', 'jetpack')))), isUserConnected && !isFetchingConnectionData && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("li", {
    className: "jp-connection-status-card--list-item-success"
  }, __('Logged in as', 'jetpack'), " ", connectedUserData === null || connectedUserData === void 0 ? void 0 : connectedUserData.display_name), !isUserConnected && !isFetchingConnectionData && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("li", {
    className: "jp-connection-status-card--list-item-error"
  }, __('Your WordPress.com account is not connected.', 'jetpack'))), !isUserConnected && !isFetchingConnectionData && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
    isPrimary: true,
    disabled: isUserConnecting,
    onClick: setIsUserConnecting,
    className: "jp-connection-status-card--btn-connect-user"
  }, __('Connect your WordPress.com account', 'jetpack')), isUserConnecting && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_connect_user__WEBPACK_IMPORTED_MODULE_10__.default, {
    redirectUri: redirectUri
  }));
};

ConnectionStatusCard.propTypes = {
  apiRoot: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().string.isRequired),
  apiNonce: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().string.isRequired),
  isRegistered: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().bool.isRequired),
  isUserConnected: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().bool.isRequired),
  redirectUri: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().string.isRequired),
  title: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().string),
  connectionInfoText: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().string),
  onDisconnected: (prop_types__WEBPACK_IMPORTED_MODULE_4___default().func)
};
ConnectionStatusCard.defaultProps = {
  title: __('Connection', 'jetpack'),
  connectionInfoText: __('Leverages the Jetpack Cloud for more features on your side.', 'jetpack')
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ConnectionStatusCard);

/***/ }),

/***/ "../../js-packages/connection/components/disconnect-dialog/index.jsx":
/*!***************************************************************************!*\
  !*** ../../js-packages/connection/components/disconnect-dialog/index.jsx ***!
  \***************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/slicedToArray.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! prop-types */ "../../../node_modules/.pnpm/prop-types@15.7.2/node_modules/prop-types/index.js");
/* harmony import */ var prop_types__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(prop_types__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @automattic/jetpack-components */ "../../js-packages/components/tools/jp-redirect/index.jsx");
/* harmony import */ var _automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! @automattic/jetpack-components */ "../../js-packages/components/components/jetpack-logo/index.jsx");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @automattic/jetpack-api */ "../../js-packages/api/index.jsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./style.scss */ "../../js-packages/connection/components/disconnect-dialog/style.scss");


/**
 * External dependencies
 */



var __ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__;




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
 * @returns {React.Component} The `DisconnectDialog` component.
 */

var DisconnectDialog = function DisconnectDialog(props) {
  var _useState = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(false),
      _useState2 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState, 2),
      isOpen = _useState2[0],
      setOpen = _useState2[1];

  var _useState3 = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(false),
      _useState4 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState3, 2),
      isDisconnecting = _useState4[0],
      setIsDisconnecting = _useState4[1];

  var _useState5 = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(false),
      _useState6 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState5, 2),
      isDisconnected = _useState6[0],
      setIsDisconnected = _useState6[1];

  var _useState7 = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(false),
      _useState8 = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_0___default()(_useState7, 2),
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

  (0,react__WEBPACK_IMPORTED_MODULE_1__.useEffect)(function () {
    _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_6__.default.setApiRoot(apiRoot);
    _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_6__.default.setApiNonce(apiNonce);
  }, [apiRoot, apiNonce]);
  /**
   * Open the Disconnect Dialog.
   */

  var openModal = (0,react__WEBPACK_IMPORTED_MODULE_1__.useCallback)(function (e) {
    e && e.preventDefault();
    setOpen(true);
  }, [setOpen]);
  /**
   * Close the Disconnect Dialog.
   */

  var closeModal = (0,react__WEBPACK_IMPORTED_MODULE_1__.useCallback)(function (e) {
    e && e.preventDefault();
    setOpen(false);
  }, [setOpen]);
  /**
   * Disconnect - Triggered upon clicking the 'Disconnect' button.
   */

  var disconnect = (0,react__WEBPACK_IMPORTED_MODULE_1__.useCallback)(function (e) {
    e && e.preventDefault();
    setDisconnectError(false);
    setIsDisconnecting(true);
    _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_6__.default.disconnectSite().then(function () {
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

  var backToWordpress = (0,react__WEBPACK_IMPORTED_MODULE_1__.useCallback)(function (e) {
    e && e.preventDefault();

    if (onDisconnected) {
      onDisconnected();
    }

    closeModal();
  }, [onDisconnected, closeModal]);
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement((react__WEBPACK_IMPORTED_MODULE_1___default().Fragment), null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
    variant: "link",
    onClick: openModal,
    className: "jp-disconnect-dialog__link"
  }, __('Disconnect', 'jetpack')), isOpen && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Modal, {
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
  }, !isDisconnected && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-disconnect-dialog__content"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("h1", {
    id: "jp-disconnect-dialog__heading"
  }, title), children), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-disconnect-dialog__actions"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-row"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "lg-col-span-8 md-col-span-8 sm-col-span-4"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("p", null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_5__.createInterpolateElement)(__('<strong>Need help?</strong> Learn more about the <jpConnectionInfoLink>Jetpack connection</jpConnectionInfoLink> or <jpSupportLink>contact Jetpack support</jpSupportLink>', 'jetpack'), {
    strong: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("strong", null),
    jpConnectionInfoLink: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("a", {
      href: (0,_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_8__.default)('why-the-wordpress-com-connection-is-important-for-jetpack'),
      rel: "noopener noreferrer",
      target: "_blank",
      className: "jp-disconnect-dialog__link"
    }),
    jpSupportLink: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("a", {
      href: (0,_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_8__.default)('jetpack-support'),
      rel: "noopener noreferrer",
      target: "_blank",
      className: "jp-disconnect-dialog__link"
    })
  }))), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", {
    className: "jp-disconnect-dialog__button-wrap lg-col-span-4 md-col-span-8 sm-col-span-4"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
    isPrimary: true,
    disabled: isDisconnecting,
    onClick: closeModal,
    className: "jp-disconnect-dialog__btn-dismiss"
  }, __('Stay connected', 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
    isPrimary: true,
    disabled: isDisconnecting,
    onClick: disconnect,
    className: "jp-disconnect-dialog__btn-disconnect"
  }, __('Disconnect', 'jetpack')))), disconnectError && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("p", {
    className: "jp-disconnect-dialog__error"
  }, errorMessage))), isDisconnected && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("div", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_automattic_jetpack_components__WEBPACK_IMPORTED_MODULE_9__.default, null), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("h1", null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_5__.createInterpolateElement)(__('Jetpack has been <br/>successfully disconnected.', 'jetpack'), {
    br: /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement("br", null)
  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
    isPrimary: true,
    onClick: backToWordpress,
    className: "jp-disconnect-dialog__btn-back-to-wp"
  }, __('Back to WordPress', 'jetpack')))));
};

DisconnectDialog.propTypes = {
  apiRoot: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string.isRequired),
  apiNonce: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string.isRequired),
  title: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string),
  onDisconnected: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().func),
  onError: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().func),
  errorMessage: (prop_types__WEBPACK_IMPORTED_MODULE_2___default().string)
};
DisconnectDialog.defaultProps = {
  title: __('Are you sure you want to disconnect?', 'jetpack'),
  errorMessage: __('Failed to disconnect. Please try again.', 'jetpack')
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (DisconnectDialog);

/***/ }),

/***/ "../../js-packages/connection/components/with-connection-status/index.jsx":
/*!********************************************************************************!*\
  !*** ../../js-packages/connection/components/with-connection-status/index.jsx ***!
  \********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/extends */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/extends.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @automattic/jetpack-api */ "../../js-packages/api/index.jsx");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _state_store__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../state/store */ "../../js-packages/connection/state/store.jsx");


/**
 * External dependencies
 */



/**
 * Internal dependencies
 */


/**
 * Fetch the connection status and update the state accordingly.
 *
 * @param {string} apiRoot - API root URL.
 * @param {string} apiNonce - API Nonce.
 * @param {Function} onSuccess - Callback that's called upon successfully fetching the connection status.
 * @param {Function} onError - Callback that's called in case of fetching error.
 */

var fetchConnectionStatus = function fetchConnectionStatus(apiRoot, apiNonce, onSuccess, onError) {
  _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_2__.default.setApiRoot(apiRoot);
  _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_2__.default.setApiNonce(apiNonce);
  _automattic_jetpack_api__WEBPACK_IMPORTED_MODULE_2__.default.fetchSiteConnectionStatus().then(onSuccess)["catch"](onError);
};
/**
 * Higher order component to fetch connection status and pass it further as a parameter.
 *
 * @param {React.Component} WrappedComponent - The component that needs connection status.
 * @returns {React.Component} The higher order component.
 */


var withConnectionStatus = function withConnectionStatus(WrappedComponent) {
  /**
   * The `WrappedComponent` with connection status passed into it.
   *
   * @param {object} props -- The properties.
   * @param {Function} props.statusCallback -- Callback to pull connection status from the component.
   * @returns {React.Component} The higher order component.
   */
  return function (props) {
    var apiRoot = props.apiRoot,
        apiNonce = props.apiNonce,
        statusCallback = props.statusCallback;
    var connectionStatus = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(function (select) {
      return select(_state_store__WEBPACK_IMPORTED_MODULE_4__.STORE_ID).getConnectionStatus();
    }, []);

    var _useDispatch = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useDispatch)(_state_store__WEBPACK_IMPORTED_MODULE_4__.STORE_ID),
        setConnectionStatus = _useDispatch.setConnectionStatus,
        setConnectionStatusIsFetching = _useDispatch.setConnectionStatusIsFetching;

    var connectionStatusIsFetching = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(function (select) {
      return select(_state_store__WEBPACK_IMPORTED_MODULE_4__.STORE_ID).getConnectionStatusIsFetching();
    }, []);
    var hasConnectionStatus = connectionStatus.hasOwnProperty('isActive');
    var onSuccess = (0,react__WEBPACK_IMPORTED_MODULE_1__.useCallback)(function (response) {
      setConnectionStatus(response);
      setConnectionStatusIsFetching(false);
    }, [setConnectionStatus, setConnectionStatusIsFetching]);
    var onError = (0,react__WEBPACK_IMPORTED_MODULE_1__.useCallback)(function (error) {
      setConnectionStatusIsFetching(false);
      throw error;
    }, [setConnectionStatusIsFetching]);
    var statusCallbackWrapped = (0,react__WEBPACK_IMPORTED_MODULE_1__.useCallback)(function () {
      if (statusCallback && {}.toString.call(statusCallback) === '[object Function]') {
        return statusCallback(connectionStatus);
      }
    }, [connectionStatus, statusCallback]);

    if (!hasConnectionStatus && !connectionStatusIsFetching) {
      setConnectionStatusIsFetching(true);
      fetchConnectionStatus(apiRoot, apiNonce, onSuccess, onError);
    }

    hasConnectionStatus && !connectionStatusIsFetching && statusCallbackWrapped();
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(WrappedComponent, _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({
      connectionStatus: connectionStatus,
      connectionStatusIsFetching: connectionStatusIsFetching
    }, props));
  };
};

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (withConnectionStatus);

/***/ }),

/***/ "../../js-packages/connection/components/with-connection-status/state/actions.jsx":
/*!****************************************************************************************!*\
  !*** ../../js-packages/connection/components/with-connection-status/state/actions.jsx ***!
  \****************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "SET_CONNECTION_STATUS": () => (/* binding */ SET_CONNECTION_STATUS),
/* harmony export */   "SET_CONNECTION_STATUS_IS_FETCHING": () => (/* binding */ SET_CONNECTION_STATUS_IS_FETCHING),
/* harmony export */   "default": () => (/* binding */ connectionStatusActions)
/* harmony export */ });
var SET_CONNECTION_STATUS = 'SET_CONNECTION_STATUS';
var SET_CONNECTION_STATUS_IS_FETCHING = 'SET_CONNECTION_STATUS_IS_FETCHING';
var connectionStatusActions = {
  setConnectionStatus: function setConnectionStatus(connectionStatus) {
    return {
      type: SET_CONNECTION_STATUS,
      connectionStatus: connectionStatus
    };
  },
  setConnectionStatusIsFetching: function setConnectionStatusIsFetching(isFetching) {
    return {
      type: SET_CONNECTION_STATUS_IS_FETCHING,
      isFetching: isFetching
    };
  }
};


/***/ }),

/***/ "../../js-packages/connection/components/with-connection-status/state/reducers.jsx":
/*!*****************************************************************************************!*\
  !*** ../../js-packages/connection/components/with-connection-status/state/reducers.jsx ***!
  \*****************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "connectionStatus": () => (/* binding */ connectionStatus),
/* harmony export */   "connectionStatusIsFetching": () => (/* binding */ connectionStatusIsFetching)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2 */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _actions__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./actions */ "../../js-packages/connection/components/with-connection-status/state/actions.jsx");


/**
 * Internal dependencies
 */


var connectionStatus = function connectionStatus() {
  var state = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
  var action = arguments.length > 1 ? arguments[1] : undefined;

  switch (action.type) {
    case _actions__WEBPACK_IMPORTED_MODULE_1__.SET_CONNECTION_STATUS:
      return _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, state), action.connectionStatus);
  }

  return state;
};

var connectionStatusIsFetching = function connectionStatusIsFetching() {
  var state = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : false;
  var action = arguments.length > 1 ? arguments[1] : undefined;

  switch (action.type) {
    case _actions__WEBPACK_IMPORTED_MODULE_1__.SET_CONNECTION_STATUS_IS_FETCHING:
      return action.isFetching;
  }

  return state;
};



/***/ }),

/***/ "../../js-packages/connection/components/with-connection-status/state/selectors.jsx":
/*!******************************************************************************************!*\
  !*** ../../js-packages/connection/components/with-connection-status/state/selectors.jsx ***!
  \******************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
var connectionSelectors = {
  getConnectionStatus: function getConnectionStatus(state) {
    return state.connectionStatus || {};
  },
  getConnectionStatusIsFetching: function getConnectionStatusIsFetching(state) {
    return state.connectionStatusIsFetching || false;
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (connectionSelectors);

/***/ }),

/***/ "../../js-packages/connection/state/actions.jsx":
/*!******************************************************!*\
  !*** ../../js-packages/connection/state/actions.jsx ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2 */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_with_connection_status_state_actions__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../components/with-connection-status/state/actions */ "../../js-packages/connection/components/with-connection-status/state/actions.jsx");


/**
 * Internal dependencies
 */


var actions = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, _components_with_connection_status_state_actions__WEBPACK_IMPORTED_MODULE_1__.default);

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (actions);

/***/ }),

/***/ "../../js-packages/connection/state/reducers.jsx":
/*!*******************************************************!*\
  !*** ../../js-packages/connection/state/reducers.jsx ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_with_connection_status_state_reducers__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../components/with-connection-status/state/reducers */ "../../js-packages/connection/components/with-connection-status/state/reducers.jsx");
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */


var reducers = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.combineReducers)({
  connectionStatus: _components_with_connection_status_state_reducers__WEBPACK_IMPORTED_MODULE_1__.connectionStatus,
  connectionStatusIsFetching: _components_with_connection_status_state_reducers__WEBPACK_IMPORTED_MODULE_1__.connectionStatusIsFetching
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (reducers);

/***/ }),

/***/ "../../js-packages/connection/state/selectors.jsx":
/*!********************************************************!*\
  !*** ../../js-packages/connection/state/selectors.jsx ***!
  \********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2 */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_with_connection_status_state_selectors__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../components/with-connection-status/state/selectors */ "../../js-packages/connection/components/with-connection-status/state/selectors.jsx");


/**
 * Internal dependencies
 */


var selectors = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, _components_with_connection_status_state_selectors__WEBPACK_IMPORTED_MODULE_1__.default);

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (selectors);

/***/ }),

/***/ "../../js-packages/connection/state/store-holder.jsx":
/*!***********************************************************!*\
  !*** ../../js-packages/connection/state/store-holder.jsx ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/classCallCheck */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/classCallCheck.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createClass */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/createClass.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/defineProperty */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/defineProperty.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);




/**
 * External dependencies
 */


var storeHolder = /*#__PURE__*/function () {
  function storeHolder() {
    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_classCallCheck__WEBPACK_IMPORTED_MODULE_0___default()(this, storeHolder);
  }

  _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_createClass__WEBPACK_IMPORTED_MODULE_1___default()(storeHolder, null, [{
    key: "mayBeInit",
    value: function mayBeInit(storeId, storeConfig) {
      if (null === storeHolder.store) {
        storeHolder.store = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.createReduxStore)(storeId, storeConfig);
        (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.register)(storeHolder.store);
      }
    }
  }]);

  return storeHolder;
}();

_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_2___default()(storeHolder, "store", null);

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (storeHolder);

/***/ }),

/***/ "../../js-packages/connection/state/store.jsx":
/*!****************************************************!*\
  !*** ../../js-packages/connection/state/store.jsx ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "STORE_ID": () => (/* binding */ STORE_ID)
/* harmony export */ });
/* harmony import */ var _reducers__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./reducers */ "../../js-packages/connection/state/reducers.jsx");
/* harmony import */ var _actions__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./actions */ "../../js-packages/connection/state/actions.jsx");
/* harmony import */ var _selectors__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./selectors */ "../../js-packages/connection/state/selectors.jsx");
/* harmony import */ var _store_holder__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./store-holder */ "../../js-packages/connection/state/store-holder.jsx");
/**
 * Internal dependencies
 */




var STORE_ID = 'jetpack-connection';
_store_holder__WEBPACK_IMPORTED_MODULE_0__.default.mayBeInit(STORE_ID, {
  reducer: _reducers__WEBPACK_IMPORTED_MODULE_1__.default,
  actions: _actions__WEBPACK_IMPORTED_MODULE_2__.default,
  selectors: _selectors__WEBPACK_IMPORTED_MODULE_3__.default
});


/***/ }),

/***/ "./_inc/actions/connection-status.js":
/*!*******************************************!*\
  !*** ./_inc/actions/connection-status.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "SET_CONNECTION_STATUS": () => (/* binding */ SET_CONNECTION_STATUS),
/* harmony export */   "default": () => (/* binding */ connectionStatusActions)
/* harmony export */ });
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
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2 */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _connection_status__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./connection-status */ "./_inc/actions/connection-status.js");


/**
 * Internal dependencies
 */


var actions = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, _connection_status__WEBPACK_IMPORTED_MODULE_1__.default);

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (actions);

/***/ }),

/***/ "./_inc/components/admin/index.jsx":
/*!*****************************************!*\
  !*** ./_inc/components/admin/index.jsx ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Admin)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _automattic_jetpack_connection__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @automattic/jetpack-connection */ "../../js-packages/connection/components/connection-status-card/index.jsx");
/* harmony import */ var _automattic_jetpack_connection__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @automattic/jetpack-connection */ "../../js-packages/connection/components/connect-screen/index.jsx");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../store */ "./_inc/store.js");
/* harmony import */ var _header__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../header */ "./_inc/components/header/index.jsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./style.scss */ "./_inc/components/admin/style.scss");
/* harmony import */ var _assets_connect_right_png__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./assets/connect-right.png */ "./_inc/components/admin/assets/connect-right.png");
/**
 * External dependencies
 */




/**
 * Internal dependencies
 */

var __ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__;




/**
 * The Connection IU Admin App.
 *
 * @returns {object} The Admin component.
 */

function Admin() {
  var APINonce = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_3__.STORE_ID).getAPINonce();
  }, []);
  var APIRoot = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_3__.STORE_ID).getAPIRoot();
  }, []);
  var registrationNonce = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_3__.STORE_ID).getRegistrationNonce();
  }, []);
  var assetBuildUrl = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_3__.STORE_ID).getAssetBuildUrl();
  }, []);
  var connectionStatus = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(function (select) {
    return select(_store__WEBPACK_IMPORTED_MODULE_3__.STORE_ID).getConnectionStatus();
  }, []);

  var _useDispatch = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)(_store__WEBPACK_IMPORTED_MODULE_3__.STORE_ID),
      setConnectionStatus = _useDispatch.setConnectionStatus;

  var statusCallback = (0,react__WEBPACK_IMPORTED_MODULE_0__.useCallback)(function (status) {
    setConnectionStatus(status);
  }, [setConnectionStatus]);
  var onDisconnectedCallback = (0,react__WEBPACK_IMPORTED_MODULE_0__.useCallback)(function () {
    setConnectionStatus({
      isActive: false,
      isRegistered: false,
      isUserConnected: false
    });
  }, [setConnectionStatus]);
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement((react__WEBPACK_IMPORTED_MODULE_0___default().Fragment), null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_header__WEBPACK_IMPORTED_MODULE_4__.default, null), connectionStatus.isRegistered && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_automattic_jetpack_connection__WEBPACK_IMPORTED_MODULE_7__.default, {
    isRegistered: connectionStatus.isRegistered,
    isUserConnected: connectionStatus.isUserConnected,
    apiRoot: APIRoot,
    apiNonce: APINonce,
    onDisconnected: onDisconnectedCallback,
    redirectUri: "tools.php?page=wpcom-connection-manager"
  }), !connectionStatus.isRegistered && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement(_automattic_jetpack_connection__WEBPACK_IMPORTED_MODULE_8__.default, {
    apiRoot: APIRoot,
    apiNonce: APINonce,
    registrationNonce: registrationNonce,
    from: "connection-ui",
    redirectUri: "tools.php?page=wpcom-connection-manager",
    statusCallback: statusCallback,
    images: [_assets_connect_right_png__WEBPACK_IMPORTED_MODULE_6__],
    assetBaseUrl: assetBuildUrl
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("p", null, __("Secure and speed up your site for free with Jetpack's powerful WordPress tools.", 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("ul", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("li", null, __('Measure your impact with beautiful stats', 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("li", null, __('Speed up your site with optimized images', 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("li", null, __('Protect your site against bot attacks', 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("li", null, __('Get notifications if your site goes offline', 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("li", null, __('Enhance your site with dozens of other features', 'jetpack')))));
}

/***/ }),

/***/ "./_inc/components/header/index.jsx":
/*!******************************************!*\
  !*** ./_inc/components/header/index.jsx ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./style.scss */ "./_inc/components/header/style.scss");
/**
 * External dependencies
 */


/**
 * Internal dependencies
 */

var __ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__;

/**
 * The Connection UI header.
 *
 * @returns {object} The header component.
 */

var Header = function Header() {
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("div", {
    className: "jetpack-cui__header"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0___default().createElement("h1", null, __('Connection Manager', 'jetpack')));
};

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Header);

/***/ }),

/***/ "./_inc/reducers/api.js":
/*!******************************!*\
  !*** ./_inc/reducers/api.js ***!
  \******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
var API = function API() {
  var state = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
  return state;
};

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (API);

/***/ }),

/***/ "./_inc/reducers/assets.js":
/*!*********************************!*\
  !*** ./_inc/reducers/assets.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
var assets = function assets() {
  var state = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
  return state;
};

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (assets);

/***/ }),

/***/ "./_inc/reducers/connection-status.js":
/*!********************************************!*\
  !*** ./_inc/reducers/connection-status.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _actions_connection_status__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../actions/connection-status */ "./_inc/actions/connection-status.js");
/**
 * Internal dependencies
 */


var connectionStatus = function connectionStatus() {
  var state = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
  var action = arguments.length > 1 ? arguments[1] : undefined;

  switch (action.type) {
    case _actions_connection_status__WEBPACK_IMPORTED_MODULE_0__.SET_CONNECTION_STATUS:
      return action.connectionStatus;
  }

  return state;
};

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (connectionStatus);

/***/ }),

/***/ "./_inc/reducers/index.js":
/*!********************************!*\
  !*** ./_inc/reducers/index.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
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




var reducer = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.combineReducers)({
  connectionStatus: _connection_status__WEBPACK_IMPORTED_MODULE_1__.default,
  API: _api__WEBPACK_IMPORTED_MODULE_2__.default,
  assets: _assets__WEBPACK_IMPORTED_MODULE_3__.default
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (reducer);

/***/ }),

/***/ "./_inc/selectors/api.js":
/*!*******************************!*\
  !*** ./_inc/selectors/api.js ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
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
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (APISelectors);

/***/ }),

/***/ "./_inc/selectors/assets.js":
/*!**********************************!*\
  !*** ./_inc/selectors/assets.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
var assetsSelectors = {
  getAssetBuildUrl: function getAssetBuildUrl(state) {
    return state.assets.buildUrl || null;
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (assetsSelectors);

/***/ }),

/***/ "./_inc/selectors/connection-status.js":
/*!*********************************************!*\
  !*** ./_inc/selectors/connection-status.js ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
var connectionSelectors = {
  getConnectionStatus: function getConnectionStatus(state) {
    return state.connectionStatus || {};
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (connectionSelectors);

/***/ }),

/***/ "./_inc/selectors/index.js":
/*!*********************************!*\
  !*** ./_inc/selectors/index.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2 */ "../../../node_modules/.pnpm/@babel+runtime@7.15.3/node_modules/@babel/runtime/helpers/objectSpread2.js");
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _connection_status__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./connection-status */ "./_inc/selectors/connection-status.js");
/* harmony import */ var _api__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./api */ "./_inc/selectors/api.js");
/* harmony import */ var _assets__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./assets */ "./_inc/selectors/assets.js");


/**
 * Internal dependencies
 */




var selectors = _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_objectSpread2__WEBPACK_IMPORTED_MODULE_0___default()({}, _connection_status__WEBPACK_IMPORTED_MODULE_1__.default), _api__WEBPACK_IMPORTED_MODULE_2__.default), _assets__WEBPACK_IMPORTED_MODULE_3__.default);

/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (selectors);

/***/ }),

/***/ "./_inc/store.js":
/*!***********************!*\
  !*** ./_inc/store.js ***!
  \***********************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "STORE_ID": () => (/* binding */ STORE_ID),
/* harmony export */   "storeConfig": () => (/* binding */ storeConfig)
/* harmony export */ });
/* harmony import */ var _reducers__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./reducers */ "./_inc/reducers/index.js");
/* harmony import */ var _actions__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./actions */ "./_inc/actions/index.js");
/* harmony import */ var _selectors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./selectors */ "./_inc/selectors/index.js");
/**
 * Internal dependencies
 */



var STORE_ID = 'jetpack-connection-ui';
var storeConfig = {
  reducer: _reducers__WEBPACK_IMPORTED_MODULE_0__.default,
  actions: _actions__WEBPACK_IMPORTED_MODULE_1__.default,
  selectors: _selectors__WEBPACK_IMPORTED_MODULE_2__.default,
  initialState: window.CUI_INITIAL_STATE || {}
};

/***/ }),

/***/ "./_inc/components/admin/assets/connect-right.png":
/*!********************************************************!*\
  !*** ./_inc/components/admin/assets/connect-right.png ***!
  \********************************************************/
/***/ ((module) => {

"use strict";
module.exports = "/" + "images/connect-right-f27775ac15cf885713c2.png";

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

"use strict";
module.exports = window["React"];

/***/ }),

/***/ "react-dom":
/*!***************************!*\
  !*** external "ReactDOM" ***!
  \***************************/
/***/ ((module) => {

"use strict";
module.exports = window["ReactDOM"];

/***/ }),

/***/ "lodash":
/*!*************************!*\
  !*** external "lodash" ***!
  \*************************/
/***/ ((module) => {

"use strict";
module.exports = window["lodash"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

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
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be in strict mode.
(() => {
"use strict";
/*!************************!*\
  !*** ./_inc/admin.jsx ***!
  \************************/
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



var store = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.createReduxStore)(_store__WEBPACK_IMPORTED_MODULE_4__.STORE_ID, _store__WEBPACK_IMPORTED_MODULE_4__.storeConfig);
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.register)(store);
/**
 * The initial renderer function.
 */

function render() {
  var container = document.getElementById('jetpack-connection-ui-container');

  if (null === container) {
    return;
  }

  react_dom__WEBPACK_IMPORTED_MODULE_0___default().render( /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1___default().createElement(_components_admin__WEBPACK_IMPORTED_MODULE_3__.default, null), container);
}

render();
})();

var __webpack_export_target__ = window;
for(var i in __webpack_exports__) __webpack_export_target__[i] = __webpack_exports__[i];
if(__webpack_exports__.__esModule) Object.defineProperty(__webpack_export_target__, "__esModule", { value: true });
/******/ })()
;
//# sourceMappingURL=index.js.map