const vipAdminNoticeDataAttribute = 'data-vip-admin-notice';
const vipAdminNoticeCookieName = 'vip-admin-notice-dismissed'
const vipAdminNoticeCookieDelimeter = '|';

function tryGetNoticeContainer(currentElement) {
    if (currentElement === null || currentElement.hasAttribute(vipAdminNoticeDataAttribute)) {
        return currentElement;
    }
    return tryGetNoticeContainer(currentElement.parentElement);
}

function getCookie() {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${vipAdminNoticeCookieName}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
}

function emitDismissedCookie(containerElement) {
    const dismissIdentifier = containerElement?.getAttribute(vipAdminNoticeDataAttribute);
    if (dismissIdentifier) {
        const previousCookieValue = getCookie();
        const cookieValue = previousCookieValue ? `${previousCookieValue}${vipAdminNoticeCookieDelimeter}${dismissIdentifier}` : dismissIdentifier;

        let expiryDate = new Date();
        expiryDate.setFullYear(expiryDate.getFullYear() + 1)
        document.cookie = `${vipAdminNoticeCookieName}=${cookieValue};expires=${expiryDate.toUTCString()};path = /`;
    }
}

function onDismissClicked(event) {
    const noticeContainer = tryGetNoticeContainer(event?.target);
    if (noticeContainer) {
        noticeContainer.style.display = 'none';
        emitDismissedCookie(noticeContainer);
    }
}

function registerDismissHooks() {
    const dismissButtons = document.getElementsByClassName("vip-notice-dismiss");
    for (const button of dismissButtons) {
        button.onclick = onDismissClicked;
    }
}

window.onload = (function (oldLoad) {
    return function () {
        oldLoad && oldLoad();
        registerDismissHooks();
    }
})(window.onload)