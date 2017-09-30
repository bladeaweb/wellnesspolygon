#!/usr/bin/env bash

####
# This script imports a copy of the database from D6 and imports it as a migration source.
# /var/www/html/$AH_SITE_GROUP.$AH_SITE_ENVIRONMENT/scripts/import-migration-source.sh
####

FILE_DATE=$(date +%Y-%m-%d)
ACQUIA_CONFIG_JSON_PATH=${ACQUIA_CONFIG_JSON_PATH:-"/var/www/site-php/$AH_SITE_GROUP.$AH_SITE_ENVIRONMENT/config.json"}
SQL_DUMP_PATH=${SQL_DUMP_PATH:-$HOME/$AH_SITE_ENVIRONMENT/files-private/migration-src}
SQL_DUMP_FILE_NAME=${SQL_DUMP_FILE_NAME:-${FILE_DATE}_migration_source.sql.gz}
MIGRATION_DB_ALIAS=${MIGRATION_DB_ALIAS:-vicpoly_d6_source}
MIGRATION_DB_NAME=${MIGRATION_DB_NAME:-$(php -r "echo json_decode(file_get_contents(\"$ACQUIA_CONFIG_JSON_PATH\"), TRUE)['databases']['$MIGRATION_DB_ALIAS']['name'];")}
MIGRATION_DB_USER=${MIGRATION_DB_USER:-$(php -r "echo json_decode(file_get_contents(\"$ACQUIA_CONFIG_JSON_PATH\"), TRUE)['databases']['$MIGRATION_DB_ALIAS']['user'];")}
MIGRATION_DB_PASS=${MIGRATION_DB_PASS:-$(php -r "echo json_decode(file_get_contents(\"$ACQUIA_CONFIG_JSON_PATH\"), TRUE)['databases']['$MIGRATION_DB_ALIAS']['pass'];")}
MIGRATION_DB_HOST=${MIGRATION_DB_HOST:-$(php -r "echo key(json_decode(file_get_contents(\"$ACQUIA_CONFIG_JSON_PATH\"), TRUE)['databases']['$MIGRATION_DB_ALIAS']['db_url_ha']);")}

# Get the database backup if there's no cached copy.
if [ ! -d "$SQL_DUMP_PATH" ]; then
  mkdir -p "$SQL_DUMP_PATH";
fi
if [ ! -f "$SQL_DUMP_PATH/$SQL_DUMP_FILE_NAME" ]; then
    printf '[%(%F %T)T] %s\n' -1 "Downloading migration source SQL dump."
    curl -s -u 'vicpolymigration:Uy@NybZFmOBC&6lz1FN6' https://d6web.vu.edu.au/sites/default/files/D6MigrationSrc/vicpoly.sql.gz > "$SQL_DUMP_PATH/$SQL_DUMP_FILE_NAME"
fi

# Make sure we have a database to import into.
echo "CREATE DATABASE IF NOT EXISTS $MIGRATION_DB_NAME" | mysql -h$MIGRATION_DB_HOST -u$MIGRATION_DB_USER -p$MIGRATION_DB_PASS $MIGRATION_DB_NAME

printf '[%(%F %T)T] %s\n' -1 "Importing dump into $MIGRATION_DB_NAME DB"
gunzip -c "$SQL_DUMP_PATH/$SQL_DUMP_FILE_NAME" | mysql -h$MIGRATION_DB_HOST -u$MIGRATION_DB_USER -p$MIGRATION_DB_PASS $MIGRATION_DB_NAME
