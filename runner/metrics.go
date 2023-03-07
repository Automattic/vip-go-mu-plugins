package main

import (
	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promauto"
	"syscall"
	"time"
)

type metrics struct {
	histGetSitesLatency           *prometheus.HistogramVec
	histGetSiteEventsLatency      *prometheus.HistogramVec
	ctrGetSiteEventsReceivedTotal *prometheus.CounterVec
	histRunEventLatency           *prometheus.HistogramVec
	histWpcliStatMaxRSS           *prometheus.HistogramVec
	histWpcliStatCpuTime          *prometheus.HistogramVec
	histFpmCallDurationSeconds    *prometheus.HistogramVec
	gaugeRunWorkerStateCount      *prometheus.GaugeVec
	gaugeRunWorkerBusyPct         prometheus.Gauge
	ctrRunWorkersAllBusyHits      prometheus.Counter
}

var Metrics *metrics = nil

func makeLabels(isSuccess bool, labels ...prometheus.Labels) prometheus.Labels {
	lenSum := 1
	for _, ls := range labels {
		lenSum += len(ls)
	}
	r := make(prometheus.Labels, lenSum)
	for _, ls := range labels {
		for k, v := range ls {
			r[k] = v
		}
	}
	if isSuccess {
		r["status"] = "success"
	} else {
		r["status"] = "failure"
	}
	return r
}

func (m *metrics) RecordGetSites(isSuccess bool, elapsed time.Duration) {
	if m != nil {
		m.histGetSitesLatency.With(makeLabels(isSuccess)).Observe(elapsed.Seconds())
	}
}

func (m *metrics) RecordGetSiteEvents(site string, isSuccess bool, elapsed time.Duration, numEvents int) {
	if m != nil {
		siteLabel := prometheus.Labels{"site": site}
		m.histGetSiteEventsLatency.With(makeLabels(isSuccess, siteLabel)).Observe(elapsed.Seconds())
		if numEvents > 0 {
			m.ctrGetSiteEventsReceivedTotal.With(siteLabel).Add(float64(numEvents))
		}
	}
}

func (m *metrics) RecordRunEvent(siteUrl string, isSuccess bool, reason string, elapsed time.Duration) {
	if m != nil {
		if siteUrl == "" {
			siteUrl = "unknown"
		}
		if reason == "" {
			reason = "unknown"
		}
		m.histRunEventLatency.With(makeLabels(isSuccess, prometheus.Labels{
			"site_url": siteUrl,
			"reason":   reason,
		})).Observe(elapsed.Seconds())
	}
}

func (m *metrics) RecordWpCliUsage(isSuccess bool, stats *syscall.Rusage) {
	if m != nil {
		m.histWpcliStatMaxRSS.With(makeLabels(isSuccess)).Observe(float64(stats.Maxrss) / 1048576.0)
		m.histWpcliStatCpuTime.With(makeLabels(isSuccess, prometheus.Labels{
			"cpu_mode": "user",
		})).Observe(float64(stats.Utime.Sec) + float64(stats.Utime.Usec)/1e6)
		m.histWpcliStatCpuTime.With(makeLabels(isSuccess, prometheus.Labels{
			"cpu_mode": "system",
		})).Observe(float64(stats.Stime.Sec) + float64(stats.Stime.Usec)/1e6)
	}
}

func (m *metrics) RecordRunWorkerStats(currBusy int32, max int32) {
	if m != nil {
		m.gaugeRunWorkerStateCount.With(prometheus.Labels{"state": "max"}).Set(float64(max))
		m.gaugeRunWorkerStateCount.With(prometheus.Labels{"state": "busy"}).Set(float64(currBusy))
		m.gaugeRunWorkerStateCount.With(prometheus.Labels{"state": "idle"}).Set(float64(max - currBusy))
		if max > 0 {
			m.gaugeRunWorkerBusyPct.Set(float64(currBusy) / float64(max))
		}
		if currBusy >= max {
			// all workers are busy right now. increment:
			m.ctrRunWorkersAllBusyHits.Inc()
		}
	}
}

