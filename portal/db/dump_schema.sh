#!/bin/bash

pg_dump --username=basisentscheid --schema-only --no-owner --no-privileges basisentscheid > basisentscheid.sql
