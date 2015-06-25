(function(){
  __sranalyticsPluginVersion = sranalytics.version;
  __reach_config = {
    pid: sranalytics.pid,
    iframe: sranalytics.iframe === '0' ? false : true,
    title: sranalytics.title,
    url: sranalytics.url,
    date: sranalytics.date,
    channels: sranalytics.channels,
    tags: sranalytics.tags,
    authors: sranalytics.authors
  };
  var s = document.createElement('script');
  s.async = true;
  s.type = 'text/javascript';
  s.src = document.location.protocol + '//d8rk54i4mohrb.cloudfront.net/js/reach.js';
  (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(s);
})();
