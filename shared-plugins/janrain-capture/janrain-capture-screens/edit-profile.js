function janrainCaptureWidgetOnLoad() {
    janrain.events.onCaptureAccessDenied.addHandler(function(result){
        if(localStorage && localStorage.getItem("janrainCaptureToken")){
            janrain.capture.ui.createCaptureSession(localStorage.getItem("janrainCaptureToken"));
        }else{
            history.back();
        }
    });
    
    janrain.capture.ui.start();
}