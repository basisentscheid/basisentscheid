
requirements:
- PHP >= 5.4 with modules: apache2, cli, postgres, gettext, session, curl, ssl, unicode
- PostgreSQL

recommended php.ini settings for cli:
- display_errors = Off   (otherwise the errors are displayed twice)


=== Installation ===

$ cd inc/
$ cp config_example.php config.php
$ vi config.php

$ cd ../db/
$ vi recreate_schema.sh
$ ./recreate_schema.sh



