<?php
/**
 * Base class for WP-CLI migrations.
 *
 * @package ms-migration
 */

namespace MS\Migration\Inc;

/**
 * Class Migrate
 */
class Migrate {
	/**
	 * Log file.
	 *
	 * @var string Log file.
	 */
	public $log_file = '';

	/**
	 * Associative arguments.
	 *
	 * @var array
	 */
	protected $assoc_args = [];

	/**
	 * Dry run command.
	 *
	 * @var bool
	 */
	public $dry_run = true;

	/**
	 * DB connection.
	 *
	 * @var $connection
	 */
	public $connection;

	/**
	 * Logs to show or hide.
	 *
	 * @var bool
	 */
	public $logs = false;

	/**
	 * Query statement.
	 *
	 * @var $statement
	 */
	protected $statement = null;

	/**
	 * Function to extract arguments.
	 *
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return bool
	 */
	protected function extract_args( $assoc_args ) {
		$this->assoc_args = $assoc_args;

		if (empty( $assoc_args ) ) {
			return false;
		}

		if (! empty( $assoc_args[ 'log-file'] ) ) {
			$this->log_file = $assoc_args[ 'log-file' ];
		}

		if (isset( $assoc_args[ 'logs' ]) ) {
			$this->logs = empty( $assoc_args[ 'logs' ] ) || 'true' === $assoc_args[' logs' ];
		} else {
			$this->logs = false;
		}

		if (isset( $assoc_args[ 'dry-run' ] ) ) {
			$this->dry_run = empty( $assoc_args[ 'dry-run' ] ) || 'true' === $assoc_args[ 'dry-run' ];
		} else {
			$this->dry_run = true;
		}
	}

	/**
	 * Method to add a log entry and to output message on screen
	 *
	 * @param string $message      Message to add to log and to output on screen.
	 * @param int    $message_type Message type - 0 for normal line, -1 for error, 1 for success, 2 for warning.
	 *
	 * @return void
	 */
	protected function write_log( $message, $message_type = 0 ) {
		// Backward compatibility.
		if ( true === $message_type ) {
			// Error message.
			$message_type = -1;
		} elseif ( false === $message_type ) {
			// Simple message.
			$message_type = 0;
		}

		$message_type = intval( $message_type );

		$message_prefix = '';

		// Message prefix for use in log file.
		switch ( $message_type ) {

		case -1:
			$message_prefix = 'Error: ';
			break;

		case 1:
			$message_prefix = 'Success: ';
			break;

		case 2:
			$message_prefix = 'Warning: ';
			break;
		}

		// Log message to log file if a log file.
		if (! empty( $this->log_file) ) {
		 file_put_contents( $this->log_file, $message_prefix . $message . "\n", FILE_APPEND );
		}

		if ( $this->logs ) {
			switch ( $message_type ) {

			case -1:
				\WP_CLI::error( $message );
				break;

			case 1:
				\WP_CLI::success( $message );
				break;

			case 2:
				\WP_CLI::warning( $message );
				break;

			case 0:
			default:
				\WP_CLI::line( $message );
				break;
			}
		}
	}

	/**
	 * Method to log an error message and stop the script from running further
	 *
	 * @param string $message Message to add to log and to output on screen.
	 *
	 * @return void
	 */
	protected function error( $message ) {
		$this->write_log( $message, -1 );
	}

	/**
	 * Method to log a success message
	 *
	 * @param string $message Message to add to log and to output on screen.
	 *
	 * @return void
	 */
	protected function success( $message ) {
		$this->write_log( $message, 1 );
	}

	/**
	 * Method to log a warning message
	 *
	 * @param string $message Message to add to log and to output on screen.
	 *
	 * @return void
	 */
	protected function warning( $message ) {
		$this->write_log( $message, 2 );
	}

	/**
	 * Define constants and call functions to make migration fast.
	 */
	protected function start_migration() {
		// no cache.
		wp_suspend_cache_addition( true );

		// suspend cache invalidation.
		wp_suspend_cache_invalidation( true );

		// don't save queries.
		// recount terms.
		wp_defer_term_counting( true );

		// recount comments.
		wp_defer_comment_counting( true );

		if (! defined( 'SAVEQUERIES' ) ) {
			define( 'SAVEQUERIES', false );
		} elseif ( true === SAVEQUERIES ) {
			\WP_CLI::error( 'Disable WordPress Debug plugins like develop query monitor etc.' );
		}

		if (! defined('WP_POST_REVISIONS') ) {
			define('WP_POST_REVISIONS', false);
		} elseif ( true === WP_POST_REVISIONS ) {
			\WP_CLI::error( 'Disable Post Revisions to speed up migration script & avoid unnecessary post creation.' );
		}

		if (! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}

		/**
		 * Call this method if you're migrating data from MSSQL database.
		 *
		 * $this->connect_database();
		 */
		$this->connect_database();
	}

