# Critical fixes for 1.1.0

Three issues found during the plugin review, fixed together for the 1.1.0 release.

## 1. `save_post` handler has no guards (data loss)

`BP_Pinned_Feed_Notices_Admin::store_hide_notice_for_member_types_selection()` fires on every
`save_post` for every post type with no nonce, capability, or post type check, and reads
`$_REQUEST` instead of `$_POST`. Any save that does not carry `notices-member-types`
(Quick Edit, bulk edit, autosave, programmatic `wp_update_post()`) silently deletes the
stored member type selection.

- [x] Output `wp_nonce_field()` in the meta box so there is something to verify against
- [x] Bail on posts that are not `pinned_feed_notices`
- [x] Verify the nonce; this also covers Quick Edit / bulk edit / autosave / programmatic saves
- [x] Add `current_user_can( 'edit_post', $post_id )`
- [x] Read `$_POST` with `wp_unslash()` instead of `$_REQUEST`

## 2. Member type meta query is inverted (notices leak)

`BP_Pinned_Feed_Notices::display_feed_notices()` joins one `NOT LIKE` clause per visitor
member type with `'relation' => 'OR'`. A visitor with two member types sees a notice blocked
for one of them, because the clause for the *other* type passes the OR. The restriction only
works for single-member-type users.

`NOT LIKE` against a serialized array is also substring-fragile (`student` matches
`student-premium`), and mixing `NOT EXISTS` with `NOT LIKE` on one meta key produces
unreliable `WP_Meta_Query` joins.

- [x] Drop the meta query entirely
- [x] Filter the result set in PHP with an exact `array_intersect()` match
- [x] Confirm no extra queries: `WP_Query` primes the meta cache, so `get_post_meta()` is free

## 3. Single member type sites see "no member types are set up"

`count( $member_types ) > 1` hides the checkbox list when exactly one member type exists.

- [x] Change to `> 0`

## 4. Unpublished notices shown to every visitor (found during verification, not in the original list)

Driving the real front-end AJAX path turned up draft notice #60 rendering to an anonymous
visitor. Root cause: BuddyPress loads the activity loop through `admin-ajax.php`, where
`is_admin()` is true. `WP_Query` given an empty `post_status` appends every status flagged
`protected` + `show_in_admin_all_list` (`draft`, `pending`, `future`) in that context,
regardless of the current user's capabilities. Pre-existing, not a regression from fix 2.

- [x] Pin `'post_status' => 'publish'` in the query args

## 5. Guests could not dismiss notices

The readme promises "allow visitors to hide the notice", but only `wp_ajax_delete_pinned_feed_notice`
was registered, so a logged out visitor's click hit admin-ajax, got `0` back and failed silently.
Dismissals were stored in user meta, which guests do not have.

- [x] Register `wp_ajax_nopriv_delete_pinned_feed_notice`
- [x] Add `get_dismissed_notice_ids()` / `store_dismissed_notice_ids()` to route storage:
      user meta for members, cookie for guests
- [x] Read dismissals through the helper in `display_feed_notices()`
- [x] Validate that the submitted ID is a real published notice before storing it, so the
      cookie cannot be padded with arbitrary values
- [x] Move the handler to `wp_send_json_success()` / `wp_send_json_error()` and update the JS
      to read the nested `data` payload

## Release bookkeeping

- [x] Plugin header `Version:` was still 1.0.3 while `BPPFN_VERSION` was already 1.0.4
- [x] The 1.0.4 changelog entry was a verbatim copy of 1.0.3 (author corrected this mid-task)

## Review

### What changed

**`app/main/class-pinned-feed-notices-admin.php`**

- `add_members_field_to_pfn()` now emits `wp_nonce_field( 'bppfn_save_member_types', 'bppfn_member_types_nonce' )`.
- `store_hide_notice_for_member_types_selection()` gained a `public` visibility keyword and four
  guards, in order: post type, nonce, capability, then the meta write. Input moved from
  `$_REQUEST` to `$_POST` with `wp_unslash()` before `sanitize_text_field()`.
- The member type list condition changed from `count( $member_types ) > 1` to `> 0`.

**`app/main/class-pinned-feed-notices.php`**

- Removed the `$meta_query_args` construction and the `$args['meta_query']` assignment.
- `$visitors_member_types` is normalized to an array right after `bp_get_member_type()`.
- Added `is_notice_visible()`, a private helper doing an exact `array_intersect()` against the
  notice's blocked list.
- The query results are filtered through that helper before the empty check and the markup.
- Added `'post_status' => 'publish'` to the query args (fix 4).
- Added the `READ_COOKIE_KEY` constant, the `wp_ajax_nopriv_` registration, and the
  `get_dismissed_notice_ids()` / `store_dismissed_notice_ids()` pair (fix 5).
- Rewrote `delete_pinned_feed_notice()`: nonce checked first, `absint()` on the ID, a post type
  and status check, then storage through the helper and `wp_send_json_*` responses.
  The stray `SELF::` casing went with the rewrite.

**`assets/js/bp-pinned-feed-notices.js`** — error branch reads `ajaxResponse.data.content`, since
`wp_send_json_error()` nests the payload under `data`.

**`bp-pinned-feed-notices.php`** — header `Version:` 1.0.3 -> 1.0.4.

**`readme.txt`** — 1.0.4 changelog entry rewritten to describe these fixes.

### Root causes

1. The handler was written assuming `save_post` only ever fires from the full post edit screen
   with the form fully populated. It fires from many other paths, and the `else` branch treated
   "field absent" as "user cleared the field" — those are different states.
