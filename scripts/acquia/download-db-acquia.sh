#!/usr/bin/env bash
##
# Download DB from Acquia.
# @see https://cloudapi.acquia.com/#GET__sites__site_envs__env_dbs__db_backups__backup_download-instance_route
# @TODO: Refactor into php/robo script so we can test it.

DEPLOY_API_USER_NAME=${DEPLOY_API_USER_NAME:-}
DEPLOY_API_USER_PASS=${DEPLOY_API_USER_PASS:-}
DEPLOY_API_DB_SITE=${DEPLOY_API_DB_SITE:-}
DEPLOY_API_DB_ENV=${DEPLOY_API_DB_ENV:-}
DEPLOY_API_DB_NAME=${DEPLOY_API_DB_NAME:-}

# Backup id. If not specified - latest backup id will be discovered and used.
DEPLOY_API_DB_BACKUP_ID=${DEPLOY_API_DB_BACKUP_ID:-}
# Remove old caches.
DEPLOY_API_DB_OLD_CACHE_REMOVE=${DEPLOY_API_DB_OLD_CACHE_REMOVE:-1}

# Uncompress or not.
DEPLOY_API_DB_DECOMPRESS_BACKUP=${DEPLOY_API_DB_DECOMPRESS_BACKUP:-1}

DEPLOY_CACHE_DIR=${DEPLOY_CACHE_DIR:-.db_cache}
# Defaults to $PROJECT_PATH/.data/{db_name}_backup.sql.
DB_DUMP=${DB_DUMP:-}

################################################################################
#################### DO NOT CHANGE ANYTHING BELOW THIS LINE ####################
################################################################################
SELF_START_TIME=$(date +%s)
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Find absolute script path.
SELF_DIR=$(dirname -- ${BASH_SOURCE[0]})
SELF_PATH=$(cd -P -- "$SELF_DIR" && pwd -P)/$(basename -- ${BASH_SOURCE[0]})

# Find absolute project root.
PROJECT_PATH=$(dirname $(dirname $(dirname $SELF_PATH)))

# Set DB dump file, dirname and compressed file name.
DB_DUMP=${DB_DUMP:-$PROJECT_PATH/.data/${DEPLOY_API_DB_NAME}_backup.sql}
DB_DUMP_DIR=$(dirname $DB_DUMP)

which curl > /dev/null ||  {
  echo "==> curl is not available in this session" && exit 1
}

if [ "$DEPLOY_API_USER_NAME" == "" ] || [ "$DEPLOY_API_USER_PASS" == "" ] || [ "$DEPLOY_API_DB_SITE" == "" ]  || [ "$DEPLOY_API_DB_ENV" == "" ]  || [ "$DEPLOY_API_DB_NAME" == "" ] ; then
  echo "==> Missing required parameter(s)" && exit 1
fi

if [ "$DEPLOY_API_USER_NAME" == "<your_cloudapi_email>" ] || [ "$DEPLOY_API_USER_PASS" == "<your_cloudapi_private_key>" ] ; then
  echo "==> Acquia API credentials were not set. Please copy default.variables.local.sh to variables.local.sh and set \$DEPLOY_API_USER_NAME and \$DEPLOY_API_USER_PASS variables to your Acquia credentials (follow instructions in default.variables.local.sh file)" && exit 1
fi

# Function to extract last value from JSON object passed via STDIN.
extract_json_last_value() {
  local key=$1
  php -r '$data=json_decode(file_get_contents("php://stdin"), TRUE); $last=array_pop($data); isset($last["'$key'"]) ? print $last["'$key'"] : exit(1);'
}

