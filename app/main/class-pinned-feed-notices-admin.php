<?php
/**
 * Class for custom work.
 *
 * @package BP_Pinned_Feed_Notices_Admin
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// If class is exist, then don't execute this.
if ( ! class_exists( 'BP_Pinned_Feed_Notices_Admin' ) ) {

    /**
     * Class for the plugin's admin functions.
     */
    class BP_Pinned_Feed_Notices_Admin {


        /**
         * Option storing the version whose rewrite rules were last flushed.
         */
        const REWRITE_VERSION_OPTION = 'bppfn_rewrite_version';

        /**
         * Single instance of this class.
         *
         * @var BP_Pinned_Feed_Notices_Admin|null
         */
        private static $instance = null;

        /**
         * Get the single instance, creating it on first call.
         *
         * @return BP_Pinned_Feed_Notices_Admin
         */
        public static function get_instance() {

            if ( self::$instance === null ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Constructor. Private so the hooks can only ever be registered once.
         */
        private function __construct() {

            $this->load_hooks();
        }

        private function load_hooks() {

            /*
             * The post type has to be registered on the front end as well, otherwise the
             * feed query has nothing to look up, so these two stay outside the admin guard.
             */
            add_action( 'init', array( $this, 'create_feed_notices_cpt' ), 0 );

            // Runs after the post type is registered above.
            add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );

            // Everything below only ever fires inside wp-admin.
            if ( ! is_admin() ) {
                return;
            }

            add_action( 'edit_form_after_editor',
                array( $this, 'add_members_field_to_pfn' ) );

            add_action( 'save_post',
                array( $this, 'store_hide_notice_for_member_types_selection' ), 20, 1 );
        }

        /**
         * Create the feed notifications custom post type
         *
         * @return void
         */
        public function create_feed_notices_cpt() {

            $labels = array(
                'name'                  => _x( 'Feed Notices', 'Post Type General Name', 'bp-pinned-feed-notices' ),
                'singular_name'         => _x( 'Feed Notice', 'Post Type Singular Name', 'bp-pinned-feed-notices' ),
                'menu_name'             => __( 'Feed Notices', 'bp-pinned-feed-notices' ),
                'name_admin_bar'        => __( 'Feed Notices', 'bp-pinned-feed-notices' ),
                'archives'              => __( 'Notice Archives', 'bp-pinned-feed-notices' ),
                'attributes'            => __( 'Notice Attributes', 'bp-pinned-feed-notices' ),
                'parent_item_colon'     => __( 'Parent Notice:', 'bp-pinned-feed-notices' ),
                'all_items'             => __( 'All Notices', 'bp-pinned-feed-notices' ),
                'add_new_item'          => __( 'Add New Notice', 'bp-pinned-feed-notices' ),
                'add_new'               => __( 'Add New', 'bp-pinned-feed-notices' ),
                'new_item'              => __( 'New Notice', 'bp-pinned-feed-notices' ),
                'edit_item'             => __( 'Edit Notice', 'bp-pinned-feed-notices' ),
                'update_item'           => __( 'Update Notice', 'bp-pinned-feed-notices' ),
                'view_item'             => __( 'View Notice', 'bp-pinned-feed-notices' ),
                'view_items'            => __( 'View Notices', 'bp-pinned-feed-notices' ),
                'search_items'          => __( 'Search Notice', 'bp-pinned-feed-notices' ),
                'not_found'             => __( 'Not found', 'bp-pinned-feed-notices' ),
                'not_found_in_trash'    => __( 'Not found in Trash', 'bp-pinned-feed-notices' ),
                'featured_image'        => __( 'Featured Image', 'bp-pinned-feed-notices' ),
                'set_featured_image'    => __( 'Set featured image', 'bp-pinned-feed-notices' ),
                'remove_featured_image' => __( 'Remove featured image', 'bp-pinned-feed-notices' ),
                'use_featured_image'    => __( 'Use as featured image', 'bp-pinned-feed-notices' ),
                'insert_into_item'      => __( 'Insert into item', 'bp-pinned-feed-notices' ),
                'uploaded_to_this_item' => __( 'Uploaded to this item', 'bp-pinned-feed-notices' ),
                'items_list'            => __( 'Notices list', 'bp-pinned-feed-notices' ),
                'items_list_navigation' => __( 'Notices list navigation', 'bp-pinned-feed-notices' ),
                'filter_items_list'     => __( 'Filter items list', 'bp-pinned-feed-notices' ),
            );
            /*
             * Notices are fragments rendered inside the activity feed, never standalone pages.
             * public/publicly_queryable/rewrite are therefore all false: no front-end URL, no
             * archive, and no rewrite rules to maintain. show_ui keeps the admin screens.
             *
             * show_in_rest stays false because the member type field below is attached to
             * edit_form_after_editor, which is a classic editor hook.
             */
            $args   = array(
                'label'               => __( 'Feed Notice', 'bp-pinned-feed-notices' ),
                'description'         => __( 'Notices that appear pinned on top of the main activity feed', 'bp-pinned-feed-notices' ),
                'labels'              => $labels,
                'supports'            => array( 'title', 'editor' ),
                'hierarchical'        => false,
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'menu_position'       => 5,
                'menu_icon'           => 'dashicons-bell',
                'show_in_admin_bar'   => true,
                'show_in_nav_menus'   => false,
                'can_export'          => false,
                'has_archive'         => false,
                'exclude_from_search' => true,
                'publicly_queryable'  => false,
                'rewrite'             => false,
                'capability_type'     => 'page',
                'show_in_rest'        => false,
            );
            register_post_type( 'pinned_feed_notices', $args );

        }


        /**
         * Flush rewrite rules once per plugin version.
         *
         * Earlier versions registered the notice post type as public with rewrite rules.
         * Those rules survive in the rewrite_rules option until something flushes them, and
         * a plugin has no activation hook that fires on upgrade, so this does it on the first
         * request after the version changes.
         *
         * @return void
         */
        public function maybe_flush_rewrite_rules() {

            if ( get_option( self::REWRITE_VERSION_OPTION ) === BPPFN_VERSION ) {
                return;
            }

            // Soft flush: regenerates the rules without touching .htaccess.
            flush_rewrite_rules( false );

            update_option( self::REWRITE_VERSION_OPTION, BPPFN_VERSION );
        }

        /**
         * Add selection of member types
         */
        public function add_members_field_to_pfn() {
            global $post;

            if ( ! isset( $post->post_type ) || $post->post_type != 'pinned_feed_notices' ) {
                return;
            }

            if ( ! function_exists( 'bp_get_member_type_object' ) ) {
                return;
            }

            // Get member's member types - uses false to get multiple types
            $member_types = bp_get_member_types();

            // If it's empty initialize array to avoid errors
            if ( empty( $member_types ) ) {
                $member_types = array();
            }

            sort( $member_types );

            // $post is already confirmed to be one of our notices, so read the meta from it
            // directly. Relying on $_GET['post'] missed every edit context that does not put
            // the ID in the query string, and the empty state then saved back as "no blocks".
            $selected_member_types = get_post_meta( $post->ID, 'notice-blocked-member-types', true );

            if ( ! is_array( $selected_member_types ) ) {
                $selected_member_types = array();
            }
            ?>
			<div id="member-type-selection" class="member-type-selection">
				<h3><?php esc_html_e( 'Hide for the following Member Types', 'bp-pinned-feed-notices' ); ?></h3>
                <?php
                // Nonce for the save handler. Without it the handler cannot tell a genuine
                // editor submission from a Quick Edit or an autosave, and would wipe the selection.
                wp_nonce_field( 'bppfn_save_member_types', 'bppfn_member_types_nonce' );

                if ( count( $member_types ) > 0 ) { ?>
					<table>
                        <?php
                        foreach ( $member_types as $member_type ) {
                            $mt_object = bp_get_member_type_object( $member_type );
                            ?>
							<tr>
								<td>
									<fieldset>
										<input type="checkbox" name="notices-member-types[]"
											   value="<?php echo esc_attr( $member_type ); ?>"
											   id="<?php echo esc_attr( $member_type ); ?>-member-type"
                                            <?php checked( in_array( $member_type, $selected_member_types, true ) ); ?>
										>
										<label for="<?php echo esc_attr( $member_type ); ?>-member-type"><?php
                                            echo esc_html( $mt_object->labels['name'] ); ?></label>
									</fieldset>
								</td>
							</tr>
                        <?php } ?>
					</table>
                    <?php
                } else {
                    esc_html_e( 'Sorry, there are no member types currently set up.', 'bp-pinned-feed-notices' );
                }
                ?>
			</div>
            <?php


        }

        /**
         * Store the user's "Hide for profile types" selection on the
         * admin Feed Notices single edit page.
         *
         * @param  int $post_id The post being saved.
         * @return void
         */
        public function store_hide_notice_for_member_types_selection( $post_id ) {

            // Only act on our own post type.
            if ( get_post_type( $post_id ) !== 'pinned_feed_notices' ) {
                return;
            }

            /*
             * The nonce is only present when the full editor form was submitted. Bailing here
             * also covers Quick Edit, bulk edit, autosaves, revisions and programmatic saves,
             * none of which carry the checkboxes. Treating those as "user unticked everything"
             * would silently wipe the stored selection.
             */
            if ( ! isset( $_POST['bppfn_member_types_nonce'] ) ||
                 ! wp_verify_nonce(
                     sanitize_text_field( wp_unslash( $_POST['bppfn_member_types_nonce'] ) ),
                     'bppfn_save_member_types'
                 ) ) {
                return;
            }

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            // No boxes ticked means the notice is visible to every member type.
            if ( empty( $_POST['notices-member-types'] ) || ! is_array( $_POST['notices-member-types'] ) ) {
                delete_post_meta( $post_id, 'notice-blocked-member-types' );

                return;
            }

            // Sanitize inputs
            $hide_for_member_types = array_map(
                'sanitize_text_field',
                wp_unslash( $_POST['notices-member-types'] )
            );

            update_post_meta(
                $post_id,
                'notice-blocked-member-types',
                $hide_for_member_types
            );
        }
    }

    BP_Pinned_Feed_Notices_Admin::get_instance();
}
