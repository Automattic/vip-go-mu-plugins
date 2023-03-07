package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"github.com/prometheus/client_golang/prometheus/promhttp"
	"io"
	"math/rand"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"os/signal"
	"path/filepath"
	"strings"
	"sync/atomic"
	"syscall"
	"time"

	"github.com/yookoala/gofast"
)

type siteInfo struct {
	Multisite int
	Siteurl   string
	Disabled  int
}

type site struct {
	URL string
}

type event struct {
	URL       string
	Timestamp int
	Action    string
	Instance  string
}

func (e event) String() string {
	return fmt.Sprintf("event(url=%q, ts=%d, action=%q, instance=%q)", e.URL, e.Timestamp, e.Action, e.Instance)
}

var (
	wpCliPath string
	wpNetwork int
	wpPath    string

	numGetWorkers int
	numRunWorkers int

	getEventsInterval int

	heartbeatInt int64

	disabledLoopCount    uint64
	eventRunErrCount     uint64
	eventRunSuccessCount uint64
	busyEventWorkers     int32

	logger    *Logger
	logDest   string
	logFormat string
	debug     bool

	fpm gofast.ClientFactory

	smartSiteList bool
	useWebsockets bool

	gRestart                bool
	gEventRetrieversRunning []bool
	gEventWorkersRunning    []bool
	gRandomDeltaMap         map[string]int64
	gRemoteToken            string
	gMetricsListenAddr      string
	gGuidLength             int
)

const getEventsBreakSec time.Duration = 1 * time.Second
const runEventsBreakSec int64 = 10

func init() {
	fpmUrlStr := ""
	flag.StringVar(&wpCliPath, "cli", "/usr/local/bin/wp", "Path to WP-CLI binary")
	flag.IntVar(&wpNetwork, "network", 0, "WordPress network ID, `0` to disable")
	flag.StringVar(&wpPath, "wp", "/var/www/html", "Path to WordPress installation")
	flag.IntVar(&numGetWorkers, "workers-get", 1, "Number of workers to retrieve events")
	flag.IntVar(&numRunWorkers, "workers-run", 5, "Number of workers to run events")
	flag.IntVar(&getEventsInterval, "get-events-interval", 60, "Seconds between event retrieval")
	flag.Int64Var(&heartbeatInt, "heartbeat", 60, "Heartbeat interval in seconds")
	flag.StringVar(&logDest, "log", "os.Stdout", "Log path, omit to log to Stdout")
	flag.StringVar(&logFormat, "log-format", "JSON", "Log format, 'Text' or 'JSON'")
	flag.BoolVar(&debug, "debug", false, "Include additional log data for debugging")
	flag.StringVar(&fpmUrlStr, "fpm-url", fpmUrlStr, "Url for the php-fpm server or socket (e.g. unix:///var/run/fastcgi.sock)")
	flag.BoolVar(&smartSiteList, "smart-site-list", false, "Use the `wp cron-control orchestrate` command instead of `wp site list`")
	flag.BoolVar(&useWebsockets, "use-websockets", false, "Use the websocket listener instead of raw tcp")
	flag.StringVar(&gRemoteToken, "token", "", "Token to authenticate remote WP CLI requests")
	flag.IntVar(&gGuidLength, "guid-len", 36, "Sets the Guid length in use for remote WP CLI requests")
	flag.StringVar(&gMetricsListenAddr, "metrics-listen-addr", "", "Listen address for prometheus metrics (e.g. :4444); if set, can scrape http://:4444/metrics.")
	flag.Parse()

	setUpLogger()

	// NEED to do this regardless of fpm because remote.go still will invoke wp-cli directly!
	validatePath(&wpCliPath, "WP-CLI path")
	validatePath(&wpPath, "WordPress path")

	if fpmUrlStr != "" {
		var err error
		fpmUrl, err := url.Parse(fpmUrlStr)
		if err != nil || fpmUrl == nil || fpmUrl.Scheme != "unix" || fpmUrl.Path == "" {
			logger.Printf("error parsing FPM url %q: %v", fpmUrlStr, err)
			panic(err)
		}
		logger.Printf("Using FPM runtime at %q", fpmUrlStr)
		fpm = gofast.SimpleClientFactory(gofast.SimpleConnFactory(fpmUrl.Scheme, fpmUrl.Path))
	}

	gRandomDeltaMap = make(map[string]int64)
}

