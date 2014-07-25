#!/bin/bash

pg_dump --username=basisentscheid --schema-only --schema=public --no-owner --no-privileges basisentscheid > basisentscheid.sql
