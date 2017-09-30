#!/usr/bin/env bash

# 'Strict' mode.
set -euo pipefail
IFS=$'\n\t'

SCRIPT_START_TIME=$(date +%s)
DRUSH_BIN=$(which drush)

TBOLD=""
TDEFAULT=""
TRED=""
TGREEN=""
TWHITE=""
COLOR_OUT=0
if [ -t 1 ]; then
  COLORS=$(tput colors)
  if [ -n "$COLORS" ]; then
    TBOLD="$(tput bold)"
    TDEFAULT="$(tput sgr0)"
    TRED="$(tput setaf 1)"
    TGREEN="$(tput setaf 2)"
    TWHITE="$(tput setaf 7)"
  fi
  COLOR_OUT=1
fi
#
# Run drush command.
#
_drush() {
  cmd="$DRUSH_BIN $drush_target $@"
  status "$cmd"
  eval "$cmd"
}

#
# Print message, with colours if supported.
#
_message() {
  if [ $COLOR_OUT -ne 1 ]; then
    printf '==> %s\n' $1
  else
    printf '%s[%(%F %T)T] %s==> %s%s\n' $2 -1 $TBOLD $1 $TDEFAULT
  fi
}

#
# Print status message.
#
status() {
  _message "$1" "$TWHITE"
}

#
# Print error message.
#
error() {
  _message "$1" "$TRED"
}

#
# Print error message.
#
success() {
  _message "$1" "$TGREEN"
}

status "Starting migration process."

[ "$#" -lt 1 ] && error "Drush alias for migration target required." && exit 1
drush_target="$1"

[ -z "${2-}" ] && status "No command specified, defaulting to 'migrate'"
MIGRATE_COMMAND=${2:-migrate}

status "Stopping all currently running migrations"
_drush migrate-stop --all

status "Current migrate status:"
_drush ms

status "Running migrations"
_drush mi VPMenuLink

success "Done"