2. The OR relation was correct for the `NOT EXISTS` fallback but wrong for the per-type `NOT LIKE`
   clauses, which need AND. Rather than nest an AND group inside the OR, the whole meta query was
   removed, since the substring and join problems remained even with the relation corrected.
3. Off-by-one in the guard condition.
4. `is_admin()` is true inside `admin-ajax.php`, which silently changes `WP_Query`'s default
   status handling. The query never said which statuses it wanted, so it inherited admin defaults
   on a public-facing request.
5. Dismissal state was modelled as user meta from the start, which has no equivalent for an
   anonymous visitor, so the guest half of the advertised feature was never wired up.

### Deviations from plan

Fix 4 was not in the original scope. It was found while runtime-verifying fixes 1-3 against the
live site: a draft notice rendered to an anonymous HTTP request. Fixed rather than only reported,
because it publicly exposes unpublished content and the fix is a single argument.

### Testing status

Runtime verified against the live install at `K:\www\bp-pinned-feed-notices`
(WordPress 7.0.2, BuddyPress 14.3.3, PHP 8.3) using WP-CLI and real HTTP requests.

Site fixture: member types `students` + `teachers`; notice #57 blocked for `students`,
#56 blocked for both, #55 unblocked, #60 draft.

- `php -l` clean on PHP 7.4.26 and 8.3.12.
- 21/21 assertions pass in the WP-CLI suite covering: the old meta query's actual leak, the new
  helper against real post meta, end-to-end ID selection, all four save guards, the save happy
  path, and the nonce field rendering.
- **Fix 2 proven both ways.** Replaying the pre-fix `meta_query` against the live database
  returned `[55,57,64,68]` for a `students`+`teachers` visitor — notice #57, blocked for
  `students`, leaked. The same visitor now resolves to `[55,64,68]`.
  Note the old code only leaked when the blocked list was a strict *subset* of the visitor's
  types; #56 (blocked for both) was hidden correctly even before the fix.
- **Fix 1 proven** by invoking the save handler with a Quick Edit shaped `$_POST` (no nonce,
  no checkboxes) against real notice #57: meta survived. A nonce-less POST carrying checkboxes
  was also rejected, and a good nonce against a `page` post was ignored. The happy path still
  writes, and unticking every box still clears the meta. #57 was snapshotted and restored.
- **Fix 3 proven** by shaping `bp_get_member_types` down to one entry: one type now renders one
  checkbox instead of the "no member types" message. Two types render two, zero renders the
  message.
- **Fix 4 proven over real HTTP.** An anonymous POST to the BP Nouveau activity loader rendered
  notice IDs `55, 56, 57, 60, 68, 64` before the fix — including draft #60 — and
  `55, 56, 57, 64, 68` after.
- **End-to-end as a real member.** Logged in over HTTP as the `student` user (member type
  `students`), the activity loop rendered `55, 64, 68`: #57 and #56 correctly withheld, draft
  #60 absent. Matches the CLI prediction exactly.
- Error log carries no plugin entry after the fixes. The one plugin fatal in the log
  (07:40:50 UTC) is from the moment between adding the `is_notice_visible()` call site and the
  method body; three later fatals are from the throwaway harness inside the wp-cli phar.
- **Fix 5 proven over real HTTP, 9/9 assertions.** From a cookie-less session: a guest sees
  `55,56,57,64,68`, dismisses #55, gets back `success` plus
  `Set-Cookie: bppfn_read_notices=55; Max-Age=31536000; HttpOnly; SameSite=Lax`, and the next
  feed load returns `56,57,64,68`. Dismissing #55 again is refused, as are an unknown ID, the
  draft #60, and a bad nonce. A second fresh guest still sees all five, so the state is per
  visitor.
- **Fix 5 confirmed from the rendered page.** Rather than generating a nonce, the final test
  scraped `ajax_nonce` out of the real activity page HTML and dismissed with it, which is what a
  browser does. Worked end to end.
- **Member path unchanged, 5/5.** Logged in as `student` over HTTP: dismissing #64 writes
  `[64]` to user meta, sets **no** cookie, and the feed drops to `55,68`.
- Test data cleaned up: #57 meta restored to `["students"]`, temporary page deleted, user #2's
  `read_feed_notices` removed (it was empty before), session tokens destroyed. User #1 still
  holds `[55,57,60,64,68]`, which is the site owner's own pre-existing data and was left alone.

### Known behaviour left unchanged

A logged-out visitor has no member types, so nothing can match the blocked list and every
published notice is shown — including ones blocked for specific types. Both the old and new code
behave identically here. If notices should be hidden from guests, that is a separate decision.

### Caveats on the guest cookie

- Guest dismissals live on one browser only. There is no way around that without an account.
- Full-page caching serves one cached HTML document to all guests, so the `ajax_nonce` baked
  into it is shared and expires on WP's 12-24h nonce tick. A guest landing on a stale cached
  page gets a rejected dismissal. The notices themselves are unaffected because BuddyPress
  fetches the activity loop over admin-ajax, which is not page cached.
- The cookie is `HttpOnly` and `SameSite=Lax`, and `secure` follows `is_ssl()`.

### Still outstanding from the review

Items 6-12 and the polish list. Highest value remaining:

- Assets are enqueued on every front-end page rather than just the activity directory.
- The CPT is `public`/`publicly_queryable` with rewrite rules that are never flushed.
- No `uninstall.php`; notice posts, post meta and `read_feed_notices` user meta survive removal.
- The dismiss control is a `<span>`, so it is not keyboard reachable and has no accessible name.
- The `$_POST['page']` gate means notices never render on template packs that build the first
  page server-side.
- Meta keys `read_feed_notices` and `notice-blocked-member-types` are unprefixed.