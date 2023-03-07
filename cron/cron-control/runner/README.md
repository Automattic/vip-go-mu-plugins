Cron Control Go Runner
======================

In addition to the REST API endpoints that can be used to run events, a Go-based runner is provided.

# DEPRECATED

Note that the runner located here is deprecated and will be removed in the future.

The new runner can be found in it's own repository now: https://github.com/Automattic/cron-control-runner

________

# Installation

1. Build the binary as described below.
2. Copy `init.sh` to `/etc/init.d/cron-control-runner`
3. To override default configuration, copy `defaults` to `/etc/default/cron-control-runner` and modify as needed
4. Run `update-rc.d cron-control-runner defaults`
5. Start the runner: `/etc/init.d/cron-control-runner start`
6. Check the runner's status: `/etc/init.d/cron-control-runner status`

# Runner options

* `-cli` string
  * Path to WP-CLI binary (default `/usr/local/bin/wp`)
* `-heartbeat` int
  * Heartbeat interval in seconds (default `60`)
* `-log` string
  * Log path, omit to log to Stdout (default `os.Stdout`)
* `-network` int
  * WordPress network ID, `0` to disable (default `0`)
* `-workers-get` int
  * Number of workers to retrieve events (default `1`)
  * Increase for multisite instances so that sites are retrieved in a timely manner
* `-workers-run` int
  * Number of workers to run events (default `5`)
  * Increase for cron-heavy sites and multisite instances so that events are run in a timely manner
* `-wp` string
  * Path to WordPress installation (default `/var/www/html`)
* `-metrics-listen-addr` ip:port
  * IP and port to listen on for `/metrics` endpoint. `0.0.0.0:1234` or `:1234` etc.

# Build the binary

If building on the target system, or under the same OS as the target machine, simply:

```
make
```

If building from a different OS:

```
env GOOS=linux make
```

Substitute `linux` with your target OS.

# Metrics

If you enable the metrics system and endpoint by providing the `-metrics-listen-addr` arg, then you will get the
following metrics for performance monitoring.

```
cron_control_runner_get_site_events_events_received_total{site="https://your.site.url"}
cron_control_runner_get_site_events_latency_seconds_bucket{site="https://your.site.url",status="success|failure",le="..."}
cron_control_runner_get_site_events_latency_seconds_count{site="https://your.site.url",status="success|failure"}
cron_control_runner_get_site_events_latency_seconds_sum{site="https://your.site.url",status="success|failure"}
cron_control_runner_get_sites_latency_seconds_bucket{status="success|failure",le="..."}
cron_control_runner_get_sites_latency_seconds_count{status="success|failure"}
cron_control_runner_get_sites_latency_seconds_sum{status="success|failure"}
cron_control_runner_run_event_latency_seconds_bucket{reason="ok|premature|error",site_url="https://your.site.url",status="success|failure",le="..."}
cron_control_runner_run_event_latency_seconds_count{reason="ok|premature|error",site_url="https://your.site.url",status="success|failure"}
cron_control_runner_run_event_latency_seconds_sum{reason="ok|premature|error",site_url="https://your.site.url",status="success|failure"}
cron_control_runner_run_worker_all_busy_hits
cron_control_runner_run_worker_busy_pct
cron_control_runner_run_worker_state_count{state="busy"}
cron_control_runner_run_worker_state_count{state="idle"}
cron_control_runner_run_worker_state_count{state="max"}
cron_control_runner_wpcli_stat_cputime_seconds_bucket{cpu_mode="system",status="success|failure",le="..."}
cron_control_runner_wpcli_stat_cputime_seconds_bucket{cpu_mode="user",status="success|failure",le="..."}
cron_control_runner_wpcli_stat_cputime_seconds_count{cpu_mode="system",status="success|failure"}
cron_control_runner_wpcli_stat_cputime_seconds_count{cpu_mode="user",status="success|failure"}
cron_control_runner_wpcli_stat_cputime_seconds_sum{cpu_mode="system",status="success|failure"}
cron_control_runner_wpcli_stat_cputime_seconds_sum{cpu_mode="user",status="success|failure"}
cron_control_runner_wpcli_stat_maxrss_mb_bucket{status="success|failure",le="..."}
cron_control_runner_wpcli_stat_maxrss_mb_count{status="success|failure"}
cron_control_runner_wpcli_stat_maxrss_mb_sum{status="success|failure"}
```

