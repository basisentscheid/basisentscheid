#!/bin/bash

path=$( readlink -f $( dirname $0 ) )

pg_dump --username=basisentscheid --schema-only --schema=public --no-owner --no-privileges basisentscheid > $path/basisentscheid.sql