func (m *metrics) RecordFpmTiming(elapsed time.Duration, isError bool) {
	if m != nil {
		var labels prometheus.Labels
		if isError {
			labels = prometheus.Labels{"status": "error"}
		} else {
			labels = prometheus.Labels{"status": "success"}
		}
		m.histFpmCallDurationSeconds.With(labels).Observe(elapsed.Seconds())
	}
}

const metricNamespace = "cron_control_runner"

func InitializeMetrics() {
	if Metrics != nil {
		logger.Printf("Metrics already initialized, ignoring call to InitializeMetrics()")
		return
	}
	logger.Printf("Initializing metrics")
	Metrics = &metrics{
		histGetSitesLatency: promauto.NewHistogramVec(prometheus.HistogramOpts{
			Namespace: metricNamespace,
			Subsystem: "get_sites",
			Name:      "latency_seconds",
			Help:      "Histogram of time taken to enumerate sites",
			Buckets:   []float64{.1, .25, .5, 1, 2.5, 5, 10},
		}, []string{"status"}),
		histGetSiteEventsLatency: promauto.NewHistogramVec(prometheus.HistogramOpts{
			Namespace: metricNamespace,
			Subsystem: "get_site_events",
			Name:      "latency_seconds",
			Help:      "Histogram of time taken to enumerate events for a site",
			Buckets:   []float64{.1, .25, .5, 1, 2.5, 5, 10},
		}, []string{"site", "status"}),
		ctrGetSiteEventsReceivedTotal: promauto.NewCounterVec(prometheus.CounterOpts{
			Namespace: metricNamespace,
			Subsystem: "get_site_events",
			Name:      "events_received_total",
			Help:      "Number of events retrieved by site",
		}, []string{"site"}),
		histRunEventLatency: promauto.NewHistogramVec(prometheus.HistogramOpts{
			Namespace: metricNamespace,
			Subsystem: "run_event",
			Name:      "latency_seconds",
			Help:      "Histogram of time taken to run events",
			Buckets:   []float64{.1, .25, .5, 1, 2.5, 5, 10, 30, 60, 90},
		}, []string{"site_url", "status", "reason"}),
		histWpcliStatMaxRSS: promauto.NewHistogramVec(prometheus.HistogramOpts{
			Namespace: metricNamespace,
			Subsystem: "wpcli_stat",
			Name:      "maxrss_mb",
			Help:      "MaxRSS (in MiB) of invoked wp-cli commands",
			Buckets:   []float64{2, 4, 8, 16, 32, 64, 128, 256, 512, 768, 1024},
		}, []string{"status"}),
		histWpcliStatCpuTime: promauto.NewHistogramVec(prometheus.HistogramOpts{
			Namespace: metricNamespace,
			Subsystem: "wpcli_stat",
			Name:      "cputime_seconds",
			Help:      "CPU time (in seconds) of invoked wp-cli commands",
			Buckets:   []float64{.1, .25, .5, 1, 2.5, 5, 10, 30, 60, 90},
		}, []string{"cpu_mode", "status"}),
		histFpmCallDurationSeconds: promauto.NewHistogramVec(prometheus.HistogramOpts{
			Namespace: metricNamespace,
			Subsystem: "fpm_client",
			Name:      "call_duration_seconds",
			Help:      "Wall time of full call to backend FPM",
			Buckets:   prometheus.DefBuckets,
		}, []string{"status"}),
		gaugeRunWorkerStateCount: promauto.NewGaugeVec(prometheus.GaugeOpts{
			Namespace: metricNamespace,
			Subsystem: "run_worker",
			Name:      "state_count",
			Help:      "Breakdown of run-workers by state",
		}, []string{"state"}),
		gaugeRunWorkerBusyPct: promauto.NewGauge(prometheus.GaugeOpts{
			Namespace: metricNamespace,
			Subsystem: "run_worker",
			Name:      "busy_pct",
			Help:      "Instantaneous percentage of busy workers",
		}),
		ctrRunWorkersAllBusyHits: promauto.NewCounter(prometheus.CounterOpts{
			Namespace: metricNamespace,
			Subsystem: "run_worker",
			Name:      "all_busy_hits",
			Help:      "Number of times all workers have been instantaneously saturated",
		}),
	}
}
