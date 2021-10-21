/* eslint-disable */
"use strict";
(self["webpackChunkwebpack"] = self["webpackChunkwebpack"] || []).push([[161],{

/***/ 8260:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "Z": function() { return /* binding */ CustomizerEventHandler; }
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var _lib_customize__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(4880);


/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

 // This component is used to bind WordPress Customizer events to the Jetpack Search application.

class CustomizerEventHandler extends react__WEBPACK_IMPORTED_MODULE_1__/* .Component */ .wA {
  constructor(...args) {
    super(...args);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleOverlayOptionsUpdate", newOverlayOptions => {
      this.props.updateOverlayOptions(newOverlayOptions, () => this.props.showResults());
    });
  }

  componentDidMount() {
    (0,_lib_customize__WEBPACK_IMPORTED_MODULE_2__/* .bindCustomizerChanges */ .vJ)(this.handleOverlayOptionsUpdate);
    (0,_lib_customize__WEBPACK_IMPORTED_MODULE_2__/* .bindCustomizerMessages */ .Em)(this.props.toggleResults);
  }

  render() {
    return null;
  }

}

/***/ }),

/***/ 6250:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "Z": function() { return /* binding */ DomEventHandler; }
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var lodash_debounce__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(5034);
/* harmony import */ var lodash_debounce__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(lodash_debounce__WEBPACK_IMPORTED_MODULE_2__);


/**
 * External dependencies
 */
 // NOTE: We only import the debounce function here for reduced bundle size.
//       Do not import the entire lodash library!
// eslint-disable-next-line lodash/import-scope

 // This component is used primarily to bind DOM event handlers to elements outside of the Jetpack Search overlay.

class DomEventHandler extends react__WEBPACK_IMPORTED_MODULE_1__/* .Component */ .wA {
  constructor() {
    super(...arguments);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleCompositionStart", () => this.setState({
      isComposing: true
    }));

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleCompositionEnd", () => this.setState({
      isComposing: false
    }));

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleFilterInputClick", event => {
      event.preventDefault();

      if (event.currentTarget.dataset.filterType) {
        if (event.currentTarget.dataset.filterType === 'taxonomy') {
          this.props.setFilter(event.currentTarget.dataset.taxonomy, event.currentTarget.dataset.val);
        } else {
          this.props.setFilter(event.currentTarget.dataset.filterType, event.currentTarget.dataset.val);
        }
      }

      this.props.setSearchQuery('');
      this.props.showResults();
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleHistoryNavigation", () => {
      // Treat history navigation as brand new query values; re-initialize.
      // Note that this re-initialization will trigger onChangeQueryString via side effects.
      this.props.initializeQueryValues({
        isHistoryNavigation: true
      });
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleInput", lodash_debounce__WEBPACK_IMPORTED_MODULE_2___default()(event => {
      var _event$inputType;

      // Reference: https://rawgit.com/w3c/input-events/v1/index.html#interface-InputEvent-Attributes
      // NOTE: inputType is not compatible with IE11, so we use optional chaining here. https://caniuse.com/mdn-api_inputevent_inputtype
      if ((_event$inputType = event.inputType) !== null && _event$inputType !== void 0 && _event$inputType.includes('format') || event.target.value === '') {
        return;
      } // Is the user still composing input with a CJK language?


      if (this.state.isComposing) {
        return;
      }

      if (this.props.overlayOptions.overlayTrigger === 'submit') {
        return;
      }

      this.props.setSearchQuery(event.target.value);

      if (this.props.overlayOptions.overlayTrigger === 'immediate') {
        this.props.showResults();
      }

      if (this.props.overlayOptions.overlayTrigger === 'results') {
        var _this$props$response;

        ((_this$props$response = this.props.response) === null || _this$props$response === void 0 ? void 0 : _this$props$response.results) && this.props.showResults();
      }
    }, 200));

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleKeyup", event => {
      // If user presses enter, propagate the query value and immediately show the results.
      if (event.key === 'Enter') {
        this.props.setSearchQuery(event.target.value);
        this.props.showResults();
      }
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleOverlayTriggerClick", event => {
      event.stopImmediatePropagation();
      this.props.setSearchQuery('');
      this.props.showResults();
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleSubmit", event => {
      event.preventDefault();
      this.handleInput.flush(); // handleInput didn't respawn the overlay. Do it manually -- form submission must spawn an overlay.

      if (!this.props.isVisible) {
        var _event$target$querySe;

        const value = (_event$target$querySe = event.target.querySelector(this.props.themeOptions.searchInputSelector)) === null || _event$target$querySe === void 0 ? void 0 : _event$target$querySe.value; // Don't do a falsy check; empty string is an allowed value.

        typeof value === 'string' && this.props.setSearchQuery(value);
        this.props.showResults();
      }
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "fixBodyScroll", () => {
      if (this.props.isVisible) {
        var _window;

        this.preventBodyScroll(); // This ensures the search input is visible on mobile devices.
        // @see https://developer.mozilla.org/en-US/docs/Web/API/Element/scrollTo

        (_window = window) === null || _window === void 0 ? void 0 : _window.scrollTo(0, 0);
      } else if (!this.props.isVisible) {
        this.restoreBodyScroll();
      }
    });

    this.state = {
      // When typing in CJK, the following events fire in order:
      // keydown, compositionstart, compositionupdate, input, keyup, keydown,compositionend, keyup
      // We toggle isComposing on compositionstart and compositionend events.
      // (CJK = Chinese, Japanese, Korean; see https://en.wikipedia.org/wiki/CJK_characters)
      isComposing: false,
      // `bodyScrollTop` remembers the body scroll position.
      bodyScrollTop: 0,
      previousStyle: null,
      previousBodyStyleAttribute: ''
    };
    this.props.initializeQueryValues();
  }

  componentDidMount() {
    this.disableUnnecessaryFormAndInputAttributes();
    this.addEventListeners();
  }

  componentWillUnmount() {
    this.removeEventListeners();
    this.restoreBodyScroll();
  }

  componentDidUpdate(prevProps) {
    if (this.props.isVisible !== prevProps.isVisible) {
      this.fixBodyScroll();
    }
  }

  disableUnnecessaryFormAndInputAttributes() {
    // Disables the following attributes:
    // - autocomplete - leads to poor UX.
    // - required - prevents Instant Search from spawning in certain scenarios.
    document.querySelectorAll(this.props.themeOptions.searchInputSelector).forEach(input => {
      input.removeAttribute('required');
      input.removeAttribute('autocomplete');
      input.form.removeAttribute('autocomplete');
    });
  }

  addEventListeners() {
    window.addEventListener('popstate', this.handleHistoryNavigation); // Add listeners for input and submit

    document.querySelectorAll(this.props.themeOptions.searchInputSelector).forEach(input => {
      input.form.addEventListener('submit', this.handleSubmit); // keydown handler is causing text duplication because it actively sets the search input
      // value after system input method empty the input but before filling the input again.
      // so changed to keyup event which is fired after compositionend when Enter is pressed.

      input.addEventListener('keyup', this.handleKeyup);
      input.addEventListener('input', this.handleInput);
      input.addEventListener('compositionstart', this.handleCompositionStart);
      input.addEventListener('compositionend', this.handleCompositionEnd);
    });
    document.querySelectorAll(this.props.themeOptions.overlayTriggerSelector).forEach(button => {
      button.addEventListener('click', this.handleOverlayTriggerClick, true);
    });
    document.querySelectorAll(this.props.themeOptions.filterInputSelector).forEach(element => {
      element.addEventListener('click', this.handleFilterInputClick);
    });
  }

  removeEventListeners() {
    window.removeEventListener('popstate', this.handleHistoryNavigation);
    document.querySelectorAll(this.props.themeOptions.searchInputSelector).forEach(input => {
      input.form.removeEventListener('submit', this.handleSubmit);
      input.removeEventListener('keyup', this.handleKeyup);
      input.removeEventListener('input', this.handleInput);
      input.removeEventListener('compositionstart', this.handleCompositionStart);
      input.removeEventListener('compositionend', this.handleCompositionEnd);
    });
    document.querySelectorAll(this.props.themeOptions.overlayTriggerSelector).forEach(button => {
      button.removeEventListener('click', this.handleOverlayTriggerClick, true);
    });
    document.querySelectorAll(this.props.themeOptions.filterInputSelector).forEach(element => {
      element.removeEventListener('click', this.handleFilterInputClick);
    });
  }

  /**
   * 1) When the overlay is open, we set body to fixed position.
   * 2) Body would be scrolled to top, so we need to set top to where the scroll position was.
   * 3) And we remember the body postition in `this.state.bodyScrollTop`
   */
  preventBodyScroll() {
    this.setState({
      bodyScrollTop: parseInt(window.scrollY) || 0,
      previousStyle: {
        top: document.body.style.top,
        left: document.body.style.left,
        right: document.body.style.right,
        scrollBehavior: document.documentElement.style.scrollBehavior
      },
      previousBodyStyleAttribute: document.body.getAttribute('style')
    }, () => {
      var _document$documentEle, _document$body;

      /**
       * For logged-in user, there's a WP Admin Bar which is made sticky by adding `margin-top` to the document (the old way of `position: sticky;`).
       * So we need to fix the offset of scrollY for fixed positioned body.
       */
      const scrollYOffset = ((_document$documentEle = document.documentElement) === null || _document$documentEle === void 0 ? void 0 : _document$documentEle.scrollHeight) - ((_document$body = document.body) === null || _document$body === void 0 ? void 0 : _document$body.scrollHeight) || 0; // This is really important - e.g. `twentytwenty` set an important style to body which we'd need to override.
      // Make body not scrollable.

      document.body.setAttribute('style', 'position: fixed !important'); // Keep body at the same position when overlay is open.

      document.body.style.top = `-${this.state.bodyScrollTop - scrollYOffset}px`; // Make body in the center.

      document.body.style.left = 0;
      document.body.style.right = 0;
    });
  }
  /**
   * 1) Unset body fixed postion
   * 2) Scroll back to the `this.state.bodyScrollTop`
   * 3) Reset `this.state.bodyScrollTop` to `0`
   */


  restoreBodyScroll() {
    var _this$state$previousS, _this$state$previousS2, _this$state$previousS3, _this$state$previousS4, _this$state$previousS5, _this$state$previousS6, _this$state$previousS7, _this$state$previousS8;

    // Restore body style attribute.
    if (this.state.previousBodyStyleAttribute) {
      document.body.setAttribute('style', this.state.previousBodyStyleAttribute);
    } else {
      document.body.removeAttribute('style');
    } // Restore body style object.


    document.body.style.top = (_this$state$previousS = (_this$state$previousS2 = this.state.previousStyle) === null || _this$state$previousS2 === void 0 ? void 0 : _this$state$previousS2.top) !== null && _this$state$previousS !== void 0 ? _this$state$previousS : '';
    document.body.style.left = (_this$state$previousS3 = (_this$state$previousS4 = this.state.previousStyle) === null || _this$state$previousS4 === void 0 ? void 0 : _this$state$previousS4.left) !== null && _this$state$previousS3 !== void 0 ? _this$state$previousS3 : '';
    document.body.style.right = (_this$state$previousS5 = (_this$state$previousS6 = this.state.previousStyle) === null || _this$state$previousS6 === void 0 ? void 0 : _this$state$previousS6.right) !== null && _this$state$previousS5 !== void 0 ? _this$state$previousS5 : ''; // Prevent smooth scroll etc if there's any.

    document.documentElement.style.scrollBehavior = 'revert'; // Restore body position.

    this.state.bodyScrollTop > 0 && window.scrollTo(0, this.state.bodyScrollTop);
    document.documentElement.style.scrollBehavior = (_this$state$previousS7 = (_this$state$previousS8 = this.state.previousStyle) === null || _this$state$previousS8 === void 0 ? void 0 : _this$state$previousS8.scrollBehavior) !== null && _this$state$previousS7 !== void 0 ? _this$state$previousS7 : ''; //Restore states.

    this.setState({
      bodyScrollTop: 0,
      previousStyle: null,
      previousBodyStyleAttribute: ''
    });
  }

  render() {
    return null;
  }

}

/***/ }),

/***/ 3687:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(3163);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);


/* !!!
This is a fork of the Jetpack Gridicon code:
 https://github.com/Automattic/jetpack/blob/f8078c2cd12ac508334da2fb08e37a92cf283c14/_inc/client/components/gridicon/index.jsx

It has been modified to work with Preact, and only includes the icons that we need.
!!! */

/**
 * External dependencies
 */


const alias__ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__;
const __ = alias__;


class Gridicon extends react__WEBPACK_IMPORTED_MODULE_1__/* .Component */ .wA {
  needsOffset(icon, size) {
    const iconNeedsOffset = ['gridicons-calendar', 'gridicons-cart', 'gridicons-folder', 'gridicons-info', 'gridicons-posts', 'gridicons-star-outline', 'gridicons-star'];

    if (iconNeedsOffset.indexOf(icon) >= 0) {
      return size % 18 === 0;
    }

    return false;
  }

  getSVGTitle(icon) {
    // Enable overriding title with falsy/truthy values.
    if ('title' in this.props) {
      return this.props.title ? /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, this.props.title) : null;
    }

    switch (icon) {
      default:
        return null;

      case 'gridicons-audio':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Has audio.', 'jetpack'));

      case 'gridicons-calendar':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Is an event.', 'jetpack'));

      case 'gridicons-cart':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Is a product.', 'jetpack'));

      case 'chevron-down':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Show filters', 'jetpack'));

      case 'gridicons-comment':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Matching comment.', 'jetpack'));

      case 'gridicons-cross':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Close search results', 'jetpack'));

      case 'gridicons-filter':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Toggle search filters.', 'jetpack'));

      case 'gridicons-folder':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Category', 'jetpack'));

      case 'gridicons-image-multiple':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Has multiple images.', 'jetpack'));

      case 'gridicons-image':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Has an image.', 'jetpack'));

      case 'gridicons-page':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Page', 'jetpack'));

      case 'gridicons-post':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Post', 'jetpack'));

      case 'gridicons-jetpack-search':
      case 'gridicons-search':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Search', 'jetpack'));

      case 'gridicons-tag':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Tag', 'jetpack'));

      case 'gridicons-video':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("title", null, __('Has a video.', 'jetpack'));
    }
  }

  renderIcon(icon) {
    switch (icon) {
      default:
        return null;

      case 'gridicons-audio':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M8 4v10.184C7.686 14.072 7.353 14 7 14c-1.657 0-3 1.343-3 3s1.343 3 3 3 3-1.343 3-3V7h7v4.184c-.314-.112-.647-.184-1-.184-1.657 0-3 1.343-3 3s1.343 3 3 3 3-1.343 3-3V4H8z"
        }));

      case 'gridicons-block':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zM4 12c0-4.418 3.582-8 8-8 1.848 0 3.545.633 4.9 1.686L5.686 16.9C4.633 15.545 4 13.848 4 12zm8 8c-1.848 0-3.546-.633-4.9-1.686L18.314 7.1C19.367 8.455 20 10.152 20 12c0 4.418-3.582 8-8 8z"
        }));

      case 'gridicons-calendar':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M19 4h-1V2h-2v2H8V2H6v2H5c-1.105 0-2 .896-2 2v13c0 1.104.895 2 2 2h14c1.104 0 2-.896 2-2V6c0-1.104-.896-2-2-2zm0 15H5V8h14v11z"
        }));

      case 'gridicons-cart':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M9 20c0 1.1-.9 2-2 2s-1.99-.9-1.99-2S5.9 18 7 18s2 .9 2 2zm8-2c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2zm.396-5c.937 0 1.75-.65 1.952-1.566L21 5H7V4c0-1.105-.895-2-2-2H3v2h2v11c0 1.105.895 2 2 2h12c0-1.105-.895-2-2-2H7v-2h10.396z"
        }));

      case 'gridicons-chevron-down':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M20 9l-8 8-8-8 1.414-1.414L12 14.172l6.586-6.586"
        }));

      case 'gridicons-comment':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M3 6v9c0 1.105.895 2 2 2h9v5l5.325-3.804c1.05-.75 1.675-1.963 1.675-3.254V6c0-1.105-.895-2-2-2H5c-1.105 0-2 .895-2 2z"
        }));

      case 'gridicons-cross':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M18.36 19.78L12 13.41l-6.36 6.37-1.42-1.42L10.59 12 4.22 5.64l1.42-1.42L12 10.59l6.36-6.36 1.41 1.41L13.41 12l6.36 6.36z"
        }));

      case 'gridicons-filter':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M10 19h4v-2h-4v2zm-4-6h12v-2H6v2zM3 5v2h18V5H3z"
        }));

      case 'gridicons-folder':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M18 19H6c-1.1 0-2-.9-2-2V7c0-1.1.9-2 2-2h3c1.1 0 2 .9 2 2h7c1.1 0 2 .9 2 2v8c0 1.1-.9 2-2 2z"
        }));

      case 'gridicons-image':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M13 9.5c0-.828.672-1.5 1.5-1.5s1.5.672 1.5 1.5-.672 1.5-1.5 1.5-1.5-.672-1.5-1.5zM22 6v12c0 1.105-.895 2-2 2H4c-1.105 0-2-.895-2-2V6c0-1.105.895-2 2-2h16c1.105 0 2 .895 2 2zm-2 0H4v7.444L8 9l5.895 6.55 1.587-1.85c.798-.932 2.24-.932 3.037 0L20 15.426V6z"
        }));

      case 'gridicons-image-multiple':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M15 7.5c0-.828.672-1.5 1.5-1.5s1.5.672 1.5 1.5S17.328 9 16.5 9 15 8.328 15 7.5zM4 20h14c0 1.105-.895 2-2 2H4c-1.1 0-2-.9-2-2V8c0-1.105.895-2 2-2v14zM22 4v12c0 1.105-.895 2-2 2H8c-1.105 0-2-.895-2-2V4c0-1.105.895-2 2-2h12c1.105 0 2 .895 2 2zM8 4v6.333L11 7l4.855 5.395.656-.73c.796-.886 2.183-.886 2.977 0l.513.57V4H8z"
        }));

      case 'gridicons-info':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"
        }));

      case 'gridicons-jetpack-search':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M0 9.257C0 4.15 4.151 0 9.257 0c5.105 0 9.256 4.151 9.256 9.257a9.218 9.218 0 01-2.251 6.045l.034.033h1.053L24 22.01l-1.986 1.989-6.664-6.662v-1.055l-.033-.033a9.218 9.218 0 01-6.06 2.264C4.15 18.513 0 14.362 0 9.257zm4.169 1.537h4.61V1.82l-4.61 8.973zm5.547-3.092v8.974l4.61-8.974h-4.61z"
        }));

      case 'gridicons-pages':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M16 8H8V6h8v2zm0 2H8v2h8v-2zm4-6v12l-6 6H6c-1.105 0-2-.895-2-2V4c0-1.105.895-2 2-2h12c1.105 0 2 .895 2 2zm-2 10V4H6v16h6v-4c0-1.105.895-2 2-2h4z"
        }));

      case 'gridicons-posts':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M16 19H3v-2h13v2zm5-10H3v2h18V9zM3 5v2h11V5H3zm14 0v2h4V5h-4zm-6 8v2h10v-2H11zm-8 0v2h5v-2H3z"
        }));

      case 'gridicons-search':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M21 19l-5.154-5.154C16.574 12.742 17 11.42 17 10c0-3.866-3.134-7-7-7s-7 3.134-7 7 3.134 7 7 7c1.42 0 2.742-.426 3.846-1.154L19 21l2-2zM5 10c0-2.757 2.243-5 5-5s5 2.243 5 5-2.243 5-5 5-5-2.243-5-5z"
        }));

      case 'gridicons-star-outline':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M12 6.308l1.176 3.167.347.936.997.042 3.374.14-2.647 2.09-.784.62.27.963.91 3.25-2.813-1.872-.83-.553-.83.552-2.814 1.87.91-3.248.27-.962-.783-.62-2.648-2.092 3.374-.14.996-.04.347-.936L12 6.308M12 2L9.418 8.953 2 9.257l5.822 4.602L5.82 21 12 16.89 18.18 21l-2.002-7.14L22 9.256l-7.418-.305L12 2z"
        }));

      case 'gridicons-star':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M12 2l2.582 6.953L22 9.257l-5.822 4.602L18.18 21 12 16.89 5.82 21l2.002-7.14L2 9.256l7.418-.304"
        }));

      case 'gridicons-tag':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M20 2.007h-7.087c-.53 0-1.04.21-1.414.586L2.592 11.5c-.78.78-.78 2.046 0 2.827l7.086 7.086c.78.78 2.046.78 2.827 0l8.906-8.906c.376-.374.587-.883.587-1.413V4.007c0-1.105-.895-2-2-2zM17.007 9c-1.105 0-2-.895-2-2s.895-2 2-2 2 .895 2 2-.895 2-2 2z"
        }));

      case 'gridicons-video':
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("g", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("path", {
          d: "M20 4v2h-2V4H6v2H4V4c-1.105 0-2 .895-2 2v12c0 1.105.895 2 2 2v-2h2v2h12v-2h2v2c1.105 0 2-.895 2-2V6c0-1.105-.895-2-2-2zM6 16H4v-3h2v3zm0-5H4V8h2v3zm4 4V9l4.5 3-4.5 3zm10 1h-2v-3h2v3zm0-5h-2V8h2v3z"
        }));
    }
  }

  render() {
    const {
      size = 24,
      className = ''
    } = this.props;
    const height = this.props.height || size;
    const width = this.props.width || size;
    const style = this.props.style || {
      height,
      width
    };
    const icon = 'gridicons-' + this.props.icon,
          needsOffset = this.needsOffset(icon, size);
    let iconClass = ['gridicon', icon, className];

    if (needsOffset) {
      iconClass.push('needs-offset');
    }

    iconClass = iconClass.join(' ');
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("svg", {
      className: iconClass,
      focusable: this.props.focusable,
      height: height,
      onClick: this.props.onClick,
      style: style,
      viewBox: "0 0 24 24",
      width: width,
      xmlns: "http://www.w3.org/2000/svg",
      "aria-hidden": this.props['aria-hidden']
    }, this.getSVGTitle(icon), this.renderIcon(icon));
  }

}

