/*! Pinned Feed Notices for BuddyPress
 * Built by Giannis Kipouros - 2022/09/20
 */

/**
 * @summary     Pinned Feed Notices for BuddyPress
 * @description Add custom notices to the top of the main activity feed.
 * @version     1.0.0
 * @file        bp-pinned-feed-notices.js
 * @author      Giannis Kipouros
 * @contact     https://gianniskipouros.com
 *
 */



(function ($) {
	'use strict';

	// On load
	$(document).ready(function ($) {

		if($('.directory.activity').length > 0) {

			$(document).on('click', '.bp-pinned-feed-notice .remove-notification', function () {
				let notifID = parseInt($(this).attr('data-notif-id'));

				if( notifID <= 0) {
					return;
				}

				let data = new FormData();
				data.append('notifID', notifID);
				data.append("nonce", BPPfnAjaxObject.ajax_nonce);
				data.append("action", 'delete_pinned_feed_notice');

				/***************
				 **   Submit  AJAX for deleting the notification
				 ***************/
				$.ajax({
					url: BPPfnAjaxObject.ajax_url,
					type: 'POST',
					data: data,
					context: this,
					cache: false,
					dataType: 'json',
					contentType: false,
					processData: false,
					error: function (jqXHR, textStatus, errorThrown) {
						console.error("The following error occurred: " + textStatus, errorThrown);
						return;
					},

					success: function (ajaxResponse) {
						if(!ajaxResponse.success) {
							console.error(ajaxResponse.content);
							return;
						}

						$(this).closest( '.bp-pinned-feed-notice').slideUp(600);
					}
				});

			});

		} // End .bp-pinned-feed-notice-wrapper


	}); // End document ready
})(jQuery);
