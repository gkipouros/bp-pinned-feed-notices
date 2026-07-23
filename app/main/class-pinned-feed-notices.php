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

        /**
         * User meta key holding the notices a logged in member has dismissed.
         */
        const READ_META_KEY = 'read_feed_notices';

        /**
         * Cookie holding the notices a logged out visitor has dismissed.
         * Guests have no user meta, so a cookie is the only storage available.
         */
        const READ_COOKIE_KEY = 'bppfn_read_notices';

        /**
         * Single instance of this class.
         *
         * @var BP_Pinned_Feed_Notices|null
         */
        private static $instance = null;

        /**
         * Get the single instance, creating it on first call.
         *
         * @return BP_Pinned_Feed_Notices
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
            // Enqueue front-end scripts
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style_scripts' ), 100 );

            add_action( 'bp_before_activity_loop', array( $this, 'display_feed_notices' ) );

            // Dismissing a notice is offered to guests too, so both AJAX hooks are registered.
            add_action( 'wp_ajax_delete_pinned_feed_notice',
                array( $this, 'delete_pinned_feed_notice' ) );

            add_action( 'wp_ajax_nopriv_delete_pinned_feed_notice',
                array( $this, 'delete_pinned_feed_notice' ) );

        }


        /**
         * Is the current request the main activity directory?
         *
         * Shared by the asset enqueue and the notice output so the two can never
         * disagree about where notices belong.
         *
         * @return bool
         */
        private function is_activity_directory() {

            if ( ! function_exists( 'bp_is_directory' ) ) {
                return false;
            }

            return ( bp_is_directory() && bp_is_current_component( 'activity' ) );
        }

        /**
         * Which page of the activity feed is being rendered?
         *
         * BP Nouveau fetches the loop over AJAX and posts the page number. Template packs
         * that render the first page server-side send nothing, and non-AJAX pagination uses
         * BuddyPress' acpage query arg. An absent value therefore means page one.
         *
         * @return int
         */
        private function get_current_feed_page() {

            /*
             * Cast with (int) rather than absint() on purpose: absint( '-4' ) is 4, which would
             * read a malformed value as a real page number. A plain cast leaves negatives and
             * junk at or below zero so they fall back to page one below.
             */
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read only display check.
            if ( isset( $_POST['page'] ) ) {
                $page = (int) wp_unslash( $_POST['page'] );
            } elseif ( isset( $_GET['acpage'] ) ) {
                $page = (int) wp_unslash( $_GET['acpage'] );
            } else {
                $page = 1;
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            return ( $page > 0 ) ? $page : 1;
        }

        /**
         * Enqueue style/script.
         *
         * @return void
         */
        public function enqueue_style_scripts() {

            // Notices only ever render on the activity directory, so nothing else needs the assets.
            if ( ! $this->is_activity_directory() ) {
                return;
            }

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
                    'ajax_url'     => admin_url( 'admin-ajax.php' ),
                    'ajax_nonce'   => wp_create_nonce( 'ajax_nonce' ),
                    // Spoken by the live region once a notice has been dismissed.
                    'removed_text' => __( 'Notice removed.', 'bp-pinned-feed-notices' ),
                )
            );
            wp_enqueue_script( 'bp-pinned-feed-notices-custom-script' );

        }

        /**
         * Display the feed notifications
         */
        public function display_feed_notices() {

            // Bail out if not the main activity feed
            if ( ! $this->is_activity_directory() ) {
                return;
            }

            // Notices are pinned to the top of the feed, so only the first page gets them.
            if ( $this->get_current_feed_page() !== 1 ) {
                return;
            }

            // Get visitor's member types - uses false to get multiple types
            $visitors_member_types = bp_get_member_type( get_current_user_id(), false );

            // Normalize so the visibility check always receives an array
            if ( ! is_array( $visitors_member_types ) ) {
                $visitors_member_types = array();
            }

            // Get the notices this visitor has already dismissed
            $read_notification_ids = $this->get_dismissed_notice_ids();

            /**
             * Add sorting filters
             */
            $order = array(
                'orderby' => 'date',
                'order'   => 'ASC',
            );

            // Filter the order of the announcements
            $order = apply_filters( 'bp-pinned-feed-notices-query-order', $order );

            /*
             * Get notifications.
             *
             * post_status is pinned to publish on purpose. BuddyPress loads the activity
             * loop through admin-ajax.php, where is_admin() is true, and WP_Query then adds
             * the draft, pending and future statuses to an unrestricted query no matter who
             * is asking. Without this, unpublished notices are served to every visitor.
             */
            $args = array(
                'post_type'      => 'pinned_feed_notices',
                'post_status'    => 'publish',
                'posts_per_page' => - 1,
                'orderby'        => $order['orderby'],
                'order'          => $order['order'],
            );

            // Remove notifications that the member has already read
            if ( count( $read_notification_ids ) >= 1 ) {
                $args['post__not_in'] = $read_notification_ids;
            }

            // Run the query
            $the_query = new WP_Query( $args );

            // If there is an error return false
            if ( is_wp_error( $the_query ) ) {
                return false;
            }

            /*
             * Drop the notices that are blocked for one of the visitor's member types.
             * WP_Query has already primed the meta cache for these posts, so the
             * get_post_meta() call inside the check costs no extra queries.
             */
            $notifications = array();
            foreach ( $the_query->posts as $notification ) {
                if ( $this->is_notice_visible( $notification->ID, $visitors_member_types ) ) {
                    $notifications[] = $notification;
                }
            }

            // If there are no notifications bail out.
            if ( count( $notifications ) <= 0 ) {
                return false;
            }
            ?>
			<ul class="bp-pinned-feed-notice-wrapper" aria-live="polite">
                <?php
                foreach ( $notifications as $notification ) {
                    ?>
					<li class="bp-pinned-feed-notice">
						<button type="button" class="remove-notification"
								data-notif-id="<?php echo absint( $notification->ID ); ?>"
								aria-label="<?php esc_attr_e( 'Remove this message', 'bp-pinned-feed-notices' ); ?>"
								title="<?php esc_attr_e( 'Remove this message', 'bp-pinned-feed-notices' ); ?>">&times;</button>
                        <?php
                        /*
                         * Rendered the way core renders post content. Notices need edit_pages
                         * to author, and WordPress already runs the content through kses on
                         * save for anyone without unfiltered_html. Running kses again here
                         * only stripped iframes and embeds back out of notices written by
                         * administrators who are allowed to use them.
                         */
                        echo apply_filters( 'the_content', $notification->post_content );
                        ?>
					</li>
                    <?php
                }

                /*
                 * Empty on purpose. A live region has to be in the document before its content
                 * changes, otherwise screen readers do not announce the update. The script
                 * writes the confirmation here once a notice has been removed.
                 */
                ?>
				<li class="bppfn-notice-status bppfn-visually-hidden"></li>
			</ul>

            <?php
        }

        /**
         * Check whether a notice may be shown to a visitor.
         *
         * A notice is hidden as soon as one of the visitor's member types appears in the
         * notice's blocked list. The comparison is exact, so member type slugs sharing a
         * prefix ( "student" and "student-premium" ) never match each other.
         *
         * @param  int   $notice_id             Notice post ID.
         * @param  array $visitors_member_types Member type slugs held by the visitor.
         * @return bool                         True when the notice should be displayed.
         */
        private function is_notice_visible( $notice_id, $visitors_member_types ) {

            // A visitor without member types can never be blocked.
            if ( empty( $visitors_member_types ) ) {
                return true;
            }

            $blocked_member_types = get_post_meta( $notice_id, 'notice-blocked-member-types', true );

            // No blocked list means the notice is public to every member type.
            if ( empty( $blocked_member_types ) || ! is_array( $blocked_member_types ) ) {
                return true;
            }

            return count( array_intersect( $visitors_member_types, $blocked_member_types ) ) === 0;
        }

        /**
         * Get the notices the current visitor has already dismissed.
         *
         * Members keep the list in user meta so it follows them between devices.
         * Guests keep it in a cookie, which is the only storage available to them.
         *
         * @return int[] Dismissed notice IDs.
         */
        private function get_dismissed_notice_ids() {

            if ( is_user_logged_in() ) {
                $dismissed = get_user_meta( get_current_user_id(), self::READ_META_KEY, true );
            } elseif ( isset( $_COOKIE[ self::READ_COOKIE_KEY ] ) ) {
                $dismissed = explode(
                    ',',
                    sanitize_text_field( wp_unslash( $_COOKIE[ self::READ_COOKIE_KEY ] ) )
                );
            } else {
                $dismissed = array();
            }

            if ( ! is_array( $dismissed ) ) {
                return array();
            }

            // The cookie is visitor supplied, so cast hard and drop anything unusable.
            return array_values( array_unique( array_filter( array_map( 'absint', $dismissed ) ) ) );
        }

        /**
         * Persist the dismissed notice list for the current visitor.
         *
         * Must run before any output so the cookie header can still be sent.
         *
         * @param  int[] $notice_ids Dismissed notice IDs.
         * @return void
         */
        private function store_dismissed_notice_ids( $notice_ids ) {

            if ( is_user_logged_in() ) {
                update_user_meta( get_current_user_id(), self::READ_META_KEY, $notice_ids );

                return;
            }

            setcookie(
                self::READ_COOKIE_KEY,
                implode( ',', $notice_ids ),
                array(
                    'expires'  => time() + YEAR_IN_SECONDS,
                    'path'     => COOKIEPATH ? COOKIEPATH : '/',
                    'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                )
            );
        }

        /**
         * Setup AJAX callback for removing feed notices.
         *
         * Serves logged in members and guests alike; the storage used is decided by
         * store_dismissed_notice_ids().
         *
         * @return void
         */
        public function delete_pinned_feed_notice() {

            // Security: validate the nonce before touching anything else.
            if ( ! isset( $_POST['nonce'] ) ||
                 ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ajax_nonce' ) ) {
                wp_send_json_error( array( 'content' => 'Security check failed' ) );
            }

            $notification_id = isset( $_POST['notifID'] ) ? absint( $_POST['notifID'] ) : 0;

            if ( $notification_id <= 0 ) {
                wp_send_json_error( array( 'content' => 'Problem with the notification ID' ) );
            }

            /*
             * Only a real published notice may be dismissed. Without this the stored list
             * could be padded with arbitrary IDs, which matters most for guests because
             * their list is a cookie sent on every subsequent request.
             */
            if ( get_post_type( $notification_id ) !== 'pinned_feed_notices' ||
                 get_post_status( $notification_id ) !== 'publish' ) {
                wp_send_json_error( array( 'content' => 'Unknown notification' ) );
            }

            $dismissed = $this->get_dismissed_notice_ids();

            if ( in_array( $notification_id, $dismissed, true ) ) {
                wp_send_json_error( array( 'content' => 'Notification already deleted' ) );
            }

            // Add the new notification to the dismissed list
            $dismissed[] = $notification_id;

            $this->store_dismissed_notice_ids( $dismissed );

            wp_send_json_success( array( 'content' => $notification_id ) );
        }
    }

    BP_Pinned_Feed_Notices::get_instance();
}
