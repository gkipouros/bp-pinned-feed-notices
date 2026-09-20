=== Pinned Feed Notices for BuddyPress ===
Contributors: giannis4, thewpgarden
Tags: buddypress, feed, notices, user
Requires at least: 5.7
Tested up to: 7.0.2
Requires PHP: 7.4
Stable tag: 1.1.0
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=J7GGEGDD4XV5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add custom notices  to the top of the main activity feed.

== Description ==

Add custom notices  to the top of the main activity feed. You can add as many as you want, select the member types who will see the notice, and allow visitors to hide the notice.

It supports BuddyPress and BuddyBoss feeds.

**Like this plugin? Please consider [leaving a 5-star review](https://wordpress.org/support/plugin/bp-pinned-feed-notices/reviews/#new-post).**

**Do you want customizations: Contact me at [gianniskipouros.com](https://gianniskipouros.com/).**

== Installation ==

1. Upload "Pinned Feed Notices for BuddyPress" plugin into the directory `wp-content/plugins/`.
2. Enable "Pinned Feed Notices for BuddyPress" plugin.


== Frequently Asked Questions ==

There are no FAQ just yet.

== Screenshots ==
1. Adding new notices and managing them (WP Backend).
2. Add/edit a new pinned notice.
3. Front-end display of the notices.

== Changelog ==
= 1.1.0 =
* Security: Added nonce and capability checks to the member type save handler.
* Security: The dismiss endpoint now confirms the submitted ID is a real published notice before storing it.
* Security: Escaping corrections in the notice markup and the admin member type list.
* Fix: Notices hidden for a member type could still show to members holding more than one member type.
* Fix: Draft, pending and scheduled notices were displayed on the activity feed to every visitor.
* Fix: Quick Edit, bulk edit and autosave no longer wipe a notice's "Hide for Member Types" selection.
* Fix: The "Hide for Member Types" boxes did not load their saved values in every edit context.
* Fix: The member type checkboxes were hidden on sites with exactly one member type.
* Fix: Logged out visitors could not dismiss a notice at all. Their choice is now remembered in a cookie for a year.
* Fix: Notices never appeared on template packs that render the first page of the feed server-side.
* Fix: Embedded content such as iframes no longer disappears from notices written by administrators who are allowed to use it.
* Accessibility: The dismiss control is now a real button, so it can be reached with the Tab key and activated with Enter or Space, and it exposes a proper label to screen readers.
* Accessibility: Dismissing a notice is announced through a polite live region, and keyboard focus moves to the next notice instead of being lost.
* Change: Notices are no longer public posts. They have no front-end URL, archive or rewrite rules, since they only ever render inside the feed.
* Change: The stylesheet and script now load only on the activity directory instead of on every page.
* Change: An admin notice now explains that the plugin is idle when neither BuddyPress nor BuddyBoss Platform is active, instead of failing silently.
* Change: Admin-only code no longer loads on the front end, and both classes now use a single shared instance.
* Change: Deleting the plugin now removes its notices, post meta, per member dismissal lists and options, on every site of a network.
* Update: WordPress 7.0.2
* Update: BuddyPress 14.5.0

= 1.0.3 =
* Update: WordPress 6.7.1
* Update: BuddyPress 14.3.3

= 1.0.2 =
* Update: WordPress 6.2
* Update: BuddyPress 11.1.0

= 1.0.1 =
* Sanitization, Escaping attributes, Title change

= 1.0 =
* First Edition release

== Upgrade Notice ==

= 1.1.0 =
Recommended for everyone. Fixes notices leaking to members they were hidden from, unpublished notices showing on the feed, and Quick Edit wiping a notice's member type settings. Adds keyboard and screen reader support, and lets logged out visitors dismiss notices.