func main() {
	logger.Printf("Starting with %d event-retrieval worker(s) and %d event worker(s)", numGetWorkers, numRunWorkers)
	logger.Printf("Retrieving events every %d seconds", getEventsInterval)
	go setupSignalHandler()

	sites := make(chan site)
	events := make(chan event)

	gEventRetrieversRunning = make([]bool, numGetWorkers)
	gEventWorkersRunning = make([]bool, numRunWorkers)

	if gMetricsListenAddr != "" {
		InitializeMetrics()
		http.Handle("/metrics", promhttp.Handler())
		go (func() {
			logger.Printf("Listening for metrics on %q", gMetricsListenAddr)
			err := http.ListenAndServe(gMetricsListenAddr, nil)
			logger.Printf("Metrics server terminated: %v", err)
		})()
	}

	for w := 1; w <= numGetWorkers; w++ {
		go queueSiteEvents(w, sites, events)
	}
	for w := 1; w <= numRunWorkers; w++ {
		go runEvents(w, events)
	}
	go retrieveSitesPeriodically(sites)

	// Only listen for connections from remote WP CLI commands is we have a token set
	if 0 < len(gRemoteToken) {
		go waitForConnect()
	}

	heartbeat(sites, events)
}

func retrieveSitesPeriodically(sites chan<- site) {
	for {
		waitForEpoch("siteRetriever", "retrieveSitesPeriodically", int64(getEventsInterval))
		if gRestart {
			logger.Println("siteRetriever: exiting")
			break
		}
		t0 := time.Now()
		logger.Printf("siteRetriever: listing sites...")
		siteList, err := getSites()
		duration := time.Since(t0)
		logger.Printf("siteRetriever: listed %d sites in %v, err=%v", len(siteList), duration, err)
		if err != nil {
			Metrics.RecordGetSites(false, duration)
			continue
		}

		Metrics.RecordGetSites(true, duration)
		for _, site := range siteList {
			sites <- site
		}
	}
}

func heartbeat(sites chan<- site, queue chan<- event) {
	if heartbeatInt == 0 {
		logger.Println("heartbeater: heartbeat disabled")
		for {
			waitForEpoch("heartbeater", "heartbeat", 60)
			if gRestart {
				logger.Println("exiting heartbeat routine")
				break
			}
		}
		return
	}

	for {
		waitForEpoch("heartbeater", "heartbeat", heartbeatInt)
		if gRestart {
			logger.Println("heartbeater: exiting heartbeat routine")
			break
		}

		if smartSiteList {
			logger.Println("heartbeater: heartbeat")
			runWpCmd([]string{"cron-control", "orchestrate", "sites", "heartbeat", fmt.Sprintf("--heartbeat-interval=%d", heartbeatInt)})
		}

		successCount, errCount := atomic.LoadUint64(&eventRunSuccessCount), atomic.LoadUint64(&eventRunErrCount)
		atomic.SwapUint64(&eventRunSuccessCount, 0)
		atomic.SwapUint64(&eventRunErrCount, 0)
		logger.Printf("heartbeater: eventsSucceededSinceLast=%d eventsErroredSinceLast=%d", successCount, errCount)
	}

	var StillRunning bool
	maxWaitCount := 30
	for {
		StillRunning = false
		for workerID, r := range gEventRetrieversRunning {
			if r {
				logger.Printf("heartbeater (shutdown): event retriever ID %d still running\n", workerID+1)
				logger.Printf("heartbeater (shutdown):sending empty site object for worker %d\n", workerID+1)
				sites <- site{}
				StillRunning = true
			}
		}
		for workerID, r := range gEventWorkersRunning {
			if r {
				logger.Printf("heartbeater (shutdown):event worker ID %d still running\n", workerID+1)
				logger.Printf("heartbeater (shutdown):sending empty event for worker %d\n", workerID+1)
				queue <- event{}
				StillRunning = true
			}
		}

		if 0 < len(gGUIDttys) {
			logger.Printf("heartbeater (shutdown): there are still %d remote WP-CLI commands running\n", len(gGUIDttys))
			StillRunning = true
		}

		if StillRunning && 0 < maxWaitCount {
			logger.Println("worker(s) still running, waiting")
			time.Sleep(1 * time.Second)
			maxWaitCount--
			continue
		}
		logger.Println(".:sayonara:.")
		os.Exit(0)
	}
}

