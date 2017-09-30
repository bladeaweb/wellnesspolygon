#!/usr/bin/env bash
##
# Copy files from one Acquia site environment to another.
# @see https://cloudapi.acquia.com/#POST__sites__site_files_copy__source__target-instance_route

# File with local variables to include.
LOCAL_VARIABLES_FILE=${LOCAL_VARIABLES_FILE:-}

DEPLOY_API_USER_NAME=${DEPLOY_API_USER_NAME:-}
DEPLOY_API_USER_PASS=${DEPLOY_API_USER_PASS:-}
DEPLOY_API_FILES_SITE=${DEPLOY_API_FILES_SITE:-}
DEPLOY_API_FILES_SRC_ENV=${DEPLOY_API_FILES_SRC_ENV:-}
DEPLOY_API_FILES_DST_ENV=${DEPLOY_API_FILES_DST_ENV:-}
DEPLOY_API_FILES_SEMAPHORE_FILE=${DEPLOY_API_FILES_SEMAPHORE_FILE:-}
DEPLOY_API_VERBOSE=${DEPLOY_API_VERBOSE:-0}

# Number of status retrieval retries. If this limit reached and task has not
# yet finished, the task is considered failed.
DEPLOY_API_STATUS_RETRIES=60
# Interval in seconds to check task status.
DEPLOY_API_STATUS_INTERVAL=10

################################################################################
#################### DO NOT CHANGE ANYTHING BELOW THIS LINE ####################
################################################################################
if [ "$DEPLOY_API_VERBOSE" == "1" ] ; then
 set -x;
fi

SELF_START_TIME=0
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Find absolute script path.
SELF_DIR=$(dirname -- $0)
SELF_PATH=$(cd -P -- "$SELF_DIR" && pwd -P)/$(basename -- $0)

# Exit early if semaphore file provided.
[ -f "$DEPLOY_API_FILES_SEMAPHORE_FILE" ] && echo "==> Skipping FILES copying (found semaphore file $DEPLOY_API_FILES_SEMAPHORE_FILE)" && exit 0

which curl > /dev/null ||  {
  echo "==> curl is not available in this session" && exit 1
}

# Include file with local variables.
[ -f "$LOCAL_VARIABLES_FILE" ] && source $LOCAL_VARIABLES_FILE

if [ "$DEPLOY_API_USER_NAME" == "" ] || [ "$DEPLOY_API_USER_PASS" == "" ] || [ "$DEPLOY_API_FILES_SITE" == "" ]  || [ "$DEPLOY_API_FILES_SRC_ENV" == "" ]  || [ "$DEPLOY_API_FILES_DST_ENV" == "" ] ; then
  echo "==> Missing required parameter(s)" && exit 1
fi

if [ "$DEPLOY_API_FILES_SRC_ENV" == "$DEPLOY_API_FILES_DST_ENV" ] ; then
  echo "==> Source and destination environments cannot be the same" && exit 1
fi

[ "$DEPLOY_API_FILES_SRC_ENV" != "dev" ] && [ "$DEPLOY_API_FILES_SRC_ENV" != "test" ] && [ "$DEPLOY_API_FILES_SRC_ENV" != "test2" ] && [ "$DEPLOY_API_FILES_SRC_ENV" != "prod" ] && echo "==> Invalid source environment name" && exit 1
[ "$DEPLOY_API_FILES_DST_ENV" != "dev" ] && [ "$DEPLOY_API_FILES_DST_ENV" != "test" ] && [ "$DEPLOY_API_FILES_DST_ENV" != "test2" ] && [ "$DEPLOY_API_FILES_DST_ENV" != "prod" ] && echo "==> Invalid destination environment name" && exit 1

# Function to extract value from JSON object passed via STDIN.
extract_json_value() {
  local key=$1
  php -r "\$data=json_decode(preg_replace('/\R/','', file_get_contents('php://stdin')), TRUE); print isset(\$data['$key']) ? \$data['$key'] : '';"
}

echo "==> Copying FILES from $DEPLOY_API_FILES_SRC_ENV to $DEPLOY_API_FILES_DST_ENV environment"
TASK_STATUS_JSON=$(curl --progress-bar -s -u $DEPLOY_API_USER_NAME:$DEPLOY_API_USER_PASS -X POST https://cloudapi.acquia.com/v1/sites/$DEPLOY_API_FILES_SITE/files-copy/$DEPLOY_API_FILES_SRC_ENV/$DEPLOY_API_FILES_DST_ENV.json)
TASK_MESSAGE=$(echo $TASK_STATUS_JSON | extract_json_value "message")
[ "$TASK_MESSAGE" == "Not authorized" ] && echo "Task configured incorrectly: $TASK_MESSAGE" && exit 1
DEPLOYMENT_TASK_ID=$(echo $TASK_STATUS_JSON | extract_json_value "id")

printf "==> Checking task status: "
TASK_COMPLETED=0
for i in `seq 1 $DEPLOY_API_STATUS_RETRIES`;
do
  printf "."
  sleep $DEPLOY_API_STATUS_INTERVAL
  TASK_STATUS_JSON=$(curl --progress-bar -s -u $DEPLOY_API_USER_NAME:$DEPLOY_API_USER_PASS https://cloudapi.acquia.com/v1/sites/$DEPLOY_API_FILES_SITE/tasks/$DEPLOYMENT_TASK_ID.json)
  TASK_STATE=$(echo $TASK_STATUS_JSON | extract_json_value "state")
  [ "$TASK_STATE" == "done" ] && TASK_COMPLETED=1 && break
done
echo

if [ $TASK_COMPLETED == 0 ] ; then
  echo "==> Unable to copy FILES from $DEPLOY_API_FILES_SRC_ENV to $DEPLOY_API_FILES_DST_ENV environment"
  exit 1
fi

echo "==> Successfully copied FILES from $DEPLOY_API_FILES_SRC_ENV to $DEPLOY_API_FILES_DST_ENV environment"

SELF_ELAPSED_TIME=$(($SECONDS - $SELF_START_TIME))
echo "==> Build duration: $(($SELF_ELAPSED_TIME/60)) min $(($SELF_ELAPSED_TIME%60)) sec"
