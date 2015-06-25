<!-- wp-parsely -->
<style type="text/css">
    #wp-parsely_version {color: #777; font-size: 12px; margin-left: 1em;}
    .help-text {
        width: 75%;
    }
</style>
<script type="text/javascript">
(function($) {
    $(document).ready(function onDOMReady() {
        var apikey = $('#apikey').val();
        var recrawlRequiredMessage = '<strong style="color: red">Important:' +
            '</strong> changing this value on a site currently tracked with ' +
            'Parse.ly will require reprocessing of your Parse.ly data. Once ' +
            'you have changed this value, please contact ' +
            '<a href="mailto:support@parsely.com?subject=Please reprocess ' +
            apikey + '">support@parsely.com</a> to kick off reprocessing of ' +
            'your data.';

        $('<p class="description"></p>')
            .appendTo("div.parsely-form-controls[data-requires-recrawl='true'] .help-text")
            .html(recrawlRequiredMessage);
    });
})(jQuery);
</script>
<!-- end wp-parsely -->
