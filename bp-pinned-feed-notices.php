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
 * Version:           1.1.0
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
	define( 'BPPFN_VERSION', '1.1.0' );
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

    /*
     * BuddyPress or BuddyBoss Platform has to be running. Both define bp_is_active(),
     * so testing for the function covers either one.
     *
     * A "Requires Plugins" header is deliberately not used here. That header matches
     * wordpress.org slugs and treats every entry as mandatory, so requiring "buddypress"
     * would stop this plugin activating on the BuddyBoss sites it also supports.
     */
    if ( ! function_exists( 'bp_is_active' ) ) {
        add_action( 'admin_notices', 'bppfn_missing_bp_notice' );

        return;
    }

	// Include Class files
	$files = array(
		'app/main/class-pinned-feed-notices',
        'app/main/class-pinned-feed-notices-admin',
	);

	foreach ( $files as $file ) {

		// Include functions file.
		require BPPFN_PATH . $file . '.php';

	}

}

add_action( 'plugins_loaded', 'bppfn_include_plugin_files' );

/**
 * Warn administrators when the plugin has nothing to attach to.
 *
 * Without this the plugin bails silently and looks broken rather than unmet.
 *
 * @return void
 */
function bppfn_missing_bp_notice() {

    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    ?>
	<div class="notice notice-error">
		<p>
            <?php
            esc_html_e(
                'Pinned Feed Notices for BuddyPress is inactive because neither BuddyPress nor BuddyBoss Platform is running. Activate one of them to display your notices.',
                'bp-pinned-feed-notices'
            );
            ?>
		</p>
	</div>
    <?php
}


/**
 * Load plugin's textdomain.
 */
function bppfn_language_textdomain_init() {
    // Localization
    load_plugin_textdomain( 'bp-pinned-feed-notices', false, dirname( plugin_basename( __FILE__ ) ) . "/languages" );
}

// Add actions
add_action( 'init', 'bppfn_language_textdomain_init' );
