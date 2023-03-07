#!/bin/sh
### BEGIN INIT INFO
# Provides:          cron-control-runner
# Required-Start:    $network $local_fs
# Required-Stop:     $network $local_fs
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Short-Description: Runner for Cron Control events
# Description:       Runner for Cron Control events
### END INIT INFO

. /lib/lsb/init-functions

NAME=cron-control-runner
USER=www-data
DAEMON="/usr/local/bin/${NAME}"
DAEMON_ARGS=""
PIDFILE="/var/run/${NAME}.pid"

# Overrides
[ -f "/etc/default/$NAME" ] && . /etc/default/$NAME

# If the daemon is not there, then exit.
test -x $DAEMON || exit 5

case $1 in
 start)
  # Checked the PID file exists and check the actual status of process
  if [ -e $PIDFILE ]; then
   status_of_proc -p $PIDFILE $DAEMON "$NAME process" && status="0" || status="$?"
   # If the status is SUCCESS then don't need to start again.
   if [ $status = "0" ]; then
    exit # Exit
   fi
  fi
  # Start the daemon.
  log_daemon_msg "Starting the process" "$NAME"
  # Start the daemon with the help of start-stop-daemon
  # Log the message appropriately
  if start-stop-daemon --start --chuid $USER --background --oknodo --pidfile $PIDFILE --make-pidfile --exec $DAEMON -- $DAEMON_ARGS; then
   log_end_msg 0
  else
   log_end_msg 1
  fi
  ;;
 stop)
  # Stop the daemon.
  if [ -e $PIDFILE ]; then
   status_of_proc -p $PIDFILE $DAEMON "Stoppping the $NAME process" && status="0" || status="$?"
   if [ "$status" = 0 ]; then
    start-stop-daemon --stop --retry=TERM/60/KILL/5 --quiet --oknodo --pidfile $PIDFILE
    /bin/rm -rf $PIDFILE
   fi
  else
   log_daemon_msg "$NAME process is not running"
   log_end_msg 0
  fi
  ;;
 restart)
  # Restart the daemon.
  $0 stop && sleep 2 && $0 start
  ;;
 status)
  # Check the status of the process.
  if [ -e $PIDFILE ]; then
   status_of_proc -p $PIDFILE $DAEMON "$NAME process" && exit 0 || exit $?
  else
   log_daemon_msg "$NAME Process is not running"
   log_end_msg 0
  fi
  ;;
 *)
  # For invalid arguments, print the usage message.
  echo "Usage: $0 {start|stop|restart|status}"
  exit 2
  ;;
esac
