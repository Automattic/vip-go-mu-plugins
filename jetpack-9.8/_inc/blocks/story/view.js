!function(e,t){for(var n in t)e[n]=t[n]}(window,function(e){var t={};function n(r){if(t[r])return t[r].exports;var c=t[r]={i:r,l:!1,exports:{}};return e[r].call(c.exports,c,c.exports,n),c.l=!0,c.exports}return n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var c in e)n.d(r,c,function(t){return e[t]}.bind(null,c));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="",n(n.s=456)}({0:function(e,t){!function(){e.exports=this.wp.element}()},1:function(e,t){!function(){e.exports=this.wp.i18n}()},10:function(e,t){!function(){e.exports=this.wp.data}()},104:function(e,t,n){"use strict";e.exports=function(e){var t,n={};return function e(t,n){var r;if(Array.isArray(n))for(r=0;r<n.length;r++)e(t,n[r]);else for(r in n)t[r]=(t[r]||[]).concat(n[r])}(n,e),(t=function(e){return function(t){return function(r){var c,i,a=n[r.type],l=t(r);if(a)for(c=0;c<a.length;c++)(i=a[c](r,e))&&e.dispatch(i);return l}}}).effects=n,t}},107:function(e,t){function n(t){return"function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?e.exports=n=function(e){return typeof e}:e.exports=n=function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},n(t)}e.exports=n},108:function(e,t){function n(t,r){return e.exports=n=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e},n(t,r)}e.exports=n},12:function(e,t){e.exports=function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}},13:function(e,t,n){var r=n(107),c=n(6);e.exports=function(e,t){return!t||"object"!==r(t)&&"function"!=typeof t?c(e):t}},131:function(e,t,n){var r=n(78);e.exports=function(e){if(Array.isArray(e))return r(e)}},132:function(e,t){e.exports=function(e){if("undefined"!=typeof Symbol&&Symbol.iterator in Object(e))return Array.from(e)}},133:function(e,t){e.exports=function(){throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}},14:function(e,t){function n(t){return e.exports=n=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)},n(t)}e.exports=n},15:function(e,t,n){var r=n(108);e.exports=function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),t&&r(e,t)}},16:function(e,t){function n(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}e.exports=function(e,t,r){return t&&n(e.prototype,t),r&&n(e,r),e}},165:function(e,t,n){"use strict";var r=Object.assign||function(e){for(var t,n=1;n<arguments.length;n++)for(var r in t=arguments[n])Object.prototype.hasOwnProperty.call(t,r)&&(e[r]=t[r]);return e};Object.defineProperty(t,"__esModule",{value:!0}),t.default=function(e){var t=e.size,n=void 0===t?24:t,c=e.onClick,i=(e.icon,e.className),l=function(e,t){var n={};for(var r in e)0<=t.indexOf(r)||Object.prototype.hasOwnProperty.call(e,r)&&(n[r]=e[r]);return n}(e,["size","onClick","icon","className"]),o=["gridicon","gridicons-fullscreen",i,!1,!1,!1].filter(Boolean).join(" ");return a.default.createElement("svg",r({className:o,height:n,width:n,onClick:c},l,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24"}),a.default.createElement("g",null,a.default.createElement("path",{d:"M21 3v6h-2V6.41l-3.29 3.3-1.42-1.42L17.59 5H15V3zM3 3v6h2V6.41l3.29 3.3 1.42-1.42L6.41 5H9V3zm18 18v-6h-2v2.59l-3.29-3.29-1.41 1.41L17.59 19H15v2zM9 21v-2H6.41l3.29-3.29-1.41-1.42L5 17.59V15H3v6z"})))};var c,i=n(19),a=(c=i)&&c.__esModule?c:{default:c};e.exports=t.default},176:function(e,t,n){"use strict";n.d(t,"a",(function(){return yt}));var r={};n.r(r),n.d(r,"setMuted",(function(){return f})),n.d(r,"setPlaying",(function(){return p})),n.d(r,"showSlide",(function(){return m})),n.d(r,"slideReady",(function(){return b})),n.d(r,"setCurrentSlideProgress",(function(){return y})),n.d(r,"resetCurrentSlideProgress",(function(){return v})),n.d(r,"setCurrentSlideEnded",(function(){return g})),n.d(r,"setFullscreen",(function(){return O})),n.d(r,"setEnded",(function(){return h})),n.d(r,"init",(function(){return E})),n.d(r,"setBuffering",(function(){return j}));var c={};n.r(c),n.d(c,"isPlayerReady",(function(){return S})),n.d(c,"getCurrentSlideIndex",(function(){return w})),n.d(c,"getCurrentSlideProgress",(function(){return x})),n.d(c,"getCurrentSlideProgressPercentage",(function(){return k})),n.d(c,"isPlaying",(function(){return _})),n.d(c,"isMuted",(function(){return P})),n.d(c,"isBuffering",(function(){return N})),n.d(c,"getCurrentMediaElement",(function(){return C})),n.d(c,"getCurrentMediaDuration",(function(){return I})),n.d(c,"hasCurrentSlideEnded",(function(){return R})),n.d(c,"isCurrentSlideReady",(function(){return T})),n.d(c,"getPreviousSlideMediaElement",(function(){return M})),n.d(c,"isFullscreen",(function(){return L})),n.d(c,"hasEnded",(function(){return A})),n.d(c,"getSettings",(function(){return z})),n.d(c,"getSlideCount",(function(){return D}));var i=n(7),a=n.n(i),l=n(35),o=n.n(l),u=n(0),s=n(10),d=n(25);n(310);function f(e,t){return{type:"SET_MUTED",value:t,playerId:e}}function p(e,t){return{type:"SET_PLAYING",value:t,playerId:e}}function m(e,t){return{type:"SHOW_SLIDE",index:t,playerId:e}}function b(e,t,n){return{type:"SLIDE_READY",mediaElement:t,duration:n,playerId:e}}function y(e,t){return{type:"SET_CURRENT_SLIDE_PROGRESS",value:t,playerId:e}}function v(e){return{type:"RESET_CURRENT_SLIDE_PROGRESS",playerId:e}}function g(e){return{type:"SET_CURRENT_SLIDE_ENDED",playerId:e}}function O(e,t){return{type:"SET_FULLSCREEN",playerId:e,fullscreen:t}}function h(e){return{type:"ENDED",playerId:e}}function E(e){var t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{};return{type:"INIT",playerId:e,settings:t}}function j(e,t){return{type:"SET_BUFFERING",value:t,playerId:e}}function S(e,t){return!!e[t]}function w(e,t){return e[t].currentSlide.index}function x(e,t){return e[t].currentSlide.progress}function k(e,t){var n=e[t].currentSlide.progress.currentTime,r=e[t].currentSlide.progress.duration,c=Math.round(100*n/r);return c>=100?100:c}function _(e,t){return e[t].playing}function P(e,t){return e[t].muted}function N(e,t){return e[t].buffering}function C(e,t){return e[t].currentSlide.mediaElement}function I(e,t){return e[t].currentSlide.duration}function R(e,t){return e[t].currentSlide.ended}function T(e,t){return e[t].currentSlide.ready}function M(e,t){var n;return null===(n=e[t].previousSlide)||void 0===n?void 0:n.mediaElement}function L(e,t){return e[t].fullscreen}function A(e,t){return e[t].ended}function z(e,t){return e[t].settings}function D(e,t){return e[t].settings.slideCount}var F=n(20),U=n.n(F),B=n(104),H=n.n(B),q=n(3),G=function(e){return e&&e.src&&"video"===e.tagName.toLowerCase()};function V(e,t){var n=t.getState,r=e.playerId,c=P(n(),r),i=_(n(),r),a=C(n(),r),l=M(n(),r),o=z(n(),r);G(l)&&(l.currentTime=0,l.onwaiting=null,l.onplaying=null,l.pause()),G(a)&&(c!==a.muted&&(a.muted=c,c||(a.volume=o.volume)),i?a.play():a.pause())}function W(e,t){var n=t.getState,r=t.dispatch,c=e.playerId,i=T(n(),c),l=_(n(),c),o=x(n(),c);if(clearTimeout(o.timeout),l&&i){var u=C(n(),c),s=I(n(),c),d=o.lastUpdate?Date.now()-o.lastUpdate:100,f=G(u)?u.currentTime:o.currentTime+d/1e3;if(f>=s){r(g(c));var p=D(n(),c);w(n(),c)===p-1&&r(h(c))}else r(y(c,{timeout:setTimeout((function(){return W(e,t)}),100),lastUpdate:Date.now(),duration:s,currentTime:f}))}else o.lastUpdate&&r(y(c,a()({},o,{lastUpdate:null})))}var K={SET_PLAYING:[W,V],SLIDE_READY:[function(e,t){var n=t.getState,r=t.dispatch,c=e.playerId,i=C(n(),c);if(G(i)){var a=x(n(),c);0===i.currentTime&&a.currentTime>0&&(i.currentTime=a.currentTime),i.onwaiting=function(){return r(j(c,!0))},i.onplaying=function(){return r(j(c,!1))}}},W,V],SET_MUTED:V,SHOW_SLIDE:V};var Y=n(4),J=n.n(Y),Q={currentTime:0,duration:null,timeout:null,lastUpdate:null},$={progress:Q,index:0,mediaElement:null,duration:null,ended:!1,ready:!1},X={slideCount:0,currentSlide:$,previousSlide:null,muted:!1,playing:!1,ended:!1,buffering:!1,fullscreen:!1,settings:{imageTime:5,startMuted:!1,playInFullscreen:!0,playOnNextSlide:!0,playOnLoad:!1,exitFullscreenOnEnd:!0,loadInFullscreen:!1,blurredBackground:!0,showSlideCount:!1,showProgressBar:!0,shadowDOM:{enabled:!0,mode:"open",globalStyleElements:'#jetpack-block-story-css, link[href*="jetpack/_inc/blocks/story/view.css"]'},defaultAspectRatio:.5625,cropUpTo:.2,volume:.8,maxBullets:7,maxBulletsFullscreen:14}};function Z(){var e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:X,t=arguments.length>1?arguments[1]:void 0;switch(t.type){case"SHOW_SLIDE":var n=e.currentSlide===t.index+1;return a()({},e,{currentSlide:a()({},$,{index:t.index}),previousSlide:e.currentSlide,playing:n?e.settings.playOnNextSlide:e.playing});case"SLIDE_READY":return a()({},e,{buffering:!1,currentSlide:a()({},e.currentSlide,{mediaElement:t.mediaElement,duration:t.duration,ready:!0}),previousSlide:null});case"SET_CURRENT_SLIDE_PROGRESS":return a()({},e,{currentSlide:a()({},e.currentSlide,{progress:t.value})});case"SET_CURRENT_SLIDE_ENDED":return a()({},e,{currentSlide:a()({},e.currentSlide,{ended:!0})});case"RESET_CURRENT_SLIDE_PROGRESS":return a()({},e,{currentSlide:a()({},e.currentSlide,{progress:a()({},Q)})});case"SET_MUTED":return a()({},e,{muted:t.value});case"SET_PLAYING":var r=t.value&&e.ended;return a()({},e,{playing:t.value,buffering:!!t.value&&e.buffering,fullscreen:!e.playing&&t.value?e.settings.playInFullscreen:e.fullscreen,ended:!r&&e.ended,currentSlide:r?a()({},$,{index:0}):e.currentSlide,previousSlide:r?null:e.previousSlide});case"SET_BUFFERING":return a()({},e,{buffering:t.value});case"SET_FULLSCREEN":return a()({},e,{fullscreen:t.fullscreen,playing:!(e.fullscreen&&!t.fullscreen&&e.settings.playInFullscreen)&&e.playing});case"INIT":var c=Object(q.merge)({},e.settings,t.settings);return a()({},e,{settings:c,playing:c.playOnLoad,fullscreen:c.loadInFullscreen});case"ENDED":return a()({},e,{currentSlide:a()({},$,{index:e.settings.slideCount-1,progress:a()({},Q,{currentTime:100,duration:100})}),ended:!0,playing:!1,fullscreen:!e.settings.exitFullscreenOnEnd})}return e}var ee,te,ne,re,ce,ie=Object(s.registerStore)("jetpack/story/player",{actions:r,reducer:function(){var e=arguments.length>0&&void 0!==arguments[0]?arguments[0]:{},t=arguments.length>1?arguments[1]:void 0;return t.playerId?a()({},e,J()({},t.playerId,Z(e[t.playerId],t))):e},selectors:c});ee=ie,ne=[H()(K)],re=function(){throw new Error("Dispatching while constructing your middleware is not allowed. Other middleware would not be applied to this dispatch.")},ce={getState:ee.getState,dispatch:function(){return re.apply(void 0,arguments)}},te=ne.map((function(e){return e(ce)})),re=q.flowRight.apply(void 0,U()(te))(ee.dispatch),ee.dispatch=re;var ae=n(22),le=n.n(ae),oe=n(9),ue=n.n(oe),se=n(8),de=n.n(se),fe=n(30),pe=n(18),me=n(1),be=n(27),ye=n.n(be);function ve(){return(ve=ye()(regeneratorRuntime.mark((function e(t){var n;return regeneratorRuntime.wrap((function(e){for(;;)switch(e.prev=e.next){case 0:if("img"!==(n=t.tagName.toLowerCase())){e.next=8;break}if(!t.complete){e.next=4;break}return e.abrupt("return");case 4:return e.next=6,new Promise((function(e){t.addEventListener("load",e,{once:!0})}));case 6:e.next=13;break;case 8:if("video"!==n&&"audio"!==n){e.next=13;break}if(t.HAVE_ENOUGH_DATA!==t.readyState){e.next=11;break}return e.abrupt("return");case 11:return e.next=13,new Promise((function(e){t.addEventListener("canplaythrough",e,{once:!0}),t.addEventListener("load",e,{once:!0}),t.HAVE_NOTHING===t.readyState&&t.networkState!==t.NETWORK_LOADING&&t.load()}));case 13:case"end":return e.stop()}}),e)})))).apply(this,arguments)}var ge=n(2);function Oe(e){var t=e.isEllipsis,n=e.disabled,r=e.index,c=e.isSelected,i=e.progress,a=e.onClick,l=n||t,o=null;return t||(o=c?Object(me.sprintf)(Object(me.__)("Slide %d, currently selected","jetpack"),r+1):Object(me.sprintf)(Object(me.__)("Go to slide %d","jetpack"),r+1)),Object(u.createElement)(ge.Button,{role:l?"presentation":"tab",key:r,className:de()("wp-story-pagination-bullet",{"wp-story-pagination-ellipsis":t}),"aria-label":o,"aria-disabled":l||c,onClick:l||c?void 0:a,disabled:l},Object(u.createElement)("div",{className:"wp-story-pagination-bullet-bar"},Object(u.createElement)("div",{className:"wp-story-pagination-bullet-bar-progress",style:{width:"".concat(i,"%")}})))}n(311);var he=function(e){var t=e.className,n=e.size,r=e.label,c=e.isPressed,i=o()(e,["className","size","label","isPressed"]);return Object(u.createElement)("button",le()({type:"button","aria-label":r,"aria-pressed":c,className:de()("jetpack-mdc-icon-button","circle-icon","outlined","bordered",t),style:{width:"".concat(n,"px"),height:"".concat(n,"px")}},i))},Ee=function(e){var t=e.className,n=e.size,r=void 0===n?24:n,c=e.label,i=e.isPressed,a=o()(e,["className","size","label","isPressed"]);return Object(u.createElement)("button",le()({type:"button","aria-label":c,"aria-pressed":i,className:de()("jetpack-mdc-icon-button",t),style:{width:"".concat(r,"px"),height:"".concat(r,"px")}},a))},je=n(26),Se=function(e){var t=e.children,n=e.size;return Object(je.a)(t,n,n)},we=function(e){var t=e.size;return Object(u.createElement)(Se,{size:t},Object(u.createElement)(ge.Path,{d:"M8 5v14l11-7z"}))},xe=function(e){var t=e.size;return Object(u.createElement)(Se,{size:t},Object(u.createElement)(ge.Path,{d:"M6 19h4V5H6v14zm8-14v14h4V5h-4z"}))},ke=function(e){var t=e.size;return Object(u.createElement)(Se,{size:t},Object(u.createElement)(ge.Path,{d:"M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"}))},_e=function(e){var t=e.size;return Object(u.createElement)(Se,{size:t},Object(u.createElement)(ge.Path,{d:"M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"}))},Pe=function(e){var t=e.size;return Object(u.createElement)(Se,{size:t},Object(u.createElement)(ge.Path,{d:"M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"}))},Ne=function(e){var t=e.size;return Object(u.createElement)(Se,{size:t},Object(u.createElement)(ge.Path,{d:"M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"}))},Ce=function(e){var t=e.size;return Object(u.createElement)(Se,{size:t},Object(u.createElement)(ge.Path,{d:"M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"}))};function Ie(e){var t=e.playing,n=e.muted,r=e.onPlayPressed,c=e.onMutePressed,i=e.showMute;return Object(u.createElement)("div",{className:"wp-story-controls"},Object(u.createElement)(Ee,{isPressed:t,label:t?Object(me.__)("pause","jetpack"):Object(me.__)("play","jetpack"),onClick:r},t?Object(u.createElement)(xe,null):Object(u.createElement)(we,null)),i&&Object(u.createElement)(Ee,{isPressed:n,label:n?Object(me.__)("unmute","jetpack"):Object(me.__)("mute","jetpack"),onClick:c},n?Object(u.createElement)(Pe,null):Object(u.createElement)(_e,null)))}function Re(e){var t=e.fullscreen,n=e.onExitFullscreen,r=e.siteIconUrl,c=e.storyTitle;return t?Object(u.createElement)("div",{className:"wp-story-meta"},Object(u.createElement)("div",{className:"wp-story-icon"},Object(u.createElement)("img",{alt:Object(me.__)("Site icon","jetpack"),src:r,width:"40",height:"40"})),Object(u.createElement)("div",null,Object(u.createElement)("div",{className:"wp-story-title"},c)),Object(u.createElement)(Ee,{className:"wp-story-exit-fullscreen",label:Object(me.__)("Exit Fullscreen","jetpack"),onClick:n},Object(u.createElement)(ke,null))):null}var Te=n(165),Me=n.n(Te);function Le(e){var t=e.ended,n=e.hasPrevious,r=e.hasNext,c=e.onNextSlide,i=e.onPreviousSlide,a=e.icon,l=e.slideCount,o=e.showSlideCount,s=Object(u.useCallback)((function(e){t||(e.stopPropagation(),i())}),[i,t]),d=Object(u.useCallback)((function(e){t||(e.stopPropagation(),c())}),[c,t]);return Object(u.createElement)("div",{className:"wp-story-overlay"},o&&Object(u.createElement)("div",{className:"wp-story-embed-icon"},a,Object(u.createElement)("span",null,l)),!o&&Object(u.createElement)("div",{className:"wp-story-embed-icon-expand"},Object(u.createElement)(Me.a,{role:"img"})),n&&Object(u.createElement)("div",{className:"wp-story-prev-slide",onClick:s},Object(u.createElement)(he,{size:44,label:Object(me.__)("Previous Slide","jetpack"),className:"outlined-w"},Object(me.isRTL)()?Object(u.createElement)(Ce,{size:24}):Object(u.createElement)(Ne,{size:24}))),r&&Object(u.createElement)("div",{className:"wp-story-next-slide",onClick:d},Object(u.createElement)(he,{size:44,label:Object(me.__)("Next Slide","jetpack"),className:"outlined-w"},Object(me.isRTL)()?Object(u.createElement)(Ne,{size:24}):Object(u.createElement)(Ce,{size:24}))))}function Ae(e){var t=e.currentMedia,n=t&&"image"===t.type?t.url:null;return Object(u.createElement)("div",{className:"wp-story-background"},Object(u.createElement)("div",{className:"wp-story-background-image",style:{backgroundImage:n?'url("'.concat(n,'")'):"none"}}),Object(u.createElement)("div",{className:"wp-story-background-blur"}),Object(u.createElement)(ge.SVG,{version:"1.1",xmlns:"http://www.w3.org/2000/svg",width:"0",height:"0"},Object(u.createElement)("filter",{id:"gaussian-blur-18"},Object(u.createElement)("feGaussianBlur",{stdDeviation:"18"}))))}var ze=function(){return Object(u.createElement)("div",{className:"wp-story-loading-spinner"},Object(u.createElement)("div",{className:"wp-story-loading-spinner__outer"},Object(u.createElement)("div",{className:"wp-story-loading-spinner__inner"})))},De=function(e){var t=e.title,n=e.alt,r=e.className,c=e.id,i=e.mediaRef,a=e.mime,l=e.sizes,o=e.srcset,s=e.url;return Object(u.createElement)("img",{ref:i,"data-id":c,"data-mime":a,title:t,alt:n,src:s,className:de()("wp-story-image","wp-image-".concat(c),r),srcSet:o,sizes:l})},Fe=function(e){var t=e.title,n=e.className,r=e.id,c=e.mediaRef,i=e.mime,a=e.url,l=e.poster;return Object(u.createElement)("video",{className:de()("wp-story-video","intrinsic-ignore","wp-video-".concat(r),n),ref:c,"data-id":r,title:t,type:i,src:a,poster:l,tabIndex:-1,preload:"auto",playsInline:!0})},Ue=function(e){var t=e.targetAspectRatio,n=e.cropUpTo,r=e.type,c=e.width,i=e.height,a=o()(e,["targetAspectRatio","cropUpTo","type","width","height"]),l=null;if(c&&i){var s=c/i;if(s>=t)s>t/(1-n)||(l="wp-story-crop-wide");else s<t*(1-n)||(l="wp-story-crop-narrow")}var d="video"===r||(a.mime||"").startsWith("video/");return Object(u.createElement)("figure",null,d?Object(u.createElement)(Fe,le()({},a,{className:l})):Object(u.createElement)(De,le()({},a,{className:l})))},Be=function(e){var t=e.playerId,n=e.media,r=e.index,c=e.playing,i=e.uploading,a=e.settings,l=e.targetAspectRatio,o=Object(s.useSelect)((function(e){return{currentSlideIndex:e("jetpack/story/player").getCurrentSlideIndex(t),buffering:e("jetpack/story/player").isBuffering(t)}}),[]),d=o.currentSlideIndex,f=o.buffering,p=Object(s.useDispatch)("jetpack/story/player").slideReady,m=r===d,b=Object(u.useRef)(null),y=Object(u.useState)(!1),v=ue()(y,2),g=v[0],O=v[1],h=Object(u.useState)(!0),E=ue()(h,2),j=E[0],S=E[1];return Object(u.useEffect)((function(){if(m&&!j){var e=b.current&&b.current.src&&"video"===b.current.tagName.toLowerCase()?b.current:null;p(t,b.current,e?e.duration:a.imageTime)}}),[m,j]),Object(u.useEffect)((function(){r<=d+(c?1:0)&&O(!0)}),[c,d]),Object(u.useLayoutEffect)((function(){b.current&&function(e){return ve.apply(this,arguments)}(b.current).then((function(){S(!1)}))}),[g,i]),Object(u.createElement)(u.Fragment,null,m&&(j||i||f)&&Object(u.createElement)("div",{className:de()("wp-story-slide","is-loading",{transparent:c&&f,"semi-transparent":i||!c&&f})},Object(u.createElement)(ze,null)),Object(u.createElement)("div",{role:"figure",className:"wp-story-slide",style:{display:m&&!j?"block":"none"},tabIndex:m?0:-1},g&&Object(u.createElement)(Ue,le()({},n,{targetAspectRatio:l,cropUpTo:a.cropUpTo,index:r,mediaRef:b}))))},He=n(86),qe=function(e){var t=e.key,n=e.playerId,r=e.index,c=e.disabled,i=e.isSelected,a=e.onClick,l=Object(s.useSelect)((function(e){return e("jetpack/story/player").getCurrentSlideProgressPercentage(n)}),[]);return Object(u.createElement)(Oe,{key:t,index:r,progress:l,disabled:c,isSelected:i,onClick:a})},Ge=function(e){var t,n=e.playerId,r=e.slides,c=e.disabled,i=e.onSlideSeek,a=e.maxBullets,l=Object(s.useSelect)((function(e){return{currentSlideIndex:e("jetpack/story/player").getCurrentSlideIndex(n)}}),[]).currentSlideIndex,o=Math.min(r.length,a),d=Math.floor(o/2),f=0,p=r.length-1;return r.length<=a||l<d?(t=l,p=o-1):l>=r.length-d?(t=l-r.length+o,f=r.length-o):(t=d,f=l-d,p=l+d),Object(u.createElement)("div",{className:"wp-story-pagination wp-story-pagination-bullets",role:"tablist"},f>0&&Object(u.createElement)(Oe,{key:"bullet-0",index:f-1,progress:100,isEllipsis:!0}),Object(q.range)(1,o+1).map((function(e,r){var a=r+f,o=null;if(a<l)o=100;else{if(!(a>l))return Object(u.createElement)(qe,{playerId:n,key:"bullet-".concat(r),index:a,disabled:c,isSelected:t===r,onClick:function(){return i(a)}});o=0}return Object(u.createElement)(Oe,{key:"bullet-".concat(r),index:a,progress:o,disabled:c,isSelected:t===r,onClick:function(){return i(a)}})})),p<r.length-1&&Object(u.createElement)(Oe,{key:"bullet-".concat(o+1),index:p+1,progress:0,isEllipsis:!0}))};function Ve(e){var t,n,r,c,i=e.id,a=e.slides,l=e.metadata,o=e.disabled,d=Object(s.useDispatch)("jetpack/story/player"),f=d.setFullscreen,p=d.setEnded,m=d.setPlaying,b=d.setMuted,y=d.showSlide,v=Object(s.useSelect)((function(e){var t=e("jetpack/story/player"),n=t.getCurrentSlideIndex,r=t.getSettings,c=t.hasCurrentSlideEnded,a=t.hasEnded,l=t.isFullscreen,o=t.isMuted;return{playing:(0,t.isPlaying)(i),muted:o(i),currentSlideIndex:n(i),currentSlideEnded:c(i),ended:a(i),fullscreen:l(i),settings:r(i)}}),[i]),g=v.playing,O=v.muted,h=v.currentSlideIndex,E=v.currentSlideEnded,j=v.ended,S=v.fullscreen,w=v.settings,x=Object(u.useRef)(),k=Object(u.useState)(null),_=ue()(k,2),P=_[0],N=_[1],C=Object(pe.useResizeObserver)(),I=ue()(C,2),R=I[0],T=I[1],M=T.width,L=T.height,A=Object(u.useState)(w.defaultAspectRatio),z=ue()(A,2),D=z[0],F=z[1],U=Object(q.some)(a,(function(e){return Object(fe.isBlobURL)(e.url)})),B=function(e){y(i,e)},H=Object(u.useCallback)((function(){o||S||w.playInFullscreen&&!g&&m(i,!0)}),[g,o,S]),G=Object(u.useCallback)((function(){h>0&&B(h-1)}),[h]),V=Object(u.useCallback)((function(){h<a.length-1?B(h+1):p(i)}),[h,a]),W=Object(u.useCallback)((function(){f(i,!1)}),[]);return Object(u.useEffect)((function(){o&&g&&m(i,!1)}),[o,g]),Object(u.useEffect)((function(){g&&E&&V()}),[g,E]),Object(u.useLayoutEffect)((function(){if(x.current){var e=Math.round(w.defaultAspectRatio*x.current.offsetHeight);S&&(e=Math.abs(1-e/M)<w.cropUpTo?M:e),N(e)}}),[M,L,S]),Object(u.useLayoutEffect)((function(){P&&x.current&&x.current.offsetHeight>0&&F(P/x.current.offsetHeight)}),[P]),t=S?[Object(me.__)("You are currently playing a story.","jetpack"),g?Object(me.__)("Press space to pause.","jetpack"):Object(me.__)("Press space to play.","jetpack"),Object(me.__)("Press escape to exit.","jetpack")].join(" "):Object(me.__)("Play story","jetpack"),n=o?"presentation":S?"dialog":"button",Object(u.createElement)("div",{className:"wp-story-display-contents"},R,Object(u.createElement)("div",{role:n,"aria-label":t,tabIndex:S?-1:0,className:de()("wp-story-container",{"wp-story-with-controls":!o&&!S&&!w.playInFullscreen,"wp-story-fullscreen":S,"wp-story-ended":j,"wp-story-disabled":o,"wp-story-clickable":!o&&!S}),style:{maxWidth:"".concat(P,"px")},onClick:H},Object(u.createElement)(Re,le()({},l,{fullscreen:S,onExitFullscreen:W})),Object(u.createElement)("div",{ref:x,className:"wp-story-wrapper"},a.map((function(e,t){return Object(u.createElement)(Be,{playerId:i,key:t,media:e,index:t,playing:!o&&g,uploading:U,settings:w,targetAspectRatio:D})}))),Object(u.createElement)(Le,{icon:He.a,slideCount:a.length,showSlideCount:w.showSlideCount,ended:j,hasPrevious:h>0,hasNext:h<a.length-1,onPreviousSlide:G,onNextSlide:V}),w.showProgressBar&&Object(u.createElement)(Ge,{playerId:i,slides:a,disabled:!S,onSlideSeek:B,maxBullets:S?w.maxBulletsFullscreen:w.maxBullets}),Object(u.createElement)(Ie,{playing:g,muted:O,onPlayPressed:function(){return m(i,!g)},onMutePressed:function(){return b(i,!O)},showMute:(r=h,c=r<a.length?a[r]:null,!!c&&("video"===c.type||(c.mime||"").startsWith("video/")))})),S&&Object(u.createElement)(Ae,{currentMedia:w.blurredBackground&&a.length>h&&a[h]}))}var We=window&&window.Element&&window.Element.prototype.hasOwnProperty("attachShadow");function Ke(e){var t=e.enabled,n=e.delegatesFocus,r=void 0!==n&&n,c=e.mode,i=void 0===c?"open":c,a=e.globalStyleElements,l=void 0===a?[]:a,o=e.adoptedStyleSheets,s=void 0===o?null:o,d=e.mountOnElement,f=void 0===d?null:d,p=e.children,m=Object(u.useState)(null),b=ue()(m,2),y=b[0],v=b[1],g=f||y,O=Object(u.useState)(null),h=ue()(O,2),E=h[0],j=h[1],S="string"==typeof l?U()(document.querySelectorAll(l)):l,w=We&&t&&S.length>0,x=Object(u.useCallback)((function(e){null!==e&&v(e.parentNode)}),[]);if(Object(u.useEffect)((function(){if(g)if(g.shadowRoot)j(g.shadowRoot);else{var e=g.attachShadow({delegatesFocus:r,mode:i});s&&(e.adoptedStyleSheets=s),j(e)}}),[g]),w&&!E)return f?null:Object(u.createElement)("span",{ref:x});var k=Object(u.createElement)(u.Fragment,null,w&&Object(u.createElement)(Ye,{globalStyleElements:S}),p);return w?Object(u.createPortal)(k,E):k}function Ye(e){var t=e.globalStyleElements;return Object(u.createElement)(u.Fragment,null,t.map((function(e,t){var n=e.id,r=e.tagName,c=e.attributes,i=e.innerHTML;return"LINK"===r?Object(u.createElement)("link",{key:n||t,id:n,rel:c.rel.value,href:c.href.value}):"STYLE"===r?Object(u.createElement)("style",{key:n||t,id:n},i):void 0})))}var Je=n(12),Qe=n.n(Je),$e=n(16),Xe=n.n($e),Ze=n(13),et=n.n(Ze),tt=n(14),nt=n.n(tt),rt=n(15),ct=n.n(rt);function it(e){var t=e.overlayClassName,n=e.children,r=e.className,c=e.focusOnMount,i=e.shouldCloseOnEsc,a=void 0===i||i,l=e.onRequestClose,o=e.onKeyDown,s=e.modalRef;var f,p,m,b,y,v=Object(pe.useFocusOnMount)(c),g=Object(pe.useConstrainedTabbing)(),O=Object(pe.useFocusReturn)();return Object(u.createElement)("div",{className:t,onKeyDown:function(e){a&&e.keyCode===d.ESCAPE&&(e.stopPropagation(),l&&l(e)),e.target&&"button"===e.target.tagName.toLowerCase()&&e.keyCode===d.SPACE||o&&o(e)}},Object(u.createElement)("div",{className:r,ref:(f=[g,O,v,s],p=Object(u.useRef)(null),m=Object(u.useRef)(!1),b=Object(u.useRef)(f),y=Object(u.useRef)(f),y.current=f,Object(u.useLayoutEffect)((function(){f.forEach((function(e,t){var n=b.current[t];"function"==typeof e&&e!==n&&!1===m.current&&(n(null),e(p.current))})),b.current=f}),f),Object(u.useLayoutEffect)((function(){m.current=!1})),Object(u.useCallback)((function(e){p.current=e,m.current=!0,(e?y.current:b.current).forEach((function(t){"function"==typeof t?t(e):t&&t.hasOwnProperty("current")&&(t.current=e)}))}),[]))},n))}var at=new Set(["alert","status","log","marquee","timer"]),lt=[],ot=!1;function ut(e){if(!ot){var t=document.body.children;Object(q.forEach)(t,(function(t){t!==e&&function(e){var t=e.getAttribute("role");return!("SCRIPT"===e.tagName||e.hasAttribute("aria-hidden")||e.hasAttribute("aria-live")||at.has(t))}(t)&&(t.setAttribute("aria-hidden","true"),lt.push(t))})),ot=!0}}var st,dt=0,ft=function(e){function t(e){var n;return Qe()(this,t),(n=et()(this,nt()(t).call(this,e))).prepareDOM(),n}return ct()(t,e),Xe()(t,[{key:"componentDidMount",value:function(){1===++dt&&this.openFirstModal()}},{key:"componentWillUnmount",value:function(){0===--dt&&this.closeLastModal(),this.cleanDOM()}},{key:"prepareDOM",value:function(){st||(st=document.createElement("div"),document.body.appendChild(st)),this.node=document.createElement("div"),st.appendChild(this.node)}},{key:"cleanDOM",value:function(){st.removeChild(this.node)}},{key:"openFirstModal",value:function(){ut(st)}},{key:"closeLastModal",value:function(){ot&&(Object(q.forEach)(lt,(function(e){e.removeAttribute("aria-hidden")})),lt=[],ot=!1)}},{key:"render",value:function(){var e=this.props,t=e.children,n=e.isOpened,r=e.shadowDOM,c=o()(e,["children","isOpened","shadowDOM"]);return Object(u.createElement)(Ke,le()({},r,{mountOnElement:this.node}),n&&Object(u.createElement)(it,c,t))}}]),t}(u.Component);ft.defaultProps={shouldCloseOnEsc:!0,isOpened:!1,focusOnMount:!0};var pt=Object(pe.withInstanceId)(ft),mt=/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(window.navigator.userAgent);function bt(e){var t=e.className,n=e.fullscreenClassName,r=e.bodyFullscreenClassName,c=e.fullscreen,i=e.shadowDOM,a=e.onKeyDown,l=e.onExitFullscreen,o=e.playerQuerySelector,s=e.children,d=Object(u.useRef)(),f=Object(u.useRef)(),p=Object(u.useState)(null),m=ue()(p,2),b=m[0],y=m[1],v=mt&&(document.fullscreenEnabled||document.webkitFullscreenEnabled||document.mozFullScreenEnabled||document.msFullscreenEnabled),g=c&&!v,O=c&&v;return Object(u.useLayoutEffect)((function(){if(v)c?d.current&&function(e,t){if((e.requestFullscreen||e.webkitRequestFullScreen||e.mozRequestFullScreen||e.msRequestFullscreen).call(e),t){document.addEventListener("fullscreenchange",(function e(){document.fullscreenElement||(document.removeEventListener("fullscreenchange",e),t())}))}}(d.current,l):(document.fullscreenElement||document.webkitFullscreenElement||document.mozFullScreenElement||document.msFullScreenElement)&&(document.exitFullscreen||document.webkitExitFullscreen||document.mozCancelFullScreen||document.msExitFullscreen).call(document);else if(c){if(y([document.documentElement.scrollLeft,document.documentElement.scrollTop]),document.body.classList.add(r),document.getElementsByTagName("html")[0].classList.add(r),f.current){var e=f.current.querySelector(o);e&&e.focus()}}else if(document.body.classList.remove(r),document.getElementsByTagName("html")[0].classList.remove(r),b){var t;(t=window).scrollTo.apply(t,U()(b));var n=d.current.querySelector(o);n&&n.focus()}}),[c]),Object(u.createElement)(u.Fragment,null,Object(u.createElement)(Ke,i,Object(u.createElement)("div",{ref:d,className:de()(t,J()({},n,O)),onKeyDown:a},!g&&s)),Object(u.createElement)(pt,{className:de()(t,J()({},n,g)),isOpened:g,onRequestClose:l,shadowDOM:i,onKeyDown:g&&a,focusOnMount:!1,modalRef:f},g&&s))}function yt(e){var t=e.id,n=e.slides,r=e.metadata,c=e.disabled,i=o()(e,["id","slides","metadata","disabled"]),l=Object(u.useMemo)((function(){return t||Math.random().toString(36)}),[t]),f=Object(s.useDispatch)("jetpack/story/player"),p=f.init,m=f.setEnded,b=f.setPlaying,y=f.setFullscreen,v=f.showSlide,g=Object(s.useSelect)((function(e){var t=e("jetpack/story/player"),n=t.getCurrentSlideIndex,r=t.getSettings,c=t.isFullscreen,i=t.isPlayerReady,a=t.isPlaying;return i(l)?{playing:a(l),currentSlideIndex:n(l),isReady:!0,fullscreen:c(l),playerSettings:r(l)}:{isReady:!1}}),[l]),O=g.playing,h=g.currentSlideIndex,E=g.fullscreen,j=g.isReady,S=g.playerSettings;Object(u.useEffect)((function(){j||p(l,a()({slideCount:n.length},i))}),[j,l]);var w=Object(u.useCallback)((function(e){switch(e.keyCode){case d.SPACE:b(l,!O);break;case d.LEFT:h>0&&v(l,h-1);break;case d.RIGHT:h<n.length-1?v(l,h+1):m(l)}}),[l,h,E,O]),x=Object(u.useCallback)((function(){y(l,!1)}),[l]);return j?Object(u.createElement)(bt,{shadowDOM:S.shadowDOM,className:"wp-story-app",fullscreenClassName:"wp-story-fullscreen",bodyFullscreenClassName:"wp-story-in-fullscreen",playerQuerySelector:".wp-story-container",fullscreen:E,onExitFullscreen:x,onKeyDown:w},Object(u.createElement)(Ve,{id:l,slides:n,metadata:r,disabled:c})):null}},18:function(e,t){!function(){e.exports=this.wp.compose}()},182:function(e,t){e.exports=function(e,t){if(null==e)return{};var n,r,c={},i=Object.keys(e);for(r=0;r<i.length;r++)n=i[r],t.indexOf(n)>=0||(c[n]=e[n]);return c}},19:function(e,t){!function(){e.exports=this.React}()},2:function(e,t){!function(){e.exports=this.wp.components}()},20:function(e,t,n){var r=n(131),c=n(132),i=n(79),a=n(133);e.exports=function(e){return r(e)||c(e)||i(e)||a()}},22:function(e,t){function n(){return e.exports=n=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var n=arguments[t];for(var r in n)Object.prototype.hasOwnProperty.call(n,r)&&(e[r]=n[r])}return e},n.apply(this,arguments)}e.exports=n},25:function(e,t){!function(){e.exports=this.wp.keycodes}()},26:function(e,t,n){"use strict";var r=n(0),c=n(2);t.a=function(e){var t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:24,n=arguments.length>2&&void 0!==arguments[2]?arguments[2]:24,i=arguments.length>3&&void 0!==arguments[3]?arguments[3]:"0 0 24 24";return Object(r.createElement)(c.SVG,{xmlns:"http://www.w3.org/2000/svg",width:t,height:n,viewBox:i},Object(r.createElement)(c.Path,{fill:"none",d:"M0 0h24v24H0V0z"}),e)}},27:function(e,t){function n(e,t,n,r,c,i,a){try{var l=e[i](a),o=l.value}catch(u){return void n(u)}l.done?t(o):Promise.resolve(o).then(r,c)}e.exports=function(e){return function(){var t=this,r=arguments;return new Promise((function(c,i){var a=e.apply(t,r);function l(e){n(a,c,i,l,o,"next",e)}function o(e){n(a,c,i,l,o,"throw",e)}l(void 0)}))}}},3:function(e,t){!function(){e.exports=this.lodash}()},30:function(e,t){!function(){e.exports=this.wp.blob}()},310:function(e,t,n){},311:function(e,t,n){},35:function(e,t,n){var r=n(182);e.exports=function(e,t){if(null==e)return{};var n,c,i=r(e,t);if(Object.getOwnPropertySymbols){var a=Object.getOwnPropertySymbols(e);for(c=0;c<a.length;c++)n=a[c],t.indexOf(n)>=0||Object.prototype.propertyIsEnumerable.call(e,n)&&(i[n]=e[n])}return i}},4:function(e,t){e.exports=function(e,t,n){return t in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}},456:function(e,t,n){n(53),e.exports=n(457)},457:function(e,t,n){"use strict";n.r(t);var r=n(7),c=n.n(r),i=n(20),a=n.n(i),l=n(22),o=n.n(l),u=n(0),s=n(55),d=n.n(s),f=n(176);function p(e,t){"string"==typeof e&&(e=document.querySelectorAll(e));var n=e.querySelector(".wp-story-wrapper"),r=e.querySelector(".wp-story-meta"),c=[];n&&n.children.length>0&&(c=function(e){return a()(e.querySelectorAll("li > figure > :first-child")).map((function(e){return{alt:e.getAttribute("alt")||e.getAttribute("title"),mime:e.getAttribute("data-mime")||e.getAttribute("type"),url:e.getAttribute("src"),id:e.getAttribute("data-id"),type:"img"===e.tagName.toLowerCase()?"image":"video",srcset:e.getAttribute("srcset"),sizes:e.getAttribute("sizes")}}))}(n));var i={};r&&r.children.length>0&&(i=function(e){var t=e.querySelector("div:first-child > img"),n=e.querySelector(".wp-story-title"),r=t&&t.src;return{storyTitle:n&&n.innerText,siteIconUrl:r}}(r));var l=function(e){return e.getAttribute("data-id")}(e);Object(u.render)(Object(u.createElement)(f.a,o()({id:l,slides:c,metadata:i,disabled:!1},t)),e)}if("undefined"!=typeof window){var m=Array.from(new URLSearchParams(window.location.search).entries()).filter((function(e){return e[0].startsWith("wp-story-")})).reduce((function(e,t){var n=t[0].replace(/^wp-story-/,"").replace(/-([a-z])/g,(function(e){return e[1].toUpperCase()}));try{e[n]=JSON.parse(t[1])}catch(r){e[n]=JSON.parse('"'.concat(t[1],'"'))}return e}),{});d()((function(){var e=a()(document.querySelectorAll(":not(#debug-bar-wp-query) .wp-story"));e.forEach((function(t){if("true"!==t.getAttribute("data-block-initialized")){var n=null;1===e.length&&(n=c()({},m));var r=t.getAttribute("data-settings");if(r)try{n=c()({},n,{},JSON.parse(r))}catch(i){}p(t,n)}}))}))}},49:function(e,t,n){"object"==typeof window&&window.Jetpack_Block_Assets_Base_Url&&window.Jetpack_Block_Assets_Base_Url.url&&(n.p=window.Jetpack_Block_Assets_Base_Url.url)},53:function(e,t,n){"use strict";n.r(t);n(49)},55:function(e,t){!function(){e.exports=this.wp.domReady}()},6:function(e,t){e.exports=function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}},7:function(e,t,n){var r=n(4);function c(e,t){var n=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),n.push.apply(n,r)}return n}e.exports=function(e){for(var t=1;t<arguments.length;t++){var n=null!=arguments[t]?arguments[t]:{};t%2?c(Object(n),!0).forEach((function(t){r(e,t,n[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(n)):c(Object(n)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(n,t))}))}return e}},78:function(e,t){e.exports=function(e,t){(null==t||t>e.length)&&(t=e.length);for(var n=0,r=new Array(t);n<t;n++)r[n]=e[n];return r}},79:function(e,t,n){var r=n(78);e.exports=function(e,t){if(e){if("string"==typeof e)return r(e,t);var n=Object.prototype.toString.call(e).slice(8,-1);return"Object"===n&&e.constructor&&(n=e.constructor.name),"Map"===n||"Set"===n?Array.from(n):"Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?r(e,t):void 0}}},8:function(e,t,n){var r;
/*!
  Copyright (c) 2017 Jed Watson.
  Licensed under the MIT License (MIT), see
  http://jedwatson.github.io/classnames
*/!function(){"use strict";var n={}.hasOwnProperty;function c(){for(var e=[],t=0;t<arguments.length;t++){var r=arguments[t];if(r){var i=typeof r;if("string"===i||"number"===i)e.push(r);else if(Array.isArray(r)&&r.length){var a=c.apply(null,r);a&&e.push(a)}else if("object"===i)for(var l in r)n.call(r,l)&&r[l]&&e.push(l)}}return e.join(" ")}e.exports?(c.default=c,e.exports=c):void 0===(r=function(){return c}.apply(t,[]))||(e.exports=r)}()},86:function(e,t,n){"use strict";var r=n(0),c=n(2),i=n(26),a=Object(i.a)(Object(r.createElement)(c.G,null,Object(r.createElement)(c.Path,{d:"M17 5a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2h-7a2 2 0 0 1-2-2h9z"}),Object(r.createElement)(c.Path,{d:"M13 4H5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2z"}),Object(r.createElement)(c.Path,{d:"M7 16h8a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"})));t.a=a},9:function(e,t,n){var r=n(91),c=n(92),i=n(79),a=n(93);e.exports=function(e,t){return r(e)||c(e,t)||i(e,t)||a()}},91:function(e,t){e.exports=function(e){if(Array.isArray(e))return e}},92:function(e,t){e.exports=function(e,t){if("undefined"!=typeof Symbol&&Symbol.iterator in Object(e)){var n=[],r=!0,c=!1,i=void 0;try{for(var a,l=e[Symbol.iterator]();!(r=(a=l.next()).done)&&(n.push(a.value),!t||n.length!==t);r=!0);}catch(o){c=!0,i=o}finally{try{r||null==l.return||l.return()}finally{if(c)throw i}}return n}}},93:function(e,t){e.exports=function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}}}));