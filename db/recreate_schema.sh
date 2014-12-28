#!/bin/bash
# recreate the database schema
#
# Usage: recreate_schema.sh [<data.sql>|-]
# - If data.sql is supplied, it will replace the existing content.
# - If the argument is a dash, the new schema will be left empty.
# - data.sql may also be compressed as data.sql.bz2 or data.sql.gz.

# exit on error
set -e

path=$( readlink -f $( dirname $0 ) )

# get configuration
dbname=$( php -r '
	const DOCROOT = "'$path'/../";
	require DOCROOT."inc/config.php";
    preg_match("/dbname=(\w+)/", DATABASE_CONNECT, $matches);
    echo $matches[1];
' )
dbuser=$( php -r '
    const DOCROOT = "'$path'/../";
    require DOCROOT."inc/config.php";
    preg_match("/user=(\w+)/",   DATABASE_CONNECT, $matches);
    echo $matches[1];
' )

tmpsql="$path/tmp_recreate_schema_$( date +%Y-%m-%d_%H-%I-%S ).sql"

datasql=$1
if [ -z "$datasql" ]
then
  datasql=$tmpsql
elif [ "$datasql" != "-" -a ! -r "$datasql" ]
then
  echo "Supplied data file not found or not readable!"
  exit 1
fi

# show commands
set -x

pg_dump --username=$dbuser --data-only $dbname > $tmpsql

echo "DROP SCHEMA public CASCADE; CREATE SCHEMA public;" | psql --username=$dbuser -v ON_ERROR_STOP=1 $dbname
psql --username=$dbuser -q -f $path/basisentscheid.sql $dbname

case "$datasql" in
  -)
    # leave schema empty
    ;;
  *.bz2)
    bunzip2 -c $datasql | psql --username=$dbuser -v ON_ERROR_STOP=1 -q $dbname
    ;;
  *.gz)
    gunzip  -c $datasql | psql --username=$dbuser -v ON_ERROR_STOP=1 -q $dbname
    ;;
  *)
    psql -f $datasql           --username=$dbuser -v ON_ERROR_STOP=1 -q $dbname
esac
