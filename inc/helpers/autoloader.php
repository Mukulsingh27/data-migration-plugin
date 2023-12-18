<?php
/**
 * Autoloader for PHP classes.
 *
 * @package ms-migration
 */

namespace MS\Migration\Inc\Helpers;

/**
 * Autoloader function.
 *
 * @param string $resource Resource.
 */
function autoloader( $resource = '' ) {
	$namespace_root = 'MS\Migration\\';

	$resource = trim( $resource, '\\' );

	if ( empty( $resource ) || strpos( $resource, '\\' ) === false || strpos( $resource, $namespace_root ) !== 0 ) {
		// not our namespace, bail out.
		return;
	}

	$path = explode(
		'\\',
		str_replace( '_', '-', strtolower( $resource ) )
	);

	/**
	 * Time to determine which type of resource path it is,
	 * so that we can deduce the correct file path for it.
	*/
	if ( ( ! empty( $path[2] ) && 'inc' === $path[2] )
		&& ( ! empty( $path[3] ) && 'helpers' !== $path[3] )
	) {
		/**
		 * Theme resource for 'inc/classes' dir
		 * The path need 'classes' dir injected into it as all classes,
		 * services, traits, interfaces etc will be in 'classes' dir
		 */
		$class_path = untrailingslashit(
			implode(
				'/',
				array_slice( $path, 3 )
			)
		);

		$resource_path = sprintf( '%s/inc/classes/%s.php', untrailingslashit( MS_MIGRATION_PLUGIN_PATH ), $class_path );

	} else {
		/**
		 * All other resource paths are translated as-is in lowercase.
		 */
		if ( ! empty( $path[1] ) && 'config' === $path[1] ) {
			$path[1] = '_config';
		}

		array_shift( $path ); // knock off the first item, we don't need the root stub here.

		$resource_path = sprintf( '%s/%s.php', untrailingslashit( MS_MIGRATION_PLUGIN_PATH ), implode( '/', $path ) );

	}

	$file_prefix = '';

	if ( strpos( $resource_path, 'traits' ) > 0 ) {
		$file_prefix = 'trait';
	} elseif ( strpos( $resource_path, 'interfaces' ) > 0 ) {
		$file_prefix = 'interface';
	} elseif ( strpos( $resource_path, '_config' ) > 0 ) {
		$file_prefix = 'class';
	} elseif ( strpos( $resource_path, 'classes' ) > 0 ) {  // this has to be the last.
		$file_prefix = 'class';
	}

	if ( ! empty( $file_prefix ) ) {

		$resource_parts = explode( '/', $resource_path );

		$resource_parts[ count( $resource_parts ) - 1 ] = sprintf(
			'%s-%s',
			strtolower( $file_prefix ),
			$resource_parts[ count( $resource_parts ) - 1 ]
		);

		$resource_path = implode( '/', $resource_parts );

	}

	if ( file_exists( $resource_path ) && validate_file( $resource_path ) === 0 ) {
		include_once $resource_path;
	}
}

/**
 * Register autoloader
 */
spl_autoload_register( __NAMESPACE__ . '\autoloader' );
