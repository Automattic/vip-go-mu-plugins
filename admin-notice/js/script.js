function tryGetNoticeContainer(currentElement) {
    if (currentElement === null || currentElement.hasAttribute(dismissal_data.data_attribute)) {
        return currentElement;
    }
    return tryGetNoticeContainer(currentElement.parentElement);
}

function persistDismissedNotice(containerElement) {
    const dismissIdentifier = containerElement && containerElement.getAttribute(dismissal_data.data_attribute);
    if (dismissIdentifier) {
        var formData = new FormData();

        formData.append('_ajax_nonce', dismissal_data.nonce);
        formData.append('action', 'dismiss_vip_notice');
        formData.append(dismissal_data.identifier_key, dismissIdentifier);

        fetch(ajaxurl, {
            method: "POST",
            body: formData,
        });
    }
}

function onDismissed(event) {
    const noticeContainer = tryGetNoticeContainer(event && event.target);
    if (noticeContainer) {
        persistDismissedNotice(noticeContainer);
    }
}

function registerDismissHooks() {
    const notices = document.getElementsByClassName("vip-notice");
    for (const notice of notices) {
        // Hooking up on the event caused by the core implementation of is-dismissible class, which generates dismiss button.
        notice.addEventListener("DOMNodeRemoved", onDismissed);
    }
}

window.onload = (function (oldLoad) {
    return function () {
        oldLoad && oldLoad();
        registerDismissHooks();
    }
})(window.onload)
