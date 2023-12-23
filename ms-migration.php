<?php
/**
 * MS Migration plugin.
 *
 * @package ms-migration
 */

/**
 * Plugin Name: MS Migration
 * Plugin URI:  https://rtCamp.com
 * Description: Migration script for data-migration. This plugin will help to migrate data to new WP site.
 * Version:     0.1.0
 * Author:      Mukul
 * Author URI:  https://github.com/Mukulsingh27
 * Text Domain: ms-migration
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package ms-migration
 * @author  mukul
 * @license GPL-2.0+
 */

define( 'MS_MIGRATION_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

require_once MS_MIGRATION_PLUGIN_PATH . '/inc/helpers/autoloader.php';

// Init plugin.
if ( defined( 'WP_CLI' ) && WP_CLI ) {

	\MS\Migration\Inc\Plugin::get_instance();

}