	/**
	 * Rollback changes after migration.
	 */
	protected function end_migration() {
		/**
		 * Call this method if you're migrating data from MSSQL database.
		 */
		$this->connection->close();

		// If Success indicate on CLI.
		\WP_CLI::success('Connection closed successfully!');

		wp_cache_flush();

		foreach ( get_taxonomies() as $tax ) {
			delete_option("{$tax}_children");
			_get_term_hierarchy( $tax );
		}

		wp_suspend_cache_addition( false );

		// suspend cache invalidation.
		wp_suspend_cache_invalidation( false );

		// don't save queries.
		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );
	}

	/**
	 * Establish connection to MSSQL database.
	 *
	 * Note: This method is to connect with MSSQL database.
	 */
	protected function connect_database() {

		// Define all constants in wp-config file to connect.
		$server_name = defined( 'MS_MIGRATION_SERVER_NAME' ) ? MS_MIGRATION_SERVER_NAME : ''; // IP comma port no space.

		if ( empty($server_name ) ) {
			return;
		}

		$username = defined( 'MS_MIGRATION_USER_ID' ) ? MS_MIGRATION_USER_ID : ''; // User ID.
		$dbname   = defined( 'MS_MIGRATION_DB_NAME' ) ? MS_MIGRATION_DB_NAME : ''; // Database Name.
		$dbpass   = defined( 'MS_MIGRATION_USER_PASS' ) ? MS_MIGRATION_USER_PASS : ''; // User Password.

		// Establishes the connection.
		$this->connection = new \mysqli($server_name, $username, $dbpass, $dbname);

		// If Success indicate on CLI.
		if ( ! $this->connection->connect_errno ) {
			\WP_CLI::success('Connected successfully!');
		}

		// Check connection
		if ( $this->connection->connect_errno ) {
			echo 'Failed to connect to MySQL: ' . $this->connection->connect_error;
			exit();
		}
		$this->connection->set_charset( 'utf8' );
	}

	/**
	 * Get rows from query or statement.
	 *
	 * Note: This method is to execute SQL queries on MSSQL database and fetch data.
	 *
	 * @param string $query  Query string.
	 * @param bool   $single Return single value or array.
	 * @param int    $trial  Retry number for connection.
	 *
	 * @return array|false|null
	 */
	protected function get_sql_server_data( $query = '', $single = false, $trial = 1 ) {
		if ( ! empty( $query ) ) {
			// Executes the query.
			$this->statement = $this->connection->query( $query );

			// Error handling.
			if ( false === $this->statement ) {

				$this->write_log( print_r( $this->connection->error, true ) );

				// Only try 3 times to reconnect.
				if ( 3 >= $trial ) {
					sleep( 15 );
					$trial++;
					$this->write_log( 'Lets try again!' );
					$this->get_sql_server_data( $query, $single, $trial );

				} elseif ( 4 === $trial ) {
					$this->write_log( 'Connection refused too many times. Please run command again from last Offset.' );
					exit;

				}
			}
		}

		if ( $single ) {
			$rows = $this->statement->fetch_array( MYSQLI_ASSOC );
		} else {
			$rows = $this->statement->fetch_all( MYSQLI_ASSOC );
		}

		$this->statement->free_result();

		return $rows;
	}

	/**
	 * Resets some values to reduce memory footprint.
	 */
	protected function stop_the_insanity() {
		/**
		 * @var object $wp_object_cache
		 * @var object $wpdb
		 */
		global $wpdb, $wp_object_cache;

		$wpdb->queries = [];

		if ( is_object( $wp_object_cache ) ) {

			$wp_object_cache->group_ops      = [];
			$wp_object_cache->stats          = [];
			$wp_object_cache->memcache_debug = [];
			$wp_object_cache->cache          = [];

			if ( method_exists($wp_object_cache, '__remoteset' ) ) {
				$wp_object_cache->__remoteset(); // important
			}
		}
	}

		/**
	 * Update post date and modified date while inserting or updating.
	 *
	 * Note: WP take current time as modified time when you insert/update post.
	 * But while migrating content we need to keep existing modified date.
	 *
	 * If CMS save date in GMT then
	 * 1. Save that date as 'post_date_gmt'
	 * 2. Get site timezone date from GMT date using WP function 'get_date_from_gmt()' for 'post_date'.
	 *
	 *    $data['post_date'] = get_date_from_gmt( $postarr['post_date'], 'Y-m-d H:i:s' );
	 *
	 * If CMS save date in site timezone then
	 * 1. Save that date as 'post_date'
	 * 2. Get site timezone date from GMT date using WP function 'get_gmt_from_date()' for 'post_date_gmt'.
	 *
	 *    $data['post_date_gmt'] = get_gmt_from_date( $postarr['post_date'], 'Y-m-d H:i:s' );
	 *
	 * You can convert date from UTC to site timezone or site timezone to UTC.
	 * Because some CMS save date in UTC. So you need to get date for site timezone from UTC date.
	 * OR get UTC time
	 *
	 * @param array $data    An array of slashed post data.
	 * @param array $postarr An array of sanitized, but otherwise unmodified post data.
	 *
	 * @return array
	 */
	public function alter_modification_date( $data, $postarr ) {

		// Update post_date field of given post.
		if ( ! empty( $postarr['post_date'] ) ) {
			$data['post_date_gmt'] = get_gmt_from_date( $postarr['post_date'], 'Y-m-d H:i:s' );
			$data['post_date']     = $postarr['post_date'];
		}

		// Update post_modified field of given post.
		if ( ! empty( $postarr['post_modified'] ) ) {
			$data['post_modified_gmt'] = get_gmt_from_date( $postarr['post_modified'], 'Y-m-d H:i:s' );
			$data['post_modified']     = $postarr['post_modified'];
		}

		return $data;
	}
}