_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(Gridicon, "defaultProps", {
  'aria-hidden': 'false',
  focusable: 'true'
});

/* harmony default export */ __webpack_exports__["Z"] = (Gridicon);

/***/ }),

/***/ 5504:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* unused harmony export svg */
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(3163);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
// The PALETTE global comes from '@automattic/color-studio' at build time.
// This is done so that the individual color values are bundled as hardcoded literals, rather than
// having to include the entire color set in the bundle.
// This will work as long as the keys are always literals as well.

/* global PALETTE */

/**
 * External dependencies
 */


/**
 * Internal dependencies
 */

const alias__ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__;
const __ = alias__;

/**
 * Module constants
 */

const COLOR_JETPACK = "#069e08";
const COLOR_WHITE = "#fff"; // eslint-disable-line dot-notation

const logoSize = 12;
const svg = /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("svg", {
  className: "jetpack-instant-search__jetpack-colophon-logo",
  height: logoSize,
  width: logoSize,
  viewBox: `0 0 32 32`
}, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("path", {
  className: "jetpack-logo__icon-circle",
  fill: COLOR_JETPACK,
  d: "M16,0C7.2,0,0,7.2,0,16s7.2,16,16,16s16-7.2,16-16S24.8,0,16,0z"
}), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("polygon", {
  className: "jetpack-logo__icon-triangle",
  fill: COLOR_WHITE,
  points: "15,19 7,19 15,3 "
}), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("polygon", {
  className: "jetpack-logo__icon-triangle",
  fill: COLOR_WHITE,
  points: "17,29 17,13 25,13 "
}));

const JetpackColophon = props => {
  const locale_prefix = typeof props.locale === 'string' ? props.locale.split('-', 1)[0] : null;
  const url = locale_prefix && locale_prefix !== 'en' ? 'https://' + locale_prefix + '.jetpack.com/search?utm_source=poweredby' : 'https://jetpack.com/search?utm_source=poweredby';
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__jetpack-colophon"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("a", {
    href: url,
    rel: "external noopener noreferrer nofollow",
    target: "_blank",
    className: "jetpack-instant-search__jetpack-colophon-link"
  }, svg, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
    className: "jetpack-instant-search__jetpack-colophon-text"
  }, __('Search powered by Jetpack', 'jetpack'))));
};

/* harmony default export */ __webpack_exports__["Z"] = (JetpackColophon);

/***/ }),

/***/ 4367:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/* harmony import */ var _gridicon__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(3687);
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */




const Notice = ({
  type,
  children
}) => {
  if (type !== 'warning') {
    return null;
  }

  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__notice jetpack-instant-search__notice--warning"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
    icon: "info",
    size: 20
  }), children);
};

/* harmony default export */ __webpack_exports__["Z"] = (Notice);

/***/ }),

/***/ 5968:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3163);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var _lib_constants__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(9379);
/**
 * External dependencies
 */

const alias__ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__;
const __ = alias__;

/**
 * Internal dependencies
 */




const callOnEscapeKey = callback => event => {
  // IE11 uses 'Esc'
  if (event.key === 'Escape' || event.key === 'Esc') {
    event.preventDefault();
    callback();
  }
};

const Overlay = props => {
  const {
    children,
    closeOverlay,
    colorTheme,
    hasOverlayWidgets,
    isVisible
  } = props;
  const closeWithEscape = callOnEscapeKey(closeOverlay);
  (0,react__WEBPACK_IMPORTED_MODULE_1__/* .useEffect */ .d4)(() => {
    window.addEventListener('keydown', closeWithEscape);
    return () => {
      // Cleanup after event
      window.removeEventListener('keydown', closeWithEscape);
    };
  }, [closeWithEscape]);
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
    "aria-hidden": !isVisible,
    "aria-labelledby": "jetpack-instant-search__overlay-title",
    className: ['jetpack-instant-search', _lib_constants__WEBPACK_IMPORTED_MODULE_2__/* .OVERLAY_CLASS_NAME */ .zg, `jetpack-instant-search__overlay--${colorTheme}`, hasOverlayWidgets ? '' : 'jetpack-instant-search__overlay--no-sidebar', isVisible ? '' : 'is-hidden'].join(' '),
    role: "dialog"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("h1", {
    id: "jetpack-instant-search__overlay-title",
    className: "screen-reader-text"
  }, __('Search results', 'jetpack')), children);
};

/* harmony default export */ __webpack_exports__["Z"] = (Overlay);

/***/ }),

/***/ 4468:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/**
 * External dependencies
 */



function splitDomainPath(path) {
  const splits = path.split('/').filter(piece => piece.length > 0);
  splits.shift(); // Removes domain name from splits; e.g. 'jetpack.com'

  return splits;
}

const PathBreadcrumbs = ({
  className,
  onClick,
  url
}) => {
  const breadcrumbPieces = splitDomainPath(url);

  if (breadcrumbPieces.length < 1) {
    return null;
  }

  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: `jetpack-instant-search__path-breadcrumb ${className ? className : ''}`
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("a", {
    className: "jetpack-instant-search__path-breadcrumb-link",
    href: `//${url}`,
    onClick: onClick
  }, breadcrumbPieces.map((piece, index, pieces) => /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
    className: "jetpack-instant-search__path-breadcrumb-piece"
  }, decodeURIComponent(piece), index !== pieces.length - 1 ? ' › ' : ''))));
};

/* harmony default export */ __webpack_exports__["Z"] = (PathBreadcrumbs);

/***/ }),

/***/ 9767:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(8900);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var _lib_hooks_use_photon__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(4664);


/**
 * External dependencies
 */

/**
 * Internal dependencies
 */



const PhotonImage = props => {
  const {
    alt,
    isPhotonEnabled,
    maxHeight = 600,
    maxWidth = 600,
    src: originalSrc,
    lazyLoad = true,
    ...otherProps
  } = props;
  const image = (0,react__WEBPACK_IMPORTED_MODULE_1__/* .useRef */ .sO)();
  const [lazySrc, setLazySrc] = (0,react__WEBPACK_IMPORTED_MODULE_1__/* .useState */ .eJ)(null);
  const src = (0,_lib_hooks_use_photon__WEBPACK_IMPORTED_MODULE_2__/* .usePhoton */ .y)(originalSrc, maxWidth, maxHeight, isPhotonEnabled); // Enable lazy loading via IntersectionObserver if possible.

  (0,react__WEBPACK_IMPORTED_MODULE_1__/* .useEffect */ .d4)(() => {
    // Wait until src is available
    if (!src) {
      return;
    }

    let observer = null;

    if (lazyLoad && 'IntersectionObserver' in window) {
      observer = new window.IntersectionObserver((entries, obs) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            setLazySrc(src);
            obs.unobserve(entry.target);
          }
        }
      });
      observer.observe(image.current);
    } else {
      setLazySrc(src);
    }

    return () => {
      var _observer;

      (_observer = observer) === null || _observer === void 0 ? void 0 : _observer.disconnect();
    };
  }, [lazyLoad, src]);
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("img", _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({
    alt: alt,
    ref: image,
    src: lazySrc
  }, otherProps));
};

/* harmony default export */ __webpack_exports__["Z"] = (PhotonImage);

/***/ }),

/***/ 1679:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/* harmony import */ var _gridicon__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(3687);
/* harmony import */ var _lib_array_overlap__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(8984);
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */



const KNOWN_SHORTCODE_TYPES = {
  video: ['youtube', 'ooyala', 'anvplayer', 'wpvideo', 'bc_video', 'video', 'brightcove', 'tp_video', 'jwplayer', 'tempo-video', 'vimeo'],
  gallery: ['gallery', 'ione_media_gallery'],
  audio: ['audio', 'soundcloud']
};
const POST_TYPE_TO_ICON_MAP = {
  product: 'cart',
  video: 'video',
  gallery: 'image-multiple',
  event: 'calendar',
  events: 'calendar'
};

const PostTypeIcon = ({
  postType,
  shortcodeTypes,
  iconSize = 18
}) => {
  // Do we have a special icon for this post type?
  if (Object.keys(POST_TYPE_TO_ICON_MAP).includes(postType)) {
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
      icon: POST_TYPE_TO_ICON_MAP[postType],
      size: iconSize
    });
  } // Otherwise, choose the icon based on whether the post has certain shortcodes


  const hasVideo = (0,_lib_array_overlap__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z)(shortcodeTypes, KNOWN_SHORTCODE_TYPES.video);
  const hasAudio = (0,_lib_array_overlap__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z)(shortcodeTypes, KNOWN_SHORTCODE_TYPES.audio);
  const hasGallery = (0,_lib_array_overlap__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z)(shortcodeTypes, KNOWN_SHORTCODE_TYPES.gallery);

  if (hasVideo) {
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
      icon: "video",
      size: iconSize
    });
  } else if (hasAudio) {
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
      icon: "audio",
      size: iconSize
    });
  }

  switch (postType) {
    case 'page':
      return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
        icon: "pages",
        size: iconSize
      });

    default:
      if (hasGallery) {
        return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
          icon: "image-multiple",
          size: iconSize
        });
      }

  }

  return null;
};

/* harmony default export */ __webpack_exports__["Z"] = (PostTypeIcon);

/***/ }),

/***/ 7473:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/**
 * External dependencies
 */

/**
 * Style dependencies
 */



class ProductPrice extends react__WEBPACK_IMPORTED_MODULE_0__/* .Component */ .wA {
  render() {
    const {
      formattedPrice,
      formattedSalePrice,
      formattedRegularPrice,
      price,
      salePrice
    } = this.props;

    if (!price) {
      return null;
    }
    /* eslint-disable react/no-danger */


    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
      className: "jetpack-instant-search__product-price"
    }, salePrice > 0 ? /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(react__WEBPACK_IMPORTED_MODULE_0__/* .Fragment */ .HY, null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("s", {
      className: "jetpack-instant-search__product-price-regular",
      dangerouslySetInnerHTML: {
        __html: formattedRegularPrice
      }
    }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
      dangerouslySetInnerHTML: {
        __html: formattedSalePrice
      }
    })) : /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
      dangerouslySetInnerHTML: {
        __html: formattedPrice
      }
    }));
    /* eslint-enable react/no-danger */
  }

}

/* harmony default export */ __webpack_exports__["Z"] = (ProductPrice);

/***/ }),

/***/ 1162:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "Z": function() { return /* binding */ ProductRatings; }
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3163);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var _gridicon__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(3687);
/**
 * External dependencies
 */

const alias_n = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__._n;
const _n = alias_n;

/**
 * Internal dependencies
 */


/**
 * Style dependencies
 */


/**
 * Renders a hook-based component for displaying product ratings.
 *
 * @param {object} props - Component properties.
 * @param {number} props.count - Number of ratings.
 * @param {number} props.rating - Average rating out of five.
 * @param {string} props.permalink - Permalink URL to product page.
 * @returns {object} Product rating component.
 */

function ProductRatings({
  rating = 0,
  count = 0,
  permalink
}) {
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__product-rating"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("span", {
    "aria-hidden": true,
    className: "jetpack-instant-search__product-rating-stars"
  }, Array(5).fill( /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z, {
    size: 16,
    icon: "star-outline"
  })).fill( /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z, {
    size: 16,
    icon: "star"
  }), 0, rating)), ' ', /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("a", {
    "aria-hidden": true,
    className: "jetpack-instant-search__product-rating-count",
    href: permalink + '#reviews'
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)(
  /* Translators: the placeholder is the number of product reviews. */
  _n('%d review', '%d reviews', count, 'jetpack'), count)), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("span", {
    className: "screen-reader-text"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)(
  /* Translators: the first placeholder is the average product rating out of 5; the second is the number of product reviews. */
  _n('Average rating of %d out of 5 from %d review.', 'Average rating of %d out of 5 from %d reviews.', count, 'jetpack'), Number(rating).toFixed(2), count)));
}

/***/ }),

/***/ 2456:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(3163);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var lodash_debounce__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(5034);
/* harmony import */ var lodash_debounce__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(lodash_debounce__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _lib_constants__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(9379);


/**
 * External dependencies
 */

 // NOTE: We only import the debounce function here for reduced bundle size.
//       Do not import the entire lodash library!
// eslint-disable-next-line lodash/import-scope

const alias__ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__;
const __ = alias__;

/**
 * Internal dependencies
 */




class ScrollButton extends react__WEBPACK_IMPORTED_MODULE_1__/* .Component */ .wA {
  constructor(...args) {
    super(...args);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "overlayElement", document.getElementsByClassName(_lib_constants__WEBPACK_IMPORTED_MODULE_4__/* .OVERLAY_CLASS_NAME */ .zg)[0]);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "checkScroll", lodash_debounce__WEBPACK_IMPORTED_MODULE_3___default()(() => {
      if (this.props.enableLoadOnScroll && window.innerHeight + this.overlayElement.scrollTop === this.overlayElement.scrollHeight) {
        this.props.onLoadNextPage();
      }
    }, 100));
  }

  componentDidMount() {
    this.overlayElement.addEventListener('scroll', this.checkScroll);
  }

  componentDidUnmount() {
    this.overlayElement.removeEventListener('scroll', this.checkScroll);
  }

  render() {
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("button", {
      className: "jetpack-instant-search__scroll-button",
      disabled: this.props.isLoading,
      onClick: this.props.onLoadNextPage
    }, this.props.isLoading ? /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("span", null, __('Loading…', 'jetpack')) : /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("span", null, __('Load more', 'jetpack')));
  }

}

/* harmony default export */ __webpack_exports__["Z"] = (ScrollButton);

/***/ }),

