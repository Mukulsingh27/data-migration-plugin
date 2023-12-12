<?php
/**
 * Class for WP-CLI migrations.
 *
 * @package ms-migration
 */

namespace MS\Migration\Inc;

/**
 * Class Plugin to register commands for migration.
 */
class Plugin {
	/**
	 * Instance of class.
	 *
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Instance of current class.
	 *
	 * @return object Instance of current class.
	 */
	public static function get_instance()
	{
		static $instance = false;

		if ( false === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Plugin constructor.
	 *
	 * Register commands for migration.
	 */
	protected function __construct() {
		// Command to migrate posts.
		\WP_CLI::add_command( 'ms-migrate-posts', '\MS\Migration\Inc\Posts' );

		// Command to migrate categories.
		\WP_CLI::add_command( 'ms-migrate-categories', '\MS\Migration\Inc\Categories' );

		// Command to migrate users.
		\WP_CLI::add_command( 'ms-migrate-users', '\MS\Migration\Inc\Users' );
	}
}
