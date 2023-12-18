<?php
/**
 * CLI command to migrate users.
 *
 * @package ms-migration
 */

namespace MS\Migration\Inc;

use WP_CLI;

/**
 * Class for migrating users.
 */
class Users extends Migrate {
	/**
	 * Users table name.
	 */
	const USERS_TABLE = 'users';

	/**
	 * Total Found user.
	 *
	 * @var int
	 */
	private int $total_found = 0;

	/**
	 * Total updated user.
	 *
	 * @var int
	 */
	private int $total_skipped = 0;

	/**
	 * Total added user.
	 *
	 * @var int
	 */
	private int $total_added = 0;

	/**
	 * Total Failed user.
	 *
	 * @var int
	 */
	private int $total_failed = 0;

	/**
	 * User emails.
	 *
	 * @var array
	 */
	private array $user_emails = [];

	/**
	 * To identify duplicate users.
	 *
	 * @var bool
	 */
	private bool $duplicate_user = false;

	/**
	 * WP-CLI command to migrate users.
	 *
	 * ## OPTIONS
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
	 * [--dry-run]
	 * : Whether or not to do dry run
	 * ---
	 * default: true
	 * options:
	 *  - true
	 *  - false
	 *
	 * [--logs]
	 * : Whether to not show logs.
	 * ---
	 * default: false
	 * options:
	 * - true
	 * - false
	 *
	 * [--log-file=<file>]
	 * : Path to the log file.
	 *
	 * ## EXAMPLES
	 *
	 *      wp ms-migrate-users migrate --offset=0 --batch=200 --dry-run=false --log-file=./log.txt
	 *
	 * @param array $args       Store all the positional arguments.
	 * @param array $assoc_args Store all the associative arguments.
	 */
	public function migrate( $args, $assoc_args ) : void {
		// Start time.
		$start_time = time();

		// Extract arguments.
		$this->extract_args( $assoc_args );

		// Offset for the query.
		$offset = ! empty( $assoc_args['offset'] ) ? intval( $assoc_args['offset'] ) : 0;

		// Batch size for the query.
		$batch = ! empty( $assoc_args['batch'] ) ? intval( $assoc_args['batch'] ) : 200;

		// Start migration.
		$this->start_migration();

		// Get total users.
		$total_users = $this->get_total_users();

		// Progressbar.
		if ( empty( $this->logs ) ) {
			$progress = WP_CLI\Utils\make_progress_bar( __( 'Users Migration', 'ms-migration' ), $total_users, 10 );
		}

		// Print starting migration script.
		$this->write_log( __( 'Starting Users Migration', 'ms-migration' ) );

		do {
			$count = 0;
			$users = $this->get_users( $offset, $batch );

			foreach ( $users as $user ) {
				if ( ! empty( $progress ) && empty( $this->logs ) ) {
					$progress->tick();
				}

				$this->process_user( $user );
				$count++;
			}

			// Increment in total found.
			$this->total_found += $count;

			// offset increment.
			$offset += $batch;

			// Sleep after every batch.
			sleep( 1 );

			$this->stop_the_insanity();

		} while ( ! empty( $users ) || $count === $batch );

		// Add a blank line to separate Overall result.
		$this->write_log( '' );

		// Print total number of user added.
		$this->write_log(
			sprintf(
				// translators: %1$s: Command Type, %2$d: Total number of users added.
				__( '%1$s: Total %2$d number of users which were added', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration' ) : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_added
			)
		);

		// Print total number of user skipped.
		$this->write_log(
			sprintf(
				// translators: %1$s: Command Type, %2$d: Total number of users skipped.
				__( '%1$s: Total %2$d number of users which were skipped', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration' ) : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_skipped
			)
		);

		// Print total number of user failed.
		$this->write_log(
			sprintf(
				// translators: %1$s: Command Type, %2$d: Total number of users failed.
				__( '%1$s: Total %2$d number of users which were failed', 'ms-migration' ),
				empty( $this->dry_run ) ? __( 'Migration Result', 'ms-migration' ) : __( 'Dry-Run Result', 'ms-migration' ),
				$this->total_failed
			)
		);

		$this->write_log(
			sprintf(
				// translators: %s: Total time taken by the script.
				__( 'Total time taken by this migration script: %s', 'ms-migration' ),
				human_time_diff( $start_time, time() )
			)
		);

		// Stop the progress bar.
		if ( ! empty( $progress ) && empty( $this->logs ) ) {
			$progress->finish();
		}

		// End migration.
		$this->end_migration();
	}

	/**
	 * Process user data.
	 *
	 * @param array $user User.
	 *
	 * @return void
	 */
	private function process_user( array $user ) : void {
		// Flush cache.
		wp_cache_flush();

		// Sanitize email.
		$sanitized_email = sanitize_email( $user['email'] );

		// Get user display name.
		$user_display_name = $this->get_user_display_name( $user );

		// Check for valid and non-empty email.
		if ( ! is_email( $sanitized_email ) ) {
			$this->warning(
				sprintf(
					// translators: %1$s: Command Type, %2$d: Old User ID, %3$s: User display name.
					__( '%1$s: Old User ID:%2$d user %3$s will not be added due to invalid email!', 'ms-migration' ),
					empty( $this->dry_run ) ? __( 'Migration', 'ms-migration' ) : __( 'Dry-Run', 'ms-migration' ),
					$user['id'],
					$user_display_name
				)
			);

			$this->total_failed++;
			return;
		}

		// Check for duplicate user entry.
		if ( in_array( $sanitized_email, $this->user_emails, true ) ) {
			$this->duplicate_user = true;
		} else {
			$this->duplicate_user = false;
		}

		// Check if user already exists.
		$user_id = $this->user_exists( $user['email'] );

		if ( $this->dry_run ) {
			if ( ! empty( $user_id ) ) {
				if ( ! $this->duplicate_user ) {
					$this->total_skipped++;
				}

				$this->success(
					sprintf(
						// translators: %1$d: Old User ID, %2$s: User display name.
						__( 'Dry-run: Old User ID:%1$d user %2$s will be skipped!', 'ms-migration' ),
						$user['id'],
						$user_display_name
					)
				);
			} else {
				if ( ! $this->duplicate_user ) {
					$this->total_added++;
				}

				$this->success(
					sprintf(
						// translators: %1$d: New User ID, %2$s: User display name.
						__( 'Dry-run: New User ID:%1$d user %2$s will be added!', 'ms-migration' ),
						$user['id'],
						$user_display_name
					)
				);
			}
			return;
		} else {
			// if user already exists then skip.
			if ( ! empty( $user_id ) ) {
				if ( ! $this->duplicate_user ) {
					$this->total_skipped++;
				}

				$this->success(
					sprintf(
						// translators: %1$d: Old User ID, %2$s: User display name.
						__( 'Old User ID:%1$d user %2$s skipped!', 'ms-migration' ),
						$user['id'],
						$user_display_name
					)
				);
				return;
			}
		}

		// Insert user.
		$user_id = $this->insert_user( $user, $user_id );
	}

	/**
	 * Insert user.
	 *
	 * @param array $user    User.
	 * @param int   $user_id User ID.
	 *
	 * @return int
	 */
	private function insert_user( array $user, int $user_id = null ) {
		$user_login = substr( $user['user_login'], 0, 60 );

		// If user login is empty or already exists then create new user login with first name, last name.
		if ( empty( $user_login ) || username_exists( $user_login ) ) {
			if ( ! empty( $user['first_name'] ) ) {
				$user_login = htmlspecialchars( strtolower( $user['first_name'] ) ) . '-';
			}

			if ( ! empty( $user['last_name'] ) ) {
				$user_login .= htmlspecialchars( strtolower( $user['last_name'] ) );
			}

			$user_login = sanitize_user( $user_login, true );
		}

		// Get user display name.
		$display_name = $this->get_user_display_name( $user );

		// Check if role exists and if not then create new role.
		if ( ! empty( $user['role'] ) && ! get_role( $user['role'] ) ) {
			$role = add_role( $user['role'], ucfirst( $user['role'] ), get_role( 'administrator' )->capabilities );
		} else {
			$role = $user['role'];
		}

		// User arguments.
		$user_args = [
			'user_login'   => $user_login,
			'user_email'   => $user['email'] ?? null,
			'display_name' => trim( $display_name ),
			'first_name'   => $user['first_name'] ?? null,
			'last_name'    => $user['last_name'] ?? null,
			'role'         => $role,
			'user_pass'    => 'password', // Passwords are not migrated.
			'meta_input'   => [
				'_legacy_user_data' => $user,
				'_old_user_id'      => $user['id'] ?? null,
			],
		];

		// Set user id. If user id is set then user will be updated.
		if ( ! empty( $user_id ) && 0 !== $user_id ) {
			$user_args['ID'] = $user_id;
		}

		$inserted_user_id = wp_insert_user( $user_args );

		if ( is_wp_error( $inserted_user_id ) ) {
			$this->total_failed++;
			$this->warning(
				sprintf(
					// translators: %1$d: Old user ID, %2$s: User display name.
					__( 'Failed: Old user ID:%1$d user %2$s failed to add!', 'ms-migration' ),
					$user['id'],
					$display_name
				)
			);
			return;
		}

		if ( ! empty( $user_id ) ) {
			if ( ! $this->duplicate_user ) {
				$this->total_skipped++;
			}

			$this->success(
				sprintf(
					// translators: %1$d: Old user ID, %2$s: User display name.
					__( 'Old user ID:%1$d user %2$s skipped!', 'ms-migration' ),
					$user['id'],
					$display_name
				)
			);
			return;
		} else {
			if ( ! $this->duplicate_user ) {
				$this->total_added++;
			}

			$this->success(
				sprintf(
					// translators: %1$d: Old user ID, %2$s: User display name.
					__( 'Old user ID:%1$d user %2$s added!', 'ms-migration' ),
					$user['id'],
					$display_name
				)
			);
		}
	}

	/**
	 * Get total users.
	 *
	 * @return int
	 */
	private function get_total_users() : int {
		// Count query.
		$count_query = sprintf( 'SELECT COUNT(id) FROM %s', self::USERS_TABLE );
		$total_count = $this->get_sql_server_data( $count_query, true );
		$total_count = array_pop( $total_count );

		return $total_count;
	}

	/**
	 * Get users.
	 *
	 * @param int $offset Offset.
	 * @param int $batch  Batch.
	 *
	 * @return array
	 */
	private function get_users( int $offset, int $batch ) : array {
		// Query to get users.
		$query = sprintf(
			'SELECT * FROM %s LIMIT %d OFFSET %d',
			self::USERS_TABLE,
			$batch,
			$offset
		);

		$users = $this->get_sql_server_data( $query );

		return $users;
	}

	/**
	 * Get user display name.
	 *
	 * @param array $user User.
	 *
	 * @return string
	 */
	private function get_user_display_name( array $user ) : string {
		$display_name = sprintf(
			'%1$s %2$s',
			$user['first_name'] ?? '',
			$user['last_name'] ?? ''
		);

		return $display_name;
	}

	/**
	 * Check if user exists.
	 *
	 * @param string $user_email User email.
	 *
	 * @return int
	 */
	private function user_exists( string $user_email ) : int {
		$user_email = sanitize_email( $user_email );
		$user       = get_user_by( 'email', $user_email );

		if ( ! empty( $user ) ) {
			$this->user_emails[] = $user_email;
			return $user->ID;
		}

		$this->user_emails[] = $user_email;
		return 0;
	}
}