/***/ 7758:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var lodash_debounce__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(5034);
/* harmony import */ var lodash_debounce__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(lodash_debounce__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_redux__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(2620);
/* harmony import */ var fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(8027);
/* harmony import */ var fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _customizer_event_handler__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(8260);
/* harmony import */ var _dom_event_handler__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(6250);
/* harmony import */ var _overlay__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(5968);
/* harmony import */ var _search_results__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(8898);
/* harmony import */ var _lib_tracks__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(814);
/* harmony import */ var _lib_constants__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(9379);
/* harmony import */ var _lib_filters__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(8033);
/* harmony import */ var _lib_query_string__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(227);
/* harmony import */ var _store_actions__WEBPACK_IMPORTED_MODULE_14__ = __webpack_require__(7717);
/* harmony import */ var _store_selectors__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(1248);


/**
 * External dependencies
 */

 // NOTE: We only import the debounce function here for reduced bundle size.
//       Do not import the entire lodash library!
// eslint-disable-next-line lodash/import-scope




/**
 * Internal dependencies
 */













class SearchApp extends react__WEBPACK_IMPORTED_MODULE_1__/* .Component */ .wA {
  constructor() {
    super(...arguments);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "getResultFormat", () => {
      // Override the result format from the query string if result_format= is specified
      const resultFormatQuery = (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_12__/* .getResultFormatQuery */ .ug)(); // Override the result format if group static filter is selected, always use expanded.

      const isMultiSite = this.props.staticFilters && this.props.staticFilters.group_id && this.props.staticFilters.group_id !== _lib_constants__WEBPACK_IMPORTED_MODULE_10__/* .MULTISITE_NO_GROUP_VALUE */ .Bk;

      if (isMultiSite) {
        return _lib_constants__WEBPACK_IMPORTED_MODULE_10__/* .RESULT_FORMAT_EXPANDED */ .Pz;
      }

      return resultFormatQuery || this.state.overlayOptions.resultFormat;
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "initializeStaticFilters", () => {
      const availableStaticFilters = (0,_lib_filters__WEBPACK_IMPORTED_MODULE_11__/* .getAvailableStaticFilters */ .bA)();

      if (availableStaticFilters.length > 0 && Object.keys(this.props.staticFilters).length === 0) {
        availableStaticFilters.forEach(filter => this.props.setStaticFilter(filter.filter_id, filter.selected, true));
      }
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "hideResults", isHistoryNav => {
      if (!this.props.shouldIntegrateWithDom) {
        return;
      }

      (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_12__/* .restorePreviousHref */ .Q0)(this.props.initialHref, () => {
        this.setState({
          isVisible: false
        });
        this.props.clearQueryValues();
      }, isHistoryNav);
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "toggleResults", isVisible => {
      // Prevent interaction if being shown in Customberg context.
      if (!this.props.shouldIntegrateWithDom) {
        return;
      } // Necessary when reacting to onMessage transport Customizer controls.
      // Both bindCustomizerChanges and bindCustomizerMessages are bound to such controls.


      if (this.state.isVisible === isVisible) {
        return;
      } // If there are static filters available, but they are not part of the url/state, we will set their default value


      isVisible && this.initializeStaticFilters();
      this.setState({
        isVisible
      });
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "showResults", this.toggleResults.bind(this, true));

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "onChangeQueryString", isHistoryNav => {
      this.getResults();

      if (this.props.hasActiveQuery && !this.state.isVisible) {
        this.showResults();
      }

      if (!this.props.hasActiveQuery && isHistoryNav) {
        this.hideResults(isHistoryNav);
      }

      this.props.searchQuery !== null && document.querySelectorAll(this.props.themeOptions.searchInputSelector).forEach(input => {
        input.value = this.props.searchQuery;
      });
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "loadNextPage", () => {
      this.props.hasNextPage && this.getResults({
        pageHandle: this.props.response.page_handle
      });
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "getResults", ({
      pageHandle
    } = {}) => {
      this.props.makeSearchRequest({
        // Skip aggregations when requesting for paged results
        aggregations: pageHandle ? {} : this.props.aggregations,
        excludedPostTypes: this.state.overlayOptions.excludedPostTypes,
        filter: this.props.filters,
        staticFilters: this.props.staticFilters,
        pageHandle,
        query: this.props.searchQuery,
        resultFormat: this.getResultFormat(),
        siteId: this.props.options.siteId,
        sort: this.props.sort,
        postsPerPage: this.props.options.postsPerPage,
        adminQueryFilter: this.props.options.adminQueryFilter,
        isInCustomizer: this.props.isInCustomizer
      });
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "updateOverlayOptions", (newOverlayOptions, callback) => {
      this.setState(state => ({
        overlayOptionsCustomizerOverride: { ...state.overlayOptionsCustomizerOverride,
          ...newOverlayOptions
        }
      }), callback);
    });

    this.state = {
      isVisible: !!this.props.initialIsVisible,
      // initialIsVisible can be undefined
      overlayOptionsCustomizerOverride: {}
    };
    this.getResults = lodash_debounce__WEBPACK_IMPORTED_MODULE_2___default()(this.getResults, 200);
    this.props.enableAnalytics ? this.initializeAnalytics() : (0,_lib_tracks__WEBPACK_IMPORTED_MODULE_9__/* .disableAnalytics */ .IJ)();

    if (this.props.shouldIntegrateWithDom) {
      this.props.initializeQueryValues();
    } else {
      this.props.disableQueryStringIntegration();
    }
  }

  static getDerivedStateFromProps(props, state) {
    return {
      overlayOptions: { ...props.overlayOptions,
        ...state.overlayOptionsCustomizerOverride
      }
    };
  }

  componentDidMount() {
    // By debouncing this upon mounting, we avoid making unnecessary requests.
    //
    // E.g. Given `/?s=apple`, the search app will mount with search query "" and invoke getResults.
    //      Once our Redux effects have executed, the search query will be updated to "apple" and
    //      getResults will be invoked once more.
    this.getResults();

    if (this.props.hasActiveQuery) {
      this.showResults();
    }
  }

  componentDidUpdate(prevProps, prevState) {
    if (prevProps.searchQuery !== this.props.searchQuery || prevProps.sort !== this.props.sort || // Note the special handling for filters prop, which use object values.
    fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_4___default()(prevProps.filters) !== fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_4___default()(this.props.filters) || fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_4___default()(prevProps.staticFilters) !== fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_4___default()(this.props.staticFilters)) {
      this.onChangeQueryString(this.props.isHistoryNavigation);
    } // These conditions can only occur in the Gutenberg preview context.


    if (prevState.overlayOptions.defaultSort !== this.state.overlayOptions.defaultSort) {
      this.props.setSort(this.state.overlayOptions.defaultSort);
    }

    if (fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_4___default()(prevState.overlayOptions.excludedPostTypes) !== fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_4___default()(this.state.overlayOptions.excludedPostTypes)) {
      this.getResults();
    }
  }

  initializeAnalytics() {
    (0,_lib_tracks__WEBPACK_IMPORTED_MODULE_9__/* .initializeTracks */ .tU)();
    (0,_lib_tracks__WEBPACK_IMPORTED_MODULE_9__/* .resetTrackingCookies */ .vy)();
    (0,_lib_tracks__WEBPACK_IMPORTED_MODULE_9__/* .identifySite */ .AM)(this.props.options.siteId);
  }

  render() {
    const noop = input => input;

    const resultFormat = this.getResultFormat();
    const portalFn = this.props.shouldCreatePortal ? react__WEBPACK_IMPORTED_MODULE_1__/* .createPortal */ .jz : noop;
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(react__WEBPACK_IMPORTED_MODULE_1__/* .Fragment */ .HY, null, this.props.isInCustomizer && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(_customizer_event_handler__WEBPACK_IMPORTED_MODULE_5__/* .default */ .Z, {
      showResults: this.showResults,
      toggleResults: this.toggleResults,
      updateOverlayOptions: this.updateOverlayOptions
    }), this.props.shouldIntegrateWithDom && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(_dom_event_handler__WEBPACK_IMPORTED_MODULE_6__/* .default */ .Z, {
      initializeQueryValues: this.props.initializeQueryValues,
      isVisible: this.state.isVisible,
      overlayOptions: this.state.overlayOptions,
      setFilter: this.props.setFilter,
      setSearchQuery: this.props.setSearchQuery,
      showResults: this.showResults,
      themeOptions: this.props.themeOptions
    }), portalFn( /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(_overlay__WEBPACK_IMPORTED_MODULE_7__/* .default */ .Z, {
      closeColor: this.state.overlayOptions.closeColor,
      closeOverlay: this.hideResults,
      colorTheme: this.state.overlayOptions.colorTheme,
      hasOverlayWidgets: this.props.hasOverlayWidgets,
      isVisible: this.state.isVisible
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(_search_results__WEBPACK_IMPORTED_MODULE_8__/* .default */ .Z, {
      closeOverlay: this.hideResults,
      enableLoadOnScroll: this.state.overlayOptions.enableInfScroll,
      enableSort: this.state.overlayOptions.enableSort,
      filters: this.props.filters,
      staticFilters: this.props.staticFilters,
      hasError: this.props.hasError,
      hasNextPage: this.props.hasNextPage,
      highlightColor: this.state.overlayOptions.highlightColor,
      isLoading: this.props.isLoading,
      isPhotonEnabled: this.props.options.isPhotonEnabled,
      isPrivateSite: this.props.options.isPrivateSite,
      isVisible: this.state.isVisible,
      locale: this.props.options.locale,
      onChangeSearch: this.props.setSearchQuery,
      onChangeSort: this.props.setSort,
      onLoadNextPage: this.loadNextPage,
      overlayTrigger: this.state.overlayOptions.overlayTrigger,
      postTypes: this.props.options.postTypes,
      response: this.props.response,
      resultFormat: resultFormat,
      searchQuery: this.props.searchQuery,
      showPoweredBy: this.state.overlayOptions.showPoweredBy,
      sort: this.props.sort,
      widgets: this.props.options.widgets,
      widgetOutsideOverlay: this.props.widgetOutsideOverlay,
      hasNonSearchWidgets: this.props.options.hasNonSearchWidgets
    })), document.body));
  }

}

_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(SearchApp, "defaultProps", {
  overlayOptions: {},
  widgets: []
});

/* harmony default export */ __webpack_exports__["Z"] = ((0,react_redux__WEBPACK_IMPORTED_MODULE_3__/* .connect */ .$j)((state, props) => ({
  filters: (0,_store_selectors__WEBPACK_IMPORTED_MODULE_13__/* .getFilters */ .Zj)(state),
  staticFilters: (0,_store_selectors__WEBPACK_IMPORTED_MODULE_13__/* .getStaticFilters */ .Bk)(state),
  hasActiveQuery: (0,_store_selectors__WEBPACK_IMPORTED_MODULE_13__/* .hasActiveQuery */ .en)(state),
  hasError: (0,_store_selectors__WEBPACK_IMPORTED_MODULE_13__/* .hasError */ .xT)(state),
  isHistoryNavigation: (0,_store_selectors__WEBPACK_IMPORTED_MODULE_13__/* .isHistoryNavigation */ .wI)(state),
  hasNextPage: (0,_store_selectors__WEBPACK_IMPORTED_MODULE_13__/* .hasNextPage */ .Qy)(state),
  isLoading: (0,_store_selectors__WEBPACK_IMPORTED_MODULE_13__/* .isLoading */ .hg)(state),
  response: (0,_store_selectors__WEBPACK_IMPORTED_MODULE_13__/* .getResponse */ .ck)(state),
  searchQuery: (0,_store_selectors__WEBPACK_IMPORTED_MODULE_13__/* .getSearchQuery */ .uP)(state),
  sort: (0,_store_selectors__WEBPACK_IMPORTED_MODULE_13__/* .getSort */ .r$)(state, props.overlayOptions.defaultSort),
  widgetOutsideOverlay: (0,_store_selectors__WEBPACK_IMPORTED_MODULE_13__/* .getWidgetOutsideOverlay */ .ZN)(state)
}), {
  clearQueryValues: _store_actions__WEBPACK_IMPORTED_MODULE_14__/* .clearQueryValues */ .Mz,
  disableQueryStringIntegration: _store_actions__WEBPACK_IMPORTED_MODULE_14__/* .disableQueryStringIntegration */ .OZ,
  initializeQueryValues: _store_actions__WEBPACK_IMPORTED_MODULE_14__/* .initializeQueryValues */ .Ln,
  makeSearchRequest: _store_actions__WEBPACK_IMPORTED_MODULE_14__/* .makeSearchRequest */ .x1,
  setStaticFilter: _store_actions__WEBPACK_IMPORTED_MODULE_14__/* .setStaticFilter */ .O1,
  setFilter: _store_actions__WEBPACK_IMPORTED_MODULE_14__/* .setFilter */ .Tv,
  setSearchQuery: _store_actions__WEBPACK_IMPORTED_MODULE_14__/* .setSearchQuery */ .ql,
  setSort: _store_actions__WEBPACK_IMPORTED_MODULE_14__/* .setSort */ .HD
})(SearchApp));

/***/ }),

/***/ 5772:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(3163);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var lodash_uniqueId__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(660);
/* harmony import */ var lodash_uniqueId__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(lodash_uniqueId__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _gridicon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(3687);
/**
 * External dependencies
 */

 // eslint-disable-next-line lodash/import-scope

const alias__ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__;
const __ = alias__;

/**
 * Internal dependencies
 */



let initiallyFocusedElement = null;

const stealFocusWithInput = inputElement => () => {
  initiallyFocusedElement = document.activeElement;
  inputElement.focus();
};

const restoreFocus = () => initiallyFocusedElement && initiallyFocusedElement.focus();

const SearchBox = props => {
  var _props$searchQuery;

  const [inputId] = (0,react__WEBPACK_IMPORTED_MODULE_0__/* .useState */ .eJ)(() => lodash_uniqueId__WEBPACK_IMPORTED_MODULE_2___default()('jetpack-instant-search__box-input-'));
  const inputRef = (0,react__WEBPACK_IMPORTED_MODULE_0__/* .useRef */ .sO)(null);
  (0,react__WEBPACK_IMPORTED_MODULE_0__/* .useEffect */ .d4)(() => {
    if (props.isVisible) {
      stealFocusWithInput(inputRef.current)();
    } else if (props.shouldRestoreFocus) {
      restoreFocus();
    }
  }, [props.isVisible, props.shouldRestoreFocus]);
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(react__WEBPACK_IMPORTED_MODULE_0__/* .Fragment */ .HY, null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__box"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("label", {
    className: "jetpack-instant-search__box-label",
    htmlFor: inputId
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
    className: "screen-reader-text assistive-text"
  }, __('Site Search', 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__box-gridicon"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_3__/* .default */ .Z, {
    icon: "search",
    size: 24
  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("input", {
    autoComplete: "off",
    id: inputId,
    className: "search-field jetpack-instant-search__box-input",
    inputMode: "search" // IE11 will immediately fire an onChange event when the placeholder contains a unicode character.
    // Ensure that the search application is visible before invoking the onChange callback to guard against this.
    ,
    onChange: props.isVisible ? props.onChange : null,
    ref: inputRef,
    placeholder: __('Search…', 'jetpack'),
    type: "search",
    value: (_props$searchQuery = props.searchQuery) !== null && _props$searchQuery !== void 0 ? _props$searchQuery : ''
  }), typeof props.searchQuery === 'string' && props.searchQuery.length > 0 &&
  /*#__PURE__*/

  /* Translators: Button is used to clear the search input query. */
  react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("input", {
    type: "button",
    value: __('clear', 'jetpack'),
    onClick: props.onClear
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("button", {
    className: "screen-reader-text assistive-text"
  }, __('Search', 'jetpack')))));
};

/* harmony default export */ __webpack_exports__["Z"] = (SearchBox);

/***/ }),

/***/ 1712:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/* harmony import */ var _search_sort__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(466);
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */




const SearchControls = props => {
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__search-form-controls"
  }, props.children, props.enableSort && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_search_sort__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
    onChange: props.onChangeSort,
    resultFormat: props.resultFormat,
    value: props.sort
  }));
};

/* harmony default export */ __webpack_exports__["Z"] = (SearchControls);

/***/ }),

/***/ 6995:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "a": function() { return /* binding */ fixDateFormat; },
/* harmony export */   "Z": function() { return /* binding */ SearchFilter; }
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var strip__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(7078);
/* harmony import */ var strip__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(strip__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var lodash_uniqueId__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(660);
/* harmony import */ var lodash_uniqueId__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(lodash_uniqueId__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _lib_dom__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(7266);


/**
 * External dependencies
 */

 // eslint-disable-next-line lodash/import-scope


/**
 * Internal dependencies
 */



function getDateOptions(interval) {
  switch (interval) {
    case 'day':
      return {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      };

    case 'month':
      return {
        year: 'numeric',
        month: 'long'
      };

    case 'year':
      return {
        year: 'numeric'
      };
  }

  return {
    year: 'numeric',
    month: 'long'
  };
} // TODO: Fix this in the API
// TODO: Remove once format is fixed in the API


const fixDateFormat = dateString => {
  return dateString.split(' ').join('T');
};
class SearchFilter extends react__WEBPACK_IMPORTED_MODULE_1__/* .Component */ .wA {
  constructor(...args) {
    super(...args);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "filtersList", /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_1__/* .createRef */ .Vf)());

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "idPrefix", lodash_uniqueId__WEBPACK_IMPORTED_MODULE_3___default()('jetpack-instant-search__search-filter-'));

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "toggleFilter", () => {
      this.props.onChange(this.getIdentifier(), (0,_lib_dom__WEBPACK_IMPORTED_MODULE_4__/* .getCheckedInputNames */ .b)(this.filtersList.current));
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "toggleStaticFilter", event => {
      this.props.onChange(this.getIdentifier(), event.target.value);
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "renderDate", ({
      key_as_string: key,
      doc_count: count
    }) => {
      const {
        locale = 'en-US'
      } = this.props;
      return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("input", {
        checked: this.isChecked(key),
        id: `${this.idPrefix}-dates-${this.getIdentifier()}-${key}`,
        name: key,
        onChange: this.toggleFilter,
        type: "checkbox",
        className: "jetpack-instant-search__search-filter-list-input"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("label", {
        htmlFor: `${this.idPrefix}-dates-${this.getIdentifier()}-${key}`,
        className: "jetpack-instant-search__search-filter-list-label"
      }, new Date(fixDateFormat(key)).toLocaleString(locale, getDateOptions(this.props.configuration.interval)), ' ', "(", count, ")"));
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "renderPostType", ({
      key,
      doc_count: count
    }) => {
      const name = key in this.props.postTypes ? this.props.postTypes[key].singular_name : key;
      return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("input", {
        checked: this.isChecked(key),
        id: `${this.idPrefix}-post-types-${key}`,
        name: key,
        onChange: this.toggleFilter,
        type: "checkbox",
        className: "jetpack-instant-search__search-filter-list-input"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("label", {
        htmlFor: `${this.idPrefix}-post-types-${key}`,
        className: "jetpack-instant-search__search-filter-list-label"
      }, strip__WEBPACK_IMPORTED_MODULE_2___default()(name), " (", count, ")"));
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "renderTaxonomy", ({
      key,
      doc_count: count
    }) => {
      // Taxonomy keys contain slug and name separated by a slash
      const [slug, name] = key && key.split(/\/(.+)/);
      return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("input", {
        checked: this.isChecked(slug),
        id: `${this.idPrefix}-taxonomies-${slug}`,
        name: slug,
        onChange: this.toggleFilter,
        type: "checkbox",
        className: "jetpack-instant-search__search-filter-list-input"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("label", {
        htmlFor: `${this.idPrefix}-taxonomies-${slug}`,
        className: "jetpack-instant-search__search-filter-list-label"
      }, strip__WEBPACK_IMPORTED_MODULE_2___default()(name), " (", count, ")"));
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "renderGroup", group => {
      return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("input", {
        checked: this.isChecked(group.value),
        id: `${this.idPrefix}-groups-${group.value}`,
        name: this.props.configuration.filter_id,
        onChange: this.toggleStaticFilter,
        value: group.value,
        type: "radio",
        className: "jetpack-instant-search__search-filter-list-input"
      }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("label", {
        htmlFor: `${this.idPrefix}-groups-${group.value}`,
        className: "jetpack-instant-search__search-filter-list-label"
      }, group.name));
    });
  }

  getIdentifier() {
    if (this.props.type === 'postType') {
      return 'post_types';
    } else if (this.props.type === 'date') {
      // (month || year)_(post_date || post_date_gmt || post_modified || post_modified_gmt )
      // Ex: month_post_date_gmt
      return `${this.props.configuration.interval}_${this.props.configuration.field}`;
    } else if (this.props.type === 'taxonomy') {
      return this.props.configuration.taxonomy;
    } else if (this.props.type === 'group') {
      return this.props.configuration.filter_id;
    }
  }

  isChecked(value) {
    // If props.value is undefined, this will return undefined.
    // Typecast so that this method always returns a boolean.
    return Boolean(this.props.value && this.props.value.includes(value));
  }

  renderDates() {
    return [...this.props.aggregation.buckets // TODO: Remove this filter; API should only be sending buckets with document counts.
    .filter(bucket => !!bucket && bucket.doc_count > 0).map(this.renderDate)] // TODO: Remove this reverse & slice when API adds filter count support
    .reverse().slice(0, this.props.configuration.count);
  }

  renderPostTypes() {
    return this.props.aggregation.buckets.map(this.renderPostType);
  }

  renderTaxonomies() {
    return this.props.aggregation.buckets.map(this.renderTaxonomy);
  }

  renderGroups() {
    return this.props.configuration.values.map(this.renderGroup);
  }

  render() {
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("h4", {
      className: "jetpack-instant-search__search-filter-sub-heading"
    }, this.props.configuration.name), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
      ref: this.filtersList
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-filter-list jetpack-instant-search__search-static-filter-list"
    }, this.props.type === 'group' && this.renderGroups()), this.props.aggregation && 'buckets' in this.props.aggregation && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-filter-list"
    }, this.props.type === 'date' && this.renderDates(), this.props.type === 'postType' && this.renderPostTypes(), this.props.type === 'taxonomy' && this.renderTaxonomies())));
  }

}

/***/ }),

/***/ 9034:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(3163);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_redux__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(2620);
/* harmony import */ var _search_filter__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(6995);
/* harmony import */ var _lib_filters__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(8033);
/* harmony import */ var _store_actions__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(7717);
/* harmony import */ var _lib_tracks__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(814);


/**
 * External dependencies
 */


const alias__ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__;
const __ = alias__;

/**
 * Internal dependencies
 */







