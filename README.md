# Bonsai Site Snags

Internal Bonsai Digital Collective tool. Not distributed publicly (yet).

A lightweight front-end QA/snagging layer for admins — no settings page dependency, no ACF requirement. Toggle it on, click anywhere on the page to drop a note, tick it off when fixed.

## What it does

Drops a "Snags" toggle button (bottom right) on the front end for logged-in admins only. Turn it on, click anywhere on the page, drop a note. Notes appear as pins on the page and as a QA punch-list under **wp-admin → Site Snags**, filterable by Open/Done. Click a pin to edit, mark done, or delete.

Nobody without the `manage_options` capability (or whatever you filter it to — see below) ever sees the toggle, the pins, or loads any of the JS/CSS. Zero front-end footprint for real visitors.

## Requirements

- PHP 7.4+
- No ACF dependency — runs on any WP install with core APIs only

## Installation

1. Upload the `bonsai-site-snags` folder to `/wp-content/plugins/`.
2. Activate via Plugins in wp-admin.
3. That's it — no settings page, no ACF dependency, no config needed.

## Changing who can use it

Two layers of control:

1. **Capability floor** — by default `manage_options` (admins). To open the pool of eligible users up to editors or a custom role, add this to the theme's `functions.php`:

   ```php
   add_filter( 'site_snags_capability', function() {
       return 'edit_pages'; // or a custom capability
   } );
   ```

2. **Per-user allow-list** — under **wp-admin → Site Snags → Settings**, tick exactly which of those eligible users can actually see the toggle and log snags. Leave it unconfigured (never saved) and everyone with the capability gets access, same as before. Once saved, only ticked users see it — including on sites where multiple people have `manage_options` but you only want specific people using it on a given build.

## Email notifications

Under **wp-admin → Site Snags → Settings** there's an "Email notifications" section. When enabled, everyone who can use snagging (the allow-list, or everyone with the capability if it's unconfigured) gets a plain-text email when:

- a snag is added
- a snag's note is edited
- a snag is marked done

Each event has its own toggle. Whoever performed the action is never emailed about their own change. The email carries the note, current status, the front-end URL, and a link to the snag's edit screen.

Defaults to on for all three events once the plugin updates — turn the whole thing off, or drop individual events, on the Settings screen. Stored as a single `site_snags_notification_settings` option.

Two filters for customisation:

- `site_snags_notification_recipients` — `( WP_User[] $recipients, int $exclude_user_id )` — change who gets mailed
- `site_snags_notification_email` — `( array $email, string $event, int $post_id, int $actor_id )` — override `subject` / `body` / `headers` (e.g. send HTML, add a Cc, route to a shared inbox)

There are also three action hooks if you want to bolt on Slack/Make.com delivery instead: `site_snags_snag_created`, `site_snags_snag_note_updated`, `site_snags_snag_completed`, each passing `( int $post_id, int $actor_id )`.

## How pin positioning survives page changes

Clicks are stored as a CSS selector path + percentage offset within that element's box — not raw pixel coordinates. On reload, the selector is re-resolved and the pixel position recalculated against the element's current size/position. This means pins hold up across most responsive breakpoints and minor content edits. If the target element is deleted entirely, the pin won't render on the page, but the note is never lost — it's still visible (with a direct link back to the page) in the **wp-admin → Site Snags** list.

## Data storage

Custom post type `site_snag`, plain post meta (no ACF dependency so it works standalone). Fields: URL, page title, CSS selector, offset_x, offset_y, status (open/done), note, author, date — all via `register_post_meta` with sanitisation + capability-gated auth callbacks.

Allow-list is a single option (`site_snags_allowed_users`, an array of user IDs). `false` (its default, never-saved state) means "everyone with the capability" for backwards compatibility on existing installs.

## Admin list page

**wp-admin → Site Snags** is the standard CPT list table with three additions:

- Page column — links straight to the live URL the snag was logged on
- Status column — Open/Done pill
- Open/Done view links above the table (with counts), same pattern as All/Published/Trash
- A "View on page" row action alongside Edit/Trash

The Title column is the note itself and links to the normal WP editor (note lives in the post content), so from this one list you can jump to either the live page or the snag's own edit screen.

## Updates

Ships with [YahnisElsts/plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) (installed via Composer, `vendor/` committed) pointed at `github.com/Bonsai-Systems/bonsai_site_snags`. Sites with the plugin installed will see updates in **Plugins** in wp-admin, same as `bonsai-code-injector` and `bonsai-maintenance`.

To ship a new version:

1. Bump the `Version:` header in `site-snags.php` and add a `CHANGELOG.md` entry.
2. Commit and push to `main`.
3. Publish a GitHub Release tagged with the new version (release-assets mode is enabled, so attach a zip of the plugin folder — plain source-archive tags won't be picked up).

Sites check for updates every 6 hours (`$checkPeriod` argument to `buildUpdateChecker()`), or immediately if an admin clicks "Check again" on the Plugins screen.

## Ideas for v2 (not built yet)

- Screenshot capture on pin creation (canvas/html2canvas) so notes carry visual context even if the element later changes
- Priority/severity tag (low/medium/high) on each snag
- Per-snag assign-to-user (route a snag to one person rather than notifying the whole allow-list) — email notifications themselves shipped in 1.3.0
- Digest mode — one daily roundup email instead of one per event, for busy snagging sessions
- Slack notification via Make.com webhook when a snag is logged or resolved — the `site_snags_snag_*` action hooks added in 1.3.0 make this a small glue script
- CSV/PDF export of the punch-list for client handoff docs
- Per-client-site enable/disable if this ever gets bundled into Drift App Suite rather than shipped as its own plugin
- Optional "resolved but keep visible, faded" mode — already partly there via the done pin styling, could add a toggle to hide fully

## Notes on selling this later

Kept deliberately decoupled from Bonsai's child theme architecture and ACF — runs on any WP install with just core APIs. If this proves useful internally across the wider client base, it's in good shape to become a standalone plugin.dev listing later with minimal rework: mainly adding a settings screen for the capability filter and maybe white-labelling the toggle button.
