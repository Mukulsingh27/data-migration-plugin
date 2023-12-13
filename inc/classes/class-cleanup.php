<?php
/**
 * WP-CLI command to cleanup migration data.
 *
 * @package ms-migration
 */

namespace MS\Migration\Inc;

class Cleanup extends Migrate {

	/**
	 * Batch size to delete.
	 *
	 * @var int
	 */
	var int $batch_size = 200;

	/**
	 * WP-CLI command to cleanup migration data.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Whether or not to do dry run.
	 * ---
	 * default: true
	 * options:
	 *   - true
	 *   - false
	 *
	 * [--log-file=<file>]
	 * : Path to the log file.
	 *
	 * ## EXAMPLES
	 *
	 *      wp ms-migrate cleanup --dry-run=false --log-file=./log.txt
	 *
	 * @subcommand cleanup
	 *
	 * @param array $args       Store all the positional arguments.
	 * @param array $assoc_args Store all the associative arguments.
	 */
	public function cleanup( array $args = [], array $assoc_args = [] ) {

		$this->start_migration();

		// Starting time of the script.
		$start_time = time();

		// Extract arguments.
		$this->extract_args( $assoc_args );

		$this->logs = true; // Default true as needs user interaction.

		if ( true === $this->dry_run ) {
			$this->warning( __( 'You have called cleanup command in dry run mode.', 'ms-migration' ) . "\n" );
		}

		// Call cleanup functions.
		$this->cleanup_article_data();
		$this->cleanup_category_data();
		$this->cleanup_user_data();

		$this->write_log( '' );
		$this->success( sprintf( __( 'Total time taken by this cleanup script: %s', 'ms-migration' ), human_time_diff( $start_time, time() ) ) . PHP_EOL );

		$this->end_migration();
		$this->stop_the_insanity();
	}

	/**
	 * Function to cleanup data.
	 *
	 * @param string $subject Post type to delete.
	 * @param string $msg Confirmation message before delete.
	 * @param array  $keys Meta keys.
	 * @param string $type Meta type to delete.
	 */
	private function cleanup_data( string $subject, string $msg, array $keys, string $type ) {
		$this->write_log( $subject . ' Data Cleanup!' );
		$input = $this->continue_execute( $msg );

		// Check input to process cleanup.
		if ( 'y' === $input ) {
			$this->delete_metas( $keys, $type );
		} else {
			$this->warning( $subject. ' meta delete ignored.' . "\n" );
		}
	}

	/**
	 * Function to clean article data.
	 */
	private function cleanup_article_data() {
		// Delete post meta.
		$msg  = __( 'Are you sure you want to delete _legacy_article_data and old_article_id post meta?', 'ms-migration' );
		$keys = [ '_legacy_article_data', '_old_article_id' ];

		$this->cleanup_data( 'Article', $msg, $keys, 'post' );
	}

	/**
	 * Function to clean up category data.
	 */
	private function cleanup_category_data() {
		// Delete category meta.
		$msg  = __( 'Are you sure you want to delete _legacy_category_data and old_category_id category meta?', 'ms-migration' );
		$keys = [ '_legacy_category_data', '_old_category_id' ];

		$this->cleanup_data( 'Category', $msg, $keys, 'term' );
	}

	/**
	 * Function to clean up User data.
	 */
	private function cleanup_user_data() {
		// Delete user meta.
		$msg  = __( 'Are you sure you want to delete _old_id and _legacy_user_data User meta?', 'ms-migration' );
		$keys = [ '_legacy_user_data', '_old_user_id' ];

		$this->cleanup_data( 'User', $msg, $keys, 'user' );

	}

	/**
	 * Function to delete meta for given keys.
	 *
	 * @param array  $keys Meta keys to delete.
	 * @param string $type Type of meta data.
	 */
	private function delete_metas( array $keys, string $type = 'post' ) {
		global $wpdb;

		$total_delete = 0;

		// Delete meta values for keys.
		foreach ( $keys as $key ) {
			if ( 'term' === $type ) {
				$sql = "DELETE FROM $wpdb->termmeta WHERE meta_key=%s";
			} elseif ( 'user' === $type ) {
				$sql = "DELETE FROM $wpdb->usermeta WHERE meta_key=%s";
			} else {
				$sql = "DELETE FROM $wpdb->postmeta WHERE meta_key=%s";
			}

			do {
				if ( true === $this->dry_run ) {
					$count = 0;
					$this->write_log( sprintf( __( 'Meta key %s data will be deleted!', 'ms-migration' ), $key ) );
				} else {
					$count = $wpdb->query( $wpdb->prepare( $sql . ' limit %d', $key, $this->batch_size ) );
					$total_delete += $count;
				}
			} while( $count !== 0 );
			$this->write_log( sprintf( __( 'Total %d records deleted for %s meta!', 'ms-migration' ), $total_delete, $key ) );
			sleep( 1 );
			$this->stop_the_insanity();
		}
	}
}