class SearchFilters extends react__WEBPACK_IMPORTED_MODULE_1__/* .Component */ .wA {
  constructor(...args) {
    super(...args);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "onChangeFilter", (filterName, filterValue) => {
      this.props.setFilter(filterName, filterValue);
      this.props.onChange && this.props.onChange();
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "onChangeStaticFilter", (filterName, filterValue) => {
      (0,_lib_tracks__WEBPACK_IMPORTED_MODULE_6__/* .recordStaticFilterSelect */ .GE)({
        filterName,
        filterValue
      });
      this.props.setStaticFilter(filterName, filterValue);
      this.props.onChange && this.props.onChange();
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "onClearFilters", event => {
      event.preventDefault();

      if (event.type === 'click' || event.type === 'keydown' && (event.key === 'Enter' || event.key === ' ')) {
        this.props.clearFilters();
        this.props.onChange && this.props.onChange();
      }
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "renderFilterComponent", ({
      configuration,
      results
    }) => results && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(_search_filter__WEBPACK_IMPORTED_MODULE_4__/* .default */ .Z, {
      aggregation: results,
      configuration: configuration,
      locale: this.props.locale,
      onChange: this.onChangeFilter,
      postTypes: this.props.postTypes,
      type: (0,_lib_filters__WEBPACK_IMPORTED_MODULE_5__/* .mapFilterToType */ .jc)(configuration),
      value: this.props.filters[(0,_lib_filters__WEBPACK_IMPORTED_MODULE_5__/* .mapFilterToFilterKey */ .jZ)(configuration)]
    }));

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "renderStaticFilterComponent", configuration => {
      if (configuration.hasOwnProperty('visible') && !configuration.visible) {
        return null;
      }

      return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(_search_filter__WEBPACK_IMPORTED_MODULE_4__/* .default */ .Z, {
        aggregation: [],
        configuration: configuration,
        locale: this.props.locale,
        onChange: this.onChangeStaticFilter,
        postTypes: this.props.postTypes,
        type: (0,_lib_filters__WEBPACK_IMPORTED_MODULE_5__/* .mapFilterToType */ .jc)(configuration),
        value: this.props.staticFilters[(0,_lib_filters__WEBPACK_IMPORTED_MODULE_5__/* .mapFilterToFilterKey */ .jZ)(configuration)]
      });
    });
  }

  hasActiveFilters() {
    return Object.keys(this.props.filters).length > 0;
  }

  render() {
    var _this$props$results, _this$props$widget, _this$props$widget2, _this$props$widget2$f;

    if (!this.props.widget) {
      return null;
    }

    const availableStaticFilters = (0,_lib_filters__WEBPACK_IMPORTED_MODULE_5__/* .getAvailableStaticFilters */ .bA)();
    const aggregations = (_this$props$results = this.props.results) === null || _this$props$results === void 0 ? void 0 : _this$props$results.aggregations;
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-filters"
    }, this.props.showTitle && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-filters-title"
    }, __('Filter options', 'jetpack')), this.props.showClearFiltersButton && this.hasActiveFilters() && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("a", {
      class: "jetpack-instant-search__clear-filters-link",
      href: "#",
      onClick: this.onClearFilters,
      onKeyDown: this.onClearFilters,
      role: "button",
      tabIndex: "0"
    }, __('Clear filters', 'jetpack')), ((_this$props$widget = this.props.widget) === null || _this$props$widget === void 0 ? void 0 : _this$props$widget.filters) && this.props.widget.filters.length > 0 && availableStaticFilters.map(this.renderStaticFilterComponent), (_this$props$widget2 = this.props.widget) === null || _this$props$widget2 === void 0 ? void 0 : (_this$props$widget2$f = _this$props$widget2.filters) === null || _this$props$widget2$f === void 0 ? void 0 : _this$props$widget2$f.map(configuration => aggregations ? {
      configuration,
      results: aggregations[configuration.filter_id]
    } : null).filter(data => !!data).filter(({
      results
    }) => !!results && Array.isArray(results.buckets) && results.buckets.length > 0).map(this.renderFilterComponent));
  }

}

_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(SearchFilters, "defaultProps", {
  showClearFiltersButton: true,
  showTitle: true
});

/* harmony default export */ __webpack_exports__["Z"] = ((0,react_redux__WEBPACK_IMPORTED_MODULE_3__/* .connect */ .$j)(null, {
  clearFilters: _store_actions__WEBPACK_IMPORTED_MODULE_7__/* .clearFilters */ .K5,
  setFilter: _store_actions__WEBPACK_IMPORTED_MODULE_7__/* .setFilter */ .Tv,
  setStaticFilter: _store_actions__WEBPACK_IMPORTED_MODULE_7__/* .setStaticFilter */ .O1
})(SearchFilters));

/***/ }),

/***/ 4715:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var _search_box__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(5772);


/**
 * External dependencies
 */

/**
 * Internal dependencies
 */



const noop = event => event.preventDefault();

class SearchForm extends react__WEBPACK_IMPORTED_MODULE_1__/* .Component */ .wA {
  constructor(...args) {
    super(...args);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "onClear", () => this.props.onChangeSearch(''));

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "onChangeSearch", event => this.props.onChangeSearch(event.currentTarget.value));
  }

  render() {
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("form", {
      autocomplete: "off",
      onSubmit: noop,
      role: "search",
      className: this.props.className
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-form"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(_search_box__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z, {
      isVisible: this.props.isVisible,
      onChange: this.onChangeSearch,
      onClear: this.onClear,
      shouldRestoreFocus: true,
      searchQuery: this.props.searchQuery
    })));
  }

}

/* harmony default export */ __webpack_exports__["Z"] = (SearchForm);

/***/ }),

/***/ 6173:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/* harmony import */ var _gridicon__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(3687);
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */




const SearchResultComments = ({
  comments,
  iconSize = 18
}) => {
  if (!comments) {
    return null;
  }

  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__search-result-comments"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
    icon: "comment",
    size: iconSize
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
    className: "jetpack-instant-search__search-result-comments-text" //eslint-disable-next-line react/no-danger
    ,
    dangerouslySetInnerHTML: {
      __html: comments.join(' ... ')
    }
  }));
};

/* harmony default export */ __webpack_exports__["Z"] = (SearchResultComments);

/***/ }),

/***/ 8848:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "Z": function() { return /* binding */ SearchResultExpanded; }
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/* harmony import */ var _path_breadcrumbs__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(4468);
/* harmony import */ var _photon_image__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(9767);
/* harmony import */ var _search_result_comments__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(6173);
/* harmony import */ var _search_filter__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(6995);
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */






function SearchResultExpanded(props) {
  const {
    isMultiSite,
    locale = 'en-US'
  } = props;
  const {
    result_type,
    fields,
    highlight
  } = props.result;

  if (result_type !== 'post') {
    return null;
  }

  const firstImage = Array.isArray(fields['image.url.raw']) ? fields['image.url.raw'][0] : fields['image.url.raw'];
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("li", {
    className: ['jetpack-instant-search__search-result', 'jetpack-instant-search__search-result-expanded', `jetpack-instant-search__search-result-expanded--${fields.post_type}`, !firstImage ? 'jetpack-instant-search__search-result-expanded--no-image' : '', isMultiSite ? 'is-multisite' : ''].join(' ')
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__search-result-expanded__content-container"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__search-result-expanded__copy-container"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("h3", {
    className: "jetpack-instant-search__search-result-title jetpack-instant-search__search-result-expanded__title"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("a", {
    className: "jetpack-instant-search__search-result-title-link jetpack-instant-search__search-result-expanded__title-link",
    href: `//${fields['permalink.url.raw']}`,
    onClick: props.onClick //eslint-disable-next-line react/no-danger
    ,
    dangerouslySetInnerHTML: {
      __html: highlight.title
    }
  })), !isMultiSite && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_path_breadcrumbs__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
    className: "jetpack-instant-search__search-result-expanded__path",
    onClick: props.onClick,
    url: `//${fields['permalink.url.raw']}`
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__search-result-expanded__content" //eslint-disable-next-line react/no-danger
    ,
    dangerouslySetInnerHTML: {
      __html: highlight.content.join(' ... ')
    }
  }), highlight.comments && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_search_result_comments__WEBPACK_IMPORTED_MODULE_3__/* .default */ .Z, {
    comments: highlight.comments
  })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("a", {
    className: "jetpack-instant-search__search-result-expanded__image-link",
    href: `//${fields['permalink.url.raw']}`,
    onClick: props.onClick
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__search-result-expanded__image-container"
  }, firstImage ? /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_photon_image__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z, {
    alt: fields['title.default'],
    className: "jetpack-instant-search__search-result-expanded__image",
    isPhotonEnabled: props.isPhotonEnabled,
    src: `//${firstImage}`
  }) : null))), isMultiSite && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("ul", {
    className: "jetpack-instant-search__search-result-expanded__footer"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("li", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_photon_image__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z, {
    alt: fields.blog_name,
    className: "jetpack-instant-search__search-result-expanded__footer-blog-image",
    isPhotonEnabled: false,
    height: 24,
    width: 24,
    src: fields.blog_icon_url,
    lazyLoad: false
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
    className: "jetpack-instant-search__search-result-expanded__footer-blog"
  }, fields.blog_name)), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("li", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
    className: "jetpack-instant-search__search-result-expanded__footer-author"
  }, fields.author)), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("li", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
    className: "jetpack-instant-search__search-result-expanded__footer-date"
  }, new Date((0,_search_filter__WEBPACK_IMPORTED_MODULE_4__/* .fixDateFormat */ .a)(fields.date)).toLocaleDateString(locale, {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })))));
}

/***/ }),

/***/ 104:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/* harmony import */ var _gridicon__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(3687);
/* harmony import */ var _path_breadcrumbs__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(4468);
/* harmony import */ var _post_type_icon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(1679);
/* harmony import */ var _search_result_comments__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(6173);
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */






const MAX_TAGS_OR_CATEGORIES = 5;

class SearchResultMinimal extends react__WEBPACK_IMPORTED_MODULE_0__/* .Component */ .wA {
  getIconSize() {
    return 18;
  }

  getTags() {
    let tags = this.props.result.fields['tag.name.default'];

    if (!tags) {
      return [];
    }

    if (!Array.isArray(tags)) {
      tags = [tags];
    }

    return tags.slice(0, MAX_TAGS_OR_CATEGORIES);
  }

  getCategories() {
    let cats = this.props.result.fields['category.name.default'];

    if (!cats) {
      return [];
    }

    if (!Array.isArray(cats)) {
      cats = [cats];
    }

    return cats.slice(0, MAX_TAGS_OR_CATEGORIES);
  }

  renderNoMatchingContent() {
    const tags = this.getTags();
    const cats = this.getCategories();
    const noTags = tags.length === 0 && cats.length === 0;
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-result-minimal-content"
    }, noTags && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_path_breadcrumbs__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z, {
      url: this.props.result.fields['permalink.url.raw']
    }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-result-minimal-cats-and-tags"
    }, tags.length !== 0 && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("ul", {
      className: "jetpack-instant-search__search-result-minimal-tags"
    }, tags.map(tag => /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("li", {
      className: "jetpack-instant-search__search-result-minimal-tag"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
      icon: "tag",
      size: this.getIconSize()
    }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
      className: "jetpack-instant-search__search-result-minimal-tag-text"
    }, tag)))), cats.length !== 0 && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("ul", {
      className: "jetpack-instant-search__search-result-minimal-cats"
    }, cats.map(cat => /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("li", {
      className: "jetpack-instant-search__search-result-minimal-cat"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
      icon: "folder",
      size: this.getIconSize()
    }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", {
      className: "jetpack-instant-search__search-result-minimal-cat-text"
    }, cat))))));
  }

  renderMatchingContent() {
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-result-minimal-content" //eslint-disable-next-line react/no-danger
      ,
      dangerouslySetInnerHTML: {
        __html: this.props.result.highlight.content.join(' ... ')
      }
    });
  }

  render() {
    const {
      result_type,
      fields,
      highlight
    } = this.props.result;

    if (result_type !== 'post') {
      return null;
    }

    const noMatchingContent = !highlight.content || highlight.content[0] === '';
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("li", {
      className: "jetpack-instant-search__search-result jetpack-instant-search__search-result-minimal"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("h3", {
      className: "jetpack-instant-search__search-result-title jetpack-instant-search__search-result-minimal-title"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_post_type_icon__WEBPACK_IMPORTED_MODULE_3__/* .default */ .Z, {
      postType: fields.post_type,
      shortcodeTypes: fields.shortcode_types
    }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("a", {
      className: "jetpack-instant-search__search-result-title-link jetpack-instant-search__search-result-minimal-title-link",
      href: `//${fields['permalink.url.raw']}`,
      onClick: this.props.onClick //eslint-disable-next-line react/no-danger
      ,
      dangerouslySetInnerHTML: {
        __html: highlight.title
      }
    })), noMatchingContent ? this.renderNoMatchingContent() : this.renderMatchingContent(), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_search_result_comments__WEBPACK_IMPORTED_MODULE_4__/* .default */ .Z, {
      comments: highlight && highlight.comments
    }));
  }

}

/* harmony default export */ __webpack_exports__["Z"] = (SearchResultMinimal);

/***/ }),

/***/ 97:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(3163);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _gridicon__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(3687);
/* harmony import */ var _photon_image__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(9767);
/* harmony import */ var _product_ratings__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(1162);
/* harmony import */ var _product_price__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(7473);
/**
 * External dependencies
 */


/**
 * Internal dependencies
 */

const alias__ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__;
const __ = alias__;




/**
 * Style dependencies
 */



class SearchResultProduct extends react__WEBPACK_IMPORTED_MODULE_0__/* .Component */ .wA {
  render() {
    var _highlight$content$;

    const {
      result_type,
      fields,
      highlight
    } = this.props.result;

    if (result_type !== 'post') {
      return null;
    }

    const firstImage = Array.isArray(fields['image.url.raw']) ? fields['image.url.raw'][0] : fields['image.url.raw'];
    const title = Array.isArray(highlight.title) && highlight.title[0].length > 0 ? highlight.title[0] : __('No title', 'jetpack'); // TODO: Remove this check once checking result.highlight is more reliable.

    const hasQuery = typeof this.props.searchQuery === 'string' && this.props.searchQuery.trim() !== '';
    const titleHasMark = title.includes('<mark>');
    const showMatchHint = hasQuery && !titleHasMark && Array.isArray(highlight.content) && ((_highlight$content$ = highlight.content[0]) === null || _highlight$content$ === void 0 ? void 0 : _highlight$content$.length) > 0;
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("li", {
      className: "jetpack-instant-search__search-result jetpack-instant-search__search-result-product"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("a", {
      className: "jetpack-instant-search__search-result-product-img-link",
      href: `//${fields['permalink.url.raw']}`,
      onClick: this.props.onClick
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
      className: `jetpack-instant-search__search-result-product-img-container ${firstImage ? '' : 'jetpack-instant-search__search-result-product-img-container--placeholder'}`
    }, firstImage ? /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_photon_image__WEBPACK_IMPORTED_MODULE_3__/* .default */ .Z, {
      alt: fields['title.default'],
      className: "jetpack-instant-search__search-result-product-img",
      isPhotonEnabled: this.props.isPhotonEnabled,
      src: `//${firstImage}`
    }) : /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-result-product-img"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z, {
      icon: "block",
      style: {} // Mandatory. Overrides manual setting of height/width in Gridicon.

    }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z, {
      icon: "image",
      style: {} // Mandatory. Overrides manual setting of height/width in Gridicon.
      ,
      title: __('Does not have an image', 'jetpack')
    })))), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("h3", {
      className: "jetpack-instant-search__search-result-title jetpack-instant-search__search-result-product-title"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("a", {
      className: "jetpack-instant-search__search-result-title-link",
      href: `//${fields['permalink.url.raw']}`,
      onClick: this.props.onClick //eslint-disable-next-line react/no-danger
      ,
      dangerouslySetInnerHTML: {
        __html: title
      }
    })), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_product_price__WEBPACK_IMPORTED_MODULE_5__/* .default */ .Z, {
      price: fields['wc.price'],
      salePrice: fields['wc.sale_price'],
      formattedPrice: fields['wc.formatted_price'],
      formattedRegularPrice: fields['wc.formatted_regular_price'],
      formattedSalePrice: fields['wc.formatted_sale_price']
    }), !!fields['meta._wc_average_rating.double'] && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_product_ratings__WEBPACK_IMPORTED_MODULE_4__/* .default */ .Z, {
      count: fields['meta._wc_review_count.long'],
      rating: fields['meta._wc_average_rating.double'],
      permalink: `//${fields['permalink.url.raw']}`
    }), showMatchHint && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-result-product-match"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("mark", null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z, {
      icon: "search",
      style: {},
      title: false
    }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("span", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)(
    /* Translators: the placeholder can be "content" or "comments". */
    __('Matches %s', 'jetpack'), 'comment' in highlight ? __('comments', 'jetpack') : __('content', 'jetpack'))))));
  }

}

/* harmony default export */ __webpack_exports__["Z"] = (SearchResultProduct);

/***/ }),

/***/ 3091:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(8900);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(1634);
/* harmony import */ var _search_result_minimal__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(104);
/* harmony import */ var _search_result_expanded__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(8848);
/* harmony import */ var _search_result_product__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(97);
/* harmony import */ var _lib_tracks__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(814);
/* harmony import */ var _lib_constants__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(9379);



/**
 * External dependencies
 */

/**
 * Internal dependencies
 */








class SearchResult extends react__WEBPACK_IMPORTED_MODULE_2__/* .Component */ .wA {
  constructor(...args) {
    super(...args);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_1___default()(this, "onClick", () => {
      // Send out analytics call
      !!this.props.railcar && (0,_lib_tracks__WEBPACK_IMPORTED_MODULE_6__/* .recordTrainTracksInteract */ .Lo)({ ...this.getCommonTrainTracksProps(),
        action: 'click'
      });
    });
  }

  componentDidMount() {
    !!this.props.railcar && (0,_lib_tracks__WEBPACK_IMPORTED_MODULE_6__/* .recordTrainTracksRender */ .Sn)(this.getCommonTrainTracksProps());
  }

  componentDidUpdate(prevProps) {
    if (this.props.railcar !== prevProps.railcar) {
      !!this.props.railcar && (0,_lib_tracks__WEBPACK_IMPORTED_MODULE_6__/* .recordTrainTracksRender */ .Sn)(this.getCommonTrainTracksProps());
    }
  }

  getCommonTrainTracksProps() {
    return {
      fetch_algo: this.props.railcar.fetch_algo,
      fetch_position: this.props.railcar.fetch_position,
      fetch_query: this.props.railcar.fetch_query,
      railcar: this.props.railcar.railcar,
      rec_blog_id: this.props.railcar.rec_blog_id,
      rec_post_id: this.props.railcar.rec_post_id,
      session_id: this.props.railcar.session_id,
      // TODO: Add a way to differentiate between different result formats
      ui_algo: 'jetpack-instant-search-ui/v1',
      ui_position: this.props.index
    };
  }

  render() {
    if (this.props.resultFormat === _lib_constants__WEBPACK_IMPORTED_MODULE_7__/* .RESULT_FORMAT_PRODUCT */ .LI) {
      return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_search_result_product__WEBPACK_IMPORTED_MODULE_5__/* .default */ .Z, _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({
        onClick: this.onClick
      }, this.props));
    } else if (this.props.resultFormat === _lib_constants__WEBPACK_IMPORTED_MODULE_7__/* .RESULT_FORMAT_EXPANDED */ .Pz) {
      const isMultiSite = this.props.staticFilters && this.props.staticFilters.group_id && this.props.staticFilters.group_id !== _lib_constants__WEBPACK_IMPORTED_MODULE_7__/* .MULTISITE_NO_GROUP_VALUE */ .Bk;
      return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_search_result_expanded__WEBPACK_IMPORTED_MODULE_4__/* .default */ .Z, _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({
        onClick: this.onClick
      }, this.props, {
        isMultiSite: isMultiSite
      }));
    }

    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_search_result_minimal__WEBPACK_IMPORTED_MODULE_3__/* .default */ .Z, _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({
      onClick: this.onClick
    }, this.props));
  }

}

/* harmony default export */ __webpack_exports__["Z"] = (SearchResult);

/***/ }),

/***/ 8898:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(3163);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(1634);
/* harmony import */ var _gridicon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(3687);
/* harmony import */ var _notice__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(4367);
/* harmony import */ var _scroll_button__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(2456);
/* harmony import */ var _search_controls__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(1712);
/* harmony import */ var _search_form__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(4715);
/* harmony import */ var _search_result__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(3091);
/* harmony import */ var _sidebar__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(6308);
/* harmony import */ var _lib_colors__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(2526);
/* harmony import */ var _lib_filters__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(8033);
/* harmony import */ var _lib_constants__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(9379);


/**
 * External dependencies
 */

const alias__ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__,
      alias_n = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__._n;
const __ = alias__,
      _n = alias_n;

/**
 * Internal dependencies
 */











/**
 * Style dependencies
 */



