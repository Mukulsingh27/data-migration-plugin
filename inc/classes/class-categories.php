<?php
/**
 * WP-CLI command to migrate categories.
 *
 * @package ms-migration
 */

namespace MS\Migration\Inc;

/**
 * Class for migrating categories.
 */
class Categories extends Migrate {

	/**
	 * Category table name.
	 */
	const CATEGORIES_TABLE = 'categories';

	/**
	 * Total Found categories.
	 *
	 * @var int
	 */
	private int $total_found = 0;

	/**
	 * Total added categories.
	 *
	 * @var int
	 */
	private int $total_added = 0;

	/**
	 * Total Failed categories.
	 *
	 * @var int
	 */
	private int $total_failed = 0;

	/**
	 * Total Skipped categories.
	 *
	 * @var int
	 */
	private int $total_skipped = 0;

	/**
	 * WP-CLI command to migrate categories.
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
	 * [--logs]
	 * : Whether or not to show logs.
	 * ---
	 * default: false
	 * options:
	 *   - true
	 *   - false
	 *
	 * [--offset=<number>]
	 * : starting from offset
	 * ---
	 * default: 0
	 * 
	 * [--batch=<number>]
	 * : Number of rows to process at a time.
	 * ---
	 * default: 200
	 *
	 * [--log-file=<file>]
	 * : Path to the log file.
	 *
	 * ## EXAMPLE
	 * 
	 *      wp ms-migrate-categories migrate --offset=0 --batch=200 --dry-run=false --log-file=./log.txt
	 *
	 * @param array $args       Store all the positional arguments.
	 * @param array $assoc_args Store all the associative arguments.
	 */
	public function migrate( $args = [], $assoc_args = [] ) {
		// Starting time of the script.
		$start_time = time();

		// Extract arguments.
		$this->extract_args( $assoc_args );

		// Offset for the query.
		$offset = ! empty( $assoc_args[ 'offset' ] ? intval( $assoc_args[ 'offset' ] ) : 0 );

		// Batch size for the query.
		$batch  = ! empty( $assoc_args['batch'] ) ? intval( $assoc_args['batch'] ) : 200;

		// Start migration.
		$this->start_migration();

		// Get total categories.
		$total_count = $this->get_total_categories();

		// Progressbar.
		if ( empty( $this->logs ) ) {
			$progress = \WP_CLI\Utils\make_progress_bar( __( 'Category Migration', 'ms-migration' ), $total_count, 10 );
		}

		// Print starting migration script.
		$this->write_log(__( 'Starting migration of categories...', 'ms-migration' ) );

		do {
			$count = 0;
			$categories  = $this->get_categories( $offset, $batch );

			foreach ( $categories as $category ) {
				if ( ! empty( $progress ) && empty( $this->logs ) ) {
					$progress->tick();
				}

				$this->process_categories( $category );
				$count++;
			}

			// Increment in total found.
			$this->total_found += $count;

			// offset increment.
			$offset += $batch;

			// Sleep after every batch.
			sleep( 1 );

			$this->stop_the_insanity();

		} while ( ! empty( $categories ) && $count === $batch );

		// Add a blank line to separate Overall result.
		$this->write_log( '' );

		// Print total number of categories.
		$this->write_log(
			sprintf(
				__( '%s: There are total %d number of categories', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration' ) : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_found
			)
		);

		// Print total number of categories added.
		$this->write_log(
			sprintf(
				__( '%s: Total %d number of categories which were added', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration' ) : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_added
			)
		);

		// Print total number of categories skipped.
		$this->write_log(
			sprintf(
				__( '%s: Total %d number of categories which were skipped', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration' ) : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_skipped
			)
		);

		// Print total number of categories failed.
		$this->write_log(
			sprintf(
				__( '%s: Total %d number of categories which were failed', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration' ) : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_failed
			),
		);

		// Print total time taken by the script.
		$this->write_log(
			sprintf(
				__( 'Total time taken by this migration script: %s', 'ms-migration' ),
				human_time_diff( $start_time, time() )
			)
		);

		// Finish progressbar.
		if ( ! empty( $progress ) && empty( $this->logs ) ) {
			$progress->finish();
		}

		// End migration.
		$this->end_migration();
	}

	/**
	 * Process category data.
	 *
	 * @param array $row $row data.
	 *
	 * @return void
	 */
	private function process_categories( array $row ): void {
		// Flush cache.
		wp_cache_flush();

		// Check if category already exists.
		$term = get_term_by( 'name', $row['name'], 'category' );

		if ( $term ) {
			if ( ! empty( $this->dry_run ) ) {
				$this->write_log(
					sprintf(
						__( 'Dry-run: ID:%d %s category already exists, therefore skipping..', 'ms-migration' ),
						$row[ 'id' ],
						$row[ 'name' ]
					)
				);
				$this->total_skipped++;
				return;
			} else {
				$this->write_log(
					sprintf(
						__( 'ID:%d %s category already exists, therefore skipped', 'ms-migration' ),
						$row[ 'id' ],
						$row[ 'name' ]
					)
				);
				$this->total_skipped++;
				return;
			}
		}

		if ( $this->dry_run ) {
			$this->write_log(
				sprintf(
					__( 'Dry-run: ID:%d %s category will be added', 'ms-migration' ),
					$row[ 'id' ],
					$row[ 'name' ]
				)
			);
			$this->total_added++;
		} else {
			// insert data in terms.
			$row_data = wp_insert_term( $row['name'], 'category' );

			if ( is_wp_error( $row_data ) ) {
				$this->warning(
					sprintf(
						__( 'Old Category ID:%d category %s failed to insert!', 'ms-migration' ),
						$row[ 'id' ],
						$row[ 'name' ]
					)
				);
				$this->total_failed++;
				return;
			} else {
				$row_id = $row_data[ 'term_id' ];

				$this->success(
					sprintf(
						__( 'Old category ID:%d WP term ID:%d category %s inserted. Updating meta...', 'ms-migration' ),
						$row[ 'id' ],
						$row_id,
						$row[ 'name' ]
					)
				);
			}
			$this->total_added++;
		}
	}

	/**
	 * Get total categories.
	 *
	 * @return int
	 */
	private function get_total_categories() : int {
		$count_query = sprintf('SELECT count(id) FROM %s limit 1', self::CATEGORIES_TABLE);
		$total_count = $this->get_sql_server_data( $count_query, true );
		$total_count = array_pop( $total_count );

		return $total_count;
	}

	/**
	 * Get categories.
	 *
	 * @param int $offset Offset.
	 * @param int $batch  Batch.
	 *
	 * @return array
	 */
	private function get_categories( int $offset, int $batch ) : array {
		// Query to get categories.
		$query = sprintf(
			'SELECT categories.* FROM %s ORDER BY id ASC LIMIT %d OFFSET %d',
			self::CATEGORIES_TABLE,
			$batch,
			$offset
		);
		$categories = $this->get_sql_server_data( $query );

		return $categories;
	}
}