func getSites() ([]site, error) {
	siteInfo, err := getInstanceInfo()
	if err != nil {
		siteInfo.Disabled = 1
	}

	if run := shouldGetSites(siteInfo.Disabled); false == run {
		return nil, err
	}

	if siteInfo.Multisite == 1 {
		sites, err := getMultisiteSites()
		if err != nil {
			sites = nil
		}

		return sites, err
	}

	// Mock for single site
	sites := make([]site, 0)
	sites = append(sites, site{URL: siteInfo.Siteurl})

	return sites, nil
}

func getInstanceInfo() (siteInfo, error) {
	raw, err := runWpCmd([]string{"cron-control", "orchestrate", "runner-only", "get-info", "--format=json"})
	if err != nil {
		return siteInfo{}, err
	}

	jsonRes := make([]siteInfo, 0)
	if err = json.Unmarshal([]byte(raw), &jsonRes); err != nil {
		if debug {
			logger.Println(fmt.Sprintf("%+v - %s", err, raw))
		}

		return siteInfo{}, err
	}

	return jsonRes[0], nil
}

func shouldGetSites(disabled int) bool {
	if disabled == 0 {
		atomic.SwapUint64(&disabledLoopCount, 0)
		return true
	}

	disabledCount, now := atomic.LoadUint64(&disabledLoopCount), time.Now()
	disabledSleep := time.Minute * 3 * time.Duration(disabledCount)
	disabledSleepSeconds := int64(disabledSleep) / 1000 / 1000 / 1000

	if disabled > 1 && (now.Unix()+disabledSleepSeconds) > int64(disabled) {
		atomic.SwapUint64(&disabledLoopCount, 0)
	} else if disabledSleep > time.Hour {
		atomic.SwapUint64(&disabledLoopCount, 0)
	} else {
		atomic.AddUint64(&disabledLoopCount, 1)
	}

	if disabledSleep > 0 {
		if debug {
			logger.Printf("Automatic execution disabled, sleeping for an additional %d minutes", disabledSleepSeconds/60)
		}

		time.Sleep(disabledSleep)
	} else if debug {
		logger.Println("Automatic execution disabled")
	}

	return false
}

func getMultisiteSites() ([]site, error) {
	var raw string
	var err error
	if smartSiteList {
		raw, err = runWpCmd([]string{"cron-control", "orchestrate", "sites", "list"})
	} else {
		raw, err = runWpCmd([]string{"site", "list", "--fields=url", "--archived=false", "--deleted=false", "--spam=false", "--format=json"})
	}

	if err != nil {
		return nil, err
	}

	jsonRes := make([]site, 0)
	if err = json.Unmarshal([]byte(raw), &jsonRes); err != nil {
		if debug {
			logger.Println(fmt.Sprintf("%+v - %s", err, raw))
		}

		return nil, err
	}

	// Shuffle site order so that none are favored
	for i := range jsonRes {
		j := rand.Intn(i + 1)
		jsonRes[i], jsonRes[j] = jsonRes[j], jsonRes[i]
	}

	return jsonRes, nil
}

func queueSiteEvents(workerID int, sites <-chan site, queue chan<- event) {
	gEventRetrieversRunning[workerID-1] = true
	logger.Printf("getEvents-%d: started retrieving events", workerID)

	defer (func() {
		logger.Printf("getEvents-%d: deferred exit", workerID)
		gEventRetrieversRunning[workerID-1] = false
	})()

	for site := range sites {
		if gRestart {
			return
		}
		logger.Printf("getEvents-%d: retrieving events for site %s", workerID, site.URL)

		t0 := time.Now()
		events, err := getSiteEvents(site.URL)
		Metrics.RecordGetSiteEvents(site.URL, err == nil, time.Since(t0), len(events))
		logger.Printf("getEvents-%d: got %d events for site %s; err=%v", workerID, len(events), site.URL, err)
		if err == nil && len(events) > 0 {
			for _, event := range events {
				event.URL = site.URL
				logger.Printf("getEvents-%d: enqueueing event %v", workerID, event)
				queue <- event
			}
		}
		time.Sleep(getEventsBreakSec)
	}

}

