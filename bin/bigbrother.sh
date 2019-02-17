#!/bin/bash

if [ $# -le 1 ]; then
  echo "Usage: $0 <jobname> <command>"
  exit 1
fi

JOBNAME="$1"; shift

function log {
  echo "$(date -Iseconds) $1"
}

function restart_needed {
  if ! /usr/bin/qstat | awk '{ print $3 }' | grep "${JOBNAME:0:10}" >/dev/null 2>&1; then
    return 0
  else
    return 1
  fi
}

function submit_job {
  /usr/bin/jstart -N "$JOBNAME" $@
}

if restart_needed; then
  log "Restarting job '$JOBNAME'"
  submit_job $@
fi
