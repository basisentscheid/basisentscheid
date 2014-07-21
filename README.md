
requirements:
- PHP >= 5.4 with modules: apache2, cli, postgres, gettext, session, curl, ssl, unicode
- PostgreSQL

recommended php.ini settings for cli:
- display_errors = Off   (otherwise the errors are displayed twice)


=== Installation ===

$ psql --username=basisentscheid basisentscheid < db/basisentscheid.sql

$ cp inc/config_example.php inc/config.php
$ vi inc/config.php
