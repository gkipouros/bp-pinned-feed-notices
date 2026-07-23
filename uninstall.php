<?php
/**
 * Uninstall routine for Pinned Feed Notices for BuddyPress.
 *
 * Removes every notice, its post meta, the per member dismissal list and the plugin's
 * own option. Runs only when the plugin is deleted from the Plugins screen.
 *
 * @package bp-pinned-feed-notices
 */

// Exit if not called by WordPress during an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all plugin data from the current site.
 *
 * The plugin is not loaded during uninstall, so the post type is not registered and
 * WP_Query cannot be trusted to resolve it. Notice IDs are read straight from the
 * posts table, then handed to wp_delete_post() so post meta is cleaned up properly.
 *
 * @return void
 */
function bppfn_uninstall_site() {
	global $wpdb;

	$notice_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
			'pinned_feed_notices'
		)
	);

	foreach ( $notice_ids as $notice_id ) {
		// true forces a permanent delete and takes the post meta with it.
		wp_delete_post( (int) $notice_id, true );
	}

	// Dismissal lists, for every user on this site.
	delete_metadata( 'user', 0, 'read_feed_notices', '', true );

	delete_option( 'bppfn_rewrite_version' );
}

if ( is_multisite() ) {

	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		bppfn_uninstall_site();
		restore_current_blog();
	}
} else {
	bppfn_uninstall_site();
}