func getSiteEvents(site string) ([]event, error) {
	raw, err := runWpCmd([]string{"cron-control", "orchestrate", "runner-only", "list-due-batch", fmt.Sprintf("--url=%s", site), "--format=json"})
	if err != nil {
		return nil, err
	}

	siteEvents := make([]event, 0)
	if err = json.Unmarshal([]byte(raw), &siteEvents); err != nil {
		if debug {
			logger.Println(fmt.Sprintf("%+v - %s", err, raw))
		}

		return nil, err
	}

	return siteEvents, nil
}

func runEvents(workerID int, events <-chan event) {
	gEventWorkersRunning[workerID-1] = true
	logger.Printf("runEvents-%d: started", workerID)

	for event := range events {
		if gRestart {
			logger.Printf("runEvents-%d: exiting", workerID)
			break
		}
		t0 := time.Now()
		if event.Timestamp > int(t0.Unix()) {
			if debug {
				logger.Printf("runEvents-%d: skipping premature job %v", workerID, event)
			}
			Metrics.RecordRunEvent(event.URL, false, "premature", time.Since(t0))
			continue
		}

		// this worker is now considered busy:
		Metrics.RecordRunWorkerStats(atomic.AddInt32(&busyEventWorkers, 1), int32(numRunWorkers))

		subcommand := []string{"cron-control", "orchestrate", "runner-only", "run", fmt.Sprintf("--timestamp=%d", event.Timestamp),
			fmt.Sprintf("--action=%s", event.Action), fmt.Sprintf("--instance=%s", event.Instance), fmt.Sprintf("--url=%s", event.URL)}

		_, err := runWpCmd(subcommand)
		duration := time.Since(t0)
		logger.Printf("runEvents-%d: finished job %v after %v; err=%v", workerID, event, duration, err)
		if err == nil {
			Metrics.RecordRunEvent(event.URL, true, "ok", duration)
			if heartbeatInt > 0 {
				atomic.AddUint64(&eventRunSuccessCount, 1)
			}
		} else {
			Metrics.RecordRunEvent(event.URL, false, "error", duration)
			if heartbeatInt > 0 {
				atomic.AddUint64(&eventRunErrCount, 1)
			}
		}

		waitForEpoch(fmt.Sprintf("runEvents-%d", workerID), "runEvents", runEventsBreakSec)

		// this worker is now considered "idle". we explicitly include the above waitForEpoch in the "busy" time
		// even though it is wasted, since it is time this worker could not be doing something else.
		// my gut feeling is that the above wait is not needed, but, alas, here we are.
		Metrics.RecordRunWorkerStats(atomic.AddInt32(&busyEventWorkers, -1), int32(numRunWorkers))

		if gRestart {
			logger.Printf("runEvents-%d: exiting", workerID)
			break
		}

	}

	// Mark this event worker as not running for graceful exit
	gEventWorkersRunning[workerID-1] = false
}

func runWpCmd(subcommand []string) (string, error) {
	// `--quiet`` included to prevent WP-CLI commands from generating invalid JSON
	subcommand = append(subcommand, "--allow-root", "--quiet", fmt.Sprintf("--path=%s", wpPath))
	if wpNetwork > 0 {
		subcommand = append(subcommand, fmt.Sprintf("--network=%d", wpNetwork))
	}
	if fpm != nil {
		return runWpFpmCmdWithMetrics(subcommand)
	} else {
		return runWpCliCmd(subcommand)
	}
}

func fpmEnv(args url.Values) map[string]string {
	return map[string]string{
		"REQUEST_METHOD":    "GET",
		"SCRIPT_FILENAME":   "/var/wpvip/fpm-cron-runner.php",
		"GATEWAY_INTERFACE": "FastCGI/1.0",
		"QUERY_STRING":      args.Encode(),
	}
}