class SearchResults extends react__WEBPACK_IMPORTED_MODULE_2__/* .Component */ .wA {
  constructor(...args) {
    super(...args);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "state", {
      shouldShowMobileSecondary: false
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "toggleMobileSecondary", event => {
      if (event.type === 'click' || event.type === 'keydown' && (event.key === 'Enter' || event.key === ' ')) {
        // Prevent page scroll from pressing spacebar
        if (event.key === ' ') {
          event.preventDefault();
        }

        this.setState(state => ({
          shouldShowMobileSecondary: !state.shouldShowMobileSecondary
        }));
      }
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "closeOverlay", event => {
      event.preventDefault();
      this.props.closeOverlay();
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "onKeyPressHandler", event => {
      if (event.key === 'Enter') {
        event.preventDefault();
        this.props.closeOverlay();
      }
    });
  }

  hasFilterOptions() {
    var _this$props$widgetOut, _this$props$widgetOut2;

    let widgets = [...this.props.widgets];

    if (((_this$props$widgetOut = this.props.widgetOutsideOverlay) === null || _this$props$widgetOut === void 0 ? void 0 : (_this$props$widgetOut2 = _this$props$widgetOut.filters) === null || _this$props$widgetOut2 === void 0 ? void 0 : _this$props$widgetOut2.length) > 0) {
      widgets = [this.props.widgetOutsideOverlay, ...widgets];
    }

    return widgets.length > 0;
  }

  getSearchTitle() {
    const {
      total = 0,
      corrected_query = false
    } = this.props.response;
    const hasQuery = this.props.searchQuery !== '';
    const hasCorrectedQuery = corrected_query !== false;
    const num = new Intl.NumberFormat().format(total);
    const isMultiSite = this.props.staticFilters && this.props.staticFilters.group_id && this.props.staticFilters.group_id !== _lib_constants__WEBPACK_IMPORTED_MODULE_11__/* .MULTISITE_NO_GROUP_VALUE */ .Bk;

    if (this.props.isLoading) {
      if (!hasQuery) {
        return __('Loading popular results…', 'jetpack');
      }

      return __('Searching…', 'jetpack');
    }

    if (total === 0 || this.props.hasError) {
      return __('No results found', 'jetpack');
    }

    if (hasQuery && hasCorrectedQuery) {
      return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)(_n('Found %s result for "%s"', 'Found %s results for "%s"', total, 'jetpack'), num, corrected_query);
    } else if (isMultiSite) {
      var _allP2$;

      const group = (0,_lib_filters__WEBPACK_IMPORTED_MODULE_10__/* .getAvailableStaticFilters */ .bA)().filter(item => item.filter_id === 'group_id');
      const allP2 = group.length === 1 && group[0].values ? group[0].values.filter(item => item.value !== _lib_constants__WEBPACK_IMPORTED_MODULE_11__/* .MULTISITE_NO_GROUP_VALUE */ .Bk) : {};
      const p2Name = (_allP2$ = allP2[0]) !== null && _allP2$ !== void 0 && _allP2$.name ? allP2[0].name : __('All P2', 'jetpack');
      return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)(
      /* translators: %1$s: number of results. - %2$s: site name. */
      _n('Found %1$s result in %2$s', 'Found %1$s results in %2$s', total, 'jetpack'), num, p2Name);
    } else if (hasQuery) {
      return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)(_n('Found %s result', 'Found %s results', total, 'jetpack'), num, this.props.searchQuery);
    }

    return __('Showing popular results', 'jetpack');
  }

  renderPrimarySection() {
    const {
      highlightColor,
      searchQuery
    } = this.props;
    const {
      results = [],
      total = 0,
      corrected_query = false
    } = this.props.response;
    const textColor = (0,_lib_colors__WEBPACK_IMPORTED_MODULE_12__/* .getConstrastingColor */ .B)(highlightColor);
    const hasCorrectedQuery = corrected_query !== false;
    const hasResults = total > 0;
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(react__WEBPACK_IMPORTED_MODULE_2__/* .Fragment */ .HY, null, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("style", {
      // eslint-disable-next-line react/no-danger
      dangerouslySetInnerHTML: {
        __html: `
							.jetpack-instant-search *::selection,
							.jetpack-instant-search .jetpack-instant-search__search-results .jetpack-instant-search__search-results-primary .jetpack-instant-search__search-result mark {
								color: ${textColor};
								background-color: ${highlightColor};
							}
						`
      }
    }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-results-title"
    }, this.getSearchTitle()), hasResults && hasCorrectedQuery && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("p", {
      className: "jetpack-instant-search__search-results-unused-query"
    }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)(__('No results for "%s"', 'jetpack'), searchQuery)), this.props.hasError && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_notice__WEBPACK_IMPORTED_MODULE_4__/* .default */ .Z, {
      type: "warning"
    }, __("It looks like you're offline. Please reconnect for results.", 'jetpack')), hasResults && !this.props.hasError && this.props.response._isOffline && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_notice__WEBPACK_IMPORTED_MODULE_4__/* .default */ .Z, {
      type: "warning"
    }, __("It looks like you're offline. Please reconnect to load the latest results.", 'jetpack')), hasResults && !this.props.hasError && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("ol", {
      className: `jetpack-instant-search__search-results-list is-format-${this.props.resultFormat}`
    }, results.map((result, index) => /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_search_result__WEBPACK_IMPORTED_MODULE_8__/* .default */ .Z, {
      index: index,
      staticFilters: this.props.staticFilters,
      isPhotonEnabled: this.props.isPhotonEnabled,
      locale: this.props.locale,
      railcar: this.props.isVisible ? result.railcar : null,
      result: result,
      resultFormat: this.props.resultFormat,
      searchQuery: this.props.searchQuery
    }))), hasResults && this.props.hasNextPage && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-results-pagination"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_scroll_button__WEBPACK_IMPORTED_MODULE_5__/* .default */ .Z, {
      enableLoadOnScroll: this.props.enableLoadOnScroll,
      isLoading: this.props.isLoading,
      onLoadNextPage: this.props.onLoadNextPage
    })));
  }

  renderSecondarySection() {
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_sidebar__WEBPACK_IMPORTED_MODULE_9__/* .default */ .Z, {
      filters: this.props.filters,
      staticFilters: this.props.staticFilters,
      isLoading: this.props.isLoading,
      locale: this.props.locale,
      postTypes: this.props.postTypes,
      response: this.props.response,
      showPoweredBy: this.props.showPoweredBy,
      widgets: this.props.widgets,
      widgetOutsideOverlay: this.props.widgetOutsideOverlay
    });
  }

  render() {
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("div", {
      "aria-hidden": this.props.isLoading === true,
      "aria-live": "polite",
      className: "jetpack-instant-search__search-results"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-results-controls"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_search_form__WEBPACK_IMPORTED_MODULE_7__/* .default */ .Z, {
      className: "jetpack-instant-search__search-results-search-form",
      isVisible: this.props.isVisible,
      onChangeSearch: this.props.onChangeSearch,
      searchQuery: this.props.searchQuery
    }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("button", {
      className: "jetpack-instant-search__overlay-close",
      onClick: this.closeOverlay,
      onKeyPress: this.onKeyPressHandler,
      tabIndex: "0",
      "aria-label": __('Close search results', 'jetpack')
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_3__/* .default */ .Z, {
      icon: "cross",
      size: "24",
      "aria-hidden": "true",
      focusable: "false"
    }))), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_search_controls__WEBPACK_IMPORTED_MODULE_6__/* .default */ .Z, {
      enableSort: this.props.enableSort,
      onChangeSort: this.props.onChangeSort,
      resultFormat: this.props.resultFormat,
      sort: this.props.sort
    }, (this.hasFilterOptions() || this.props.hasNonSearchWidgets) && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("div", {
      role: "button",
      onClick: this.toggleMobileSecondary,
      onKeyDown: this.toggleMobileSecondary,
      tabIndex: "0",
      className: "jetpack-instant-search__search-results-filter-button"
    }, __('Filters', 'jetpack'), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement(_gridicon__WEBPACK_IMPORTED_MODULE_3__/* .default */ .Z, {
      icon: "chevron-down",
      size: 16,
      alt: __('Show search filters', 'jetpack'),
      "aria-hidden": "true"
    }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("span", {
      className: "screen-reader-text assistive-text"
    }, this.state.shouldShowMobileSecondary ? __('Hide filters', 'jetpack') : __('Show filters', 'jetpack')))), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-results-content"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-results-primary"
    }, this.renderPrimarySection()), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_2__/* .default.createElement */ .ZP.createElement("div", {
      className: ['jetpack-instant-search__search-results-secondary', `${this.state.shouldShowMobileSecondary ? 'jetpack-instant-search__search-results-secondary--show-as-modal' : ''} `].join(' ')
    }, this.renderSecondarySection())));
  }

}

/* harmony default export */ __webpack_exports__["Z"] = (SearchResults);

/***/ }),

/***/ 466:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "Z": function() { return /* binding */ SearchSort; }
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(3163);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _lib_sort__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(7297);


/**
 * External dependencies
 */


/**
 * Internal dependencies
 */

const alias__ = _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__;
const __ = alias__;


class SearchSort extends react__WEBPACK_IMPORTED_MODULE_1__/* .Component */ .wA {
  constructor(...args) {
    super(...args);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleKeyPress", event => {
      if (this.props.value !== event.currentTarget.value && event.key === 'Enter') {
        event.preventDefault();
        this.props.onChange(event.currentTarget.dataset.value);
      }
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleClick", event => {
      if (this.props.value !== event.currentTarget.value) {
        event.preventDefault();
        this.props.onChange(event.currentTarget.dataset.value);
      }
    });

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "handleSelectChange", event => {
      if (this.props.value !== event.currentTarget.value) {
        event.preventDefault();
        this.props.onChange(event.currentTarget.value);
      }
    });
  }

  render() {
    const sortOptions = (0,_lib_sort__WEBPACK_IMPORTED_MODULE_3__/* .getSortOptions */ .n)(this.props.resultFormat); // If there are more than 3 sort options, use a select

    if (sortOptions.size > 3) {
      return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
        className: "jetpack-instant-search__search-sort jetpack-instant-search__search-sort-with-select"
      }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("label", {
        htmlFor: "jetpack-instant-search__search-sort-select"
      }, __('Sort:', 'jetpack')), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("select", {
        id: "jetpack-instant-search__search-sort-select",
        onBlur: this.handleSelectChange,
        onChange: this.handleSelectChange
      }, [...sortOptions.entries()].map(([sortKey, label]) => /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("option", {
        value: sortKey,
        key: sortKey,
        selected: this.props.value === sortKey ? 'selected' : ''
      }, label))));
    }

    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__search-sort jetpack-instant-search__search-sort-with-links"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
      className: "screen-reader-text"
    }, __('Sort by: ', 'jetpack')), [...sortOptions.entries()].map(([sortKey, label]) => /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("a", {
      className: `jetpack-instant-search__search-sort-option ${this.props.value === sortKey ? 'is-selected' : ''}`,
      "data-value": sortKey,
      key: sortKey,
      onClick: this.handleClick,
      onKeyPress: this.handleKeyPress,
      role: "button",
      tabIndex: 0
    }, label)));
  }

}

/***/ }),

/***/ 6308:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/* harmony import */ var _search_filters__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(9034);
/* harmony import */ var _widget_area_container__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(7784);
/* harmony import */ var _jetpack_colophon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(5504);
/**
 * External dependencies
 */




/**
 * Internal dependencies
 */




const Sidebar = props => {
  return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
    className: "jetpack-instant-search__sidebar"
  }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_search_filters__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
    filters: props.filters,
    staticFilters: props.staticFilters,
    loading: props.isLoading,
    locale: props.locale,
    postTypes: props.postTypes,
    results: props.response,
    showClearFiltersButton: true,
    widget: props.widgetOutsideOverlay
  }), /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_widget_area_container__WEBPACK_IMPORTED_MODULE_2__/* .default */ .Z, null), props.widgets.map(widget => {
    // Creates portals to elements moved into the WidgetAreaContainer.
    return /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_0__/* .createPortal */ .jz)( /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement("div", {
      id: `${widget.widget_id}-portaled-wrapper`,
      className: "jetpack-instant-search__portaled-wrapper"
    }, /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_search_filters__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z, {
      filters: props.filters,
      staticFilters: props.staticFilters,
      loading: props.isLoading,
      locale: props.locale,
      postTypes: props.postTypes,
      results: props.response,
      showClearFiltersButton: false,
      showTitle: false,
      widget: widget
    })), document.getElementById(`${widget.widget_id}-wrapper`));
  }), props.showPoweredBy && /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_0__/* .default.createElement */ .ZP.createElement(_jetpack_colophon__WEBPACK_IMPORTED_MODULE_3__/* .default */ .Z, {
    locale: props.locale
  }));
};

/* harmony default export */ __webpack_exports__["Z"] = (Sidebar);

/***/ }),

/***/ 7784:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "Z": function() { return /* binding */ WidgetAreaContainer; }
/* harmony export */ });
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(3197);
/* harmony import */ var _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);


/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

 // NOTE:
//
// We use Preact.Component instead of a Hooks based component because
// we need to set shouldComponentUpdate to always return false.
//

class WidgetAreaContainer extends react__WEBPACK_IMPORTED_MODULE_1__/* .Component */ .wA {
  constructor(...args) {
    super(...args);

    _home_runner_work_jetpack_jetpack_node_modules_pnpm_babel_runtime_7_15_3_node_modules_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_0___default()(this, "container", /*#__PURE__*/(0,react__WEBPACK_IMPORTED_MODULE_1__/* .createRef */ .Vf)());
  }

  componentDidMount() {
    const widgetArea = document.getElementsByClassName('jetpack-instant-search__widget-area')[0];

    if (widgetArea) {
      widgetArea.style.removeProperty('display');
      this.container.current.appendChild(widgetArea);
    }
  }

  shouldComponentUpdate() {
    return false;
  }

  render() {
    return /*#__PURE__*/react__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement("div", {
      className: "jetpack-instant-search__widget-area-container",
      ref: this.container
    });
  }

}

/***/ }),

/***/ 6241:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "J": function() { return /* binding */ decode; }
/* harmony export */ });
// These two functions are a temporary addition while we wait for @jsnmoon's PR
// to be merged into the qss package: https://github.com/lukeed/qss/pull/8
function toValue(mix, tcBools, tcNumbers) {
  if (!mix) {
    return '';
  }

  const str = decodeURIComponent(mix);

  if (tcBools && str === 'false') {
    return false;
  }

  if (tcBools && str === 'true') {
    return true;
  }

  return tcNumbers && +str * 0 === 0 ? +str : str;
}

function decode(str, tcBools, tcNumbers) {
  let tmp, k;
  const out = {},
        arr = str.split('&');
  tcBools = typeof tcBools !== 'undefined' ? tcBools : true;
  tcNumbers = typeof tcNumbers !== 'undefined' ? tcNumbers : true;

  while (tmp = arr.shift()) {
    tmp = tmp.split('=');
    k = tmp.shift();

    if (out[k] !== void 0) {
      out[k] = [].concat(out[k], toValue(tmp.shift(), tcBools, tcNumbers));
    } else {
      out[k] = toValue(tmp.shift(), tcBools, tcNumbers);
    }
  }

  return out;
}

/***/ }),

/***/ 5298:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "initialize": function() { return /* binding */ initialize; }
/* harmony export */ });
/* harmony import */ var _set_webpack_public_path__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(8959);
/* harmony import */ var _set_webpack_public_path__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_set_webpack_public_path__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var preact_compat__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1634);
/* harmony import */ var preact__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(7465);
/* harmony import */ var react_redux__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(2620);
/* harmony import */ var _components_search_app__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(7758);
/* harmony import */ var _lib_dom__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(7266);
/* harmony import */ var _lib_constants__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(9379);
/* harmony import */ var _lib_api__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(7722);
/* harmony import */ var _lib_customize__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(4880);
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(5820);
// NOTE: This must be imported first before any other imports.
// See: https://github.com/webpack/webpack/issues/2776#issuecomment-233208623

/**
 * External dependencies
 * NOTE: We directly import preact here since we don't expect this file to be used in a React context.
 */




/**
 * Internal dependencies
 */








const injectSearchApp = () => {
  (0,preact__WEBPACK_IMPORTED_MODULE_2__/* .render */ .sY)( /*#__PURE__*/preact_compat__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(react_redux__WEBPACK_IMPORTED_MODULE_3__/* .Provider */ .zt, {
    store: _store__WEBPACK_IMPORTED_MODULE_8__/* .default */ .Z
  }, /*#__PURE__*/preact_compat__WEBPACK_IMPORTED_MODULE_1__/* .default.createElement */ .ZP.createElement(_components_search_app__WEBPACK_IMPORTED_MODULE_4__/* .default */ .Z, {
    aggregations: (0,_lib_api__WEBPACK_IMPORTED_MODULE_6__/* .buildFilterAggregations */ .a5)([...window[_lib_constants__WEBPACK_IMPORTED_MODULE_5__/* .SERVER_OBJECT_NAME */ .W1].widgets, ...window[_lib_constants__WEBPACK_IMPORTED_MODULE_5__/* .SERVER_OBJECT_NAME */ .W1].widgetsOutsideOverlay]),
    enableAnalytics: true,
    hasOverlayWidgets: !!window[_lib_constants__WEBPACK_IMPORTED_MODULE_5__/* .SERVER_OBJECT_NAME */ .W1].hasOverlayWidgets,
    initialHref: window.location.href // NOTE: initialIsVisible is only used in the customizer. See lib/customize.js.
    ,
    initialIsVisible: window[_lib_constants__WEBPACK_IMPORTED_MODULE_5__/* .SERVER_OBJECT_NAME */ .W1].showResults,
    isInCustomizer: (0,_lib_customize__WEBPACK_IMPORTED_MODULE_7__/* .isInCustomizer */ .bS)(),
    overlayOptions: window[_lib_constants__WEBPACK_IMPORTED_MODULE_5__/* .SERVER_OBJECT_NAME */ .W1].overlayOptions,
    options: window[_lib_constants__WEBPACK_IMPORTED_MODULE_5__/* .SERVER_OBJECT_NAME */ .W1],
    shouldCreatePortal: true,
    shouldIntegrateWithDom: true,
    themeOptions: (0,_lib_dom__WEBPACK_IMPORTED_MODULE_9__/* .getThemeOptions */ .C)(window[_lib_constants__WEBPACK_IMPORTED_MODULE_5__/* .SERVER_OBJECT_NAME */ .W1])
  })), document.body);
};
/**
 * Main function.
 */


function initialize() {
  if (window[_lib_constants__WEBPACK_IMPORTED_MODULE_5__/* .SERVER_OBJECT_NAME */ .W1] && 'siteId' in window[_lib_constants__WEBPACK_IMPORTED_MODULE_5__/* .SERVER_OBJECT_NAME */ .W1]) {
    injectSearchApp();
  }
}

/***/ }),

/***/ 7722:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "a5": function() { return /* binding */ buildFilterAggregations; },
/* harmony export */   "yC": function() { return /* binding */ search; }
/* harmony export */ });
/* unused harmony export generateDateRangeFilter */
/* harmony import */ var qss__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(4090);
/* harmony import */ var q_flat__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(8118);
/* harmony import */ var fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(8027);
/* harmony import */ var fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var tiny_lru_lib_tiny_lru_esm__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(5857);
/* harmony import */ var _filters__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(8033);
/* harmony import */ var _constants__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(9379);
/**
 * External dependencies
 */




/**
 * Internal dependencies
 */



let abortController;

const isLengthyArray = array => Array.isArray(array) && array.length > 0; // Cache contents evicted after fixed time-to-live


const cache = (0,tiny_lru_lib_tiny_lru_esm__WEBPACK_IMPORTED_MODULE_4__/* .default */ .Z)(30, 5 * _constants__WEBPACK_IMPORTED_MODULE_3__/* .MINUTE_IN_MILLISECONDS */ .AG);
const backupCache = (0,tiny_lru_lib_tiny_lru_esm__WEBPACK_IMPORTED_MODULE_4__/* .default */ .Z)(30, 30 * _constants__WEBPACK_IMPORTED_MODULE_3__/* .MINUTE_IN_MILLISECONDS */ .AG); // Set up initial abort controller

