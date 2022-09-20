<?php
/**
 * Class for custom work.
 *
 * @package BP_Pinned_Feed_Notices
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// If class is exist, then don't execute this.
if ( ! class_exists( 'BP_Pinned_Feed_Notices' ) ) {

    /**
     * Class for the plugin's core.
     */
    class BP_Pinned_Feed_Notices {

        const READ_META_KEY = 'read_feed_notices';

        /**
         * Constructor for class.
         */
        public function __construct() {


            $this->load_hooks();
        }

        private function load_hooks() {
            // Enqueue front-end scripts
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style_scripts' ), 100 );

            add_action( 'bp_before_activity_loop', array( $this, 'display_feed_notices' ) );

            add_action( 'wp_ajax_delete_pinned_feed_notice',
                array( $this, 'delete_pinned_feed_notice' ) );

        }


        /**
         * Enqueue style/script.
         *
         * @return void
         */
        public function enqueue_style_scripts() {

            // Custom plugin script.
            wp_enqueue_style(
                'bp-pinned-feed-notices-core-style',
                BPPFN_URL . 'assets/css/bp-pinned-feed-notices.css',
                '',
                BPPFN_VERSION
            );

            // Register plugin's JS script
            wp_register_script(
                'bp-pinned-feed-notices-custom-script',
                BPPFN_URL . 'assets/js/bp-pinned-feed-notices.js',
                array(
                    'jquery',
                ),
                BPPFN_VERSION,
                true
            );


            // Provide a global object to our JS file containing the AJAX url and security nonce
            wp_localize_script( 'bp-pinned-feed-notices-custom-script', 'BPPfnAjaxObject',
                array(
                    'ajax_url'   => admin_url( 'admin-ajax.php' ),
                    'ajax_nonce' => wp_create_nonce( 'ajax_nonce' ),
                )
            );
            wp_enqueue_script( 'bp-pinned-feed-notices-custom-script' );

        }

        /**
         * Display the feed notifications
         */
        public function display_feed_notices() {

            // Bail out if not the main activity feed
            if ( ! bp_is_directory() || ! bp_is_current_component( 'activity' ) ) {
                return;
            }

            // Show only on the first page
            if ( ! isset( $_POST['page'] ) || (int) $_POST['page'] != 1 ) {
                return;
            }

            // Get visitor's member types - uses false to get multiple types
            $visitors_member_types = bp_get_member_type( get_current_user_id(), false );

            // Add the meta query in order to fetch only the appropriate notifications
            $meta_query_args = '';
            if ( is_array( $visitors_member_types ) && count( $visitors_member_types ) > 0 ) {
                $meta_query_args = array(
                    'relation' => 'OR',

                );
                foreach ( $visitors_member_types as $member_type ) {
                    $meta_query_args[] = array(
                        'key'     => 'notice-blocked-member-types',
                        'value'   => $member_type,
                        'compare' => 'NOT LIKE'
                    );
                }

                /**
                 * Do this for notices that have no blocked types
                 */
                $meta_query_args[] = array(
                    'key'     => 'notice-blocked-member-types',
                    'compare' => 'NOT EXISTS'
                );

            }


            // Get member's read notifications
            $read_notification_ids = get_user_meta( get_current_user_id(), SELF::READ_META_KEY, true );

            if ( empty( $read_notification_ids ) ) {
                $read_notification_ids = array();
            }

            /**
             * Add sorting filters
             */
            $order = array(
                'orderby' => 'date',
                'order'   => 'ASC',
            );

            // Filter the order of the announcements
            $order = apply_filters( 'bp-pinned-feed-notices-query-order', $order );

            // Get notifications
            $args = array(
                'post_type'      => 'pinned_feed_notices',
                'posts_per_page' => - 1,
                'orderby'        => $order['orderby'],
                'order'          => $order['order'],
            );

            // Remove notifications that the member has already read
            if ( count( $read_notification_ids ) >= 1 ) {
                $args['post__not_in'] = $read_notification_ids;
            }

            if ( ! empty( $meta_query_args ) ) {
                $args['meta_query'] = $meta_query_args;
            }

            // Run the query
            $the_query = new WP_Query( $args );

            // If there is an error return false
            if ( is_wp_error( $the_query ) ) {
                return false;
            }

            // Return an array of post objects
            $notifications = $the_query->posts;

            // If there are no notifications bail out.
            if ( count( $notifications ) <= 0 ) {
                return false;
            }
            ?>
			<ul class="bp-pinned-feed-notice-wrapper">
                <?php
                foreach ( $notifications as $notification ) {
                    ?>
					<li class="bp-pinned-feed-notice">
						<span class="remove-notification" data-notif-id="<?php echo absint( $notification->ID ); ?>"
							  title="<?php _e( 'Remove this message', 'bp-pinned-feed-notices' ); ?>">x</span>
                        <?php
                        $content = wp_kses_post( $notification->post_content );
                        echo apply_filters( 'the_content', $content );
                        ?>
					</li>
                    <?php
                }
                ?>

			</ul>

            <?php
        }

        /**
         * Setup AJAX callback for removing feed notices
         *
         */
        public function delete_pinned_feed_notice() {

            $nonce                 = sanitize_text_field( $_POST['nonce'] );
            $notification_id       = (int) sanitize_text_field( $_POST['notifID'] );
            $meta_key              = SELF::READ_META_KEY;
            $removed_notifications = get_user_meta( get_current_user_id(), $meta_key, true );

            if ( empty( $removed_notifications ) ) {
                $removed_notifications = array();
            }


            // Security Validate Nonce
            if ( ! wp_verify_nonce( $nonce, 'ajax_nonce' ) ) {
                $response['success'] = false;
                $response['content'] = 'Security check failed';
                echo json_encode( $response );
                wp_die();
            }

            // Security Validate Nonce
            if ( $notification_id <= 0 ) {
                $response['success'] = false;
                $response['content'] = 'Problem with the notification ID';
                echo json_encode( $response );
                wp_die();
            }

            if ( in_array( $notification_id, $removed_notifications ) ) {
                $response['success'] = false;
                $response['content'] = 'Notification already deleted';
                echo json_encode( $response );
                wp_die();
            }

            // Add the new notification to the deleted list
            $removed_notifications[] = $notification_id;

            // Update deleted notifications meta
            $result = update_user_meta( get_current_user_id(), $meta_key, $removed_notifications );

            $response['success'] = true;
            $response['content'] = $result;


            echo json_encode( $response );
            exit;

        }
    }

    new BP_Pinned_Feed_Notices();
}
