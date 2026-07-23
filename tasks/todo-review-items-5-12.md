# Review items 5 to 12

Continues `todo-critical-fixes-1.1.0.md`. Same unreleased release, so no separate bump.

## 5. `$_POST['page']` gate is template-pack dependent

`display_feed_notices()` returns unless `$_POST['page'] === 1`, assuming the activity loop always
arrives through BP's AJAX template loader. BP Nouveau does that, but template packs that render
the first page server-side send no `page` at all, so notices never appear there.

- [ ] Treat "no page in the request" as page one
- [ ] Also honour BP's `acpage` query arg used by non-AJAX pagination

## 6. AJAX handler was loose

Already resolved while fixing item 4: `isset()` guards, `wp_unslash()`, `absint()`, a post
type/status check, `wp_send_json_*` responses, and the `SELF::` casing.

- [ ] Confirm nothing remains rather than assume it

## 7. Meta box reads `$_GET['post']`

Saved values fail to load in any edit context that does not put the ID in the query string,
and the empty checkbox state then saves back as "no restrictions".

- [ ] Use the already validated `$post->ID`
- [ ] Stop casting a `''` meta miss into `array( '' )`

## 8. Assets load on every front-end page

`wp_enqueue_scripts` is hooked unconditionally although only the activity directory renders
notices.

- [ ] Extract the directory check `display_feed_notices()` already does into a helper
- [ ] Bail early in `enqueue_style_scripts()`
- [ ] Verify over HTTP that assets still load on the directory and no longer load elsewhere

## 9. CPT is publicly queryable with unflushed rewrite rules

Notices are feed fragments, not standalone pages, yet each gets a front-end URL and rewrite
rules that no activation hook ever flushes.

- [ ] `public`, `publicly_queryable`, `rewrite` all false
- [ ] Flush once per version so the stale rules registered by earlier versions are dropped
- [ ] Verify the front-end query still returns notices for a non-public post type

## 10. Escaping slips

- [ ] `_e()` inside a `title=""` attribute -> `esc_attr_e()`
- [ ] `esc_attr()` on label text -> `esc_html()`
- [ ] Raw `echo $checked` -> `checked()`
- [ ] Bare `_e()` in the admin markup -> `esc_html_e()`

## 11. Version numbers out of sync

Done in the previous task: header was 1.0.3 while the constant said 1.0.4.

## 12. No uninstall handling

Removing the plugin leaves notice posts, their post meta, and `read_feed_notices` on every user.

- [ ] Add `uninstall.php` removing notices, their meta, the user meta and the plugin option
- [ ] Handle multisite
- [ ] Do not rely on the CPT being registered, since the plugin is not loaded during uninstall

## Review

All eight items done. Every checkbox above is complete.

### What changed

**`app/main/class-pinned-feed-notices.php`**

- New `is_activity_directory()` helper, now the single source of truth for "should notices be
  here", used by both the enqueue and the output (items 5, 8).
- New `get_current_feed_page()`. Absent page data means page one, so server-side first renders
  work; `acpage` is honoured for non-AJAX pagination (item 5).
- `enqueue_style_scripts()` bails unless the request is the activity directory (item 8).
- `esc_attr_e()` for the dismiss control's `title` attribute (item 10).

**`app/main/class-pinned-feed-notices-admin.php`**

- CPT registered with `public`, `publicly_queryable` and `rewrite` all false (item 9).
- New `REWRITE_VERSION_OPTION` and `maybe_flush_rewrite_rules()`, hooked to `init` at 20 so it
  runs after registration and flushes once per version (item 9).
- Meta box reads meta from `$post->ID` instead of `$_GET['post']`, and a missing value stays an
  empty array instead of becoming `array( '' )` (item 7).
- `checked()` instead of a hand-built attribute, `esc_html()` for label text, `esc_html_e()` for
  the two bare strings (item 10).

**`uninstall.php`** (new) — deletes notices and their meta, the `read_feed_notices` user meta for
every user, and the plugin option; loops network sites on multisite (item 12).

### Root causes

5. The page gate read one specific transport's request shape (`$_POST['page']`) rather than
   asking what page was being rendered, so it silently assumed BP Nouveau.
7. The meta box re-derived a post ID from the URL when the validated `$post` object was already
   in scope two lines above.
8. Enqueue was hooked unconditionally because the "where do notices belong" logic lived only
   inside the output method.
9. The post type was registered with the copy-paste public defaults, which do not match what a
   feed fragment needs.
12. Never written.

### Deviations from plan

One correction found by testing. `get_current_feed_page()` first used `absint()`, but
`absint( '-4' )` is `4`, so a negative page was read as a real page number and the `> 0` guard
below it could only ever catch zero. Switched to `(int)` so malformed input lands at or below
zero and falls back to page one.

Item 6 needed no work: it was already resolved by the item 4 rewrite. Verified by grep rather
than assumed - no `SELF::`, no `echo json_encode`, and every superglobal read guarded and
unslashed.

### Testing status

Runtime verified against the live install (WordPress 7.0.2, BuddyPress 14.3.3, PHP 8.3).
**78 assertions across six suites, all passing.** `php -l` clean on PHP 7.4.26 and 8.3.12.

- **Item 5** — eight page-resolution cases: no page data, AJAX `page=1/2`, `acpage=3`, POST
  winning over `acpage`, and garbage/zero/negative all falling back to page one. Over HTTP,
  page 1 returns 5 notices and pages 2 and 3 return none.
- **Item 7** — the meta box renders with `$_GET['post']` explicitly unset and the `students` box
  still comes back ticked from `$post->ID` alone, with `teachers` correctly unticked.
- **Item 8** — over HTTP, `/activity/` serves both the CSS and the JS; `/` and `/members/` serve
  neither.
- **Item 9** — the post type object reports `public`, `publicly_queryable` and `rewrite` false
  with `show_ui` and the admin menu intact. `?p=55` now 404s. The `?post_type=` archive URL
  returns 200 but serves the home page and exposes no notice content, which is correct for a
  non-queryable type. The `rewrite_rules` option contains no `pinned_feed_notices` entries and
  `bppfn_rewrite_version` matches the current plugin version.
- **Item 12** — the risky assumption is that `wp_delete_post()` works when the post type is not
  registered, which is exactly the state at uninstall. Proven on throwaway data: seeded two
  posts of an unregistered type plus user meta on every user, ran the uninstall logic verbatim,
  and confirmed the posts, their post meta and the user meta were all gone while the six real
  notices and #57's meta were untouched. **The real uninstall path was never executed against
  live data**, and the multisite branch was not exercised because this install is single site.
- **No regressions** — the earlier suites still pass unchanged: 21/21 for fixes 1-3, 9/9 for the
  guest dismiss flow, 5/5 for the member flow. This matters most for item 9, since making the
  post type non-public could have broken the front-end query outright; it did not.
- Error log carries no new plugin entries.
- Test data restored: user #2's dismissal meta removed, sessions destroyed, probe posts gone.
  User #1's `[55,57,60,64,68]` is the owner's own pre-existing data and was left alone.

### Note for the release

Existing installs upgrade into a rewrite flush on the first request after the version changes.
That is one extra `flush_rewrite_rules( false )` per site, then never again for that version.

### Still outstanding

The polish list from the original review: keyboard accessibility on the dismiss control (still a
`<span>`), unprefixed meta keys (`read_feed_notices`, `notice-blocked-member-types`) which would
need a migration, query efficiency flags, a `Requires Plugins: buddypress` header, singleton
structure, and the dead zero-alpha `box-shadow` in the CSS.
