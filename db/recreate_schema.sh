#!/bin/bash
# delete all database content and recreate the schema

dbname=$( php -r 'define("DOCROOT", "../"); require DOCROOT."inc/config.php"; preg_match("/dbname=(\w+)/", DATABASE_CONNECT, $matches); echo $matches[1];' )
dbuser=$( php -r 'define("DOCROOT", "../"); require DOCROOT."inc/config.php"; preg_match("/user=(\w+)/",   DATABASE_CONNECT, $matches); echo $matches[1];' )

echo "DROP SCHEMA public CASCADE; CREATE SCHEMA public;" | psql --username=$dbuser -v ON_ERROR_STOP=1 $dbname && \
psql --username=$dbuser -q -f basisentscheid.sql $dbname
