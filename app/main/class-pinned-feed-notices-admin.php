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
         * Constructor for class.
         */
        public function __construct() {


            $this->load_hooks();
        }

        private function load_hooks() {
            add_action( 'init', array( $this, 'create_feed_notices_cpt' ), 0 );

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
            $args   = array(
                'label'               => __( 'Feed Notice', 'bp-pinned-feed-notices' ),
                'description'         => __( 'Notices that appear pinned on top of the main activity feed', 'bp-pinned-feed-notices' ),
                'labels'              => $labels,
                'supports'            => array( 'title', 'editor' ),
                'hierarchical'        => false,
                'public'              => true,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'menu_position'       => 5,
                'menu_icon'           => 'dashicons-bell',
                'show_in_admin_bar'   => true,
                'show_in_nav_menus'   => false,
                'can_export'          => false,
                'has_archive'         => false,
                'exclude_from_search' => true,
                'publicly_queryable'  => true,
                'rewrite'             => true,
                'capability_type'     => 'page',
                'show_in_rest'        => false,
            );
            register_post_type( 'pinned_feed_notices', $args );

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

            $selected_member_types = array();

            if ( isset( $_GET['post'] ) && absint( $_GET['post'] ) > 0 ) {
                $post_id               = absint( $_GET['post'] );
                $selected_member_types = (array) get_post_meta( $post_id, 'notice-blocked-member-types', true );
            }
            ?>
			<div id="member-type-selection" class="member-type-selection">
				<h3><?php _e( 'Hide for the following Member Types', 'bp-pinned-feed-notices' ); ?></h3>
                <?php

                if ( count( $member_types ) > 1 ) { ?>
					<table>
                        <?php
                        foreach ( $member_types as $member_type ) {
                            $mt_object   = bp_get_member_type_object( $member_type );
                            $checked     = '';
                            if ( in_array( $member_type, $selected_member_types ) ) {
                                $checked = 'checked';
                            }
                            ?>
							<tr>
								<td>
									<fieldset>
										<input type="checkbox" name="notices-member-types[]"
											   value="<?php echo esc_attr( $member_type ); ?>"
											   id="<?php echo esc_attr( $member_type ); ?>-member-type"
                                            <?php echo $checked; ?>
										>
										<label for="<?php echo esc_attr( $member_type ); ?>-member-type"><?php
                                            echo esc_attr( $mt_object->labels['name'] ); ?></label>
									</fieldset>
								</td>
							</tr>
                        <?php } ?>
					</table>
                    <?php
                } else {
                    _e( 'Sorry, there are no member types currently set up.', 'bp-pinned-feed-notices' );
                }
                ?>
			</div>
            <?php


        }

        /**
         * Store the user's "Hide for profile types" selection on the
         * admin Feed Notices single edit page.
         *
         * @param $post_id
         */
        function store_hide_notice_for_member_types_selection( $post_id ) {

            if ( ! isset( $_REQUEST['notices-member-types'] ) ||
                 empty( $_REQUEST['notices-member-types'] ) ) {
                delete_post_meta( $post_id, 'notice-blocked-member-types' );
            } else {
                // Sanitize inputs
                $hide_for_member_types = array_map(
                    'sanitize_text_field',
                    $_REQUEST['notices-member-types']
                );
                update_post_meta(
                    $post_id,
                    'notice-blocked-member-types',
                    $hide_for_member_types
                );
            }
        }
    }

    new BP_Pinned_Feed_Notices_Admin();
}
