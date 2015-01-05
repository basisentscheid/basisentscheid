#!/bin/bash
# write the database schema to basisentscheid.sql
#
# Usage: dump_schema.sh

path=$( readlink -f $( dirname $0 ) )

# get configuration
dbname=$( php -r '
	const DOCROOT = "'$path'/../";
	require DOCROOT."inc/config.php";
    preg_match("/dbname=(\S+)/", DATABASE_CONNECT, $matches);
    echo $matches[1];
' )
dbuser=$( php -r '
    const DOCROOT = "'$path'/../";
    require DOCROOT."inc/config.php";
    preg_match("/user=(\S+)/",   DATABASE_CONNECT, $matches);
    echo $matches[1];
' )

pg_dump --username=$dbuser --schema-only --schema=public --no-owner --no-privileges $dbname > $path/basisentscheid.sql