resetAbortController();
/**
 * Builds ElasticSerach aggregations for filters defined by search widgets.
 *
 * @param {object[]} widgets - an array of widget configuration objects
 * @returns {object} filter aggregations
 */

function buildFilterAggregations(widgets = []) {
  const aggregation = {};
  widgets.forEach(({
    filters: widgetFilters
  }) => widgetFilters.forEach(filter => {
    aggregation[filter.filter_id] = generateAggregation(filter);
  }));
  return aggregation;
}
/**
 * Builds ElasticSearch aggregations for a given filter.
 *
 * @param {object[]} filter - a filter object from a widget configuration object.
 * @returns {object} filter aggregations
 */

function generateAggregation(filter) {
  switch (filter.type) {
    case 'date_histogram':
      {
        const field = filter.field === 'post_date_gmt' ? 'date_gmt' : 'date';
        return {
          date_histogram: {
            field,
            interval: filter.interval
          }
        };
      }

    case 'taxonomy':
      {
        let field = `taxonomy.${filter.taxonomy}.slug_slash_name`;

        if (filter.taxonomy === 'post_tag') {
          field = 'tag.slug_slash_name';
        } else if (filter.taxonomy === 'category') {
          field = 'category.slug_slash_name';
        }

        return {
          terms: {
            field,
            size: filter.count
          }
        };
      }

    case 'post_type':
      {
        return {
          terms: {
            field: filter.type,
            size: filter.count
          }
        };
      }
  }
}

const DATE_REGEX = /(\d{4})-(\d{2})-(\d{2})/;
/**
 * Generates a ElasticSerach date range filter.
 *
 * @param {string} fieldName - Name of the field (created, modified, etc).
 * @param {string} input - Filter value.
 * @param {string} type - Date range type (year vs month).
 * @returns {object} date filter.
 */

function generateDateRangeFilter(fieldName, input, type) {
  let year, month;

  if (type === 'year') {
    [, year,,] = input.match(DATE_REGEX);
  }

  if (type === 'month') {
    [, year, month] = input.match(DATE_REGEX);
  }

  let startDate = '';
  let endDate = '';

  if (month) {
    const nextMonth = +month + 1;
    const nextMonthPadded = nextMonth < 10 ? `0${nextMonth}` : `${nextMonth}`;
    startDate = `${year}-${month}-01`;
    endDate = nextMonth <= 12 ? `${year}-${nextMonthPadded}-01` : `${+year + 1}-01-01`;
  } else if (year) {
    startDate = `${year}-01-01`;
    endDate = `${+year + 1}-01-01`;
  }

  return {
    range: {
      [fieldName]: {
        gte: startDate,
        lt: endDate
      }
    }
  };
}
const filterKeyToEsFilter = new Map([// Post type
['post_types', postType => ({
  term: {
    post_type: postType
  }
})], // Built-in taxonomies
['category', category => ({
  term: {
    'category.slug': category
  }
})], ['post_tag', tag => ({
  term: {
    'tag.slug': tag
  }
})], // Dates
['month_post_date', datestring => generateDateRangeFilter('date', datestring, 'month')], ['month_post_date_gmt', datestring => generateDateRangeFilter('date_gmt', datestring, 'month')], ['month_post_modified', datestring => generateDateRangeFilter('date', datestring, 'month')], ['month_post_modified_gmt', datestring => generateDateRangeFilter('date_gmt', datestring, 'month')], ['year_post_date', datestring => generateDateRangeFilter('date', datestring, 'year')], ['year_post_date_gmt', datestring => generateDateRangeFilter('date_gmt', datestring, 'year')], ['year_post_modified', datestring => generateDateRangeFilter('date', datestring, 'year')], ['year_post_modified_gmt', datestring => generateDateRangeFilter('date_gmt', datestring, 'year')]]);
/**
 * Build static filters object
 *
 * @param {object} staticFilters - list of static filter key-value.
 * @returns {object} - list of selected static filters.
 */

function buildStaticFilters(staticFilters) {
  const selectedFilters = {};
  Object.keys(staticFilters).forEach(key => {
    const value = staticFilters[key];

    if (key === 'group_id') {
      if (value !== _constants__WEBPACK_IMPORTED_MODULE_3__/* .MULTISITE_NO_GROUP_VALUE */ .Bk) {
        // Do not set filter if for no_groups, it should just use current blog.
        selectedFilters[key] = value;
      }
    }
  });
  return selectedFilters;
}
/**
 * Build an ElasticSerach filter object.
 *
 * @param {object} filterQuery - Filter query value object.
 * @param {object} adminQueryFilter - Manual ElasticSearch query override.
 * @param {string} excludedPostTypes - Post types excluded via the Customizer.
 * @returns {object} ElasticSearch filter object.
 */


function buildFilterObject(filterQuery, adminQueryFilter, excludedPostTypes) {
  const filter = {
    bool: {
      must: []
    }
  };
  (0,_filters__WEBPACK_IMPORTED_MODULE_2__/* .getFilterKeys */ .wP)().filter(key => isLengthyArray(filterQuery[key])).forEach(key => {
    filterQuery[key].forEach(item => {
      if (filterKeyToEsFilter.has(key)) {
        filter.bool.must.push(filterKeyToEsFilter.get(key)(item));
      } else {
        // If key is not in the standard map, assume to be a custom taxonomy
        filter.bool.must.push({
          term: {
            [`taxonomy.${key}.slug`]: item
          }
        });
      }
    });
  });

  if (adminQueryFilter) {
    filter.bool.must.push(adminQueryFilter);
  }

  if ((excludedPostTypes === null || excludedPostTypes === void 0 ? void 0 : excludedPostTypes.length) > 0) {
    filter.bool.must.push({
      bool: {
        must_not: excludedPostTypes.map(postType => filterKeyToEsFilter.get('post_types')(postType))
      }
    });
  }

  return filter;
} // Maps sort values to values expected by the API


const SORT_QUERY_MAP = new Map([['oldest', 'date_asc'], ['newest', 'date_desc'], ['relevance', 'score_default']]);
/**
 * Map sort values to ones compatible with the API.
 *
 * @param {string} sort - Sort value.
 * @returns {string} Mapped sort value.
 */

function mapSortToApiValue(sort) {
  // Some sorts don't need to be mapped
  if (['price_asc', 'price_desc', 'rating_desc'].includes(sort)) {
    return sort;
  }

  return SORT_QUERY_MAP.get(sort, 'score_default');
}
/* eslint-disable jsdoc/require-param,jsdoc/check-param-names */

/**
 * Generate the query string for an API request
 *
 * @param {object} options - Options object for the function
 * @returns {string} The generated query string.
 */


function generateApiQueryString({
  aggregations,
  excludedPostTypes,
  filter,
  staticFilters,
  pageHandle,
  query,
  resultFormat,
  sort,
  postsPerPage = 10,
  adminQueryFilter,
  isInCustomizer = false
}) {
  if (query === null) {
    query = '';
  }

  let fields = ['date', 'permalink.url.raw', 'tag.name.default', 'category.name.default', 'post_type', 'has.image', 'shortcode_types', 'image.url.raw'];
  const highlightFields = ['title', 'content', 'comments'];
  /* Fetch additional fields for product results
   *
   * We always need these in the Customizer too, because the API request is not
   * repeated when switching result format
   */

  if (resultFormat === _constants__WEBPACK_IMPORTED_MODULE_3__/* .RESULT_FORMAT_PRODUCT */ .LI || isInCustomizer) {
    fields = fields.concat(['meta._wc_average_rating.double', 'meta._wc_review_count.long', 'wc.formatted_price', 'wc.formatted_regular_price', 'wc.formatted_sale_price', 'wc.price', 'wc.sale_price']);
  }
  /**
   * Fetch additional fields for multi site results
   */


  if (staticFilters && staticFilters.group_id && staticFilters.group_id !== _constants__WEBPACK_IMPORTED_MODULE_3__/* .MULTISITE_NO_GROUP_VALUE */ .Bk) {
    fields = fields.concat(['author', 'blog_name', 'blog_icon_url']);
  }

  let params = {
    aggregations,
    fields,
    highlight_fields: highlightFields,
    filter: buildFilterObject(filter, adminQueryFilter, excludedPostTypes),
    query: encodeURIComponent(query),
    sort: mapSortToApiValue(sort),
    page_handle: pageHandle,
    size: postsPerPage
  };

  if (staticFilters && Object.keys(staticFilters).length > 0) {
    params = { ...params,
      ...buildStaticFilters(staticFilters)
    };
  }

  return (0,qss__WEBPACK_IMPORTED_MODULE_5__/* .encode */ .c)((0,q_flat__WEBPACK_IMPORTED_MODULE_0__/* .flatten */ .x)(params));
}
/* eslint-enable jsdoc/require-param,jsdoc/check-param-names */

/**
 * Generate an error handler for a given cache key
 *
 * @param {string} cacheKey - The cache key to use
 * @returns {Function} An error handler to be used with a search request
 */


function errorHandlerFactory(cacheKey) {
  return function errorHandler(error) {
    // TODO: Display a message about falling back to a cached value in the interface.
    const fallbackValue = cache.get(cacheKey) || backupCache.get(cacheKey); // Fallback to cached value if request has been cancelled.

    if (error.name === 'AbortError') {
      return fallbackValue ? {
        _isCached: true,
        _isError: false,
        _isOffline: false,
        ...fallbackValue
      } : null;
    } // Fallback to cached value if we run into any errors.


    if (fallbackValue) {
      return {
        _isCached: true,
        _isError: true,
        _isOffline: false,
        ...fallbackValue
      };
    } // Otherwise, propagate the error.


    throw error;
  };
}
/**
 * Generate a response handler for a given cache key
 *
 * @param {string} cacheKey - The cache key to use
 * @param {number} requestId - Sequential ID used to determine recency of requests.
 * @returns {Function} A response handler to be used with a search request
 */


function responseHandlerFactory(cacheKey, requestId) {
  return function responseHandler(responseJson) {
    const response = { ...responseJson,
      requestId
    };
    cache.set(cacheKey, response);
    backupCache.set(cacheKey, response);
    return response;
  };
}
/**
 * Abort the existing request and set up a new abort controller, for new requests.
 */


function resetAbortController() {
  if (abortController) {
    abortController.abort();
  }

  abortController = new AbortController();
}
/**
 * Perform a search.
 *
 * @param {object} options - Search options
 * @param {number} requestId - Sequential ID used to determine recency of requests.
 * @returns {Promise} A promise to the JSON response object
 */


function search(options, requestId) {
  const key = fast_json_stable_stringify__WEBPACK_IMPORTED_MODULE_1___default()(Array.from(arguments)); // Use cached value from the last 30 minutes if browser is offline

  if (!navigator.onLine && backupCache.get(key)) {
    return Promise.resolve(backupCache.get(key)).then(data => ({
      _isCached: true,
      _isError: false,
      _isOffline: true,
      ...data
    }));
  } // Use cached value from the last 5 minutes


  if (cache.get(key)) {
    return Promise.resolve(cache.get(key)).then(data => ({
      _isCached: true,
      _isError: false,
      _isOffline: false,
      ...data
    }));
  }

  const queryString = generateApiQueryString(options);
  const errorHandler = errorHandlerFactory(key);
  const responseHandler = responseHandlerFactory(key, requestId);
  const pathForPublicApi = `/sites/${options.siteId}/search?${queryString}`;
  const {
    apiNonce,
    apiRoot,
    homeUrl,
    isPrivateSite,
    isWpcom
  } = window[_constants__WEBPACK_IMPORTED_MODULE_3__/* .SERVER_OBJECT_NAME */ .W1]; // NOTE: Both simple and atomic sites can be set to "private".
  //       "Private" Jetpack sites are not yet supported.

  const urlForPublicApi = `https://public-api.wordpress.com/rest/v1.3${pathForPublicApi}`;
  const urlForWpcomOrigin = `${homeUrl}/wp-json/wpcom-origin/v1.3${pathForPublicApi}`;
  const urlForAtomicOrigin = `${apiRoot}wpcom/v2/search?${queryString}`;
  let url = urlForPublicApi;

  if (isPrivateSite && isWpcom) {
    url = urlForWpcomOrigin;
  } else if (isPrivateSite) {
    url = urlForAtomicOrigin;
  }

  resetAbortController(); // NOTE: API Nonce is necessary to authenticate requests to class-wpcom-rest-api-v2-endpoint-search.php.

  return fetch(url, {
    headers: isPrivateSite ? {
      'X-WP-Nonce': apiNonce
    } : {},
    credentials: isPrivateSite ? 'include' : 'same-origin',
    signal: abortController.signal
  }).then(response => {
    if (response.status !== 200) {
      return Promise.reject(`Unexpected response from API with status code ${response.status}.`);
    }

    return response;
  }).then(r => r.json()).then(responseHandler).catch(errorHandler);
}

/***/ }),

/***/ 8984:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "Z": function() { return /* binding */ arrayOverlap; }
/* harmony export */ });
function arrayOverlap(a1, a2) {
  if (!Array.isArray(a1)) {
    a1 = [a1];
  }

  const intersection = a1.filter(value => a2.includes(value));
  return intersection.length !== 0;
}

/***/ }),

/***/ 2526:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "B": function() { return /* binding */ getConstrastingColor; }
/* harmony export */ });
function extractHexCode(input) {
  let output;

  if (input[0] === '#') {
    output = input.substring(1);
  }

  if (output.length === 3) {
    output = output.split('').map(letter => `${letter}${letter}`).join('');
  }

  return output;
}

function getConstrastingColor(input) {
  // https://gomakethings.com/dynamically-changing-the-text-color-based-on-background-color-contrast-with-vanilla-js/
  const colorHex = extractHexCode(input);
  const r = parseInt(colorHex.substr(0, 2), 16);
  const g = parseInt(colorHex.substr(2, 2), 16);
  const b = parseInt(colorHex.substr(4, 2), 16);
  const yiq = (r * 299 + g * 587 + b * 114) / 1000;
  return yiq >= 128 ? 'black' : 'white';
}

/***/ }),

/***/ 7266:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "b": function() { return /* binding */ getCheckedInputNames; },
/* harmony export */   "C": function() { return /* binding */ getThemeOptions; }
/* harmony export */ });
function getCheckedInputNames(parentDom) {
  return [...parentDom.querySelectorAll('input[type="checkbox"]').values()].filter(input => input.checked).map(input => input.name);
}
function getThemeOptions(searchOptions) {
  const options = {
    searchInputSelector: ['input[name="s"]:not(.jetpack-instant-search__box-input)', '#searchform input.search-field:not(.jetpack-instant-search__box-input)', '.search-form input.search-field:not(.jetpack-instant-search__box-input)', '.searchform input.search-field:not(.jetpack-instant-search__box-input)'].join(', '),
    filterInputSelector: ['a.jetpack-search-filter__link'],
    overlayTriggerSelector: ['.jetpack-instant-search__open-overlay-button', 'header#site-header .search-toggle[data-toggle-target]' // TwentyTwenty theme's search button
    ].join(',')
  };
  return searchOptions.theme_options ? { ...options,
    ...searchOptions.theme_options
  } : options;
}

/***/ }),

/***/ 4832:
/***/ (function(__unused_webpack_module, __webpack_exports__) {

const noop = () => {};
/**
 * Used to replace `debug` calls in production.
 *
 * @returns {Function} A noop function.
 */


/* harmony default export */ __webpack_exports__["Z"] = (() => noop);

/***/ }),

/***/ 8033:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "wP": function() { return /* binding */ getFilterKeys; },
/* harmony export */   "bA": function() { return /* binding */ getAvailableStaticFilters; },
/* harmony export */   "i3": function() { return /* binding */ getStaticFilterKeys; },
/* harmony export */   "do": function() { return /* binding */ getUnselectableFilterKeys; },
/* harmony export */   "jZ": function() { return /* binding */ mapFilterToFilterKey; },
/* harmony export */   "$s": function() { return /* binding */ mapFilterKeyToFilter; },
/* harmony export */   "jc": function() { return /* binding */ mapFilterToType; }
/* harmony export */ });
/* unused harmony exports FILTER_KEYS, getSelectableFilterKeys */
/* harmony import */ var _constants__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(9379);
/**
 * Internal dependencies
 */
 // NOTE: This list is missing custom taxonomy names.
//       getFilterKeys must be used to get the conclusive list of valid filter keys.

const FILTER_KEYS = Object.freeze([// Post types
'post_types', // Built-in taxonomies
'category', 'post_format', 'post_tag', // Date filters
'month_post_date', 'month_post_date_gmt', 'month_post_modified', 'month_post_modified_gmt', 'year_post_date', 'year_post_date_gmt', 'year_post_modified', 'year_post_modified_gmt']);
/**
 * Returns an array of valid filter key strings.
 *
 * @param {object[]} widgets - Array of Jetpack Search widget objects inside the overlay sidebar.
 * @param {object[]} widgetsOutsideOverlay - Array of Jetpack Search widget objects outside the overlay sidebar.
 * @returns {string[]} filterKeys
 */

function getFilterKeys(widgets = (() => {
  var _window$SERVER_OBJECT;

  return (_window$SERVER_OBJECT = window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1]) === null || _window$SERVER_OBJECT === void 0 ? void 0 : _window$SERVER_OBJECT.widgets;
})(), widgetsOutsideOverlay = (() => {
  var _window$SERVER_OBJECT2;

  return (_window$SERVER_OBJECT2 = window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1]) === null || _window$SERVER_OBJECT2 === void 0 ? void 0 : _window$SERVER_OBJECT2.widgetsOutsideOverlay;
})()) {
  // Extract taxonomy names from server widget data
  const keys = new Set(FILTER_KEYS);
  [...(widgets !== null && widgets !== void 0 ? widgets : []), ...(widgetsOutsideOverlay !== null && widgetsOutsideOverlay !== void 0 ? widgetsOutsideOverlay : [])].map(w => w.filters).filter(filters => Array.isArray(filters)).reduce((filtersA, filtersB) => filtersA.concat(filtersB), []).filter(filter => filter.type === 'taxonomy').forEach(filter => keys.add(filter.taxonomy));
  return [...keys];
}
/**
 * Get a list of provided static filters.
 *
 * @returns {Array} list of available static filters.
 */

function getAvailableStaticFilters() {
  var _window$SERVER_OBJECT3;

  if (!((_window$SERVER_OBJECT3 = window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1]) !== null && _window$SERVER_OBJECT3 !== void 0 && _window$SERVER_OBJECT3.staticFilters)) {
    return [];
  }

  return window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1].staticFilters;
}
/**
 * Get static filter keys.
 *
 * @returns {Array} list of available static filters keys.
 */

function getStaticFilterKeys() {
  const staticFilters = getAvailableStaticFilters();
  const keys = new Set();
  staticFilters.forEach(filter => keys.add(filter.filter_id));
  return [...keys];
}
/**
 * Returns an array of filter keys selectable from within the overlay.
 *
 * @param {object[]} widgets - Array of Jetpack Search widget objects inside the overlay sidebar.
 * @returns {string[]} filterKeys
 */

function getSelectableFilterKeys(widgets = (() => {
  var _window$SERVER_OBJECT4;

  return (_window$SERVER_OBJECT4 = window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1]) === null || _window$SERVER_OBJECT4 === void 0 ? void 0 : _window$SERVER_OBJECT4.widgets;
})()) {
  var _widgets$map$reduce;

  return (_widgets$map$reduce = widgets === null || widgets === void 0 ? void 0 : widgets.map(extractFilterKeys).reduce((prev, current) => prev.concat(current), [])) !== null && _widgets$map$reduce !== void 0 ? _widgets$map$reduce : [];
}
/**
 * Returns an array of filter keys not selectable from within the overlay.
 * In other words, they were either selected via filters outside the search sidebar or entered manually.
 *
 * @param {object[]} widgets - Array of Jetpack Search widget objects inside the overlay sidebar.
 * @returns {string[]} filterKeys
 */