LATEST_BACKUP=0
if [ "$DEPLOY_API_DB_BACKUP_ID" == "" ] ; then
  echo "==> Discovering latest backup id for DB $DEPLOY_API_DB_NAME"
  BACKUPS_JSON=$(curl --progress-bar -L -u $DEPLOY_API_USER_NAME:$DEPLOY_API_USER_PASS https://cloudapi.acquia.com/v1/sites/$DEPLOY_API_DB_SITE/envs/$DEPLOY_API_DB_ENV/dbs/$DEPLOY_API_DB_NAME/backups.json)
  # Acquia response has all backups sorted chronologically by created date.
  DEPLOY_API_DB_BACKUP_ID=$(echo $BACKUPS_JSON | extract_json_last_value "id")
  [ "$DEPLOY_API_DB_BACKUP_ID" == "" ] && echo "Unable to discover backup id" && exit 1
  LATEST_BACKUP=1
fi

# Insert backup id as a suffix
DB_DUMP_EXT="${DB_DUMP##*.}"
DB_DUMP_FILENAME="${DB_DUMP%.*}"
DB_DUMP=${DB_DUMP_FILENAME}_${DEPLOY_API_DB_BACKUP_ID}.${DB_DUMP_EXT}
DB_DUMP_DISCOVERED=$DB_DUMP
DB_DUMP_COMPRESSED=$DB_DUMP.gz

if [ -f $DB_DUMP ] ; then
  echo "==> Found existing cached DB file $DB_DUMP for DB $DEPLOY_API_DB_NAME"
else
  # If the gzip version exists, then we don't need to redownload it.
  if [ ! -f $DB_DUMP_COMPRESSED ] ; then
    [ ! -d $DB_DUMP_DIR ] && echo "==> Creating dump directory $DB_DUMP_DIR" && mkdir -p $DB_DUMP_DIR
    [ "$DEPLOY_API_DB_OLD_CACHE_REMOVE" == "1" ] && echo "==> Removing all previously cached DB dumps" && rm -Rf $DB_DUMP_DIR/${DB_DUMP_FILENAME}_*
    echo "==> Using latest backup id $DEPLOY_API_DB_BACKUP_ID for DB $DEPLOY_API_DB_NAME"
    echo "==> Downloading DB dump into file $DB_DUMP_COMPRESSED"
    curl --progress-bar -L -u $DEPLOY_API_USER_NAME:$DEPLOY_API_USER_PASS https://cloudapi.acquia.com/v1/sites/$DEPLOY_API_DB_SITE/envs/$DEPLOY_API_DB_ENV/dbs/$DEPLOY_API_DB_NAME/backups/$DEPLOY_API_DB_BACKUP_ID/download.json -o $DB_DUMP_COMPRESSED
  else
    echo "==> Found existing cached gzipped DB file $DB_DUMP_COMPRESSED for DB $DEPLOY_API_DB_NAME"
  fi
  if [ $DEPLOY_API_DB_DECOMPRESS_BACKUP != 0 ] ; then
    echo "==> Expanding DB file $DB_DUMP_COMPRESSED into $DB_DUMP"
    gunzip -c $DB_DUMP_COMPRESSED > $DB_DUMP
    DECOMPRESS_RESULT=$?
    rm $DB_DUMP_COMPRESSED
    [ ! -f $DB_DUMP ] || [ $DECOMPRESS_RESULT != 0 ] && echo "==> Unable to process DB dump file $DB_DUMP" && rm -f $DB_DUMP_COMPRESSED && rm -f $DB_DUMP exit 1
  fi
fi

if [ $LATEST_BACKUP != 0 ] ; then
  LATEST_SYMLINK=${DEPLOY_API_DB_NAME}_latest.${DB_DUMP_EXT}
  if [ -f $DB_DUMP ] ; then
    echo "==> Creating symlink $(basename $DB_DUMP) => $LATEST_SYMLINK"
    (cd $DB_DUMP_DIR && rm -f $LATEST_SYMLINK && ln -s $(basename $DB_DUMP) $LATEST_SYMLINK)
  fi
  LATEST_SYMLINK=$LATEST_SYMLINK.gz
  if [ -f $DB_DUMP_COMPRESSED ] ; then
    echo "==> Creating symlink $(basename $DB_DUMP_COMPRESSED) => $LATEST_SYMLINK"
    (cd $DB_DUMP_DIR && rm -f $LATEST_SYMLINK && ln -s $(basename $DB_DUMP_COMPRESSED) $LATEST_SYMLINK)
  fi
fi
SECONDS=$(date +%s)
SELF_ELAPSED_TIME=$(($SECONDS - $SELF_START_TIME))
echo "==> Build duration: $(($SELF_ELAPSED_TIME/60)) min $(($SELF_ELAPSED_TIME%60)) sec"
