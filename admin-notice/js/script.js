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

function getDismissedNotices() {
    const cookie_value = getCookie() || '';
    return cookie_value.split(vipAdminNoticeCookieDelimeter);

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

function hideNotice(notice) {
    notice.style.display = 'none';
}

function onDismissClicked(event) {
    const noticeContainer = tryGetNoticeContainer(event?.target);
    if (noticeContainer) {
        hideNotice(noticeContainer);
        emitDismissedCookie(noticeContainer);
    }
}

function registerDismissHooks() {
    const dismissButtons = document.getElementsByClassName("vip-notice-dismiss");
    for (const button of dismissButtons) {
        button.onclick = onDismissClicked;
    }
}

function hideDismissedNotices() {
    const notices = document.getElementsByClassName("vip-notice");
    const dismissed_notices = getDismissedNotices();
    for (const notice of notices) {
        const identifier = notice.getAttribute(vipAdminNoticeDataAttribute);
        if (identifier && dismissed_notices.includes(identifier)) {
            hideNotice(notice);
        }
    }
}

window.onload = (function (oldLoad) {
    return function () {
        oldLoad && oldLoad();
        hideDismissedNotices();
        registerDismissHooks();
    }
})(window.onload)