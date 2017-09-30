#!/usr/bin/env bash
##
# Master script to refresh Acquia environment.
#
# Used as a pipeline to run all required actions to refresh
# Acquia environment.
#
DEPLOY_API_VERBOSE=${DEPLOY_API_VERBOSE:-0}

# File with per-environment local variables to include.
LOCAL_VARIABLES_FILE=${LOCAL_VARIABLES_FILE:-}

# An alias of the environment to refresh.
DST_DRUSH_ALIAS=${DST_DRUSH_ALIAS:-}

# Refresh steps.
STEP_COPY_FILES=${STEP_COPY_FILES:-1}
STEP_COPY_DB=${STEP_COPY_DB:-1}
# Note that legcy DB is not copied by default.
STEP_COPY_DB_LEGACY=${STEP_COPY_DB_LEGACY:-0}
STEP_MAINTENANCE_MODE=${STEP_MAINTENANCE_MODE:-1}
STEP_DB_UPDATES=${STEP_DB_UPDATES:-1}
STEP_SEARCH_INDEX=${STEP_SEARCH_INDEX:-1}
STEP_VARNISH_CACHE_PURGE=${STEP_VARNISH_CACHE_PURGE:-1}
STEP_MIGRATE=${STEP_VARNISH_CACHE_PURGE:-1}

################################################################################
#################### DO NOT CHANGE ANYTHING BELOW THIS LINE ####################
################################################################################
if [ "$DEPLOY_API_VERBOSE" == "1" ] ; then
 set -x;
fi

SELF_START_TIME_TOTAL=0

# Find absolute script path.
SELF_DIR=$(dirname -- $0)
SELF_PATH=$(cd -P -- "$SELF_DIR" && pwd -P)/$(basename -- $0)
SCRIPTS_DIR=${SCRIPTS_DIR:-$(dirname $SELF_PATH)}
HOOKS_DIR=${HOOKS_DIR:- $(dirname $SCRIPTS_DIR)/hooks}

# Include file with local variables.
[ -f "$LOCAL_VARIABLES_FILE" ] && source $LOCAL_VARIABLES_FILE

[ "$DST_DRUSH_ALIAS" == "" ] && echo "##### ERROR: Destination drush alias is not provided" && exit 1

################################################################################
############################## REFRESH TASKS ###################################
################################################################################

echo "#####"
echo "##### STARTING ENVIRONMENT REFRESH"
echo "##### ALIAS: $DST_DRUSH_ALIAS"
echo "#####"

if [ "$STEP_COPY_FILES" == "1" ]; then
  echo "#####"
  echo "##### COPY FILES STARTED"
  echo "#####"
  source $SCRIPTS_DIR/copy-files-acquia.sh
  echo "#####"
  echo "##### COPY FILES FINISHED"
  echo "#####"
fi

if [ "$STEP_COPY_DB" == "1" ]; then
  echo "#####"
  echo "##### DB COPY STARTED"
  echo "#####"
  source $SCRIPTS_DIR/copy-db-acquia.sh
  echo "#####"
  echo "##### DB COPY FINISHED"
  echo "#####"
fi

if [ "$STEP_COPY_DB_LEGACY" == "1" ]; then
  echo "#####"
  echo "##### LEGACY DB COPY STARTED"
  echo "#####"
  LOCAL_VARIABLES_FILE="" DEPLOY_API_DB_NAME=$DEPLOY_API_DB_LEGACY_NAME DEPLOY_API_DB_SRC_ENV=$DEPLOY_API_DB_LEGACY_SRC_ENV DEPLOY_API_DB_DST_ENV=$DEPLOY_API_DB_LEGACY_DST_ENV DEPLOY_API_DB_SEMAPHORE_FILE=$DEPLOY_API_DB_LEGACY_SEMAPHORE_FILE source $SCRIPTS_DIR/copy-db-acquia.sh
  echo "#####"
  echo "##### LEGACY DB COPY FINISHED"
  echo "#####"
fi

if [ "$STEP_MAINTENANCE_MODE" == "1" ]; then
  echo "#####"
  echo "##### ENABLING MAINTENANCE MODE STARTED"
  echo "#####"
  drush $DST_DRUSH_ALIAS vset --format=boolean maintenance_mode 1
  echo "#####"
  echo "##### ENABLING MAINTENANCE MODE FINISHED"
  echo "#####"
fi

if [ "$STEP_DB_UPDATES" == "1" ]; then
  echo "#####"
  echo "##### DB UPDATES STARTED"
  echo "#####"
  source $HOOKS_DIR/library/db-update.sh $AH_SITE_GROUP $AH_SITE_ENVIRONMENT
  source $HOOKS_DIR/library/enable-shield.sh $AH_SITE_GROUP $AH_SITE_ENVIRONMENT
  echo "#####"
  echo "##### DB UPDATES FINISHED"
  echo "#####"
fi

if [ "$STEP_MIGRATE" == "1" ]; then
  echo "#####"
  echo "##### MIGRATION STARTED"
  echo "#####"
  $SCRIPTS_DIR/migration.sh $DST_DRUSH_ALIAS
  echo "#####"
  echo "##### MIGRATION FINISHED"
  echo "#####"
fi

if [ "$STEP_SEARCH_INDEX" == "1" ]; then
  echo "#####"
  echo "##### SEARCH INDEX STARTED"
  echo "#####"
  drush $DST_DRUSH_ALIAS search-api-clear
  drush $DST_DRUSH_ALIAS search-api-index
  echo "#####"
  echo "##### SEARCH INDEX FINISHED"
  echo "#####"
fi

if [ "$STEP_MAINTENANCE_MODE" == "1" ]; then
  echo "#####"
  echo "##### DISABLING MAINTENANCE MODE STARTED"
  echo "#####"
  drush $DST_DRUSH_ALIAS vset --format=boolean maintenance_mode 0
  echo "#####"
  echo "##### DISABLING MAINTENANCE MODE FINISHED"
  echo "#####"
fi

if [ "$STEP_VARNISH_CACHE_PURGE" == "1" ]; then
  echo "#####"
  echo "##### VARNISH CACHE PURGE STARTED"
  echo "#####"
  source $HOOKS_DIR/library/flush-varnish.sh $AH_SITE_GROUP $AH_SITE_ENVIRONMENT
  echo "#####"
  echo "##### VARNISH CACHE PURGE FINISHED"
  echo "#####"
fi

SELF_ELAPSED_TIME=$(($SECONDS - $SELF_START_TIME_TOTAL))
echo "##### GRAND TOTAL BUILD DURATION: $(($SELF_ELAPSED_TIME/60)) min $(($SELF_ELAPSED_TIME%60)) sec"


