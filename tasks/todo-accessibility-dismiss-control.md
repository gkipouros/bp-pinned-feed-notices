# Accessibility: dismiss control

The dismiss control was `<span class="remove-notification">x</span>`: not in the tab order, not
operable by keyboard, and with no accessible name. Screen reader and keyboard users had no way
to dismiss a notice at all.

- [x] Make it a `<button type="button">` with `aria-label` and a `&times;` glyph
- [x] Give the notice list `aria-live="polite"` so dismissal is announced
- [x] Strip the browser's default button styling back to the old span's appearance
- [x] Guarantee a visible focus ring even on themes that blanket-remove outlines
- [x] Remove the notice from the DOM rather than only hiding it, so the live region sees a change
- [x] Keep focus somewhere sensible once the focused button is gone

## What changed

**`app/main/class-pinned-feed-notices.php`**

- `<span>` -> `<button type="button">` carrying `aria-label`, the existing `title` for a pointer
  tooltip, and `&times;` instead of a lowercase `x`.
- The `<ul>` gained `aria-live="polite"` and now ends with an empty
  `<li class="bppfn-notice-status bppfn-visually-hidden">`. The element is deliberately rendered
  empty and present from the start: a live region has to exist in the document before its
  content changes or the update is not announced.
- Localized a `removed_text` string for the announcement.

**`assets/js/bp-pinned-feed-notices.js`**

- On success the notice is `.remove()`d after `slideUp()` rather than left hidden, the status
  text is written into the live region, and focus is moved to the next dismiss button.
- The next button is captured *before* the animation starts, because by the time the callback
  runs the current button has been detached.

**`assets/css/bp-pinned-feed-notices.css`**

- Button defaults reset so the control looks exactly as it did.
- `:focus` fallback, `:focus:not(:focus-visible)` withdrawal for pointer users, and
  `:focus-visible` for keyboards.
- `.bppfn-visually-hidden` helper. The plugin defines its own rather than relying on the theme
  providing `screen-reader-text`.

## Why the extra focus handling

Making the control focusable introduces a problem the `<span>` never had: the focused element is
destroyed on activation, which drops focus to `<body>` and dumps a keyboard user back at the top
of the document. Moving focus to the next dismiss button keeps a run of dismissals workable.

## Testing status

Driven in a real Chrome session against the live site, plus the existing automated suites.

Browser, as a logged out visitor:

- Tab order — focused the filter `<select>` that precedes the list, pressed a real Tab, and
  focus landed on the first dismiss button. `tabIndex` is 0 with no `tabindex` attribute, so it
  is natively focusable rather than patched into the order.
- All five controls are `BUTTON`, `type="button"`, `aria-label="Remove this message"`, glyph `×`.
  No `<span>` controls and no bare `x` remain.
- Focus ring is visibly rendered (confirmed by screenshot, and `:focus-visible` matches with a
  computed solid outline). The BuddyX theme's own focus style wins here; the plugin rule is the
  fallback for themes that have none.
- **Enter** dismissed notice 55: removed from the DOM, live region read `Notice removed.`, and
  focus moved to notice 56's button rather than `<body>`.
- **Space** dismissed notice 56, focus moved on to 57. Native button activation on both keys.
- A pointer click dismissed a third notice, so the mouse path is unchanged.
- After a reload the dismissed notices stayed gone, so the guest cookie still works.
- `document.cookie` cannot see `bppfn_read_notices`, confirming HttpOnly from the browser side.
- No console errors or exceptions.

Automated: `php -l` clean on 7.4.26 and 8.3.12, `node --check` clean on the JS, 13/13 markup and
asset assertions over HTTP, and the earlier suites unchanged - 21/21 fixes 1-3, 22/22 items
5-12, 9/9 uninstall, 12/12 items 5/8/9, 9/9 guest flow, 5/5 member flow.

Not covered: no real screen reader was used, so the announcement is verified structurally (live
region present before the change, text injected into it) rather than by listening to it.

## Note

The Chrome session dismissed notices 55, 56 and 57 as a guest, which is stored in the HttpOnly
`bppfn_read_notices` cookie for `localhost/bp-pinned-feed-notices/`. Being HttpOnly it cannot be
cleared from JavaScript. Clear it via DevTools > Application > Cookies if you want all five
notices back in that browser. Nothing was written to the database.