func runWpFpmCmdWithMetrics(subcommand []string) (string, error) {
	t0 := time.Now()
	res, err := runWpFpmCmdSafe(subcommand)
	elapsed := time.Since(t0)
	Metrics.RecordFpmTiming(elapsed, err != nil)
	return res, err
}

type fakeHttpResponseWriter struct {
	Dest       io.Writer
	LastStatus int
	Headers    http.Header
}

func (f *fakeHttpResponseWriter) Header() http.Header {
	if f.Headers == nil {
		f.Headers = http.Header{}
	}
	return f.Headers
}

func (f *fakeHttpResponseWriter) Write(bytes []byte) (int, error) {
	return f.Dest.Write(bytes)
}

func (f *fakeHttpResponseWriter) WriteHeader(statusCode int) {
	f.LastStatus = statusCode
}

type SiteUrl struct {
	Url string `json:"url"`
}

func runWpFpmCmdSafe(subcommand []string) (string, error) {

	fpmClient, err := fpm()
	if err != nil {
		return "", err
	}
	defer (func(){ _ = fpmClient.Close() })()

	args, err := buildFpmQuery(subcommand)
	if err != nil {
		return "", err
	}

	fcgiReq := gofast.NewRequest(nil)
	fcgiReq.Params = fpmEnv(args)

	fcgiResp, err := fpmClient.Do(fcgiReq)
	if err != nil {
		return "", err
	}
	defer fcgiResp.Close()

	stdErr := &strings.Builder{}
	stdOut := &strings.Builder{}
	hrw := &fakeHttpResponseWriter{Dest: stdOut}

	if err = fcgiResp.WriteTo(hrw, stdErr); err != nil {
		return "", err
	}

	stdOutStr := stdOut.String()
	if hrw.LastStatus != http.StatusOK {
		return "", fmt.Errorf("fpm error: lastStatus=%d, headers=%v, stdout=%q, stderr=%q", hrw.LastStatus, hrw.Headers, stdOutStr, stdErr.String())
	}

	var res struct {
		Buf    string `json:"buf"`
		Stdout string `json:"stdout"`
		Stderr string `json:"stderr"`
	}

	err = json.Unmarshal([]byte(stdOutStr), &res)

	if debug {
		logger.Printf("fpm result: lastStatus=%d, headers=%v, stdout=%q, stderr=%q, res=%+v, err=%v", hrw.LastStatus, hrw.Headers, stdOutStr, stdErr.String(), res, err)
	}

	if err != nil {
		if idx := strings.Index(stdOutStr, `[{"url":`); idx >= 0 {
			var urls []SiteUrl
			// if a site manages to escape the output buffering, this will try to find the output anyways...
			err = json.NewDecoder(strings.NewReader(stdOutStr[idx:])).Decode(&urls)
			if err == nil {
				var buf []byte
				buf, err = json.Marshal(&urls)
				res.Buf = string(buf)
			}
		}
	}

	if err != nil {
		return "", fmt.Errorf("fpm error: could not decode json response from %q: err=%v", stdOutStr, err)
	}

	return res.Buf, err
}

func buildFpmQuery(subcommand []string) (url.Values, error) {
	jsonBytes, err := json.Marshal(subcommand)
	if err != nil {
		return nil, err
	}
	return url.Values{
		"payload": []string{string(jsonBytes)},
	}, nil
}

