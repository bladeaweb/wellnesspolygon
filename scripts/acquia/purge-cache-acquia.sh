#!/usr/bin/env bash
##
# Purge varnish cache for a site.
# @see https://cloudapi.acquia.com/#DELETE__sites__site_envs__env_domains__domain_cache-instance_route

# File with local variables to include.
LOCAL_VARIABLES_FILE=${LOCAL_VARIABLES_FILE:-}

DEPLOY_API_USER_NAME=${DEPLOY_API_USER_NAME:-}
DEPLOY_API_USER_PASS=${DEPLOY_API_USER_PASS:-}
DEPLOY_API_PURGE_SITE=${DEPLOY_API_PURGE_SITE:-}
DEPLOY_API_PURGE_ENV=${DEPLOY_API_PURGE_ENV:-}
DEPLOY_API_PURGE_DOMAIN=${DEPLOY_API_PURGE_DOMAIN:-}
DEPLOY_API_PURGE_SEMAPHORE_FILE=${DEPLOY_API_PURGE_SEMAPHORE_FILE:-}
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
[ -f "$DEPLOY_API_PURGE_SEMAPHORE_FILE" ] && echo "==> Skipping Varnish cache purging (found semaphore file $DEPLOY_API_PURGE_SEMAPHORE_FILE)" && exit 0

which curl > /dev/null ||  {
  echo "==> curl is not available in this session" && exit 1
}

# Include file with local variables.
[ -f "$LOCAL_VARIABLES_FILE" ] && source $LOCAL_VARIABLES_FILE

if [ "$DEPLOY_API_USER_NAME" == "" ] || [ "$DEPLOY_API_USER_PASS" == "" ] || [ "$DEPLOY_API_PURGE_DOMAIN" == "" ]  || [ "$DEPLOY_API_PURGE_ENV" == "" ]  || [ "$DEPLOY_API_PURGE_SITE" == "" ] ; then
  echo "==> Missing required parameter(s)" && exit 1
fi

[ "$DEPLOY_API_PURGE_ENV" != "dev" ] && [ "$DEPLOY_API_PURGE_ENV" != "test" ] && [ "$DEPLOY_API_PURGE_ENV" != "test2" ] && [ "$DEPLOY_API_PURGE_ENV" != "prod" ] && echo "==> Invalid environment name" && exit 1

# Function to extract value from JSON object passed via STDIN.
extract_json_value() {
  local key=$1
  php -r "\$data=json_decode(preg_replace('/\R/','', file_get_contents('php://stdin')), TRUE); print isset(\$data['$key']) ? \$data['$key'] : '';"
}

echo "==> Purging Varnish cache in $DEPLOY_API_PURGE_ENV environment for $DEPLOY_API_PURGE_DOMAIN domain"
TASK_STATUS_JSON=$(curl --progress-bar -s -u $DEPLOY_API_USER_NAME:$DEPLOY_API_USER_PASS -X DELETE https://cloudapi.acquia.com/v1/sites/$DEPLOY_API_PURGE_SITE/envs/$DEPLOY_API_PURGE_ENV/domains/$DEPLOY_API_PURGE_DOMAIN/cache.json)
TASK_MESSAGE=$(echo $TASK_STATUS_JSON | extract_json_value "message")
[ "$TASK_MESSAGE" == "Not authorized" ] && echo "Task configured incorrectly: $TASK_MESSAGE" && exit 1
DEPLOYMENT_TASK_ID=$(echo $TASK_STATUS_JSON | extract_json_value "id")

printf "==> Checking task status: "
TASK_COMPLETED=0
for i in `seq 1 $DEPLOY_API_STATUS_RETRIES`;
do
  printf "."
  sleep $DEPLOY_API_STATUS_INTERVAL
  TASK_STATUS_JSON=$(curl --progress-bar -s -u $DEPLOY_API_USER_NAME:$DEPLOY_API_USER_PASS https://cloudapi.acquia.com/v1/sites/$DEPLOY_API_PURGE_SITE/tasks/$DEPLOYMENT_TASK_ID.json)
  TASK_STATE=$(echo $TASK_STATUS_JSON | extract_json_value "state")
  [ "$TASK_STATE" == "done" ] && TASK_COMPLETED=1 && break
done
echo

if [ $TASK_COMPLETED == 0 ] ; then
  echo "==> Unable to purge Varnish cache in $DEPLOY_API_PURGE_ENV environment for $DEPLOY_API_PURGE_DOMAIN domain"
  exit 1
fi

echo "==> Successfully purged Varnish cache in $DEPLOY_API_PURGE_ENV environment for $DEPLOY_API_PURGE_DOMAIN domain"

SELF_ELAPSED_TIME=$(($SECONDS - $SELF_START_TIME))
echo "==> Build duration: $(($SELF_ELAPSED_TIME/60)) min $(($SELF_ELAPSED_TIME%60)) sec"
