#!/bin/bash
# backup all data except raw voting data
#
# Usage: backup_data.sh

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

backupfile="$path/backup_data_$( date +%Y-%m-%d_%H-%I-%S ).sql.bz2"

pg_dump --username=$dbuser --data-only --exclude-table="*_token" --exclude-table="*_vote" $dbname | bzip2 -c > $backupfile
