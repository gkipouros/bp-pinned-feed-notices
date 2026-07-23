# Dependency header, content filtering, and structure

Final polish items from the review. Same unreleased release, so no separate version bump.

## 1. Dependency declaration

- [x] Make the missing-BuddyPress case visible instead of a silent bail
- [x] **Did not** add `Requires Plugins: buddypress` - see below

### Why the header was not added

`readme.txt` line 18 states the plugin supports BuddyBoss feeds, and BuddyBoss Platform defines
`bp_is_active()` just like BuddyPress does, which is why the existing runtime check works for
both.

`Requires Plugins` resolves against wordpress.org slugs and treats every entry as mandatory -
there is no OR. Adding `Requires Plugins: buddypress` would therefore stop the plugin activating
on every BuddyBoss site, because the `buddypress` slug is not installed there. That is a
regression, not a fix.

The actual complaint was the *silent* bail, so the fix is an `admin_notices` callback that tells
an administrator the plugin is idle and why. The runtime `function_exists( 'bp_is_active' )`
check stays, since it is the only check that covers both platforms.

If BuddyBoss support is ever dropped, adding the header is a one-line change and the readme
claim should go with it.

## 2. `wp_kses_post()` before `apply_filters( 'the_content' )`

- [x] Removed the output-time `wp_kses_post()`

Notices require `edit_pages` to author, and WordPress already runs content through kses on save
for anyone without `unfiltered_html`. Re-filtering on output only stripped iframes and embeds
back out of notices written by administrators who are permitted to use them. Rendering now
matches how core renders post content everywhere else.

## 3. Structure

- [x] `get_instance()` singletons with private constructors on both classes
- [x] Admin-only hooks behind `is_admin()`, with post type registration left outside it
- [x] Dead `$includes = array()` merge removed

Post type registration and the rewrite flush stay outside the admin guard: the feed query needs
the post type to exist on the front end.

## Review

### What changed

**`bp-pinned-feed-notices.php`** — dead `$includes` merge dropped; the BP check now registers
`bppfn_missing_bp_notice()`, an `activate_plugins`-gated admin notice.

**`app/main/class-pinned-feed-notices.php`** — `private static $instance`, `get_instance()`,
private constructor, `BP_Pinned_Feed_Notices::get_instance()` at file scope. Output-time
`wp_kses_post()` removed.

**`app/main/class-pinned-feed-notices-admin.php`** — same singleton treatment;
`load_hooks()` returns early after the two `init` hooks when `! is_admin()`.

### Deviations from plan

Item 1 was implemented differently from how it was requested, for the BuddyBoss reason above.
Everything else went as planned.

### Testing status

Runtime verified against the live install. **114 assertions across eight suites, all passing.**
`php -l` clean on PHP 7.4.26 and 8.3.12.

- **Singletons** — both constructors report `private` via reflection, `get_instance()` exists,
  and two calls return the identical object.
- **Hook split, proven both ways.** Off-admin (WP-CLI, where `is_admin()` is false) the plugin's
  own `edit_form_after_editor` and `save_post` callbacks are absent, while its `init` callback is
  present at priority 0 and the post type is registered. The callbacks are named explicitly in
  these assertions - a bare `has_action( $tag )` reports true because core and BuddyPress hook
  `save_post` themselves, which made the first version of this test wrong.
- **Admin side proven over real HTTP.** Fetched `wp-admin/post.php?post=57&action=edit` as the
  administrator: the meta box heading, the nonce field and the `students` checkbox all render,
  with `students` ticked and `teachers` unticked. This is what shows the `is_admin()` branch
  really does execute in wp-admin. `save_post` is registered in the same branch, immediately
  after the hook that was just proven to fire, and its handler logic is covered separately by
  the fixes 1-3 suite; a full form round trip through `post.php` was not performed.
- **Dependency handling** — no `Requires Plugins:` header line (matched by regex against the
  header block, since the phrase also appears in the comment explaining its absence), the notice
  callback is defined, and `$includes` is gone.
- **Content rendering** — no `wp_kses_post` remains in the class, and `the_content` is still
  applied.
- **No regressions** — 21/21 fixes 1-3, 22/22 items 5-12, 9/9 uninstall, 3/3 single member type,
  12/12 items 5/8/9 over HTTP, 13/13 accessibility markup, 9/9 guest dismiss flow, 5/5 member
  flow.

Not covered: the admin notice was not viewed in a browser with BuddyPress deactivated, since
deactivating BuddyPress on the live site would have disrupted it. The callback is registered and
defined, and its guard and markup are straightforward.

### Cleanup

Three WP-CLI session tokens created for the admin HTTP test were removed individually; the
administrator's real browser session (from 07:37, with a Mozilla user agent) was identified and
left intact, so no one was logged out. The student account's dismissal meta and sessions were
cleared. User #1's `[55,57,60,64,68]` is pre-existing owner data and untouched.

### Still outstanding

Only the low-value polish remains: unprefixed meta keys (`read_feed_notices`,
`notice-blocked-member-types`, both needing a migration), query efficiency flags
(`no_found_rows`, `update_post_term_cache`), and the dead zero-alpha `box-shadow` in the CSS.