func runWpCliCmd(subcommand []string) (string, error) {
	var stdout, stderr strings.Builder
	wpCli := exec.Command(wpCliPath, subcommand...)
	wpCli.Stdout = &stdout
	wpCli.Stderr = &stderr
	err := wpCli.Run()
	wpOutStr := stdout.String()
	if stderr.Len() > 0 {
		stderrStr := strings.TrimSpace(stderr.String())
		if len(stderrStr) > 0 {
			logger.Printf("STDERR for command[%s]: %s", strings.Join(subcommand, " "), stderrStr)
		}
	}

	// always log stats, even in case of an error:
	if stats, ok := wpCli.ProcessState.SysUsage().(*syscall.Rusage); ok && stats != nil {
		Metrics.RecordWpCliUsage(err == nil, stats)
		job_info := ""
		for _, s := range subcommand {
			if 0 == strings.Index(s, "--action=") {
				job_info += strings.Replace(s, "--action=", "action: ", 1) + " "
			} else if 0 == strings.Index(s, "--url=") {
				job_info += strings.Replace(s, "--url=", "url: ", 1) + " "
			}
		}
		if "" != job_info {
			logger.Printf(
				"%s: max rss: %0.0f KB : user time %0.2f sec : sys time %0.2f sec",
				job_info,
				float64(stats.Maxrss)/1024,
				float64(stats.Utime.Sec)+float64(stats.Utime.Usec)/1e6,
				float64(stats.Stime.Sec)+float64(stats.Stime.Usec)/1e6)
		}
	}

	if err != nil {
		if debug {
			logger.Printf("%s - %s", err, wpOutStr)
			logger.Println(fmt.Sprintf("%+v", subcommand))
		}

		return wpOutStr, err
	}

	return wpOutStr, nil
}

func setUpLogger() {
	if "os.Stdout" == logDest {
		logger = &Logger{FileName: "os.Stdout", Type: Text}
	} else if "json" == strings.ToLower(logFormat) {
		logger = &Logger{FileName: logDest, Type: JSON}
	} else {
		logger = &Logger{FileName: logDest, Type: Text}
	}
	logger.Init()
}

func validatePath(path *string, label string) {
	if len(*path) > 1 {
		var err error
		*path, err = filepath.Abs(*path)

		if err != nil {
			fmt.Printf("Error for %s: %s\n", label, err.Error())
			os.Exit(3)
		}

		if _, err = os.Stat(*path); os.IsNotExist(err) {
			fmt.Printf("Error for %s: '%s' does not exist\n", label, *path)
			usage()
		}
	} else {
		fmt.Printf("Empty path provided for %s\n", label)
		usage()
	}
}

func usage() {
	flag.Usage()
	os.Exit(3)
}

func waitForEpoch(waiter, whom string, epoch_sec int64) {
	tEpochNano := epoch_sec * time.Second.Nanoseconds()
	tEpochDelta := tEpochNano - (time.Now().UnixNano() % tEpochNano)
	if tEpochDelta < 1*time.Second.Nanoseconds() {
		tEpochDelta += epoch_sec * time.Second.Nanoseconds()
	}

	// We need to offset each epoch wait by a fixed random value to prevent
	// all Cron Runners having their epochs at exactly the same time.
	_, found := gRandomDeltaMap[whom]
	if !found {
		rand.Seed(time.Now().UnixNano() + epoch_sec)
		gRandomDeltaMap[whom] = rand.Int63n(tEpochNano)
	}

	tNextEpoch := time.Now().UnixNano() + tEpochDelta + gRandomDeltaMap[whom]

	if totalWait := time.Unix(0, tNextEpoch).Sub(time.Now()); totalWait > 1*time.Second {
		logger.Printf("%s: LONG wait (%v) for epoch %q", waiter, totalWait, whom)
	}

	// Sleep in 3sec intervals by default, less if we are running out of time
	tMaxDelta := 3 * time.Second.Nanoseconds()
	tDelta := tMaxDelta

	for i := tDelta; time.Now().UnixNano() < tNextEpoch; i += tDelta {
		if i > tEpochNano*2 {
			// if we ever loop here for more than 2 full epochs, bail out
			break
		}
		if gRestart {
			return
		}
		tDelta = tNextEpoch - time.Now().UnixNano()
		if tDelta > tMaxDelta {
			tDelta = tMaxDelta
		}
		time.Sleep(time.Duration(tDelta))
	}
}

func setupSignalHandler() {
	sigChan := make(chan os.Signal)
	signal.Notify(sigChan, syscall.SIGINT, syscall.SIGTERM, syscall.SIGQUIT)
	for {
		select {
		case sig := <-sigChan:
			logger.Printf("caught termination signal %s, scheduling shutdown\n", sig)
			gRestart = true
		}
	}
}
