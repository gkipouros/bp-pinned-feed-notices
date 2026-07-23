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
						// wp_send_json_success()/_error() nest the payload under "data".
						if(!ajaxResponse.success) {
							console.error(ajaxResponse.data ? ajaxResponse.data.content : 'Unknown error');
							return;
						}

						let $notice  = $(this).closest('.bp-pinned-feed-notice');
						let $wrapper = $notice.closest('.bp-pinned-feed-notice-wrapper');

						// Grab the next dismiss button before this one leaves the document.
						let $next = $notice.nextAll('.bp-pinned-feed-notice').first()
							.find('.remove-notification').first();

						$notice.slideUp(600, function () {
							// Remove rather than just hide, so the live region sees a change.
							$(this).remove();

							$wrapper.find('.bppfn-notice-status').text(BPPfnAjaxObject.removed_text);

							// Focus would otherwise fall back to <body> now the button is gone.
							if ($next.length) {
								$next.trigger('focus');
							}
						});
					}
				});

			});

		} // End .bp-pinned-feed-notice-wrapper


	}); // End document ready
})(jQuery);