function getUnselectableFilterKeys(widgets = (() => {
  var _window$SERVER_OBJECT5;

  return (_window$SERVER_OBJECT5 = window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1]) === null || _window$SERVER_OBJECT5 === void 0 ? void 0 : _window$SERVER_OBJECT5.widgets;
})()) {
  const selectable = getSelectableFilterKeys(widgets);
  return getFilterKeys().filter(key => !selectable.includes(key));
}
/**
 * Returns an array of filter keys from a given widget.
 *
 * @param {object} widget - a Jetpack Search widget object
 * @returns {string[]} filterKeys
 */

function extractFilterKeys(widget) {
  return widget.filters.map(mapFilterToFilterKey).filter(filterName => typeof filterName === 'string');
}
/**
 * Returns a filter key given a filter object.
 *
 * @param {object} filter - a Jetpack Search filter object
 * @returns {string} filterKeys
 */


function mapFilterToFilterKey(filter) {
  if (filter.type === 'date_histogram') {
    return `${filter.interval}_${filter.field}`;
  } else if (filter.type === 'taxonomy') {
    return `${filter.taxonomy}`;
  } else if (filter.type === 'post_type') {
    return 'post_types';
  } else if (filter.type === 'group') {
    return filter.filter_id;
  }

  return null;
}
/**
 * Returns a filter object corresponding to the filterKey input.
 * Inverse of `mapFilterToFilterKey`.
 *
 * @param {string} filterKey - filter key string to be mapped.
 * @returns {object} filterObject
 */

function mapFilterKeyToFilter(filterKey) {
  if (filterKey.includes('month')) {
    return {
      field: filterKey.split('month_').pop(),
      type: 'date_histogram',
      interval: 'month'
    };
  } else if (filterKey.includes('year')) {
    return {
      field: filterKey.split('year_').pop(),
      type: 'date_histogram',
      interval: 'year'
    };
  } else if (filterKey === 'post_types') {
    return {
      type: 'post_type'
    };
  } else if (filterKey === 'group') {
    return {
      type: 'group'
    };
  }

  return {
    type: 'taxonomy',
    taxonomy: filterKey
  };
}
/**
 * Returns the type of the inputted filter object.
 *
 * @param {object} filter - filter key string to be mapped.
 * @returns {string} output
 */

function mapFilterToType(filter) {
  if (filter.type === 'date_histogram') {
    return 'date';
  } else if (filter.type === 'taxonomy') {
    return 'taxonomy';
  } else if (filter.type === 'post_type') {
    return 'postType';
  } else if (filter.type === 'group') {
    return 'group';
  }
}

/***/ }),

/***/ 4664:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "y": function() { return /* binding */ usePhoton; }
/* harmony export */ });
/* harmony import */ var photon__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(278);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(1634);
/**
 * External dependencies
 */


/**
 * Strips query string values from URLs; photon can't handle them.
 *
 * @param {string} url - Image URL
 * @returns {string} - Image URL without any query strings.
 */

function stripQueryString(url) {
  if (!url) {
    return '';
  }

  return url.split('?', 1)[0];
}
/**
 * Hook for returning a Photonized image URL given width and height parameters.
 *
 * @param {string} initialSrc - Image URL
 * @param {number} width - width in pixels
 * @param {number} height - height in pixels
 * @param {boolean} isPhotonEnabled - Toggle photon on/off
 * @returns {string} - Photonized image URL if service is available; initialSrc otherwise.
 */


function usePhoton(initialSrc, width, height, isPhotonEnabled = true) {
  const [src, setSrc] = (0,react__WEBPACK_IMPORTED_MODULE_0__/* .useState */ .eJ)(null);
  const initialSrcWithoutQueryString = stripQueryString(initialSrc); // Photon only supports GIF, JPG, PNG and WebP images
  // @see https://developer.wordpress.com/docs/photon/

  const supportedImageTypes = ['gif', 'jpg', 'jpeg', 'png', 'webp'];
  const fileExtension = initialSrcWithoutQueryString === null || initialSrcWithoutQueryString === void 0 ? void 0 : initialSrcWithoutQueryString.substring(initialSrcWithoutQueryString.lastIndexOf('.') + 1).toLowerCase();
  const isSupportedImageType = supportedImageTypes.includes(fileExtension);
  (0,react__WEBPACK_IMPORTED_MODULE_0__/* .useEffect */ .d4)(() => {
    if (isPhotonEnabled && isSupportedImageType) {
      const photonSrc = (0,photon__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z)(initialSrcWithoutQueryString, {
        resize: `${width},${height}`
      });
      setSrc(photonSrc ? photonSrc : initialSrc);
    } else {
      setSrc(initialSrc);
    }
  }, [initialSrc, width, height, isPhotonEnabled, initialSrcWithoutQueryString, isSupportedImageType]);
  return src;
}

/***/ }),

/***/ 227:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "pm": function() { return /* binding */ getQuery; },
/* harmony export */   "_L": function() { return /* binding */ setQuery; },
/* harmony export */   "ug": function() { return /* binding */ getResultFormatQuery; },
/* harmony export */   "Q0": function() { return /* binding */ restorePreviousHref; }
/* harmony export */ });
/* harmony import */ var qss__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(4090);
/* harmony import */ var _constants__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(9379);
/* harmony import */ var _filters__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(8033);
/* harmony import */ var _external_query_string_decode__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(6241);
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */




/**
 * Parses the address bar's query string into an object.
 *
 * @param {string} search - raw query string prepended with '?'
 * @returns {object} queryObject - a query object.
 */

function getQuery(search = window.location.search) {
  return (0,_external_query_string_decode__WEBPACK_IMPORTED_MODULE_2__/* .decode */ .J)(search.substring(1), false, false);
}
/**
 * Updates the browser's query string via a query object.
 *
 * @param {object} queryObject - a query object.
 */

function setQuery(queryObject) {
  pushQueryString((0,qss__WEBPACK_IMPORTED_MODULE_3__/* .encode */ .c)(queryObject));
}
/**
 * Updates the browser's query string via an encoded query string.
 *
 * @param {string} queryString - an encoded query string.
 */

function pushQueryString(queryString) {
  if (history.pushState) {
    const url = new window.URL(window.location.href);

    if (window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1] && 'homeUrl' in window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1]) {
      url.href = window[_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1].homeUrl;
    }

    url.search = queryString;
    window.history.pushState(null, null, url.toString());
  }
}
/**
 * Returns a result format value from the query string. Used to override the site's configured result format.
 *
 * @returns {null|string} resultFormatQuery
 */


function getResultFormatQuery() {
  const query = getQuery();

  if (!_constants__WEBPACK_IMPORTED_MODULE_0__/* .VALID_RESULT_FORMAT_KEYS.includes */ .bk.includes(query.result_format)) {
    return null;
  }

  return query.result_format;
}
/**
 * Navigates the window to a specified location with all search-related query values stirpped out.
 *
 * @param {string} initialHref - Target location to navigate to via push/replaceState.
 * @param {Function} callback - Callback to be invoked if initialHref didn't include any search queries.
 * @param {boolean} replaceState - Flag to toggle replaceState or pushState invocation. Useful if this function's being invoked due to history navigation.
 */

function restorePreviousHref(initialHref, callback, replaceState = false) {
  if (history.pushState && history.replaceState) {
    const url = new URL(initialHref);
    const queryObject = getQuery(url.search);
    const keys = [...(0,_filters__WEBPACK_IMPORTED_MODULE_1__/* .getFilterKeys */ .wP)(), ...(0,_filters__WEBPACK_IMPORTED_MODULE_1__/* .getStaticFilterKeys */ .i3)(), 's', 'sort']; // If initialHref has search or filter query values, clear them.

    const initialHasSearchQueries = Object.keys(queryObject).some(key => keys.includes(key));

    if (initialHasSearchQueries) {
      keys.forEach(key => delete queryObject[key]);
    }

    url.search = (0,qss__WEBPACK_IMPORTED_MODULE_3__/* .encode */ .c)(queryObject);
    replaceState ? window.history.replaceState(null, null, url.toString()) : window.history.pushState(null, null, url.toString()); // If initialHref had search queries, then the page rendered beneath the search modal is WordPress's default search page.
    // We want to strip these search queries from the URL and direct the user to the root if possible.

    if (initialHasSearchQueries) {
      window.location.reload();
      return;
    } // If we didn't need to reload the window, invoke the callback which is usually used for
    // React/Redux state transitions to reflect the newly set URL.


    callback();
  }
}

/***/ }),

/***/ 7297:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "n": function() { return /* binding */ getSortOptions; }
/* harmony export */ });
/* harmony import */ var _constants__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(9379);
/**
 * Internal dependencies
 */

/**
 * Get the available sort options for the provided result format
 *
 * @param   {string} resultFormat - Result format
 * @returns {Map} - Sort options
 */

function getSortOptions(resultFormat = null) {
  if (resultFormat !== _constants__WEBPACK_IMPORTED_MODULE_0__/* .RESULT_FORMAT_PRODUCT */ .LI) {
    return _constants__WEBPACK_IMPORTED_MODULE_0__/* .SORT_OPTIONS */ .aP;
  } // For product results, add additional product sort options


  return new Map([..._constants__WEBPACK_IMPORTED_MODULE_0__/* .SORT_OPTIONS */ .aP, ..._constants__WEBPACK_IMPORTED_MODULE_0__/* .PRODUCT_SORT_OPTIONS */ .rs]);
}

/***/ }),

/***/ 814:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "IJ": function() { return /* binding */ disableAnalytics; },
/* harmony export */   "tU": function() { return /* binding */ initializeTracks; },
/* harmony export */   "vy": function() { return /* binding */ resetTrackingCookies; },
/* harmony export */   "AM": function() { return /* binding */ identifySite; },
/* harmony export */   "Sn": function() { return /* binding */ recordTrainTracksRender; },
/* harmony export */   "Lo": function() { return /* binding */ recordTrainTracksInteract; },
/* harmony export */   "GE": function() { return /* binding */ recordStaticFilterSelect; }
/* harmony export */ });
/* unused harmony export recordEvent */
let isAnalyticsEnabled = true;
const globalProperties = {};
/**
 * Disable Analytics.
 */

function disableAnalytics() {
  isAnalyticsEnabled = false;
}
/**
 * Initalizes Tracks.
 *
 * @param {boolean} forceEnableAnalytics - Forcibly enable analytics, ignoring the isAnalyticsEnabled flag.
 */

function initializeTracks(forceEnableAnalytics = false) {
  if (forceEnableAnalytics || isAnalyticsEnabled) {
    window._tkq = window._tkq || [];
  }
}
/**
 * Resets current user's tracked identity.
 *
 * @param {boolean} forceEnableAnalytics - Forcibly enable analytics, ignoring the isAnalyticsEnabled flag.
 */

function resetTrackingCookies(forceEnableAnalytics = false) {
  (forceEnableAnalytics || isAnalyticsEnabled) && window._tkq.push(['clearIdentity']);
}
/**
 * Associates the current site with events fired in the future.
 *
 * @param {number|string} siteId - Current site identifier.
 * @param {boolean} forceEnableAnalytics - Forcibly enable analytics, ignoring the isAnalyticsEnabled flag.
 */

function identifySite(siteId, forceEnableAnalytics = false) {
  if (forceEnableAnalytics || isAnalyticsEnabled) {
    globalProperties.blog_id = siteId;
  }
}
/**
 * Fires a general event to Tracks.
 *
 * @param {string} eventName - Name of the event.
 * @param {object} properties - Event properties.
 * @param {boolean} forceEnableAnalytics - Forcibly enable analytics, ignoring the isAnalyticsEnabled flag.
 */

function recordEvent(eventName, properties, forceEnableAnalytics = false) {
  (forceEnableAnalytics || isAnalyticsEnabled) && window._tkq.push(['recordEvent', eventName, { ...globalProperties,
    ...properties
  }]);
}
/**
 * Fires a TrainTracks render event to Tracks.
 *
 * @param {object} properties - Event properties.
 * @param {boolean} forceEnableAnalytics - Forcibly enable analytics, ignoring the isAnalyticsEnabled flag.
 */

function recordTrainTracksRender(properties, forceEnableAnalytics = false) {
  recordEvent('jetpack_instant_search_traintracks_render', properties, forceEnableAnalytics);
}
/**
 * Fires a TrainTracks interaction event to Tracks.
 *
 * @param {object} properties - Event properties.
 * @param {boolean} forceEnableAnalytics - Forcibly enable analytics, ignoring the isAnalyticsEnabled flag.
 */

function recordTrainTracksInteract(properties, forceEnableAnalytics = false) {
  recordEvent('jetpack_instant_search_traintracks_interact', properties, forceEnableAnalytics);
}
/**
 * Fires a static filter selection event to Tracks.
 *
 * @param {object} properties - Event properties to send to Tracks.
 * @param {boolean} forceEnableAnalytics - Forcibly enable analytics, ignoring the isAnalyticsEnabled flag.
 */

function recordStaticFilterSelect(properties, forceEnableAnalytics = false) {
  recordEvent('jetpack_instant_search_static_filter_select', properties, forceEnableAnalytics);
}

/***/ }),

/***/ 7717:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "x1": function() { return /* binding */ makeSearchRequest; },
/* harmony export */   "C0": function() { return /* binding */ recordSuccessfulSearchRequest; },
/* harmony export */   "Y6": function() { return /* binding */ recordFailedSearchRequest; },
/* harmony export */   "Ln": function() { return /* binding */ initializeQueryValues; },
/* harmony export */   "ql": function() { return /* binding */ setSearchQuery; },
/* harmony export */   "HD": function() { return /* binding */ setSort; },
/* harmony export */   "Tv": function() { return /* binding */ setFilter; },
/* harmony export */   "O1": function() { return /* binding */ setStaticFilter; },
/* harmony export */   "K5": function() { return /* binding */ clearFilters; },
/* harmony export */   "Mz": function() { return /* binding */ clearQueryValues; },
/* harmony export */   "OZ": function() { return /* binding */ disableQueryStringIntegration; }
/* harmony export */ });
/**
 * Returns an action object used to make a search result request.
 *
 * @param {object} options - Search options.
 * @returns {object} Action object.
 */
function makeSearchRequest(options) {
  return {
    type: 'MAKE_SEARCH_REQUEST',
    options
  };
}
/**
 * Returns an action object used to record a successful search request.
 *
 * @param {object} params - Input parameters.
 * @param {object} params.options - Action options that generated this API response.
 * @param {object} params.response - API response.
 * @returns {object} Action object.
 */

function recordSuccessfulSearchRequest({
  options,
  response
}) {
  return {
    type: 'RECORD_SUCCESSFUL_SEARCH_REQUEST',
    options,
    response
  };
}
/**
 * Returns an action object used to record a failed search request.
 *
 * @param {object} error - Error from the failed search request.
 * @returns {object} Action object.
 */

function recordFailedSearchRequest(error) {
  return {
    type: 'RECORD_FAILED_SEARCH_REQUEST',
    error
  };
}
/**
 * Returns an action object used to initialize query value related reducers.
 *
 * @param {object} params - Input parameters.
 * @param {boolean} params.isHistoryNavigation - True if this action is invoked via history navigation.
 * @returns {object} Action object.
 */

function initializeQueryValues({
  isHistoryNavigation = false
} = {}) {
  return {
    type: 'INITIALIZE_QUERY_VALUES',
    isHistoryNavigation
  };
}
/**
 * Returns an action object used to set a search query value.
 *
 * @param {string} query - Inputted user query.
 * @param {boolean} propagateToWindow - If true, will tell the effects handler to set the search query in the location bar.
 * @returns {object} Action object.
 */

function setSearchQuery(query, propagateToWindow = true) {
  return {
    type: 'SET_SEARCH_QUERY',
    query,
    propagateToWindow
  };
}
/**
 * Returns an action object used to set a search sort value.
 *
 * @param {string} sort - Sort value.
 * @param {boolean} propagateToWindow - If true, will tell the effects handler to set the query string in the location bar.
 * @returns {object} Action object.
 */

function setSort(sort, propagateToWindow = true) {
  return {
    type: 'SET_SORT',
    sort,
    propagateToWindow
  };
}
/**
 * Returns an action object used to set a search filter.
 *
 * @param {string} name - Filter name.
 * @param {string[]} value - Filter values.
 * @param {boolean} propagateToWindow - If true, will tell the effects handler to set the query string in the location bar.
 * @returns {object} Action object.
 */

function setFilter(name, value, propagateToWindow = true) {
  return {
    type: 'SET_FILTER',
    name,
    value,
    propagateToWindow
  };
}
/**
 * Returns an action object used to set a static search filter.
 *
 * @param {string} name - Filter name.
 * @param {string[]} value - Filter values.
 * @param {boolean} propagateToWindow - If true, will tell the effects handler to set the query string in the location bar.
 * @returns {object} Action object.
 */

function setStaticFilter(name, value, propagateToWindow = true) {
  return {
    type: 'SET_STATIC_FILTER',
    name,
    value,
    propagateToWindow
  };
}
/**
 * Returns an action object used to clear all filter values.
 *
 * @param {boolean} propagateToWindow - If true, will tell the effects handler to update the query string in the location bar.
 * @returns {object} Action object.
 */

function clearFilters(propagateToWindow = true) {
  return {
    type: 'CLEAR_FILTERS',
    propagateToWindow
  };
}
/**
 * Returns an action object used to clear all query values. Invoked when the search modal is dismissed.
 *
 * @returns {object} Action object.
 */

function clearQueryValues() {
  return {
    type: 'CLEAR_QUERY_VALUES'
  };
}
/**
 * Returns an action object used to disable query string integration.
 * Used when search app is used in the Gutenberg context.
 *
 * @returns {object} Action object.
 */

function disableQueryStringIntegration() {
  return {
    type: 'DISABLE_QUERY_STRING_INTEGRATION'
  };
}

/***/ }),

/***/ 1569:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var _lib_api__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(7722);
/* harmony import */ var _lib_constants__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(9379);
/* harmony import */ var _lib_filters__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(8033);
/* harmony import */ var _lib_query_string__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(227);
/* harmony import */ var _actions__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(7717);
/**
 * Internal dependencies
 */





let requestCounter = 0;
let queryStringIntegrationEnabled = true;
/**
 * Effect handler which will fetch search results from the API.
 *
 * @param {object} action - Action which had initiated the effect handler.
 * @param {object} store -  Store instance.
 */

function makeSearchAPIRequest(action, store) {
  requestCounter++;
  (0,_lib_api__WEBPACK_IMPORTED_MODULE_0__/* .search */ .yC)(action.options, requestCounter).then(response => {
    if (response === null) {
      // Request has been cancelled by a more recent request.
      return;
    }

    store.dispatch((0,_actions__WEBPACK_IMPORTED_MODULE_4__/* .recordSuccessfulSearchRequest */ .C0)({
      options: action.options,
      response
    }));
  }).catch(error => {
    // eslint-disable-next-line no-console
    console.error('Jetpack Search encountered an error:', error);
    store.dispatch((0,_actions__WEBPACK_IMPORTED_MODULE_4__/* .recordFailedSearchRequest */ .Y6)(error));
  });
}
/**
 * Initialize query values from the browser's address bar.
 *
 * @param {object} action - Action which had initiated the effect handler.
 * @param {object} store -  Store instance.
 */


