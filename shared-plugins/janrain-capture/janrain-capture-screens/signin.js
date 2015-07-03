function janrainCaptureWidgetOnLoad() {
    janrain.events.onCaptureLoginSuccess.addHandler(function(){location.reload(true);});
    janrain.events.onCaptureRegistrationSuccess.addHandler(function(){location.reload(true);});
    janrain.capture.ui.start();
}
