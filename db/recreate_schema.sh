#!/bin/bash

dbname=basisentscheid
dbuser=basisentscheid

echo "DROP SCHEMA public CASCADE; CREATE SCHEMA public;" | psql --username=$dbuser -v ON_ERROR_STOP=1 $dbname && \
psql --username=$dbuser -q -f basisentscheid.sql $dbname