function initializeQueryValues(action, store) {
  const queryObject = (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_3__/* .getQuery */ .pm)(); //
  // Initialize search query value for the reducer.
  //

  if ('s' in queryObject) {
    store.dispatch((0,_actions__WEBPACK_IMPORTED_MODULE_4__/* .setSearchQuery */ .ql)(queryObject.s, false));
  } else {
    store.dispatch((0,_actions__WEBPACK_IMPORTED_MODULE_4__/* .setSearchQuery */ .ql)(null, false));
  } //
  // Initialize sort value for the reducer.
  //


  let sort;

  if (_lib_constants__WEBPACK_IMPORTED_MODULE_1__/* .VALID_SORT_KEYS.includes */ .kQ.includes(queryObject.sort)) {
    // Set sort value from `sort` query value.
    sort = queryObject.sort;
  } else if ('date' === queryObject.orderby) {
    // Set sort value from legacy `orderby` query value.
    sort = typeof queryObject.order === 'string' && queryObject.order.toUpperCase() === _lib_constants__WEBPACK_IMPORTED_MODULE_1__/* .SORT_DIRECTION_ASC */ .oy ? 'oldest' : 'newest';
  } else if ('relevance' === queryObject.orderby) {
    // Set sort value from legacy `orderby` query value.
    sort = 'relevance';
  }

  typeof sort === 'string' && store.dispatch((0,_actions__WEBPACK_IMPORTED_MODULE_4__/* .setSort */ .HD)(sort, false)); //
  // Initialize filter value for the reducer.
  //

  store.dispatch((0,_actions__WEBPACK_IMPORTED_MODULE_4__/* .clearFilters */ .K5)(false));
  (0,_lib_filters__WEBPACK_IMPORTED_MODULE_2__/* .getFilterKeys */ .wP)().filter(filterKey => filterKey in queryObject).forEach(filterKey => store.dispatch((0,_actions__WEBPACK_IMPORTED_MODULE_4__/* .setFilter */ .Tv)(filterKey, queryObject[filterKey], false))); //
  // Initialize static filters
  //

  (0,_lib_filters__WEBPACK_IMPORTED_MODULE_2__/* .getStaticFilterKeys */ .i3)().filter(filterKey => filterKey in queryObject).forEach(filterKey => store.dispatch((0,_actions__WEBPACK_IMPORTED_MODULE_4__/* .setStaticFilter */ .O1)(filterKey, queryObject[filterKey], false)));
}
/**
 * Effect handler which will update the location bar's search query string
 *
 * @param {object} action - Action which had initiated the effect handler.
 */


function updateSearchQueryString(action) {
  if (action.propagateToWindow === false || !queryStringIntegrationEnabled) {
    return;
  }

  const queryObject = (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_3__/* .getQuery */ .pm)();

  if (action.query !== null) {
    queryObject.s = action.query;
  } else {
    delete queryObject.s;
  }

  (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_3__/* .setQuery */ ._L)(queryObject);
}
/**
 * Effect handler which will update the location bar's sort query string
 *
 * @param {object} action - Action which had initiated the effect handler.
 */


function updateSortQueryString(action) {
  if (action.propagateToWindow === false || !queryStringIntegrationEnabled) {
    return;
  }

  if (!_lib_constants__WEBPACK_IMPORTED_MODULE_1__/* .VALID_SORT_KEYS.includes */ .kQ.includes(action.sort)) {
    return;
  }

  const queryObject = (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_3__/* .getQuery */ .pm)();
  queryObject.sort = action.sort; // Removes legacy sort query values, just in case.

  delete queryObject.order;
  delete queryObject.orderby;
  (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_3__/* .setQuery */ ._L)(queryObject);
}
/**
 * Effect handler which will update the location bar's filter query string
 *
 * @param {object} action - Action which had initiated the effect handler.
 */


function updateFilterQueryString(action) {
  if (action.propagateToWindow === false || !queryStringIntegrationEnabled) {
    return;
  }

  if (!(0,_lib_filters__WEBPACK_IMPORTED_MODULE_2__/* .getFilterKeys */ .wP)().includes(action.name)) {
    return;
  }

  const queryObject = (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_3__/* .getQuery */ .pm)();
  queryObject[action.name] = action.value;
  (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_3__/* .setQuery */ ._L)(queryObject);
}
/**
 * Effect handler which will update the location bar's static filter query string
 *
 * @param {object} action - Action which had initiated the effect handler.
 */


function updateStaticFilterQueryString(action) {
  if (action.propagateToWindow === false) {
    return;
  }

  if (!(0,_lib_filters__WEBPACK_IMPORTED_MODULE_2__/* .getStaticFilterKeys */ .i3)().includes(action.name)) {
    return;
  }

  const queryObject = (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_3__/* .getQuery */ .pm)();
  queryObject[action.name] = action.value;
  (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_3__/* .setQuery */ ._L)(queryObject);
}
/**
 * Effect handler which will clear filter queries from the location bar
 *
 * @param {object} action - Action which had initiated the effect handler.
 */


function clearFilterQueryString(action) {
  if (action.propagateToWindow === false || !queryStringIntegrationEnabled) {
    return;
  }

  const queryObject = (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_3__/* .getQuery */ .pm)();
  (0,_lib_filters__WEBPACK_IMPORTED_MODULE_2__/* .getFilterKeys */ .wP)().forEach(key => delete queryObject[key]);
  (0,_lib_filters__WEBPACK_IMPORTED_MODULE_2__/* .getStaticFilterKeys */ .i3)().forEach(key => delete queryObject[key]);
  (0,_lib_query_string__WEBPACK_IMPORTED_MODULE_3__/* .setQuery */ ._L)(queryObject);
}
/**
 * Effect handler to disable query string integration for all effects.
 */


function disableQueryStringIntegration() {
  queryStringIntegrationEnabled = false;
}

/* harmony default export */ __webpack_exports__["Z"] = ({
  CLEAR_FILTERS: clearFilterQueryString,
  DISABLE_QUERY_STRING_INTEGRATION: disableQueryStringIntegration,
  INITIALIZE_QUERY_VALUES: initializeQueryValues,
  MAKE_SEARCH_REQUEST: makeSearchAPIRequest,
  SET_FILTER: updateFilterQueryString,
  SET_STATIC_FILTER: updateStaticFilterQueryString,
  SET_SEARCH_QUERY: updateSearchQueryString,
  SET_SORT: updateSortQueryString
});

/***/ }),

/***/ 5820:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var redux__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(4978);
/* harmony import */ var refx__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(2601);
/* harmony import */ var refx__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(refx__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _effects__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(1569);
/* harmony import */ var _reducer__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(352);
/**
 * External dependencies
 */


/**
 * Internal dependencies
 */



const middlewares = [refx__WEBPACK_IMPORTED_MODULE_0___default()(_effects__WEBPACK_IMPORTED_MODULE_1__/* .default */ .Z)];
const store = (0,redux__WEBPACK_IMPORTED_MODULE_3__/* .createStore */ .MT)(_reducer__WEBPACK_IMPORTED_MODULE_2__/* .default */ .ZP, {}, (0,redux__WEBPACK_IMPORTED_MODULE_3__/* .applyMiddleware */ .md)(...middlewares));
/* harmony default export */ __webpack_exports__["Z"] = (store);

/***/ }),

/***/ 2742:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "xT": function() { return /* binding */ hasError; },
/* harmony export */   "hg": function() { return /* binding */ isLoading; },
/* harmony export */   "p": function() { return /* binding */ response; }
/* harmony export */ });
/**
 * Reducer for recording if the previous search request yielded an error.
 *
 * @param {object} state - Current state.
 * @param {object} action - Dispatched action.
 * @returns {object} Updated state.
 */
function hasError(state = false, action) {
  switch (action.type) {
    case 'MAKE_SEARCH_REQUEST':
    case 'RECORD_SUCCESSFUL_SEARCH_REQUEST':
      return false;

    case 'RECORD_FAILED_SEARCH_REQUEST':
      return true;
  }

  return state;
}
/**
 * Reducer for recording search request state.
 *
 * @param {object} state - Current state.
 * @param {object} action - Dispatched action.
 * @returns {object} Updated state.
 */

function isLoading(state = false, action) {
  switch (action.type) {
    case 'MAKE_SEARCH_REQUEST':
      return true;

    case 'RECORD_SUCCESSFUL_SEARCH_REQUEST':
    case 'RECORD_FAILED_SEARCH_REQUEST':
      return false;
  }

  return state;
}
/**
 * Reducer for recording search results.
 *
 * @param {object} state - Current state.
 * @param {object} action - Dispatched action.
 * @returns {object} Updated state.
 */

function response(state = {}, action) {
  switch (action.type) {
    case 'RECORD_SUCCESSFUL_SEARCH_REQUEST':
      {
        // A more recent response has already been saved.
        if ('requestId' in state && 'requestId' in action.response && state.requestId > action.response.requestId) {
          return state;
        }

        const newState = { ...action.response
        }; // For paginated results, merge previous search results with new search results.

        if (action.options.pageHandle) {
          newState.aggregations = { ...('aggregations' in state && !Array.isArray(state) ? state.aggregations : {}),
            ...(!Array.isArray(newState.aggregations) ? newState.aggregations : {})
          };
          newState.results = [...('results' in state ? state.results : []), ...newState.results];
        } // To prevent our interface from erroneously rendering a "no result" search results page when
        // we actually have results, override the total if the size of our results exceed the `response.total` value.


        if (Array.isArray(newState.results) && newState.results.length > newState.total) {
          newState.total = newState.results.length;
        }

        return newState;
      }
  }

  return state;
}

/***/ }),

/***/ 3714:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "w": function() { return /* binding */ isHistoryNavigation; }
/* harmony export */ });
/**
 * Returns true if the query string change was performed by a history navigation.
 *
 * @param {object} state - Current state.
 * @param {object} action - Dispatched action.
 * @returns {object} Updated state.
 */
function isHistoryNavigation(state = false, action) {
  switch (action.type) {
    case 'INITIALIZE_QUERY_VALUES':
      // Triggered by SearchApp.handleHistoryNavigation.
      return action.isHistoryNavigation;

    case 'SET_SEARCH_QUERY':
    case 'SET_SORT':
    case 'CLEAR_FILTERS':
    case 'SET_FILTER':
      // A query string update is invoked to the window, creating a history state.
      // In other words, the query string change was performed by UI interaction.
      // It was *not* performed by a history navigation.
      return action.propagateToWindow ? false : state;
  }

  return state;
}

/***/ }),

/***/ 352:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony import */ var redux__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(4978);
/* harmony import */ var _api__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(2742);
/* harmony import */ var _query_string__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(2);
/* harmony import */ var _server_options__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(7946);
/* harmony import */ var _history__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(3714);
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */






/* harmony default export */ __webpack_exports__["ZP"] = ((0,redux__WEBPACK_IMPORTED_MODULE_2__/* .combineReducers */ .UY)({
  filters: _query_string__WEBPACK_IMPORTED_MODULE_0__/* .filters */ .u8,
  staticFilters: _query_string__WEBPACK_IMPORTED_MODULE_0__/* .staticFilters */ .OY,
  hasError: _api__WEBPACK_IMPORTED_MODULE_3__/* .hasError */ .xT,
  isLoading: _api__WEBPACK_IMPORTED_MODULE_3__/* .isLoading */ .hg,
  isHistoryNavigation: _history__WEBPACK_IMPORTED_MODULE_4__/* .isHistoryNavigation */ .w,
  response: _api__WEBPACK_IMPORTED_MODULE_3__/* .response */ .p,
  searchQuery: _query_string__WEBPACK_IMPORTED_MODULE_0__/* .searchQuery */ .w4,
  serverOptions: _server_options__WEBPACK_IMPORTED_MODULE_1__/* .serverOptions */ .M,
  sort: _query_string__WEBPACK_IMPORTED_MODULE_0__/* .sort */ .DY
}));

/***/ }),

/***/ 2:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "w4": function() { return /* binding */ searchQuery; },
/* harmony export */   "DY": function() { return /* binding */ sort; },
/* harmony export */   "u8": function() { return /* binding */ filters; },
/* harmony export */   "OY": function() { return /* binding */ staticFilters; }
/* harmony export */ });
/* harmony import */ var _lib_constants__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(9379);
/* harmony import */ var _lib_filters__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(8033);
/**
 * Internal dependencies
 */


/**
 * Reducer for keeping track of the user's inputted search query
 *
 * @param {object} state - Current state.
 * @param {object} action - Dispatched action.
 * @returns {object} Updated state.
 */

function searchQuery(state = null, action) {
  switch (action.type) {
    case 'SET_SEARCH_QUERY':
      return action.query;

    case 'CLEAR_QUERY_VALUES':
      return null;
  }

  return state;
}
/**
 * Reducer for keeping track of the user's selected sort type
 *
 * @param {object} state - Current state.
 * @param {object} action - Dispatched action.
 * @returns {object} Updated state.
 */

function sort(state = null, action) {
  switch (action.type) {
    case 'SET_SORT':
      {
        if (!_lib_constants__WEBPACK_IMPORTED_MODULE_0__/* .VALID_SORT_KEYS.includes */ .kQ.includes(action.sort)) {
          return state;
        }

        return action.sort;
      }

    case 'CLEAR_QUERY_VALUES':
      return null;
  }

  return state;
}
/**
 * Reducer for keeping track of the user's selected filter value
 *
 * @param {object} state - Current state.
 * @param {object} action - Dispatched action.
 * @returns {object} Updated state.
 */

function filters(state = {}, action) {
  switch (action.type) {
    case 'CLEAR_FILTERS':
    case 'CLEAR_QUERY_VALUES':
      return {};

    case 'SET_FILTER':
      if (!(0,_lib_filters__WEBPACK_IMPORTED_MODULE_1__/* .getFilterKeys */ .wP)().includes(action.name) || !Array.isArray(action.value) && typeof action.value !== 'string') {
        return state;
      }

      if (action.value.length === 0) {
        const newState = { ...state
        };
        delete newState[action.name];
        return newState;
      }

      return { ...state,
        [action.name]: typeof action.value === 'string' ? [action.value] : action.value
      };
  }

  return state;
}
/**
 * Reducer for keeping track of the user's selected static filter value
 *
 * @param {object} state - Current state.
 * @param {object} action - Dispatched action.
 * @returns {object} Updated state.
 */

function staticFilters(state = {}, action) {
  switch (action.type) {
    case 'CLEAR_QUERY_VALUES':
      return {};

    case 'SET_STATIC_FILTER':
      if (!(0,_lib_filters__WEBPACK_IMPORTED_MODULE_1__/* .getStaticFilterKeys */ .i3)().includes(action.name)) {
        return state;
      }

      return { ...state,
        [action.name]: action.value
      };
  }

  return state;
}

/***/ }),

/***/ 7946:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "M": function() { return /* binding */ serverOptions; }
/* harmony export */ });
/* harmony import */ var _lib_constants__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(9379);
/**
 * Internal dependencies
 */

/**
 * Reducer for storing server-generated values in the Redux store.
 *
 * @param {object} state - Current state.
 * @returns {object} Updated state.
 */

function serverOptions(state = (() => {
  var _window$SERVER_OBJECT;

  return (_window$SERVER_OBJECT = window[_lib_constants__WEBPACK_IMPORTED_MODULE_0__/* .SERVER_OBJECT_NAME */ .W1]) !== null && _window$SERVER_OBJECT !== void 0 ? _window$SERVER_OBJECT : {};
})()) {
  return state;
}

/***/ }),

/***/ 1248:
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "ck": function() { return /* binding */ getResponse; },
/* harmony export */   "xT": function() { return /* binding */ hasError; },
/* harmony export */   "Qy": function() { return /* binding */ hasNextPage; },
/* harmony export */   "hg": function() { return /* binding */ isLoading; },
/* harmony export */   "uP": function() { return /* binding */ getSearchQuery; },
/* harmony export */   "r$": function() { return /* binding */ getSort; },
/* harmony export */   "Zj": function() { return /* binding */ getFilters; },
/* harmony export */   "Bk": function() { return /* binding */ getStaticFilters; },
/* harmony export */   "en": function() { return /* binding */ hasActiveQuery; },
/* harmony export */   "ZN": function() { return /* binding */ getWidgetOutsideOverlay; },
/* harmony export */   "wI": function() { return /* binding */ isHistoryNavigation; }
/* harmony export */ });
/* unused harmony export hasFilters */
/* harmony import */ var _lib_constants__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(9379);
/* harmony import */ var _lib_filters__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(8033);
/**
 * Internal dependencies
 */


/**
 * Get the stored API response.
 *
 * @param {object} state - Current state.
 * @returns {object} Response object.
 */

function getResponse(state) {
  return state.response;
}
/**
 * Get the hasError flag.
 *
 * @param {object} state - Current state.
 * @returns {boolean} hasError - Whether the API returned an erroneous response.
 */

function hasError(state) {
  return state.hasError;
}
/**
 * Get the hasNextPage flag.
 *
 * @param {object} state - Current state.
 * @returns {boolean} hasNextPage - Whether the API contains a page handle for a subsequent page.
 */

function hasNextPage(state) {
  var _getResponse;

  return !hasError(state) && ((_getResponse = getResponse(state)) === null || _getResponse === void 0 ? void 0 : _getResponse.page_handle);
}
/**
 * Get the isLoading flag.
 *
 * @param {object} state - Current state.
 * @returns {boolean} isLoading - Whether the API request is still loading.
 */

function isLoading(state) {
  return state.isLoading;
}
/**
 * Get the search query.
 *
 * @param {object} state - Current state.
 * @returns {string} searchQuery - The search query entered by the user.
 */

function getSearchQuery(state) {
  return state.searchQuery;
}
/**
 * Get the sort key.
 *
 * @param {object} state - Current state.
 * @param {string?} defaultSort - Default sort order specified via the Customizer.
 * @returns {string} sort - The selected sort key for the search interface.
 */

function getSort(state, defaultSort) {
  // Default non-string defaultSort to 'relevance'
  if (typeof defaultSort !== 'string') {
    defaultSort = _lib_constants__WEBPACK_IMPORTED_MODULE_0__/* .RELEVANCE_SORT_KEY */ .PP;
  }

  return typeof state.sort === 'string' ? state.sort : defaultSort;
}
/**
 * Get the filters.
 *
 * @param {object} state - Current state.
 * @returns {object} filters - An object mapping filter keys and its selected values.
 */

function getFilters(state) {
  return state.filters;
}
/**
 * Get the selected static filters.
 *
 * @param {object} state - Current state.
 * @returns {object} filters - An object mapping filter keys and its selected values.
 */

function getStaticFilters(state) {
  return state.staticFilters;
}
/**
 * Checks if any filters have been selected.
 *
 * @param {object} state - Current state.
 * @returns {object} hasFilters - true if any filter has been selected.
 */

function hasFilters(state) {
  return Object.keys(state.filters).length > 0;
}
/**
 * Checks if any static filters have been selected.
 *
 * @param {object} state - Current state.
 * @returns {object} hasStaticFilters - true if any filter has been selected.
 */

function hasStaticFilters(state) {
  return Object.keys(state.staticFilters).length > 0;
}
/**
 * Checks if there is an active search-related query values.
 *
 * @param {object} state - Current state.
 * @returns {object} hasActiveQuery - true if any search-related query value has been defined.
 */


function hasActiveQuery(state) {
  return getSearchQuery(state) !== null || hasFilters(state) || hasStaticFilters(state) || state.sort !== null;
}
/**
 * This selector combines multiple widgets outside overlay into a single widget consisting only of the `filters` key.
 * After combining the widgets, we the filter out all unselected filter values.
 *
 * This is used to render a single SearchFilters component for all filters selected outside the search overlay.
 *
 * @param {object} state - Redux state tree.
 * @returns {{ filters: object[] }} pseudoWidget - contains `filters`, an array of filter objects selected outside the search overlay.
 */

function getWidgetOutsideOverlay(state) {
  // Both of these values should default to [] when empty; they should never be falsy.
  if (!state.serverOptions.widgets || !state.filters) {
    return {};
  }

  const keys = (0,_lib_filters__WEBPACK_IMPORTED_MODULE_1__/* .getUnselectableFilterKeys */ .do)(state.serverOptions.widgets);
  const filters = Object.keys(state.filters).filter(key => keys.includes(key)).map(_lib_filters__WEBPACK_IMPORTED_MODULE_1__/* .mapFilterKeyToFilter */ .$s);
  return {
    filters
  };
}
/**
 * Returns true if the query string change was performed by a history navigation.
 *
 * @param {object} state - Current state.
 * @returns {boolean} isHistoryNavigation.
 */

function isHistoryNavigation(state) {
  return state.isHistoryNavigation;
}

/***/ })

}]);