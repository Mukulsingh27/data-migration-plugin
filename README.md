# MS Migration

[![Project Status: Active – The project has reached a stable, usable state and is being actively developed.](https://www.repostatus.org/badges/latest/active.svg)](https://www.repostatus.org/#active)

Provides WP_CLI commands to migrate data to your new WordPress site using command line interface.

## Migration from MySQL

Define DB connection constants in wp-config.php.

~~~PHP
    define( 'MS_MIGRATION_SERVER_NAME', 'localhost' )
    define( 'MS_MIGRATION_DB_NAME', 'migration' );
    define( 'MS_MIGRATION_USER_ID', 'root' );
    define( 'MS_MIGRATION_USER_PASS', 'root' );
~~~

### Common parameters

Some common parameters for migration commands are as below.

-   logs[true/false]: Displays logs on screen or show progress bar.
-   dry-run[true/false]: Run command in dry-run mode.
-   log-file: File to add logs.

#### Example

`wp ms-migrate-posts migrate --logs=false --dry-run=false --batch-size=200 --log-file=posts.log`

### Migration Steps & Commands

-   Add connection contants to the wp-config file
-   Open the CLI and run specific commands to execute

### Available Migration Commands

-   wp ms-migrate-categories migrate
-   wp ms-migrate-users migrate
-   wp ms-migrate-posts migrate
-   wp ms-migrate cleanup
