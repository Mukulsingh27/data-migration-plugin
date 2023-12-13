<?php
/**
 * WP-CLI command to migrate Posts.
 *
 * @package ms-migration
 */

namespace MS\Migration\Inc;

/**
 * Class for migrating posts.
 */
class Posts extends Migrate {
	/**
	 * Article table name.
	*/
	const ARTICLES_TABLE = 'articles';

	/**
	 * Total Found articles.
	 *
	 * @var int
	 */
	private int $total_found = 0;

	/**
	 * Total updated articles.
	 *
	 * @var int
	 */
	private int $total_update = 0;

	/**
	 * Total added articles.
	 *
	 * @var int
	 */
	private int $total_added = 0;

	/**
	 * Total Failed articles.
	 *
	 * @var int
	 */
	private int $total_failed = 0;

	/**
	 * Total Skipped articles.
	 *
	 * @var int
	 */
	private int $total_skipped = 0;

	/**
	 * Stores Type of current type migration.
	 *
	 * @var string
	 */
	private string $type = 'Post';

	/**
	 * WP-CLI command to migrate posts.
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
	 * [--logs]
	 * : Whether to not show logs.
	 * ---
	 * default: true
	 * options:
	 *   - true
	 *   - false
	 *
	 * ## EXAMPLES
	 *
	 *      wp ms-migrate-posts migrate --offset=0 --batch=200 --dry-run=false --log-file=./log.txt
	 *
	 * @subcommand migrate
	 *
	 * @param array $args       Store all the positional arguments.
	 * @param array $assoc_args Store all the associative arguments.
	 */
	public function migrate( $args = [], $assoc_args = [] ) {
		// Starting time of the script.
		$start_time = time();

		$this->extract_args( $assoc_args );

		// Offset for the query.
		$offset = ! empty( $assoc_args[ 'offset' ] ? intval( $assoc_args[ 'offset' ] ) : 0 );

		// Batch size for the query.
		$batch  = ! empty( $assoc_args['batch'] ) ? intval( $assoc_args['batch'] ) : 200;

		// Fetch users and categories.
		$this->fetch_users();
		$this->fetch_categories();

		// Start migration.
		$this->start_migration();

		// Get total articles.
		$total_count = $this->get_total_articles();

		// Progressbar.
		if ( empty( $this->logs ) ) {
			// Creating progressbar.
			$progress = \WP_CLI\Utils\make_progress_bar( __( 'Articles Migration', 'ms-migration' ), $total_count, 10) ;
		}

		// Print starting migration script.
		$this->write_log( __( 'Starting migration of articles...', 'ms-migration' ) );

		do {
			$count = 0;
			$articles = $this->get_articles( $offset, $batch );

			foreach ( $articles as $article ) {
				if (! empty( $progress ) && empty( $this->logs ) ) {
					$progress->tick();
				}

				$this->process_articles( $article );
				$count++;
			}

			// Increment in total found.
			$this->total_found += $count;

			// offset increment.
			$offset += $batch;

			// Sleep after every batch.
			sleep( 1 );

			$this->stop_the_insanity();

		} while ( ! empty( $articles ) && $count === $batch );

		// Add a blank line to separate Overall result.
		$this->write_log( '' );

		// Print total number of posts.
		$this->write_log(
			sprintf(
				__( '%s: There are total %d number of posts', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration') : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_found
			)
		);

		// Print total number of posts added.
		$this->write_log(
			sprintf(
				__( '%s: Total %d number of posts which were added', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration') : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_added
			)
		);

		// Print total number of posts updated.
		$this->write_log(
			sprintf(
				__( '%s: Total %d number of posts which were updated', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration') : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_update
			)
		);

		// Print total number of posts skipped.
		$this->write_log(
			sprintf(
				__( '%s: Total %d number of posts which were skipped', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration') : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_skipped
			)
		);

		// Print total number of posts failed.
		$this->write_log(
			sprintf(
				__( '%s: Total %d number of posts which were failed', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration') : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_failed
			),
		);

		// Print total time taken by the script.
		$this->write_log(
			sprintf(
				__( 'Total time taken by this migration script: %s', 'ms-migration' ),
				human_time_diff($start_time, time())
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
	 * Process posts data and insert/update.
	 *
	 * @param array $row $row data.
	 *
	 * @return void
	 */
	private function process_articles( array $row ): void {
		// Flush cache.
		wp_cache_flush();

		// Post data.
		$post_data = [
			'post_title'    => ! empty( $row[ 'title' ] ) ? $row[ 'title' ] : '',
			'post_modified' => ! empty( $row[ 'updated' ] ) ? $row[ 'updated' ] : '',
			'post_date'     => ! empty( $row[ 'added' ] ) ? $row[ 'added' ] : '',
			'post_content'  => ! empty( $row[ 'html' ] ) ? $row[ 'html' ] : '',
			'post_type'     => isset( $row[ 'type' ] ) ? $row[ 'type' ] : 'post',
		];

		// Assign Category.
		if ( ! empty( $row[ 'category' ] ) ) {
			$post_data['post_category'][] = $this->categories[ intval( $row['category'] ) ];
		}

		// Assign Author.
		if ( ! empty( $row['author'] ) ) {
			$post_data['post_author'] = $this->users[ intval( $row['author'] ) ];
		}

		// Store legacy post data.
		$post_data['meta_input'] = [
			'_legacy_article_data' => $row,
			'_old_article_id'      => $row['id'],
		];

		// Check already exists post.
		$post_exists_data = get_post( $row[ 'id' ]);
		$post_exists      = ! ( null === $post_exists_data );

		if ( true === $post_exists ) {
			// Check for update.
			$old_updated_at = get_post_modified_time( 'Y-m-d H:i:s', true, $post_exists_data->ID );

			if ( ! empty( $row[ 'updated' ] ) && $row[ 'updated' ] === $old_updated_at ) {
				// Same post.
				$this->warning(sprintf(__( 'Post %d has already exists.', 'ms-migration' ), $row[ 'id' ] ) );
				$this->write_log( '' );
				$this->total_skipped++;
				return;
			}

			$post_data[ 'ID' ] = $post_exists_data->ID;
		} else {
			$post_data[ 'import_id' ] = $row[ 'id' ];
		}

		// Post status
		$post_data[ 'post_status' ] = match ( $row[ 'status' ] ) {
			'Draft'  => 'draft',
			'Trash'  => 'trash',
			default  => 'publish', // published.
		};

		if ( $this->dry_run ) {
			$this->write_log(
				sprintf(
					__( 'Dry-run: ID:%d post will be migrated', 'ms-migration' ),
					$row[ 'id' ]
				)
			);
			$this->total_added++;
		} else {
			// add filter to modify 'post_modified' field which will not work with 'wp_insert_post'.
			add_filter( 'wp_insert_post_data', [ $this, 'alter_modification_date' ], 10, 2 );

			$post = wp_insert_post( $post_data, true );

			// remove added filter after post insertion.
			remove_filter( 'wp_insert_post_data', [ $this, 'alter_modification_date' ], 10 );

			if (is_wp_error( $post ) ) {
				$this->warning( $post->get_error_message() );
				$this->total_failed++;

			} else {
				if ( ! empty( $post_data[ 'ID' ] ) ) {
					$this->success( sprintf( __( 'Successfully Updated %s %d.', 'ms-migration' ), $this->type, $post_data['ID'] ) );
					$this->total_update++;

				} else {
					$this->success( sprintf( __( 'Successfully Migrated %s %d.', 'ms-migration' ), $this->type, $post ) );
					$this->total_added++;
				}
			}
		}
	}

	/**
	 * Get total articles.
	 *
	 * @return int
	 */
	private function get_total_articles() : int {
		// Count query.
		$count_query = sprintf( 'SELECT count(id) FROM %s limit 1', self::ARTICLES_TABLE );
		$total_count = $this->get_sql_server_data( $count_query, true );
		$total_count = array_pop( $total_count );

		return $total_count;
	}

	/**
	 * Get articles.
	 *
	 * @param int $offset Offset.
	 * @param int $batch  Batch.
	 *
	 * @return array
	 */
	private function get_articles( int $offset, int $batch ) : array {
		// Query to get articles.
		$query = sprintf(
			'SELECT articles.* FROM %s ORDER BY id ASC LIMIT %d OFFSET %d',
			self::ARTICLES_TABLE,
			$batch,
			$offset
		);
		$articles = $this->get_sql_server_data( $query );

		return $articles;
	}
}