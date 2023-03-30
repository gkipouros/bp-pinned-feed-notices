<?php
/**
 * Pinned Feed Notices for BuddyPress
 *
 * Add custom notices  to the top of the main activity feed. You can add as many as you want,
 * select the member types who will see the notice, and allow visitors to hide the notice.
 *
 * @link              https://gianniskipouros.com/bp-pinned-feed-notices/
 * @since             1.0.0
 * @package           bp-pinned-feed-notices
 *
 * @wordpress-plugin
 * Plugin Name:       Pinned Feed Notices for BuddyPress
 * Plugin URI:        https://gianniskipouros.com/bp-pinned-feed-notices/
 * Description:       Add custom notices  to the top of the main activity feed.
 * Version:           1.0.2
 * Author:            Giannis Kipouros
 * Author URI:        https://gianniskipouros.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bp-pinned-feed-notices
 * Domain Path:       /languages
 */

/**
 * Main file, contains the plugin metadata and activation processes
 *
 * @package    bp-pinned-feed-notices
 */
if ( ! defined( 'BPPFN_VERSION' ) ) {
	/**
	 * The version of the plugin.
	 */
	define( 'BPPFN_VERSION', '1.0.2' );
}

if ( ! defined( 'BPPFN_PATH' ) ) {
	/**
	 *  The server file system path to the plugin directory.
	 */
	define( 'BPPFN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'BPPFN_URL' ) ) {
	/**
	 * The url to the plugin directory.
	 */
	define( 'BPPFN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'BPPFN_BASE_NAME' ) ) {
	/**
	 * The url to the plugin directory.
	 */
	define( 'BPPFN_BASE_NAME', plugin_basename( __FILE__ ) );
}

/**
 * Include files.
 */
function bppfn_include_plugin_files() {

    // Bail out if BP is not enabled.
    if ( ! function_exists('bp_is_active') ) {
        return;
    }

	// Include Class files
	$files = array(
		'app/main/class-pinned-feed-notices',
        'app/main/class-pinned-feed-notices-admin',
	);

	// Include Includes files
	$includes = array(

	);

	// Merge the two arrays
	$files = array_merge( $files, $includes );

	foreach ( $files as $file ) {

		// Include functions file.
		require BPPFN_PATH . $file . '.php';

	}

}

add_action( 'plugins_loaded', 'bppfn_include_plugin_files' );


/**
 * Load plugin's textdomain.
 */
function bppfn_language_textdomain_init() {
    // Localization
    load_plugin_textdomain( 'bp-pinned-feed-notices', false, dirname( plugin_basename( __FILE__ ) ) . "/languages" );
}

// Add actions
add_action( 'init', 'bppfn_language_textdomain_init' );
