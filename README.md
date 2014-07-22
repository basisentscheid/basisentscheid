
requirements:
- PHP >= 5.4 with modules: apache2, cli, postgres, gettext, session, curl, ssl, unicode
- PostgreSQL

recommended php.ini settings for cli:
- display_errors = Off   (otherwise the errors are displayed twice)

Portal
======

## Installation

$ psql --username=basisentscheid basisentscheid < db/basisentscheid.sql

$ cp inc/config_example.php inc/config.php
$ vi inc/config.php


Portal using Zend Framework
===========================

## Installation

1. Setup database as above
2. Initialize git submodules (`git submodules init && git submodules update`)
3. Configure your vhost to serve `zendportal/public`
4. Put your database credentials in `zendportal/config/autoload/local.php.dist`and save it as `zendportal/config/autoload/local.php`
